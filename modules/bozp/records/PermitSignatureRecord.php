<?php

declare(strict_types=1);

namespace modules\bozp\records;

use craft\db\ActiveRecord;

/**
 * @property int $id
 * @property int $permitId
 * @property string $role
 * @property string $signerName
 * @property string|null $signerEmployer
 * @property int|null $signatureAssetId
 * @property string|null $signatureDate
 * @property string $signedAt
 * @property string|null $ipAddress
 * @property string|null $userAgent
 * @property string $dateCreated
 * @property string $dateUpdated
 * @property string $uid
 */
class PermitSignatureRecord extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%bozp_permit_signatures}}';
    }
}
