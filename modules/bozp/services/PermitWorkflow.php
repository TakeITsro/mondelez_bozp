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
        'approved'        => ['signed', 'cancelled'],
        'rejected'        => ['draft', 'cancelled'],
        'signed'          => ['active', 'cancelled'],
        'active'          => ['pending_closure', 'expired', 'cancelled'],
        'pending_closure' => ['closed', 'active'],
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

        $this->transition(
            $permit,
            PermitStatus::Approved,
            $actorUserId,
            [
                'approverId' => $actorUserId,
                'approvedAt' => $approvedAt,
                'approvalComment' => $comment !== '' ? $comment : null,
                'validTo' => $validTo,
            ],
            'approved',
            $comment,
        );
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
    }

    /**
     * Core transition method. Validates, persists, audits.
     *
     * @param array<string, mixed> $extraColumns Additional column updates (e.g. submittedAt).
     */
    public function transition(
        PermitRecord $permit,
        PermitStatus $to,
        int $actorUserId,
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
}
