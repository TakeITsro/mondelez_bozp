<?php

declare(strict_types=1);

namespace modules\bozp\services;

use Craft;
use modules\bozp\enums\PermitStatus;
use modules\bozp\enums\SubpermitStatus;
use modules\bozp\Module;
use modules\bozp\records\PermitRecord;
use modules\bozp\records\SubpermitRecord;
use yii\base\Component;
use yii\base\InvalidArgumentException;

/**
 * PermitWorkflow
 *
 * Owns the permit state machine. All status transitions go through here so we
 * have one place that validates "can X go to Y", writes the audit log entry,
 * and updates timestamp columns (submittedAt, approvedAt, etc.).
 *
 * In Phase 2B we only use submit() — the rest of the transitions land as their
 * UI lands (approve/reject in 2C, sign/close in 3+). They're stubbed out here
 * so the surface is obvious and we don't have to revisit this file just to add
 * a method.
 */
class PermitWorkflow extends Component
{
    /**
     * Allowed transitions: from-state => [allowed to-states].
     * Anything not in this map is rejected.
     */
    private const TRANSITIONS = [
        'awaiting_assessment'   => ['draft', 'cancelled'],
        'draft'                 => ['submitted', 'cancelled'],
        'submitted'             => ['approved', 'rejected', 'cancelled'],
        'approved'              => ['signed', 'cancelled', 'pending_closure'],
        'rejected'              => ['draft', 'cancelled'],
        'signed'                => ['active', 'cancelled'],
        'active'                => ['pending_closure', 'expired', 'cancelled'],
        'pending_closure'       => ['awaiting_hse_closure', 'cancelled', 'active'],
        'awaiting_hse_closure'  => ['closed', 'cancelled'],
        'closed'                => [],
        'cancelled'             => [],
        'expired'               => [],
    ];

    /**
     * Required high-risk types (permit.requiresHighRisk) that do not yet
     * have at least one non-cancelled subpermit. Empty array = coverage
     * complete (or nothing required).
     *
     * @return string[] missing SubpermitType values
     */
    public function missingRequiredSubpermitTypes(PermitRecord $permit): array
    {
        $raw = $permit->requiresHighRisk;
        if (is_string($raw)) {
            $raw = json_decode($raw, true);
        }
        $required = is_array($raw) ? $raw : [];
        if ($required === []) {
            return [];
        }

        $existing = SubpermitRecord::find()
            ->select('type')
            ->where(['parentPermitId' => $permit->id])
            ->andWhere(['not', ['status' => SubpermitStatus::Cancelled->value]])
            ->column();

        return array_values(array_diff($required, $existing));
    }

    /**
     * Move a draft permit to "submitted" — i.e. send it to the HSE queue.
     * Sets submittedAt.
     */
    public function submit(PermitRecord $permit, int $actorUserId): void
    {
        $this->transition(
            $permit,
            PermitStatus::Submitted,
            $actorUserId,
            ['submittedAt' => date('Y-m-d H:i:s')],
            'submitted',
        );
    }

    /**
     * HSE officer approves a submitted permit.
     * Sets approverId, approvedAt, approvalComment, and stamps validTo =
     * validFrom + 7 days (general permits are valid for 7 days from the
     * planned start date the issuer entered at submission). If validFrom
     * is missing for some reason, falls back to approvedAt + 7 days so
     * the permit still has a defined window.
     */
    public function approve(PermitRecord $permit, int $actorUserId, ?string $comment = null): void
    {
        $now = new \DateTimeImmutable();
        $approvedAt = $now->format('Y-m-d H:i:s');

        $validFromRaw = (string) ($permit->validFrom ?? '');
        try {
            $validFromBase = $validFromRaw !== ''
                ? new \DateTimeImmutable($validFromRaw)
                : $now;
        } catch (\Throwable) {
            $validFromBase = $now;
        }
        $validTo = $validFromBase->modify('+7 days')->format('Y-m-d H:i:s');

        // Generate the contractor's per-permit access credentials. The
        // plaintext password is held in memory just long enough to email
        // it; only the hash is persisted.
        $security = Craft::$app->getSecurity();
        $accessToken = $security->generateRandomString(48);
        $plaintextPassword = $this->generateContractorPassword();
        $accessPasswordHash = $security->hashPassword($plaintextPassword);

        $this->transition(
            $permit,
            PermitStatus::Approved,
            $actorUserId,
            [
                'approverId' => $actorUserId,
                'approvedAt' => $approvedAt,
                'approvalComment' => $comment !== '' ? $comment : null,
                'validTo' => $validTo,
                'accessToken' => $accessToken,
                'accessPasswordHash' => $accessPasswordHash,
                'accessExpiresAt' => $validTo,
            ],
            'approved',
            $comment,
        );

        $this->mailer()->notifyParticipantsOfApproval($permit, $plaintextPassword);
    }

    /**
     * Regenerate the contractor's access credentials for an already-approved
     * permit (used by the "resend approval" action). Updates accessToken,
     * accessPasswordHash, and accessExpiresAt = validTo. Returns the new
     * plaintext password — caller must hand it to the mailer immediately.
     *
     * Throws if the permit isn't in a state where contractor access makes
     * sense (i.e. not approved / signed / active).
     */
    public function regenerateContractorAccess(PermitRecord $permit): string
    {
        if (!in_array($permit->status, ['approved', 'signed', 'active'], true)) {
            throw new InvalidArgumentException(
                "Cannot regenerate contractor access for permit in status '{$permit->status}'."
            );
        }

        $security = Craft::$app->getSecurity();
        $accessToken = $security->generateRandomString(48);
        $plaintext = $this->generateContractorPassword();

        $permit->accessToken = $accessToken;
        $permit->accessPasswordHash = $security->hashPassword($plaintext);
        $permit->accessExpiresAt = $permit->validTo;

        if (!$permit->save()) {
            throw new \RuntimeException(
                'Failed to save permit during access regeneration: ' . print_r($permit->getErrors(), true)
            );
        }

        return $plaintext;
    }

    /**
     * Ensure the permit has contractor access credentials (accessToken +
     * accessPasswordHash). If already present, returns null. If freshly
     * generated, returns the plaintext password so the caller can mail it.
     *
     * Used by the new subpermit workflow where the contractor must sign
     * pre-work BEFORE HSE approves the permit, so credentials need to exist
     * before the approve() step would normally create them.
     */
    public function ensureContractorAccess(PermitRecord $permit): ?string
    {
        if (!empty($permit->accessToken) && !empty($permit->accessPasswordHash)) {
            return null;
        }

        $security = Craft::$app->getSecurity();
        $plaintext = $this->generateContractorPassword();

        $permit->accessToken = $security->generateRandomString(48);
        $permit->accessPasswordHash = $security->hashPassword($plaintext);
        // No validTo yet (permit not approved); give the contractor 14 days
        // to come sign. approve() will overwrite this with validTo.
        if (empty($permit->accessExpiresAt)) {
            $permit->accessExpiresAt = date('Y-m-d H:i:s', strtotime('+14 days'));
        }

        if (!$permit->save()) {
            throw new \RuntimeException(
                'Failed to save permit during access provisioning: ' . print_r($permit->getErrors(), true)
            );
        }

        return $plaintext;
    }

    /**
     * Generate an 8-character readable password for the contractor.
     * Avoids visually-similar characters (0/O, 1/l/I) so the contractor
     * can type it from the email without confusion.
     */
    private function generateContractorPassword(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $out = '';
        $max = strlen($alphabet) - 1;
        for ($i = 0; $i < 8; $i++) {
            $out .= $alphabet[random_int(0, $max)];
        }
        return $out;
    }

    /**
     * HSE officer rejects a submitted permit. Comment is mandatory at the
     * controller level; enforced here as a sanity check too.
     */
    public function reject(PermitRecord $permit, int $actorUserId, string $comment): void
    {
        if (trim($comment) === '') {
            throw new InvalidArgumentException('Rejection requires a comment.');
        }

        $this->transition(
            $permit,
            PermitStatus::Rejected,
            $actorUserId,
            [
                'approverId' => $actorUserId,
                'rejectedAt' => date('Y-m-d H:i:s'),
                'approvalComment' => $comment,
            ],
            'rejected',
            $comment,
        );

        $this->mailer()->notifyParticipantsOfRejection($permit, $comment);
    }

    /**
     * Contractor (recipient) closure — sets recipientClosure* columns
     * and transitions the permit to "pending_closure". Status flags is
     * an array of checkbox keys from the contractor closure form
     * (work_completed, equipment_operational, etc.). The actual close
     * vs cancel decision is taken later by the issuer.
     *
     * @param string[] $statusFlags
     */
    public function closeByRecipient(
        PermitRecord $permit,
        array $statusFlags,
        string $signerName,
    ): void {
        $allowed = ['approved', 'signed', 'active'];
        if (!in_array($permit->status, $allowed, true)) {
            throw new InvalidArgumentException(
                "Cannot close as recipient from status '{$permit->status}'."
            );
        }

        $this->transition(
            $permit,
            PermitStatus::PendingClosure,
            actorUserId: null,
            extraColumns: [
                'recipientClosureStatus' => $statusFlags !== [] ? $statusFlags : null,
                'recipientClosureSignedAt' => date('Y-m-d H:i:s'),
                'recipientClosureBy' => $signerName,
            ],
            auditAction: 'recipient_closure_signed',
            note: $signerName,
        );
    }

    /**
     * Contractor (recipient) cancellation — they sign that the work
     * cannot be done / is being suspended. Permit goes straight to
     * "cancelled"; no further issuer action is required.
     */
    public function cancelByRecipient(
        PermitRecord $permit,
        ?string $reason,
        string $signerName,
    ): void {
        $allowed = ['approved', 'signed', 'active'];
        if (!in_array($permit->status, $allowed, true)) {
            throw new InvalidArgumentException(
                "Cannot cancel as recipient from status '{$permit->status}'."
            );
        }

        $this->transition(
            $permit,
            PermitStatus::Cancelled,
            actorUserId: null,
            extraColumns: [
                'recipientClosureStatus' => ['work_suspended'],
                'recipientClosureSignedAt' => date('Y-m-d H:i:s'),
                'recipientClosureBy' => $signerName,
                'cancelledAt' => date('Y-m-d H:i:s'),
            ],
            auditAction: 'recipient_cancelled',
            note: $reason !== null && $reason !== '' ? $reason : $signerName,
        );
    }

    /**
     * Issuer cancellation — final close path that marks the permit as
     * cancelled instead of completed. Allowed at any point after
     * approval (including during/after contractor closure).
     */
    public function cancelByIssuer(
        PermitRecord $permit,
        int $actorUserId,
        ?string $reason = null,
    ): void {
        $allowed = ['approved', 'signed', 'active', 'pending_closure'];
        if (!in_array($permit->status, $allowed, true)) {
            throw new InvalidArgumentException(
                "Cannot cancel from status '{$permit->status}'."
            );
        }

        $this->transition(
            $permit,
            PermitStatus::Cancelled,
            $actorUserId,
            extraColumns: [
                'issuerClosureStatus' => 'work_canceled_equipment_isolated',
                'issuerClosureSignedAt' => date('Y-m-d H:i:s'),
                'cancelledAt' => date('Y-m-d H:i:s'),
            ],
            auditAction: 'issuer_cancelled',
            note: $reason,
        );
    }

    /**
     * Issuer (employee) closure signature — only allowed once the contractor
     * has signed RecipientClosure (status "pending_closure"). Transitions to
     * "awaiting_hse_closure"; the HSE officer must still countersign in CP
     * before the permit is fully closed.
     */
    public function closeByIssuer(
        PermitRecord $permit,
        int $actorUserId,
        bool $requiresTrialOperation,
    ): void {
        if ($permit->status !== 'pending_closure') {
            throw new InvalidArgumentException(
                "Cannot close as issuer from status '{$permit->status}'. "
                . 'Contractor closure must be signed first.'
            );
        }

        $this->transition(
            $permit,
            PermitStatus::AwaitingHseClosure,
            $actorUserId,
            extraColumns: [
                'issuerClosureStatus' => 'work_completed_loto_removed',
                'issuerClosureSignedAt' => date('Y-m-d H:i:s'),
                'requiresTrialOperation' => $requiresTrialOperation,
            ],
            auditAction: 'issuer_closed',
        );
    }

    /**
     * HSE officer final closure signature — fully closes the permit.
     * Only allowed once the issuer has signed (status "awaiting_hse_closure").
     */
    public function closeByHse(PermitRecord $permit, int $hseUserId): void
    {
        if ($permit->status !== 'awaiting_hse_closure') {
            throw new InvalidArgumentException(
                "Cannot close as HSE from status '{$permit->status}'. "
                . 'Issuer closure must be signed first.'
            );
        }

        $this->transition(
            $permit,
            PermitStatus::Closed,
            $hseUserId,
            extraColumns: [
                'closedAt' => date('Y-m-d H:i:s'),
            ],
            auditAction: 'hse_closed',
        );
    }

    /**
     * Core transition method. Validates, persists, audits.
     *
     * @param array<string, mixed> $extraColumns Additional column updates (e.g. submittedAt).
     */
    public function transition(
        PermitRecord $permit,
        PermitStatus $to,
        ?int $actorUserId,
        array $extraColumns = [],
        ?string $auditAction = null,
        ?string $note = null,
    ): void {
        $from = PermitStatus::from($permit->status);

        if (!$this->canTransition($from, $to)) {
            throw new InvalidArgumentException(sprintf(
                'Invalid permit transition: %s -> %s',
                $from->value,
                $to->value,
            ));
        }

        // Update only the columns affected by this transition. Using updateAll
        // here (instead of $permit->save()) avoids re-binding unrelated JSON
        // columns like recipientClosureStatus / requiresHighRisk that Yii loads
        // as PHP arrays — those would otherwise trigger "Array to string
        // conversion" when AR tries to bind the whole record on save.
        //
        // Any array passed as an extraColumn value is JSON-encoded for safe
        // binding to a JSON/longtext column.
        $persisted = ['status' => $to->value];
        foreach ($extraColumns as $col => $val) {
            $persisted[$col] = is_array($val) ? json_encode($val, JSON_UNESCAPED_UNICODE) : $val;
        }

        $affected = PermitRecord::updateAll($persisted, ['id' => $permit->id]);
        if ($affected < 1) {
            throw new \RuntimeException("Failed to update permit #{$permit->id} during transition.");
        }

        // Sync the in-memory record so subsequent code (PDF render, mailer)
        // sees the new values without a separate findOne() call. Keep the
        // original (possibly array) values on the in-memory object so
        // downstream readers see the natural type.
        $permit->status = $to->value;
        foreach ($extraColumns as $col => $val) {
            $permit->{$col} = $val;
        }

        $this->auditLogger()->log(
            permitId: (int) $permit->id,
            userId: $actorUserId,
            action: $auditAction ?? ('transition_to_' . $to->value),
            fromStatus: $from->value,
            toStatus: $to->value,
            note: $note,
        );
    }

    public function canTransition(PermitStatus $from, PermitStatus $to): bool
    {
        return in_array($to->value, self::TRANSITIONS[$from->value] ?? [], true);
    }

    /** @return string[] */
    public function allowedNextStates(PermitStatus $from): array
    {
        return self::TRANSITIONS[$from->value] ?? [];
    }

    private function auditLogger(): AuditLogger
    {
        /** @var Module $module */
        $module = Craft::$app->getModule('bozp');
        return $module->auditLogger;
    }

    private function mailer(): PermitMailer
    {
        /** @var Module $module */
        $module = Craft::$app->getModule('bozp');
        return $module->permitMailer;
    }
}
