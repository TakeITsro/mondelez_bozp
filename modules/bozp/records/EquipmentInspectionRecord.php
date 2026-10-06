<?php

declare(strict_types=1);

namespace modules\bozp\records;

use craft\db\ActiveRecord;

/**
 * EquipmentInspectionRecord
 *
 * One day's equipment safety condition inspection for an `energized`
 * subpermit. Generated daily at 16:00 while the subpermit is open; the
 * issuer closure is blocked while any row is unperformed.
 *
 * @property int         $id
 * @property int         $subpermitId
 * @property int         $permitId
 * @property string      $dueDate            Y-m-d — the day covered
 * @property string      $dueAt              Y-m-d H:i:s — dueDate at 16:00
 * @property string|null $performedAt
 * @property int|null    $performerUserId
 * @property string|null $performerName
 * @property string|null $status             'ok' | 'issue'
 * @property string|null $items              JSON: {itemKey: 'ok'|'nok'|'na'}
 * @property string|null $notes
 * @property int|null    $signatureAssetId
 * @property string|null $ipAddress
 * @property string|null $reminderSentAt
 */
class EquipmentInspectionRecord extends ActiveRecord
{
    public const STATUS_OK    = 'ok';
    public const STATUS_ISSUE = 'issue';

    public static function tableName(): string
    {
        return '{{%bozp_equipment_inspections}}';
    }

    /**
     * Decoded per-item results.
     *
     * @return array<string, string>
     */
    public function decodedItems(): array
    {
        if (!is_string($this->items) || $this->items === '') {
            return [];
        }
        $decoded = json_decode($this->items, true);
        return is_array($decoded) ? $decoded : [];
    }
}
