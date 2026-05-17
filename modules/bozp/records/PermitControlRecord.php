<?php

declare(strict_types=1);

namespace modules\bozp\records;

use craft\db\ActiveRecord;

/**
 * Active Record for bozp_permit_controls.
 *
 * @property int         $id
 * @property int         $permitId
 * @property int|null    $controllerUserId
 * @property string      $controllerName
 * @property string      $controlledAt      datetime
 * @property string      $result            ok | issues | stopped
 * @property string|null $notes
 * @property int|null    $signatureAssetId
 * @property string|null $signedAt
 * @property string|null $ipAddress
 * @property string      $dateCreated
 * @property string      $dateUpdated
 * @property string      $uid
 */
class PermitControlRecord extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%bozp_permit_controls}}';
    }
}
