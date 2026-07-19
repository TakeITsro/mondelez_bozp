<?php

declare(strict_types=1);

namespace modules\bozp\controllers;

use Craft;
use craft\web\Controller;
use modules\bozp\Module;
use Throwable;
use yii\web\Response;

/**
 * CP-side bug report form. Submitting creates a task in the configured
 * ClickUp list via ClickUpClient. Requires `bozp:reportBug` permission.
 *
 * Routes (registered in Module.php):
 *   GET  bozp/bug-report   → actionIndex
 *   POST bozp/bug-report   → actionSubmit
 */
class BugReportController extends Controller
{
    /** Severity label → ClickUp priority (1=urgent, 2=high, 3=normal, 4=low). */
    private const SEVERITY_PRIORITY = [
        'critical' => 1,
        'high'     => 2,
        'medium'   => 3,
        'low'      => 4,
    ];

    public function actionIndex(): Response
    {
        $this->requireLogin();
        $this->requirePermission('bozp:reportBug');

        return $this->renderTemplate('bozp/cp/bug-report', [
            'errors' => [],
            'values' => [
                'title'       => '',
                'severity'    => 'medium',
                'description' => '',
            ],
        ]);
    }

    public function actionSubmit(): Response
    {
        $this->requirePostRequest();
        $this->requireLogin();
        $this->requirePermission('bozp:reportBug');

        $request = Craft::$app->getRequest();
        $values = [
            'title'       => trim((string) $request->getBodyParam('title', '')),
            'severity'    => trim((string) $request->getBodyParam('severity', 'medium')),
            'description' => trim((string) $request->getBodyParam('description', '')),
        ];

        $errors = $this->validate($values);
        if ($errors !== []) {
            Craft::$app->getSession()->setError(Craft::t('bozp', 'Skontrolujte chyby vo formulári.'));
            return $this->renderTemplate('bozp/cp/bug-report', [
                'errors' => $errors,
                'values' => $values,
            ]);
        }

        $user = Craft::$app->getUser()->getIdentity();
        $userLabel = $user
            ? sprintf('%s <%s>', $user->fullName ?: $user->username, $user->email ?: '—')
            : 'unknown';
        $referrer = (string) ($request->getReferrer() ?? '—');
        $userAgent = (string) ($request->getUserAgent() ?? '—');
        $now = date('Y-m-d H:i:s');

        $body  = "## Reported by\n" . $userLabel . "\n\n";
        $body .= "## Context\n";
        $body .= "- Time: " . $now . "\n";
        $body .= "- Referrer URL: " . $referrer . "\n";
        $body .= "- User agent: " . $userAgent . "\n\n";
        $body .= "## Description\n" . $values['description'];

        $priority = self::SEVERITY_PRIORITY[$values['severity']] ?? 3;

        try {
            /** @var Module $module */
            $module = Craft::$app->getModule('bozp');
            $result = $module->clickUpClient->createTask([
                'name'        => '[BOZP] ' . $values['title'],
                'description' => $body,
                'priority'    => $priority,
                'tags'        => ['bozp', 'bug-report', $values['severity']],
            ]);

            $taskUrl = $result['url'] ?? null;
            $notice = $taskUrl
                ? Craft::t('bozp', 'Chyba bola nahlásená. Sledovať môžete na: {url}', ['url' => $taskUrl])
                : Craft::t('bozp', 'Chyba bola nahlásená.');
            Craft::$app->getSession()->setNotice($notice);
        } catch (Throwable $e) {
            Craft::error('BOZP bug report submission failed: ' . $e->getMessage(), __METHOD__);
            $msg = (string) Craft::t('bozp', 'Nahlásenie chyby zlyhalo. Skúste znova alebo kontaktujte správcu.');
            if (Craft::$app->getConfig()->getGeneral()->devMode) {
                $msg .= ' [debug: ' . $e->getMessage() . ']';
            }
            Craft::$app->getSession()->setError($msg);
            return $this->renderTemplate('bozp/cp/bug-report', [
                'errors' => [],
                'values' => $values,
            ]);
        }

        return $this->redirect('bug-report');
    }

    /**
     * @param array<string, string> $values
     * @return array<string, string>
     */
    private function validate(array $values): array
    {
        $errors = [];
        if ($values['title'] === '') {
            $errors['title'] = (string) Craft::t('bozp', 'Názov chyby je povinný.');
        } elseif (mb_strlen($values['title']) > 200) {
            $errors['title'] = (string) Craft::t('bozp', 'Názov je príliš dlhý (max. 200 znakov).');
        }
        if ($values['description'] === '') {
            $errors['description'] = (string) Craft::t('bozp', 'Popis chyby je povinný.');
        }
        if (!isset(self::SEVERITY_PRIORITY[$values['severity']])) {
            $errors['severity'] = (string) Craft::t('bozp', 'Neplatná závažnosť.');
        }
        return $errors;
    }
}
