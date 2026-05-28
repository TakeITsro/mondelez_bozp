<?php

declare(strict_types=1);

namespace modules\bozp\services;

use Craft;
use craft\helpers\App;
use Throwable;
use yii\base\Component;

/**
 * Thin HTTP client for the ClickUp API.
 *
 * Currently used only by BugReportController to create a task in a
 * predefined list when a CP user files a bug report. Credentials are
 * env-only (never persisted in DB / project config).
 *
 *   CLICKUP_TOKEN           Personal API token, e.g. `pk_xxxxxxxx`
 *   CLICKUP_LIST_ID         Target list id (numeric string)
 *   CLICKUP_PARENT_TASK_ID  Optional. When set, every created task is
 *                           nested as a subtask of this parent.
 */
class ClickUpClient extends Component
{
    private const API_BASE = 'https://api.clickup.com/api/v2';
    private const TIMEOUT_SECONDS = 15;

    /**
     * Create a task in the configured list.
     *
     * @param array{name: string, description: string, priority: int, tags?: string[], parent?: string} $payload
     * @return array<string, mixed> decoded JSON response (includes new task `id`)
     * @throws \RuntimeException when credentials missing or HTTP fails
     */
    public function createTask(array $payload): array
    {
        $token        = (string) App::env('CLICKUP_TOKEN');
        $listId       = (string) App::env('CLICKUP_LIST_ID');
        $parentTaskId = trim((string) App::env('CLICKUP_PARENT_TASK_ID'));

        if ($token === '' || $listId === '') {
            throw new \RuntimeException('ClickUp credentials missing (CLICKUP_TOKEN / CLICKUP_LIST_ID).');
        }

        // When configured, nest every created task as a subtask of the
        // designated parent. Caller-supplied `parent` (if any) wins.
        if ($parentTaskId !== '' && empty($payload['parent'])) {
            $payload['parent'] = $parentTaskId;
        }

        $client = Craft::createGuzzleClient([
            'timeout' => self::TIMEOUT_SECONDS,
        ]);

        try {
            $response = $client->post(
                self::API_BASE . '/list/' . $listId . '/task',
                [
                    'headers' => [
                        'Authorization' => $token,
                        'Content-Type'  => 'application/json',
                    ],
                    'json' => $payload,
                ]
            );
        } catch (Throwable $e) {
            Craft::error(
                'BOZP ClickUp createTask HTTP failure: ' . $e->getMessage(),
                __METHOD__,
            );
            throw new \RuntimeException('ClickUp task creation failed: ' . $e->getMessage(), 0, $e);
        }

        $body = (string) $response->getBody();
        $decoded = json_decode($body, true);

        if (!is_array($decoded)) {
            throw new \RuntimeException('ClickUp returned malformed JSON: ' . substr($body, 0, 200));
        }
        if (!empty($decoded['err'])) {
            throw new \RuntimeException('ClickUp API error: ' . $decoded['err']);
        }

        return $decoded;
    }
}
