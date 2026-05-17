<?php

declare(strict_types=1);

namespace modules\bozp\controllers;

use Craft;
use craft\elements\User;
use craft\web\Controller;
use craft\web\View;
use modules\bozp\enums\HazardCategory;
use modules\bozp\enums\PermitStatus;
use modules\bozp\enums\SubpermitStatus;
use modules\bozp\enums\SubpermitType;
use modules\bozp\Module;
use modules\bozp\records\AuditLogRecord;
use modules\bozp\records\PermitAttachmentRecord;
use modules\bozp\records\PermitControlRecord;
use modules\bozp\records\PermitHazardRecord;
use modules\bozp\records\PermitRecord;
use modules\bozp\records\SubpermitRecord;
use modules\bozp\records\ZoneRecord;
use Throwable;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * HSE approval queue — landing page for "BOZP Permity" in the CP.
 *
 * Phase 2C.1 adds:
 *   actionView($id)    — permit detail view
 *   actionApprove($id) — POST approve (requires bozp:approve)
 *   actionReject($id)  — POST reject with comment
 */
class QueueController extends Controller
{
    public function actionIndex(): Response
    {
        $this->requireLogin();
        $this->requirePermission('bozp:viewQueue');

        $pendingPermits = PermitRecord::find()
            ->where(['status' => PermitStatus::Submitted->value])
            ->orderBy(['submittedAt' => SORT_ASC])
            ->limit(50)
            ->all();

        $pendingCount = (int) PermitRecord::find()
            ->where(['status' => PermitStatus::Submitted->value])
            ->count();

        $this->view->setTemplateMode(View::TEMPLATE_MODE_CP);

        return $this->renderTemplate('bozp/cp/queue', [
            'pendingCount' => $pendingCount,
            'pendingPermits' => $pendingPermits,
            'incompletePermitIds' => $this->computeIncompletePermitIds($pendingPermits),
        ]);
    }

    public function actionAll(): Response
    {
        $this->requireLogin();
        $this->requirePermission('bozp:viewAll');

        $request = Craft::$app->getRequest();
        $statusFilter = (string) $request->getQueryParam('status', '');

        $validStatuses = array_map(static fn (PermitStatus $s) => $s->value, PermitStatus::cases());

        $query = PermitRecord::find()->orderBy(['dateCreated' => SORT_DESC])->limit(200);

        if ($statusFilter !== '' && in_array($statusFilter, $validStatuses, true)) {
            $query->andWhere(['status' => $statusFilter]);
        }

        $permits = $query->all();

        $this->view->setTemplateMode(View::TEMPLATE_MODE_CP);

        return $this->renderTemplate('bozp/cp/all-permits', [
            'permits' => $permits,
            'statusFilter' => $statusFilter,
            'validStatuses' => $validStatuses,
        ]);
    }

    public function actionView(int $id): Response
    {
        $this->requireLogin();
        $this->requirePermission('bozp:viewQueue');

        $permit = $this->findPermit($id);

        $zones = $this->loadZonesFor($permit->id);
        $issuer = $permit->issuerId ? User::find()->id($permit->issuerId)->one() : null;
        $approver = $permit->approverId ? User::find()->id($permit->approverId)->one() : null;
        $auditEntries = AuditLogRecord::find()
            ->where(['permitId' => $permit->id])
            ->orderBy(['dateCreated' => SORT_DESC])
            ->limit(50)
            ->all();

        $subpermits = SubpermitRecord::find()
            ->where(['parentPermitId' => $permit->id])
            ->orderBy(['dateCreated' => SORT_ASC])
            ->all();

        $rawRequired = $permit->requiresHighRisk;
        $requiredTypes = is_string($rawRequired)
            ? (json_decode($rawRequired, true) ?? [])
            : ($rawRequired ?? []);

        $this->view->setTemplateMode(View::TEMPLATE_MODE_CP);

        return $this->renderTemplate('bozp/cp/permit-view', [
            'permit' => $permit,
            'zones' => $zones,
            'issuer' => $issuer,
            'approver' => $approver,
            'auditEntries' => $auditEntries,
            'hazardCategories' => HazardCategory::pdfOrder(),
            'hazards' => $this->loadHazardsFor((int) $permit->id),
            'attachments' => $this->loadAttachmentsFor((int) $permit->id),
            'canApprove' => Craft::$app->getUser()->checkPermission('bozp:approve'),
            'canDelete' => Craft::$app->getUser()->checkPermission('bozp:deletePermit'),
            'subpermits' => $subpermits,
            'subpermitTypes' => SubpermitType::cases(),
            'requiredSubpermitTypes' => $requiredTypes,
            'controls' => PermitControlRecord::find()
                ->where(['permitId' => $permit->id])
                ->orderBy(['controlledAt' => SORT_DESC])
                ->all(),
        ]);
    }

    /**
     * CP subpermit detail view for HSE officer.
     * GET bozp/permit/<permitId>/subpermit/<id>
     */
    public function actionSubpermitView(int $permitId, int $id): Response
    {
        $this->requireLogin();
        $this->requirePermission('bozp:viewQueue');

        $permit    = $this->findPermit($permitId);
        $subpermit = $this->findSubpermit($id, $permitId);

        $rawData = is_string($subpermit->data) ? (json_decode($subpermit->data, true) ?? []) : ($subpermit->data ?? []);
        $type    = SubpermitType::tryFrom($subpermit->type);

        /** @var Module $module */
        $module     = Craft::$app->getModule('bozp');
        $signatures = $module->subpermitSignatureService->findAllForSubpermit((int) $subpermit->id);

        $this->view->setTemplateMode(View::TEMPLATE_MODE_CP);

        return $this->renderTemplate('bozp/cp/subpermit-view', [
            'permit'        => $permit,
            'subpermit'     => $subpermit,
            'subpermitType' => $type,
            'values'        => $rawData,
            'signatures'    => $signatures,
            'canApprove'    => Craft::$app->getUser()->checkPermission('bozp:approve'),
            'subpermitTypes' => SubpermitType::cases(),
        ]);
    }

    /**
     * GET bozp/permit/<id>/pdf — CP permit PDF download.
     */
    public function actionPermitPdf(int $id): Response
    {
        $this->requireLogin();
        $this->requirePermission('bozp:viewQueue');

        $permit = $this->findPermit($id);

        /** @var Module $module */
        $module = Craft::$app->getModule('bozp');

        if (empty($permit->pdfAssetId)) {
            $module->permitPdfService->generateForPermit($permit);
            $permit = $this->findPermit($id);
        }

        if (empty($permit->pdfAssetId)) {
            throw new \yii\web\NotFoundHttpException('PDF is not available.');
        }

        $asset = Craft::$app->getAssets()->getAssetById((int) $permit->pdfAssetId);
        if (!$asset || !$asset->url) {
            throw new \yii\web\NotFoundHttpException('PDF asset not found.');
        }

        return $this->redirect($asset->url);
    }

    /**
     * GET bozp/permit/<permitId>/subpermit/<id>/pdf — CP subpermit PDF download.
     */
    public function actionSubpermitPdf(int $permitId, int $id): Response
    {
        $this->requireLogin();
        $this->requirePermission('bozp:viewQueue');

        $permit    = $this->findPermit($permitId);
        $subpermit = $this->findSubpermit($id, $permitId);

        /** @var Module $module */
        $module = Craft::$app->getModule('bozp');

        if (empty($subpermit->pdfAssetId)) {
            $module->permitPdfService->generateForSubpermit($subpermit, $permit);
            $subpermit = $this->findSubpermit($id, $permitId);
        }

        if (empty($subpermit->pdfAssetId)) {
            throw new \yii\web\NotFoundHttpException('PDF is not available.');
        }

        $asset = Craft::$app->getAssets()->getAssetById((int) $subpermit->pdfAssetId);
        if (!$asset || !$asset->url) {
            throw new \yii\web\NotFoundHttpException('PDF asset not found.');
        }

        return $this->redirect($asset->url);
    }

    /**
     * CP subpermit edit form for HSE officer.
     * GET bozp/permit/<permitId>/subpermit/<id>/edit
     */
    public function actionEditSubpermit(int $permitId, int $id): Response
    {
        $this->requireLogin();
        $this->requirePermission('bozp:approve');

        $permit    = $this->findPermit($permitId);
        $subpermit = $this->findSubpermit($id, $permitId);
        $rawData   = is_string($subpermit->data) ? (json_decode($subpermit->data, true) ?? []) : ($subpermit->data ?? []);
        $type      = SubpermitType::tryFrom($subpermit->type);

        $this->view->setTemplateMode(View::TEMPLATE_MODE_CP);

        return $this->renderTemplate('bozp/cp/subpermit-edit', [
            'permit'        => $permit,
            'subpermit'     => $subpermit,
            'subpermitType' => $type,
            'values'        => $rawData,
            'errors'        => [],
            'canApprove'    => true,
        ]);
    }

    /**
     * CP permit edit form for HSE officer.
     * GET bozp/permit/<permitId>/edit
     */
    public function actionEditPermit(int $permitId): Response
    {
        $this->requireLogin();
        $this->requirePermission('bozp:approve');

        $permit = $this->findPermit($permitId);
        $zones  = $this->loadZonesFor((int) $permit->id);

        $allZones = ZoneRecord::find()->orderBy(['sortOrder' => SORT_ASC, 'name' => SORT_ASC])->all();

        $rawRequired = $permit->requiresHighRisk;
        $selectedZoneIds = array_map(static fn(ZoneRecord $z) => (int) $z->id, $zones);

        $this->view->setTemplateMode(View::TEMPLATE_MODE_CP);

        return $this->renderTemplate('bozp/cp/permit-edit', [
            'permit'          => $permit,
            'zones'           => $allZones,
            'selectedZoneIds' => $selectedZoneIds,
            'errors'          => [],
            'subpermitTypes'  => SubpermitType::cases(),
        ]);
    }

    public function actionApproveSubpermit(): ?Response
    {
        $this->requirePostRequest();
        $this->requireLogin();
        $this->requirePermission('bozp:approve');

        $request = Craft::$app->getRequest();
        $permitId = (int) $request->getRequiredBodyParam('permitId');
        $id = (int) $request->getRequiredBodyParam('id');

        $permit = $this->findPermit($permitId);
        $subpermit = $this->findSubpermit($id, $permitId);

        if ($subpermit->status !== SubpermitStatus::Pending->value) {
            Craft::$app->getSession()->setError(Craft::t('bozp', 'Subpermit nie je v stave na schválenie.'));
            return $this->redirect("bozp/permit/{$permitId}");
        }

        $now = date('Y-m-d H:i:s');
        $expiresAt = date('Y-m-d H:i:s', strtotime('+8 hours'));

        $subpermit->status = SubpermitStatus::Approved->value;
        $subpermit->approverId = Craft::$app->getUser()->getId();
        $subpermit->approvedAt = $now;
        $subpermit->expiresAt = $expiresAt;

        if (!$subpermit->save()) {
            Craft::error('Subpermit approve failed: ' . print_r($subpermit->getErrors(), true), __METHOD__);
            Craft::$app->getSession()->setError(Craft::t('bozp', 'Subpermit sa nepodarilo schváliť.'));
            return $this->redirect("bozp/permit/{$permitId}");
        }

        // Generate subpermit PDF
        /** @var Module $module */
        $module = Craft::$app->getModule('bozp');
        $module->permitPdfService->generateForSubpermit($subpermit, $permit);

        Craft::$app->getSession()->setNotice(Craft::t('bozp', 'Subpermit bol schválený. Platnosť 8 hodín.'));
        return $this->redirect("bozp/permit/{$permitId}");
    }

    public function actionRejectSubpermit(): ?Response
    {
        $this->requirePostRequest();
        $this->requireLogin();
        $this->requirePermission('bozp:approve');

        $request = Craft::$app->getRequest();
        $permitId = (int) $request->getRequiredBodyParam('permitId');
        $id = (int) $request->getRequiredBodyParam('id');
        $note = trim((string) $request->getBodyParam('rejectionNote', ''));

        if ($note === '') {
            Craft::$app->getSession()->setError(Craft::t('bozp', 'Pri zamietnutí je dôvod povinný.'));
            return $this->redirect("bozp/permit/{$permitId}");
        }

        $permit = $this->findPermit($permitId);
        $subpermit = $this->findSubpermit($id, $permitId);

        if ($subpermit->status !== SubpermitStatus::Pending->value) {
            Craft::$app->getSession()->setError(Craft::t('bozp', 'Subpermit nie je v stave na zamietnutie.'));
            return $this->redirect("bozp/permit/{$permitId}");
        }

        $subpermit->status = SubpermitStatus::Rejected->value;
        $subpermit->approverId = Craft::$app->getUser()->getId();
        $subpermit->rejectedAt = date('Y-m-d H:i:s');
        $subpermit->rejectionNote = $note;

        if (!$subpermit->save()) {
            Craft::error('Subpermit reject failed: ' . print_r($subpermit->getErrors(), true), __METHOD__);
            Craft::$app->getSession()->setError(Craft::t('bozp', 'Subpermit sa nepodarilo zamietnuť.'));
            return $this->redirect("bozp/permit/{$permitId}");
        }

        // Generate subpermit PDF
        /** @var Module $module */
        $module = Craft::$app->getModule('bozp');
        $module->permitPdfService->generateForSubpermit($subpermit, $permit);

        Craft::$app->getSession()->setNotice(Craft::t('bozp', 'Subpermit bol zamietnutý.'));
        return $this->redirect("bozp/permit/{$permitId}");
    }

    private function findSubpermit(int $id, int $permitId): SubpermitRecord
    {
        $subpermit = SubpermitRecord::findOne(['id' => $id, 'parentPermitId' => $permitId]);
        if (!$subpermit) {
            throw new NotFoundHttpException('Subpermit not found.');
        }
        return $subpermit;
    }

    /** @return PermitAttachmentRecord[] */
    private function loadAttachmentsFor(int $permitId): array
    {
        return PermitAttachmentRecord::find()
            ->where(['permitId' => $permitId])
            ->orderBy(['dateCreated' => SORT_DESC])
            ->all();
    }

    /**
     * @return array<string, PermitHazardRecord> keyed by hazardKey
     */
    private function loadHazardsFor(int $permitId): array
    {
        $rows = PermitHazardRecord::find()
            ->where(['permitId' => $permitId])
            ->orderBy(['sortOrder' => SORT_ASC, 'id' => SORT_ASC])
            ->all();

        $out = [];
        foreach ($rows as $row) {
            $out[$row->hazardKey] = $row;
        }
        return $out;
    }

    public function actionApprove(): ?Response
    {
        $this->requirePostRequest();
        $this->requireLogin();
        $this->requirePermission('bozp:approve');

        $id = (int) Craft::$app->getRequest()->getRequiredBodyParam('id');
        $permit = $this->findPermit($id);

        if ($permit->status !== PermitStatus::Submitted->value) {
            Craft::$app->getSession()->setError(Craft::t('bozp', 'Permit nie je v stave na schválenie.'));
            return $this->redirect("bozp/permit/{$permit->id}");
        }

        $comment = trim((string) Craft::$app->getRequest()->getBodyParam('comment', ''));
        $userId = Craft::$app->getUser()->getId();

        /** @var Module $module */
        $module = Craft::$app->getModule('bozp');

        $db = Craft::$app->getDb();
        $transaction = $db->beginTransaction();

        try {
            $module->permitWorkflow->approve($permit, $userId, $comment !== '' ? $comment : null);
            $transaction->commit();
        } catch (Throwable $e) {
            $transaction->rollBack();
            Craft::error('Permit approve failed: ' . $e->getMessage(), __METHOD__);

            $message = (string) Craft::t('bozp', 'Permit sa nepodarilo schváliť. Skúste znova.');
            if (Craft::$app->getConfig()->getGeneral()->devMode) {
                $message .= ' [dev] ' . $e->getMessage();
            }
            Craft::$app->getSession()->setError($message);
            return $this->redirect("bozp/permit/{$permit->id}");
        }

        // Generate PDF after transaction commit (silently on failure)
        $module->permitPdfService->generateForPermit($permit);

        Craft::$app->getSession()->setNotice(Craft::t('bozp', 'Permit {n} bol schválený.', ['n' => $permit->permitNumber]));
        return $this->redirect('bozp');
    }

    public function actionReject(): ?Response
    {
        $this->requirePostRequest();
        $this->requireLogin();
        $this->requirePermission('bozp:approve');

        $id = (int) Craft::$app->getRequest()->getRequiredBodyParam('id');
        $permit = $this->findPermit($id);

        if ($permit->status !== PermitStatus::Submitted->value) {
            Craft::$app->getSession()->setError(Craft::t('bozp', 'Permit nie je v stave na zamietnutie.'));
            return $this->redirect("bozp/permit/{$permit->id}");
        }

        $comment = trim((string) Craft::$app->getRequest()->getBodyParam('comment', ''));

        if ($comment === '') {
            Craft::$app->getSession()->setError(Craft::t('bozp', 'Pri zamietnutí je komentár povinný.'));
            return $this->redirect("bozp/permit/{$permit->id}");
        }

        $userId = Craft::$app->getUser()->getId();

        /** @var Module $module */
        $module = Craft::$app->getModule('bozp');

        $db = Craft::$app->getDb();
        $transaction = $db->beginTransaction();

        try {
            $module->permitWorkflow->reject($permit, $userId, $comment);
            $transaction->commit();
        } catch (Throwable $e) {
            $transaction->rollBack();
            Craft::error('Permit reject failed: ' . $e->getMessage(), __METHOD__);

            $message = (string) Craft::t('bozp', 'Permit sa nepodarilo zamietnuť. Skúste znova.');
            if (Craft::$app->getConfig()->getGeneral()->devMode) {
                $message .= ' [dev] ' . $e->getMessage();
            }
            Craft::$app->getSession()->setError($message);
            return $this->redirect("bozp/permit/{$permit->id}");
        }

        // Generate PDF after transaction commit
        $module->permitPdfService->generateForPermit($permit);

        Craft::$app->getSession()->setNotice(Craft::t('bozp', 'Permit {n} bol zamietnutý.', ['n' => $permit->permitNumber]));
        return $this->redirect('bozp');
    }

    /**
     * Resend the approval / rejection notification for a permit.
     * Approval resend regenerates the contractor token + password
     * (per requirement); rejection resend just re-sends the existing
     * reason from the audit trail / approvalComment.
     */
    public function actionResend(int $id): ?Response
    {
        $this->requirePostRequest();
        $this->requireLogin();
        $this->requirePermission('bozp:approve');

        $permit = $this->findPermit($id);

        /** @var Module $module */
        $module = Craft::$app->getModule('bozp');

        try {
            if ($permit->status === PermitStatus::Approved->value
                || $permit->status === PermitStatus::Signed->value
                || $permit->status === PermitStatus::Active->value
            ) {
                $newPassword = $module->permitWorkflow->regenerateContractorAccess($permit);
                $module->permitMailer->notifyParticipantsOfApproval($permit, $newPassword);
                $msg = (string) Craft::t(
                    'bozp',
                    'Notifikácia o schválení bola znova odoslaná. Vygenerované nové prístupové údaje pre dodávateľa.'
                );
            } elseif ($permit->status === PermitStatus::Rejected->value) {
                $reason = (string) ($permit->approvalComment ?? '');
                $module->permitMailer->notifyParticipantsOfRejection($permit, $reason);
                $msg = (string) Craft::t('bozp', 'Notifikácia o zamietnutí bola znova odoslaná.');
            } else {
                Craft::$app->getSession()->setError(
                    Craft::t('bozp', 'Notifikáciu možno znova odoslať len pre schválené alebo zamietnuté permity.')
                );
                return $this->redirect("bozp/permit/{$permit->id}");
            }
        } catch (Throwable $e) {
            Craft::error('Permit resend failed: ' . $e->getMessage(), __METHOD__);
            $error = (string) Craft::t('bozp', 'Notifikáciu sa nepodarilo odoslať.');
            if (Craft::$app->getConfig()->getGeneral()->devMode) {
                $error .= ' [dev] ' . $e->getMessage();
            }
            Craft::$app->getSession()->setError($error);
            return $this->redirect("bozp/permit/{$permit->id}");
        }

        Craft::$app->getSession()->setNotice($msg);
        return $this->redirect("bozp/permit/{$permit->id}");
    }

    /**
     * Hard-delete a permit. Requires bozp:deletePermit. The DB FK
     * definitions cascade-delete dependent rows (zones, hazards,
     * signatures, attachments); audit log rows survive (SET NULL on
     * permitId) so the trail isn't fully erased.
     */
    public function actionDelete(int $id): ?Response
    {
        $this->requirePostRequest();
        $this->requireLogin();
        $this->requirePermission('bozp:deletePermit');

        $permit = $this->findPermit($id);
        $number = $permit->permitNumber;

        try {
            if (!$permit->delete()) {
                throw new \RuntimeException('Failed to delete permit row.');
            }
        } catch (Throwable $e) {
            Craft::error('Permit delete failed: ' . $e->getMessage(), __METHOD__);
            $msg = (string) Craft::t('bozp', 'Permit sa nepodarilo zmazať.');
            if (Craft::$app->getConfig()->getGeneral()->devMode) {
                $msg .= ' [dev] ' . $e->getMessage();
            }
            Craft::$app->getSession()->setError($msg);
            return $this->redirect("bozp/permit/{$id}");
        }

        Craft::$app->getSession()->setNotice(
            Craft::t('bozp', 'Permit {n} bol zmazaný.', ['n' => $number])
        );
        return $this->redirect('bozp/all');
    }

    /**
     * Returns a set of permit IDs that have at least one required subpermit
     * type without a corresponding approved subpermit.
     *
     * @param PermitRecord[] $permits
     * @return array<int, true>  keyed by permitId
     */
    private function computeIncompletePermitIds(array $permits): array
    {
        if ($permits === []) {
            return [];
        }

        $permitIds = array_map(fn(PermitRecord $p) => (int) $p->id, $permits);

        // Collect all approved subpermit types for these permits in one query.
        $approvedRows = (new \yii\db\Query())
            ->select(['parentPermitId', 'type'])
            ->from('{{%bozp_subpermits}}')
            ->where(['parentPermitId' => $permitIds, 'status' => SubpermitStatus::Approved->value])
            ->all();

        // Build map: permitId → [type => true]
        $approvedByPermit = [];
        foreach ($approvedRows as $row) {
            $approvedByPermit[(int) $row['parentPermitId']][$row['type']] = true;
        }

        $incomplete = [];
        foreach ($permits as $permit) {
            $raw = $permit->requiresHighRisk;
            // JSON column may come back as string or already-decoded array.
            if (is_string($raw)) {
                $raw = json_decode($raw, true);
            }
            $required = is_array($raw) ? $raw : [];

            if ($required === []) {
                continue;
            }

            $approved = $approvedByPermit[(int) $permit->id] ?? [];
            foreach ($required as $type) {
                if (!isset($approved[$type])) {
                    $incomplete[(int) $permit->id] = true;
                    break;
                }
            }
        }

        return $incomplete;
    }

    private function findPermit(int $id): PermitRecord
    {
        $permit = PermitRecord::findOne(['id' => $id]);
        if (!$permit) {
            throw new NotFoundHttpException('Permit not found.');
        }
        return $permit;
    }

    /** @return ZoneRecord[] */
    private function loadZonesFor(int $permitId): array
    {
        $zoneIds = (new \yii\db\Query())
            ->select('zoneId')
            ->from('{{%bozp_permit_zones}}')
            ->where(['permitId' => $permitId])
            ->column();

        if ($zoneIds === []) {
            return [];
        }

        return ZoneRecord::find()
            ->where(['id' => $zoneIds])
            ->orderBy(['sortOrder' => SORT_ASC, 'name' => SORT_ASC])
            ->all();
    }
}
