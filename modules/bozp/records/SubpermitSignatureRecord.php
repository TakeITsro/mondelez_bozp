<?php

declare(strict_types=1);

namespace modules\bozp\records;

use craft\db\ActiveRecord;

/**
 * @property int         $id
 * @property int         $subpermitId
 * @property string      $role          'issuer' | 'contractor'
 * @property string      $signerName
 * @property string|null $signerEmployer
 * @property int|null    $signatureAssetId
 * @property string|null $signatureDate
 * @property string      $signedAt
 * @property string|null $ipAddress
 * @property string|null $userAgent
 * @property string      $dateCreated
 * @property string      $dateUpdated
 * @property string      $uid
 */
class SubpermitSignatureRecord extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%bozp_subpermit_signatures}}';
    }
}
