<?php

declare(strict_types=1);

namespace modules\bozp\services;

use Craft;
use modules\bozp\enums\PermitType;
use modules\bozp\records\PermitRecord;
use yii\base\Component;
use yii\db\Exception as DbException;

/**
 * PermitNumberGenerator
 *
 * Generates human-readable permit numbers in the format:
 *   {TYPE_CODE}-{YEAR}-{SEQ}
 *
 * Examples:
 *   GPTW-2026-00001
 *   GPTW-2026-00002
 *   CSE-2026-00001   (v2)
 *
 * Strategy:
 *   - Count existing permits of the same type+year, +1, zero-pad to 5 digits.
 *   - The DB has a UNIQUE index on bozp_permits.permitNumber, which acts as the
 *     final backstop. On the (rare) race where two requests get the same count,
 *     the second INSERT will fail; we retry with the next number, up to 5 times.
 *
 * Why not a separate sequence table? Adds a write per permit and another point
 * of failure. The unique index already gives us correctness; this approach
 * gives us readable numbers without extra schema.
 */
class PermitNumberGenerator extends Component
{
    private const PAD_LENGTH = 5;
    private const MAX_RETRIES = 5;

    /**
     * Reserve the next number for a given permit type. Caller is expected to
     * INSERT a permit with this number immediately. If the INSERT fails because
     * of the unique index, call next() again — it'll skip the taken number.
     */
    public function next(PermitType $type, ?int $year = null): string
    {
        $year ??= (int) date('Y');
        $code = $type->shortCode();

        for ($attempt = 0; $attempt < self::MAX_RETRIES; $attempt++) {
            $candidate = $this->build($code, $year, $this->nextSeq($code, $year) + $attempt);

            if (!$this->numberExists($candidate)) {
                return $candidate;
            }
        }

        // Extremely unlikely — but if we hit it, something is wrong upstream.
        throw new DbException(sprintf(
            'PermitNumberGenerator: could not allocate a free number for %s-%d after %d attempts.',
            $code,
            $year,
            self::MAX_RETRIES,
        ));
    }

    private function nextSeq(string $code, int $year): int
    {
        $prefix = $code . '-' . $year . '-';

        $count = (int) PermitRecord::find()
            ->where(['like', 'permitNumber', $prefix . '%', false])
            ->count();

        return $count + 1;
    }

    private function build(string $code, int $year, int $seq): string
    {
        return sprintf('%s-%d-%s', $code, $year, str_pad((string) $seq, self::PAD_LENGTH, '0', STR_PAD_LEFT));
    }

    private function numberExists(string $number): bool
    {
        return PermitRecord::find()->where(['permitNumber' => $number])->exists();
    }
}
