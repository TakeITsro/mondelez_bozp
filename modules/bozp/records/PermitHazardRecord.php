<?php

declare(strict_types=1);

namespace modules\bozp\records;

use craft\db\ActiveRecord;

/**
 * @property int $id
 * @property int $permitId
 * @property string $hazardKey
 * @property bool|null $exposed
 * @property string|null $measure
 * @property string|null $controlDuringActivity
 * @property string|null $controlDuringActivityOther
 * @property int|null $sortOrder
 * @property string $dateCreated
 * @property string $dateUpdated
 * @property string $uid
 */
class PermitHazardRecord extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%bozp_permit_hazards}}';
    }
}
