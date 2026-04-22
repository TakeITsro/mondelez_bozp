<?php

declare(strict_types=1);

namespace modules\bozp\records;

use craft\db\ActiveRecord;

/**
 * @property int $id
 * @property int $permitId
 * @property string $attachmentType
 * @property int $assetId
 * @property int|null $uploadedById
 * @property string|null $uploadedByName
 * @property string|null $note
 * @property string $dateCreated
 * @property string $dateUpdated
 * @property string $uid
 */
class PermitAttachmentRecord extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%bozp_permit_attachments}}';
    }
}
