<?php

declare(strict_types=1);

namespace modules\bozp\enums;

/**
 * The 14 fixed hazard categories on the GPTW PPE matrix, plus standby protection
 * and a generic "other" slot. Each category has a Slovak label and a default
 * mitigation measure pre-populated from the Mondelez PDF template.
 */
enum HazardCategory: string
{
    case Noise = 'noise';
    case Skin = 'skin';
    case Eyes = 'eyes';
    case HeadImpact = 'head_impact';
    case PinchCrush = 'pinch_crush';
    case Cutting = 'cutting';
    case Ergonomic = 'ergonomic';
    case SlipTripFall = 'slip_trip_fall';
    case IndustrialTrucks = 'industrial_trucks';
    case HotSurface = 'hot_surface';
    case Respiratory = 'respiratory';
    case HazardousEnergyLoto = 'hazardous_energy_loto';
    case Body = 'body';
    case StandbyRequired = 'standby_required';
    case StandbyProtection = 'standby_protection';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Noise => 'Hluk',
            self::Skin => 'Koža',
            self::Eyes => 'Oči',
            self::HeadImpact => 'Náraz do hlavy',
            self::PinchCrush => 'Bod vtiahnutia alebo pomliaždenia',
            self::Cutting => 'Porezanie',
            self::Ergonomic => 'Ergonomický rizikový faktor',
            self::SlipTripFall => 'Pošmyknutie, zakopnutie, pád',
            self::IndustrialTrucks => 'Priemyselné vozíky/plošiny',
            self::HotSurface => 'Horúci povrch',
            self::Respiratory => 'Respiračné riziko',
            self::HazardousEnergyLoto => 'Nebezpečná energia (LOTO)',
            self::Body => 'Telo',
            self::StandbyRequired => 'Vyžaduje sa pohotovostný režim',
            self::StandbyProtection => 'Ochrana v pohotovostnom režime',
            self::Other => 'Iné riziko',
        };
    }

    public function defaultMeasure(): string
    {
        return match ($this) {
            self::Noise => 'Štuple do uší',
            self::Skin => 'Kombinézy/Špeciálny oblek',
            self::Eyes => 'Ochranné okuliare/Ochranný štít/Šilt',
            self::HeadImpact => 'Nárazová čiapka/Prilba',
            self::PinchCrush => 'Kryt/Uzamknutie-Lockout/PLC/PLD',
            self::Cutting => 'Kryt/Rukavice/Uzamknutie-Lockout',
            self::Ergonomic => 'Vybavenie/Postupy/Spoločná práca',
            self::SlipTripFall => 'Protišmyková obuv/Systém zadržiavania pádu',
            self::IndustrialTrucks => 'Bariéra/Notifikácia/Vysoká viditeľnosť',
            self::HotSurface => 'Ochranné rukavice/vychladnutie',
            self::Respiratory => 'Maska proti prachu/Kazeta/Celá tvár',
            self::HazardousEnergyLoto => 'Označenie/Zámok(y)/Haspra(y)',
            self::Body => 'Zástera/Oblečenie spomaľujúce horenie',
            self::StandbyRequired => 'Dozorujúci pripravený a vstupujúci je pripojený k monitorovaciemu systému',
            self::StandbyProtection => 'OOPP/Procedúra',
            self::Other => '',
        };
    }

    /** Path to the SVG piktogram for the map/PDF (we'll place these later). */
    public function piktogramHandle(): string
    {
        return $this->value;
    }

    /**
     * Returns all categories in the order they appear on the PDF template.
     *
     * @return list<self>
     */
    public static function pdfOrder(): array
    {
        return [
            self::Noise,
            self::Skin,
            self::Eyes,
            self::HeadImpact,
            self::PinchCrush,
            self::Cutting,
            self::Ergonomic,
            self::SlipTripFall,
            self::IndustrialTrucks,
            self::HotSurface,
            self::Respiratory,
            self::HazardousEnergyLoto,
            self::Body,
            self::StandbyRequired,
            self::StandbyProtection,
            self::Other,
        ];
    }
}
