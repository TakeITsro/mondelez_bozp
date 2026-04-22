<?php

declare(strict_types=1);

namespace modules\bozp\enums;

/**
 * Permit types — the general (GPTW) parent and 5 high-risk children.
 *
 * v1 only implements General. High-risk types are reserved keys for v2.
 */
enum PermitType: string
{
    case General = 'general';        // GPTW — Všeobecné povolenie na prácu
    case ConfinedSpace = 'cse';      // CSE — Práce v stiesnených priestoroch
    case WorkAtHeight = 'wah';       // WAH — Práce vo výškach
    case HotWork = 'hw';             // HW  — Činnosť so zvýšeným nebezpečenstvom vzniku požiaru
    case Excavation = 'oc';          // OC  — Povolenie na výkopové práce
    case LiftingCrane = 'exc';       // EXC — Povolenie na zdvíhacie práce a práce so žeriavom

    public function label(): string
    {
        return match ($this) {
            self::General => 'Všeobecné povolenie na prácu',
            self::ConfinedSpace => 'Práce v stiesnených priestoroch',
            self::WorkAtHeight => 'Práce vo výškach',
            self::HotWork => 'Činnosť so zvýšeným nebezpečenstvom vzniku požiaru',
            self::Excavation => 'Výkopové práce',
            self::LiftingCrane => 'Zdvíhacie práce a práce so žeriavom',
        };
    }

    public function shortCode(): string
    {
        return match ($this) {
            self::General => 'GPTW',
            self::ConfinedSpace => 'CSE',
            self::WorkAtHeight => 'WAH',
            self::HotWork => 'HW',
            self::Excavation => 'OC',
            self::LiftingCrane => 'EXC',
        };
    }

    public function isHighRisk(): bool
    {
        return $this !== self::General;
    }

    /**
     * Validity duration in hours.
     * General = 7 days = 168 h; high-risk = 8 h.
     */
    public function validityHours(): int
    {
        return $this === self::General ? 24 * 7 : 8;
    }
}
