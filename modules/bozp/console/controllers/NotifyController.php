<?php

declare(strict_types=1);

namespace modules\bozp\console\controllers;

use Craft;
use modules\bozp\Module;
use modules\bozp\records\PermitRecord;
use modules\bozp\records\SubpermitRecord;
use yii\console\Controller;
use yii\console\ExitCode;

/**
 * Console: bozp/notify/*
 *
 * Driven by an OS-level cron (run every ~15 minutes). Each action picks
 * candidate rows whose expiry falls inside the warning window AND whose
 * expirationWarningSentAt IS NULL, mails the warning, then stamps the row
 * so the next cron tick doesn't re-mail.
 *
 *   * /15 * * * * php craft bozp/notify/expiring-permits
 *   * /15 * * * * php craft bozp/notify/expiring-subpermits
 *
 * Windows:
 *   Permits — 24h before validTo (general permit lifetime is 7 days).
 *   Subpermits — 1h before expiresAt (subpermit lifetime is 8 hours).
 */
class NotifyController extends Controller
{
    /**
     * Warn issuer + contractor 24h before a permit's validTo.
     * Status restricted to approved / signed / active — anything else is
     * either pre-approval or already closed.
     */
    public function actionExpiringPermits(): int
    {
        $now = new \DateTimeImmutable();
        $windowStart = $now->format('Y-m-d H:i:s');
        $windowEnd   = $now->modify('+24 hours')->format('Y-m-d H:i:s');

        $rows = PermitRecord::find()
            ->where(['expirationWarningSentAt' => null])
            ->andWhere(['in', 'status', ['approved', 'signed', 'active']])
            ->andWhere(['between', 'validTo', $windowStart, $windowEnd])
            ->all();

        if (!$rows) {
            $this->stdout("No permits in the 24h warning window.\n");
            return ExitCode::OK;
        }

        /** @var Module $module */
        $module = Craft::$app->getModule('bozp');
        $sent = 0;
        foreach ($rows as $permit) {
            try {
                $module->permitMailer->notifyPermitExpiringSoon($permit);
                PermitRecord::updateAll(
                    ['expirationWarningSentAt' => date('Y-m-d H:i:s')],
                    ['id' => $permit->id],
                );
                $sent++;
            } catch (\Throwable $e) {
                Craft::error(
                    'bozp/notify/expiring-permits failed for ' . $permit->id . ': ' . $e->getMessage(),
                    __METHOD__,
                );
            }
        }
        $this->stdout("Permits warned: {$sent}\n");
        return ExitCode::OK;
    }

    /**
     * Warn issuer + contractor 1h before a subpermit's expiresAt.
     * Only 'approved' subpermits — they're the only ones with expiresAt set.
     */
    public function actionExpiringSubpermits(): int
    {
        $now = new \DateTimeImmutable();
        $windowStart = $now->format('Y-m-d H:i:s');
        $windowEnd   = $now->modify('+1 hour')->format('Y-m-d H:i:s');

        $rows = SubpermitRecord::find()
            ->where(['expirationWarningSentAt' => null])
            ->andWhere(['status' => 'approved'])
            ->andWhere(['between', 'expiresAt', $windowStart, $windowEnd])
            ->all();

        if (!$rows) {
            $this->stdout("No subpermits in the 1h warning window.\n");
            return ExitCode::OK;
        }

        /** @var Module $module */
        $module = Craft::$app->getModule('bozp');
        $sent = 0;
        foreach ($rows as $subpermit) {
            $permit = PermitRecord::findOne(['id' => $subpermit->parentPermitId]);
            if (!$permit) {
                continue;
            }
            try {
                $module->permitMailer->notifySubpermitExpiringSoon($subpermit, $permit);
                SubpermitRecord::updateAll(
                    ['expirationWarningSentAt' => date('Y-m-d H:i:s')],
                    ['id' => $subpermit->id],
                );
                $sent++;
            } catch (\Throwable $e) {
                Craft::error(
                    'bozp/notify/expiring-subpermits failed for ' . $subpermit->id . ': ' . $e->getMessage(),
                    __METHOD__,
                );
            }
        }
        $this->stdout("Subpermits warned: {$sent}\n");
        return ExitCode::OK;
    }
}
