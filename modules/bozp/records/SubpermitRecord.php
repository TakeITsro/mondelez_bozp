<?php

declare(strict_types=1);

namespace modules\bozp\records;

use craft\db\ActiveRecord;

/**
 * Active Record for bozp_subpermits.
 *
 * @property int         $id
 * @property int         $parentPermitId
 * @property string      $type            SubpermitType enum value
 * @property string      $status          SubpermitStatus enum value
 * @property array|null  $data            JSON blob of type-specific form fields
 * @property int         $issuerId
 * @property int|null    $approverId
 * @property string|null $approvedAt
 * @property string|null $expiresAt       approvedAt + 8 h
 * @property string|null $rejectedAt
 * @property string|null $rejectionNote
 * @property string|null $cancelledAt
 * @property string      $dateCreated
 * @property string      $dateUpdated
 * @property string      $uid
 */
class SubpermitRecord extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%bozp_subpermits}}';
    }
}
