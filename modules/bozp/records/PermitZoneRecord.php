<?php

declare(strict_types=1);

namespace modules\bozp\records;

use craft\db\ActiveRecord;

/**
 * @property int $id
 * @property int $permitId
 * @property int $zoneId
 * @property int|null $sortOrder
 * @property string $dateCreated
 * @property string $dateUpdated
 * @property string $uid
 */
class PermitZoneRecord extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%bozp_permit_zones}}';
    }
}
