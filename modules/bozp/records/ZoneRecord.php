<?php

declare(strict_types=1);

namespace modules\bozp\records;

use craft\db\ActiveRecord;

/**
 * @property int $id
 * @property string $name
 * @property string|null $code
 * @property string|null $description
 * @property array|null $geometry
 * @property int|null $sortOrder
 * @property bool $archived
 * @property string $dateCreated
 * @property string $dateUpdated
 * @property string $uid
 */
class ZoneRecord extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%bozp_zones}}';
    }
}
