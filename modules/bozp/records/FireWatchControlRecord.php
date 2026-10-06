<?php

declare(strict_types=1);

namespace modules\bozp\records;

use craft\db\ActiveRecord;

/**
 * FireWatchControlRecord
 *
 * One hourly post-hot-work fire watch check. Four rows are created per
 * hot_work subpermit when the contractor signs its closure; the issuer
 * closure of that subpermit is gated on all four being performed.
 *
 * @property int         $id
 * @property int         $subpermitId
 * @property int         $permitId
 * @property int         $sequence          1..4
 * @property string      $dueAt
 * @property string|null $performedAt
 * @property int|null    $performerUserId
 * @property string|null $performerName
 * @property string|null $result            'ok' | 'issue'
 * @property string|null $notes
 * @property int|null    $signatureAssetId
 * @property string|null $ipAddress
 * @property string|null $reminderSentAt
 */
class FireWatchControlRecord extends ActiveRecord
{
    public const RESULT_OK    = 'ok';
    public const RESULT_ISSUE = 'issue';

    public static function tableName(): string
    {
        return '{{%bozp_fire_watch_controls}}';
    }
}
