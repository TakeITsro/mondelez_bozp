<?php

declare(strict_types=1);

namespace modules\bozp\console\controllers;

use Craft;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\db\Query;
use Throwable;

/**
 * Console: bozp/maintenance/*
 *
 * Manual cleanup tools that don't require direct DB access. Each action
 * prompts for confirmation before destructive work.
 *
 *   php craft bozp/maintenance/delete-all-subpermits
 *   php craft bozp/maintenance/delete-all-permits
 *   php craft bozp/maintenance/delete-all
 */
class MaintenanceController extends Controller
{
    /**
     * Delete every subpermit row + its signatures + signing-request rows.
     *
     * Wrapped in a transaction. Asset files (signature PNGs, SSoW uploads,
     * generated PDFs) are NOT removed — purge orphan assets via Craft CP or
     * the standard Craft asset cleanup utility.
     */
    public function actionDeleteAllSubpermits(): int
    {
        $db = Craft::$app->getDb();

        $count = (int) (new Query())->from('{{%bozp_subpermits}}')->count('*', $db);
        if ($count === 0) {
            $this->stdout("No subpermits to delete.\n");
            return ExitCode::OK;
        }

        $this->stdout("This will delete {$count} subpermits plus all their signatures and signing requests.\n");
        if (!$this->confirm('Proceed? This is irreversible.')) {
            $this->stdout("Aborted.\n");
            return ExitCode::OK;
        }

        $tx = $db->beginTransaction();
        try {
            $counts = $this->purgeSubpermitFamily($db);
            $tx->commit();
        } catch (Throwable $e) {
            $tx->rollBack();
            $this->stderr("Delete failed: " . $e->getMessage() . "\n");
            return ExitCode::SOFTWARE;
        }

        $this->printCounts($counts);
        return ExitCode::OK;
    }

    /**
     * Delete every general permit row + everything that depends on it:
     *   - subpermits (and their signatures + signing requests)
     *   - permit signatures, hazards, attachments, controls
     *   - risk assessments
     *   - audit log entries
     *
     * Asset files are NOT removed.
     */
    public function actionDeleteAllPermits(): int
    {
        $db = Craft::$app->getDb();

        $count = (int) (new Query())->from('{{%bozp_permits}}')->count('*', $db);
        if ($count === 0) {
            $this->stdout("No permits to delete.\n");
            return ExitCode::OK;
        }

        $this->stdout("This will delete {$count} permits plus ALL related subpermits, signatures, hazards, attachments, controls, risk assessments, and audit-log rows.\n");
        if (!$this->confirm('Proceed? This is irreversible.')) {
            $this->stdout("Aborted.\n");
            return ExitCode::OK;
        }

        $tx = $db->beginTransaction();
        try {
            $counts = $this->purgePermitFamily($db);
            $tx->commit();
        } catch (Throwable $e) {
            $tx->rollBack();
            $this->stderr("Delete failed: " . $e->getMessage() . "\n");
            return ExitCode::SOFTWARE;
        }

        $this->printCounts($counts);
        return ExitCode::OK;
    }

    /**
     * Convenience: wipe everything (permits + subpermits + all related rows).
     */
    public function actionDeleteAll(): int
    {
        return $this->actionDeleteAllPermits();
    }

    // ------------------------------------------------------------------
    // internals
    // ------------------------------------------------------------------

    /**
     * @return array<string, int> table-name => rows-deleted
     */
    private function purgeSubpermitFamily($db): array
    {
        $counts = [];
        $counts['bozp_subpermit_signatures']       = $db->createCommand()->delete('{{%bozp_subpermit_signatures}}')->execute();
        $counts['bozp_subpermit_signing_requests'] = $db->createCommand()->delete('{{%bozp_subpermit_signing_requests}}')->execute();
        $counts['bozp_subpermits']                 = $db->createCommand()->delete('{{%bozp_subpermits}}')->execute();
        return $counts;
    }

    /**
     * @return array<string, int> table-name => rows-deleted
     */
    private function purgePermitFamily($db): array
    {
        // Order matters: child rows first to avoid FK errors. Subpermit
        // family first, then permit-level children, then permits.
        $counts = $this->purgeSubpermitFamily($db);

        // Risk-assessment row table (per-row content) — only delete if table
        // exists, since the schema migration is fairly recent.
        if ($db->getTableSchema('{{%bozp_risk_assessment_rows}}') !== null) {
            $counts['bozp_risk_assessment_rows'] = $db->createCommand()->delete('{{%bozp_risk_assessment_rows}}')->execute();
        }
        $counts['bozp_risk_assessments']  = $db->createCommand()->delete('{{%bozp_risk_assessments}}')->execute();

        $counts['bozp_permit_signatures']  = $db->createCommand()->delete('{{%bozp_permit_signatures}}')->execute();
        $counts['bozp_permit_hazards']     = $db->createCommand()->delete('{{%bozp_permit_hazards}}')->execute();
        $counts['bozp_permit_attachments'] = $db->createCommand()->delete('{{%bozp_permit_attachments}}')->execute();
        $counts['bozp_permit_controls']    = $db->createCommand()->delete('{{%bozp_permit_controls}}')->execute();
        $counts['bozp_audit_log']          = $db->createCommand()->delete('{{%bozp_audit_log}}')->execute();
        $counts['bozp_permits']            = $db->createCommand()->delete('{{%bozp_permits}}')->execute();
        return $counts;
    }

    /**
     * @param array<string, int> $counts
     */
    private function printCounts(array $counts): void
    {
        $this->stdout("Deleted:\n");
        foreach ($counts as $table => $n) {
            $this->stdout("  {$table}: {$n}\n");
        }
    }
}
