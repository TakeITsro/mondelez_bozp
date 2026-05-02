<?php

declare(strict_types=1);

namespace modules\bozp\services;

use Craft;
use modules\bozp\enums\PermitStatus;
use modules\bozp\Module;
use modules\bozp\records\PermitRecord;
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
        'draft'           => ['submitted', 'cancelled'],
        'submitted'       => ['approved', 'rejected', 'cancelled'],
        'approved'        => ['signed', 'cancelled', 'pending_closure'],
        'rejected'        => ['draft', 'cancelled'],
        'signed'          => ['active', 'cancelled'],
        'active'          => ['pending_closure', 'expired', 'cancelled'],
        'pending_closure' => ['closed', 'cancelled', 'active'],
        'closed'          => [],
        'cancelled'       => [],
        'expired'         => [],
    ];

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
     * approvedAt + 7 days (general permits are valid for 7 days from approval).
     */
    public function approve(PermitRecord $permit, int $actorUserId, ?string $comment = null): void
    {
        $now = new \DateTimeImmutable();
        $approvedAt = $now->format('Y-m-d H:i:s');
        $validTo = $now->modify('+7 days')->format('Y-m-d H:i:s');

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
     * Issuer final closure — only allowed once the contractor has
     * signed RecipientClosure (i.e. status is "pending_closure").
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
            PermitStatus::Closed,
            $actorUserId,
            extraColumns: [
                'issuerClosureStatus' => 'work_completed_loto_removed',
                'issuerClosureSignedAt' => date('Y-m-d H:i:s'),
                'requiresTrialOperation' => $requiresTrialOperation,
                'closedAt' => date('Y-m-d H:i:s'),
            ],
            auditAction: 'issuer_closed',
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

        $permit->status = $to->value;
        foreach ($extraColumns as $col => $val) {
            $permit->{$col} = $val;
        }

        if (!$permit->save()) {
            throw new \RuntimeException('Failed to save permit during transition: ' . print_r($permit->getErrors(), true));
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
