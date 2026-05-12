<?php

declare(strict_types=1);

namespace modules\bozp\enums;

use Craft;

/**
 * The eight high-risk subpermit types (Attachments 2–9).
 *
 * Each maps to a unique form partial under
 * templates/site/subpermits/<value>.twig
 */
enum SubpermitType: string
{
    case HotWork       = 'hot_work';       // Príloha 2 — Zvýšené nebezpečenstvo požiaru
    case ConfinedSpace = 'confined_space'; // Príloha 3 — Stiesnené priestory
    case Heights       = 'heights';        // Príloha 4 — Práce vo výškach
    case CommandB      = 'command_b';      // Príloha 5 — Príkaz „B"
    case Electrical    = 'electrical';     // Príloha 6 — Vysokorizikové elektrické práce
    case Excavation    = 'excavation';     // Príloha 7 — Výkopové práce
    case Lifting       = 'lifting';        // Príloha 8 — Zdvíhacie práce / žeriav
    case Atex          = 'atex';           // Príloha 9 — Prostredie ATEX

    public function label(): string
    {
        return match ($this) {
            self::HotWork       => Craft::t('bozp', 'Zvýšené nebezpečenstvo požiaru'),
            self::ConfinedSpace => Craft::t('bozp', 'Vstup do stiesnených priestorov'),
            self::Heights       => Craft::t('bozp', 'Práce vo výškach'),
            self::CommandB      => Craft::t('bozp', 'Príkaz „B"'),
            self::Electrical    => Craft::t('bozp', 'Vysokorizikové elektrické práce'),
            self::Excavation    => Craft::t('bozp', 'Výkopové práce'),
            self::Lifting       => Craft::t('bozp', 'Zdvíhacie práce a práce so žeriavom'),
            self::Atex          => Craft::t('bozp', 'Práce v prostredí ATEX'),
        };
    }

    /** Appendix number shown in the UI (Príloha č. X). */
    public function appendixNumber(): int
    {
        return match ($this) {
            self::HotWork       => 2,
            self::ConfinedSpace => 3,
            self::Heights       => 4,
            self::CommandB      => 5,
            self::Electrical    => 6,
            self::Excavation    => 7,
            self::Lifting       => 8,
            self::Atex          => 9,
        };
    }

    /** Template partial path relative to templates/site/subpermits/. */
    public function templateName(): string
    {
        return $this->value;
    }

    /** All types as [value => label] for select/checkbox rendering. */
    public static function options(): array
    {
        return array_column(
            array_map(fn(self $t) => ['value' => $t->value, 'label' => $t->label()], self::cases()),
            'label',
            'value',
        );
    }
}
