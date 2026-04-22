<?php

declare(strict_types=1);

namespace modules\bozp\enums;

/**
 * The four signature slots on a GPTW.
 *
 *   IssuerIssuance     — Vydavateľ povolenia, signed at issuance
 *   RecipientIssuance  — Prijímateľ povolenia, signed at issuance
 *   RecipientClosure   — Prijímateľ povolenia, signed at end of work
 *   IssuerClosure      — Vydavateľ povolenia, final closure signature
 */
enum SignatureRole: string
{
    case IssuerIssuance = 'issuer_issuance';
    case RecipientIssuance = 'recipient_issuance';
    case RecipientClosure = 'recipient_closure';
    case IssuerClosure = 'issuer_closure';

    public function label(): string
    {
        return match ($this) {
            self::IssuerIssuance => 'Vydavateľ povolenia (vydanie)',
            self::RecipientIssuance => 'Prijímateľ povolenia (prevzatie)',
            self::RecipientClosure => 'Prijímateľ povolenia (ukončenie)',
            self::IssuerClosure => 'Vydavateľ povolenia (uzavretie)',
        };
    }

    public function isIssuanceTime(): bool
    {
        return in_array($this, [self::IssuerIssuance, self::RecipientIssuance], true);
    }

    public function isClosureTime(): bool
    {
        return in_array($this, [self::RecipientClosure, self::IssuerClosure], true);
    }
}
