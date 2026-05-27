<?php

declare(strict_types=1);

namespace modules\bozp\services;

/**
 * Reference data for the contractor risk assessment, transcribed verbatim
 * from the client's Excel template (Sheet2 — Tables 1, 2, 3, 4).
 *
 * The matrix maps Probability (P, 1..5) × Consequence (D, 1..4) to a Risk
 * score (R, 1..20). The bands then map R to a qualitative rating and
 * required action.
 *
 * Used by:
 *   - RiskAssessmentService::submit() to compute R server-side, NEVER trust
 *     the client value (the form lets the contractor see the result live,
 *     but persistence is recomputed here).
 *   - The public form Twig template (renders the legend tables).
 *   - The RA PDF template (footnote + per-row colour coding).
 */
final class RiskAssessmentScale
{
    /**
     * P (Pravdepodobnosť) → [label, description].
     * @return array<int, array{label: string, description: string}>
     */
    public static function probability(): array
    {
        return [
            1 => ['label' => 'Veľmi nízka',   'description' => 'Vznik javu je takmer vylúčený'],
            2 => ['label' => 'Nízka',         'description' => 'Vznik javu je málo pravdepodobný, alebo možný'],
            3 => ['label' => 'Stredná',       'description' => 'Jav vznikne niekedy počas životnosti zariadenia, príp. činnosti'],
            4 => ['label' => 'Vysoká',        'description' => 'Jav vznikne niekoľkokrát počas životnosti zariadenia, príp. činnosti'],
            5 => ['label' => 'Veľmi vysoká',  'description' => 'Jav vznikne veľmi často'],
        ];
    }

    /**
     * D (Dôsledok) → [label, characteristic].
     * @return array<int, array{label: string, description: string}>
     */
    public static function consequence(): array
    {
        return [
            1 => ['label' => 'Zanedbateľný',  'description' => 'Evidovaný pracovný úraz bez PN a iný úraz bez PN (bez alebo s ošetrením lekára), zanedbateľná porucha systému'],
            2 => ['label' => 'Málo významný', 'description' => 'Evidovaný pracovný úraz s PN do troch dní / iný úraz s PN / registrovaný pracovný úraz, ohrozenie chorobou z povolania alebo menšie poškodenie systému, finančné straty'],
            3 => ['label' => 'Kritický',      'description' => 'Závažný pracovný úraz, choroba z povolania alebo rozsiahle poškodenie systému, straty vo výrobe, veľké finančné straty'],
            4 => ['label' => 'Katastrofický', 'description' => 'Usmrtenie v dôsledku pracovného úrazu / iného úrazu alebo úplné zničenie systému, nenahraditeľné straty'],
        ];
    }

    /**
     * 5×4 risk matrix from Sheet2!B11:F15.
     * Indexed [probability 1..5][consequence 1..4] => result 1..20.
     *
     * @return array<int, array<int, int>>
     */
    public static function matrix(): array
    {
        return [
            1 => [1 => 1,  2 => 4,  3 => 6,  4 => 12],
            2 => [1 => 2,  2 => 7,  3 => 11, 4 => 13],
            3 => [1 => 3,  2 => 10, 3 => 15, 4 => 17],
            4 => [1 => 5,  2 => 12, 3 => 16, 4 => 19],
            5 => [1 => 8,  2 => 14, 3 => 18, 4 => 20],
        ];
    }

    /**
     * R score bands → rating + advice (Sheet2 Table 4).
     * Ordered from low to high.
     *
     * @return list<array{min: int, max: int, key: string, label: string, advice: string}>
     */
    public static function bands(): array
    {
        return [
            ['min' => 1,  'max' => 3,  'key' => 'acceptable',   'label' => 'Prijateľné',   'advice' => 'Systém je bezpečný, bežné postupy'],
            ['min' => 4,  'max' => 11, 'key' => 'mild',         'label' => 'Mierne',       'advice' => 'Systém je bezpečný s podmienkou zaškolenia obsluhy, prehliadok a pod.'],
            ['min' => 12, 'max' => 15, 'key' => 'undesirable',  'label' => 'Nežiadúce',    'advice' => 'Systém je nebezpečný, je potrebné prijať technické, organizačné, bezpečnostné opatrenia'],
            ['min' => 16, 'max' => 20, 'key' => 'unacceptable', 'label' => 'Neprijateľné', 'advice' => 'Systém je neprijateľný — okamžité uplatnenie ochranných opatrení, odstavenie systému'],
        ];
    }

    /**
     * Compute R from P × D using the matrix. Returns null if either input is
     * out of range (so the caller can flag the row as invalid).
     */
    public static function computeResult(int $probability, int $consequence): ?int
    {
        $matrix = self::matrix();
        return $matrix[$probability][$consequence] ?? null;
    }

    /**
     * Look up the band a given R score falls into.
     * Returns null only if $result is outside 1..20.
     *
     * @return array{min: int, max: int, key: string, label: string, advice: string}|null
     */
    public static function ratingForResult(int $result): ?array
    {
        foreach (self::bands() as $band) {
            if ($result >= $band['min'] && $result <= $band['max']) {
                return $band;
            }
        }
        return null;
    }

    public static function isValidProbability(int $p): bool
    {
        return $p >= 1 && $p <= 5;
    }

    public static function isValidConsequence(int $d): bool
    {
        return $d >= 1 && $d <= 4;
    }
}
