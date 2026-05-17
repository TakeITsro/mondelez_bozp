<?php

declare(strict_types=1);

namespace modules\bozp\records;

use craft\db\ActiveRecord;

/**
 * Active Record for bozp_subpermit_signing_requests.
 *
 * One row per required signer per subpermit. Created when a multi-signer
 * subpermit (e.g. Electrical) is saved. signedAt is null until the signer
 * follows their token URL and submits their signature.
 *
 * @property int         $id
 * @property int         $subpermitId
 * @property string      $role             SubpermitSigningRole value
 * @property string      $signerEmail      address where the invitation was sent
 * @property string|null $signerName       filled by the signer when they sign
 * @property string      $token            unique 64-char hex string
 * @property int|null    $signatureAssetId FK → assets, set when signed
 * @property string|null $signedAt
 * @property string|null $ipAddress
 * @property string      $dateCreated
 * @property string      $dateUpdated
 * @property string      $uid
 */
class SubpermitSigningRequestRecord extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%bozp_subpermit_signing_requests}}';
    }
}
