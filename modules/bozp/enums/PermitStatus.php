<?php

declare(strict_types=1);

namespace modules\bozp\enums;

/**
 * Permit lifecycle states.
 *
 * Allowed transitions are enforced in PermitWorkflowService (next phase).
 */
enum PermitStatus: string
{
    case AwaitingAssessment = 'awaiting_assessment';
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Signed = 'signed';
    case Active = 'active';
    case PendingClosure = 'pending_closure';
    case AwaitingHseClosure = 'awaiting_hse_closure';
    case Closed = 'closed';
    case Cancelled = 'cancelled';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::AwaitingAssessment => 'Čaká na hodnotenie rizík od dodávateľa',
            self::Draft => 'Koncept',
            self::Submitted => 'Čaká na schválenie HSE',
            self::Approved => 'Schválené',
            self::Rejected => 'Zamietnuté',
            self::Signed => 'Podpísané',
            self::Active => 'Aktívne',
            self::PendingClosure => 'Čaká na uzavretie',
            self::AwaitingHseClosure => 'Čaká na podpis HSE',
            self::Closed => 'Uzavreté',
            self::Cancelled => 'Zrušené',
            self::Expired => 'Expirované',
        };
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Closed, self::Cancelled, self::Expired, self::Rejected], true);
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::Active, self::PendingClosure, self::AwaitingHseClosure], true);
    }
}
