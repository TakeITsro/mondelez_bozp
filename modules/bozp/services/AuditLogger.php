<?php

declare(strict_types=1);

namespace modules\bozp\services;

use Craft;
use modules\bozp\records\AuditLogRecord;
use yii\base\Component;

/**
 * AuditLogger
 *
 * Single entry point for writing rows into bozp_audit_log. Anything that
 * mutates a permit should call this so we keep an immutable trail for
 * compliance and "who did what when" debugging.
 *
 * Captures IP + user agent automatically from the current request when one is
 * available (CP/site requests). For console / queue jobs both come back null.
 *
 * Freeform context (previously called "note") goes into the `payload` JSON
 * column under key "note" so we can add more structured fields later without
 * another migration.
 */
class AuditLogger extends Component
{
    /**
     * @param array<string, mixed>|null $payload Extra structured data to log (goes into bozp_audit_log.payload).
     */
    public function log(
        int $permitId,
        ?int $userId,
        string $action,
        ?string $fromStatus = null,
        ?string $toStatus = null,
        ?string $note = null,
        ?array $payload = null,
    ): void {
        if ($note !== null && $note !== '') {
            $payload = ($payload ?? []) + ['note' => $note];
        }

        $record = new AuditLogRecord();
        $record->permitId = $permitId;
        $record->userId = $userId;
        $record->action = $action;
        $record->fromStatus = $fromStatus;
        $record->toStatus = $toStatus;
        $record->payload = $payload;
        $record->ipAddress = $this->currentIp();
        $record->userAgent = $this->currentUserAgent();

        if (!$record->save()) {
            // Don't throw — losing an audit row should never break a workflow
            // step. Log loudly so we notice in monitoring.
            Craft::error(
                'AuditLogger: failed to write audit row for permit ' . $permitId
                . ' / action ' . $action
                . ' — ' . print_r($record->getErrors(), true),
                __METHOD__,
            );
        }
    }

    private function currentIp(): ?string
    {
        $request = Craft::$app->getRequest();
        if ($request->getIsConsoleRequest()) {
            return null;
        }
        $ip = $request->getUserIP();
        return $ip !== null ? substr($ip, 0, 45) : null;
    }

    private function currentUserAgent(): ?string
    {
        $request = Craft::$app->getRequest();
        if ($request->getIsConsoleRequest()) {
            return null;
        }
        $ua = $request->getUserAgent();
        return $ua !== null ? substr($ua, 0, 255) : null;
    }
}
