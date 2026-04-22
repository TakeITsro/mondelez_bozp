<?php

declare(strict_types=1);

namespace modules\bozp\records;

use craft\db\ActiveRecord;

/**
 * @property int $id
 * @property int|null $permitId
 * @property int|null $userId
 * @property string $action
 * @property string|null $fromStatus
 * @property string|null $toStatus
 * @property array|null $payload
 * @property string|null $ipAddress
 * @property string|null $userAgent
 * @property string $dateCreated
 * @property string $uid
 */
class AuditLogRecord extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%bozp_audit_log}}';
    }
}
