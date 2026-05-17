<?php

declare(strict_types=1);

namespace modules\bozp\enums;

use Craft;

/**
 * Lifecycle states for a subpermit.
 *
 *   pending_signatures ──► pending ──► approved ──► expired   (8 h after approvedAt)
 *                              │             │
 *                              └─► rejected  └─► cancelled
 *
 * pending_signatures: multi-signer subpermit waiting for all token-sign emails.
 * pending:            ready for HSE review (either submitted directly or all signers done).
 */
enum SubpermitStatus: string
{
    case PendingSignatures = 'pending_signatures';
    case Pending   = 'pending';
    case Approved  = 'approved';
    case Rejected  = 'rejected';
    case Expired   = 'expired';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::PendingSignatures => Craft::t('bozp', 'Čaká na podpisy'),
            self::Pending   => Craft::t('bozp', 'Čaká na schválenie'),
            self::Approved  => Craft::t('bozp', 'Schválené'),
            self::Rejected  => Craft::t('bozp', 'Zamietnuté'),
            self::Expired   => Craft::t('bozp', 'Expirované'),
            self::Cancelled => Craft::t('bozp', 'Zrušené'),
        };
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Rejected, self::Expired, self::Cancelled], true);
    }

    public function isActive(): bool
    {
        return $this === self::Approved;
    }

    public function isPendingSignatures(): bool
    {
        return $this === self::PendingSignatures;
    }
}
