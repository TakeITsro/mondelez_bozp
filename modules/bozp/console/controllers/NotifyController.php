<?php

declare(strict_types=1);

namespace modules\bozp\console\controllers;

use Craft;
use modules\bozp\Module;
use modules\bozp\enums\SubpermitStatus;
use modules\bozp\records\EquipmentInspectionRecord;
use modules\bozp\records\FireWatchControlRecord;
use modules\bozp\records\PermitRecord;
use modules\bozp\records\SubpermitRecord;
use modules\bozp\services\PermitWorkflow;
use modules\bozp\services\SubpermitSignatureService;
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

    /**
     * Expire permits whose validTo has passed, cascade to their still-open
     * subpermits, and tell the issuer.
     *
     * Only work-capable permits expire (approved / signed / active). Ones
     * already in the closure chain are left alone — the work is done and
     * pulling them out mid-signature would strand the issuer.
     *
     * Subpermits whose own expiresAt has passed are swept too, even when
     * the parent permit is still valid.
     *
     * Fire watch rows are deliberately NOT voided here: those hourly checks
     * outlive the permit, because the fire risk does.
     *
     *   * /15 * * * * php craft bozp/notify/expire-permits
     */
    public function actionExpirePermits(): int
    {
        $now = date('Y-m-d H:i:s');

        /** @var Module $module */
        $module = Craft::$app->getModule('bozp');

        $permits = PermitRecord::find()
            ->where(['in', 'status', PermitWorkflow::EXPIRABLE_STATUSES])
            ->andWhere(['not', ['validTo' => null]])
            ->andWhere(['<', 'validTo', $now])
            ->all();

        $expired = 0;
        foreach ($permits as $permit) {
            try {
                $subpermitCount = $module->permitWorkflow->expireSubpermitsFor((int) $permit->id);
                $module->permitWorkflow->expire($permit);
                $expired++;

                try {
                    $module->permitMailer->notifyIssuerOfExpiry($permit, $subpermitCount);
                } catch (\Throwable $mailErr) {
                    Craft::error(
                        'Expiry notification failed for permit ' . $permit->id . ': ' . $mailErr->getMessage(),
                        __METHOD__,
                    );
                }
            } catch (\Throwable $e) {
                Craft::error(
                    'bozp/notify/expire-permits failed for permit ' . $permit->id . ': ' . $e->getMessage(),
                    __METHOD__,
                );
            }
        }

        // Subpermits that ran out on their own clock while the parent permit
        // is still inside its validity window.
        $orphanExpired = 0;
        $staleSubpermits = SubpermitRecord::find()
            ->where(['status' => SubpermitStatus::Approved->value])
            ->andWhere(['not', ['expiresAt' => null]])
            ->andWhere(['<', 'expiresAt', $now])
            ->all();

        foreach ($staleSubpermits as $subpermit) {
            // Already closed off by the issuer? Leave it — actionSignClosure
            // records the signature without touching the status.
            if ($module->subpermitSignatureService->findSignature(
                (int) $subpermit->id,
                SubpermitSignatureService::ROLE_ISSUER_CLOSURE
            )) {
                continue;
            }
            SubpermitRecord::updateAll(
                ['status' => SubpermitStatus::Expired->value],
                ['id' => $subpermit->id],
            );
            $orphanExpired++;
        }

        $this->stdout("Permits expired: {$expired}, subpermits expired on own clock: {$orphanExpired}\n");
        return ExitCode::OK;
    }

    /**
     * Daily equipment safety inspection for energized subpermits.
     *
     * Raises today's row for every still-open energized subpermit once 16:00
     * has passed, then mails any due-and-unmailed inspection to the issuer.
     *
     * Generation and mailing are both idempotent — a unique
     * (subpermitId, dueDate) index and reminderSentAt mean repeated ticks on
     * the same day add nothing and re-mail nothing.
     *
     *   * /15 * * * * php craft bozp/notify/equipment-inspections
     */
    public function actionEquipmentInspections(): int
    {
        /** @var Module $module */
        $module = Craft::$app->getModule('bozp');

        try {
            $created = $module->equipmentInspectionService->generateDue();
        } catch (\Throwable $e) {
            Craft::error('Equipment inspection generation failed: ' . $e->getMessage(), __METHOD__);
            $created = [];
        }

        $rows = EquipmentInspectionRecord::find()
            ->where(['performedAt' => null, 'reminderSentAt' => null])
            ->andWhere(['<=', 'dueAt', date('Y-m-d H:i:s')])
            ->orderBy(['dueAt' => SORT_ASC])
            ->all();

        $sent = 0;
        foreach ($rows as $inspection) {
            $subpermit = SubpermitRecord::findOne(['id' => $inspection->subpermitId]);
            $permit    = PermitRecord::findOne(['id' => $inspection->permitId]);

            if (!$subpermit || !$permit) {
                continue;
            }

            try {
                $module->permitMailer->notifyIssuerOfEquipmentInspection($permit, $subpermit, $inspection);
                EquipmentInspectionRecord::updateAll(
                    ['reminderSentAt' => date('Y-m-d H:i:s')],
                    ['id' => $inspection->id],
                );
                $sent++;
            } catch (\Throwable $e) {
                Craft::error(
                    'bozp/notify/equipment-inspections failed for ' . $inspection->id . ': ' . $e->getMessage(),
                    __METHOD__,
                );
            }
        }

        $this->stdout('Equipment inspections raised: ' . count($created) . ", notified: {$sent}\n");
        return ExitCode::OK;
    }

    /**
     * Post-hot-work fire watch: mail the issuer each hourly check as it
     * falls due.
     *
     * Picks rows already due, not yet performed, not yet mailed. Because
     * the pick-up is "dueAt <= now" rather than a window, a missed cron run
     * mails late instead of never. reminderSentAt keeps it to one mail per
     * check.
     *
     *   * /15 * * * * php craft bozp/notify/fire-watch
     */
    public function actionFireWatch(): int
    {
        $now = date('Y-m-d H:i:s');

        $rows = FireWatchControlRecord::find()
            ->where(['performedAt' => null, 'reminderSentAt' => null])
            ->andWhere(['<=', 'dueAt', $now])
            ->orderBy(['dueAt' => SORT_ASC])
            ->all();

        if (!$rows) {
            $this->stdout("No fire watch controls due.\n");
            return ExitCode::OK;
        }

        /** @var Module $module */
        $module = Craft::$app->getModule('bozp');
        $sent = 0;

        foreach ($rows as $control) {
            $subpermit = SubpermitRecord::findOne(['id' => $control->subpermitId]);
            $permit    = PermitRecord::findOne(['id' => $control->permitId]);

            if (!$subpermit || !$permit) {
                continue;
            }

            try {
                $module->permitMailer->notifyIssuerOfFireWatchControl($permit, $subpermit, $control);
                FireWatchControlRecord::updateAll(
                    ['reminderSentAt' => date('Y-m-d H:i:s')],
                    ['id' => $control->id],
                );
                $sent++;
            } catch (\Throwable $e) {
                Craft::error(
                    'bozp/notify/fire-watch failed for control ' . $control->id . ': ' . $e->getMessage(),
                    __METHOD__,
                );
            }
        }

        $this->stdout("Fire watch controls notified: {$sent}\n");
        return ExitCode::OK;
    }
}
