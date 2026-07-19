<?php

declare(strict_types=1);

namespace modules\bozp\controllers;

use Craft;
use craft\elements\User;
use craft\helpers\DateTimeHelper;
use craft\web\Controller;
use craft\web\View;
use modules\bozp\enums\HazardCategory;
use modules\bozp\enums\PermitStatus;
use modules\bozp\enums\SignatureRole;
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

        // Permits awaiting HSE final closure signature
        $awaitingClosurePermits = PermitRecord::find()
            ->where(['status' => PermitStatus::AwaitingHseClosure->value])
            ->orderBy(['issuerClosureSignedAt' => SORT_ASC])
            ->limit(50)
            ->all();

        $this->view->setTemplateMode(View::TEMPLATE_MODE_CP);

        return $this->renderTemplate('bozp/cp/queue', [
            'pendingCount' => $pendingCount,
            'pendingPermits' => $pendingPermits,
            'incompletePermitIds' => $this->computeIncompletePermitIds($pendingPermits),
            'awaitingClosurePermits' => $awaitingClosurePermits,
            'awaitingClosureCount' => count($awaitingClosurePermits),
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

        $allZones = ZoneRecord::find()->orderBy(['sortOrder' => SORT_ASC, 'name' => SORT_ASC])->all();

        // Single zone lives on the permit row (the M2M table is legacy).
        $selectedZoneIds = $permit->zoneId ? [(int) $permit->zoneId] : [];

        $this->view->setTemplateMode(View::TEMPLATE_MODE_CP);

        return $this->renderTemplate('bozp/cp/permit-edit', [
            'permit'          => $permit,
            'zones'           => $allZones,
            'selectedZoneIds' => $selectedZoneIds,
            'errors'          => [],
            'values'          => $this->permitToValues($permit),
            'subpermitTypes'  => SubpermitType::cases(),
        ]);
    }

    /**
     * POST bozp/permit/<id>/edit save target (HSE edits an existing permit).
     * Writes via updateAll to avoid Yii AR re-binding the JSON column
     * (requiresHighRisk) — see HANDOUT §18.
     */
    public function actionUpdatePermit(): ?Response
    {
        $this->requirePostRequest();
        $this->requireLogin();
        $this->requirePermission('bozp:approve');

        $request = Craft::$app->getRequest();
        $id = (int) $request->getRequiredBodyParam('id');
        $permit = $this->findPermit($id);

        $prep = $this->normalizePreparation((array) $request->getBodyParam('preparation', []));

        $values = [
            'contractorCompany'    => trim((string) $request->getBodyParam('contractorCompany', '')),
            'contractorPersonName' => trim((string) $request->getBodyParam('contractorPersonName', '')),
            'contractorEmail'      => trim((string) $request->getBodyParam('contractorEmail', '')),
            'workLocation'         => trim((string) $request->getBodyParam('workLocation', '')),
            'workOverview'         => trim((string) $request->getBodyParam('workOverview', '')),
            'zoneId'               => (int) $request->getBodyParam('zoneId', 0) ?: null,
            'approvalComment'      => trim((string) $request->getBodyParam('approvalComment', '')),
            'requiresHighRisk'     => $this->normalizeRequiresHighRisk((array) $request->getBodyParam('requiresHighRisk', [])),
        ];

        // Craft forms.dateField / dateTimeField post nested arrays — read via
        // DateTimeHelper, never a (string) cast (HANDOUT §18).
        $validFrom = $this->readPostedDate($request->getBodyParam('validFrom'), 'Y-m-d');
        $validTo   = $this->readPostedDate($request->getBodyParam('validTo'), 'Y-m-d H:i:s');

        $errors = [];
        if ($values['contractorCompany'] === '') {
            $errors['contractorCompany'] = (string) Craft::t('bozp', 'Názov dodávateľa je povinný.');
        }
        if ($values['workLocation'] === '') {
            $errors['workLocation'] = (string) Craft::t('bozp', 'Miesto výkonu je povinné.');
        }
        if ($values['workOverview'] === '') {
            $errors['workOverview'] = (string) Craft::t('bozp', 'Popis prác je povinný.');
        }
        if ($values['contractorEmail'] !== '' && !filter_var($values['contractorEmail'], FILTER_VALIDATE_EMAIL)) {
            $errors['contractorEmail'] = (string) Craft::t('bozp', 'Neplatná e-mailová adresa.');
        }

        if ($errors !== []) {
            Craft::$app->getSession()->setError(Craft::t('bozp', 'Skontrolujte chyby vo formulári.'));
            $this->view->setTemplateMode(View::TEMPLATE_MODE_CP);
            return $this->renderTemplate('bozp/cp/permit-edit', [
                'permit'          => $permit,
                'zones'           => ZoneRecord::find()->orderBy(['sortOrder' => SORT_ASC, 'name' => SORT_ASC])->all(),
                'selectedZoneIds' => $values['zoneId'] !== null ? [$values['zoneId']] : [],
                'errors'          => $errors,
                'values'          => $values + $prep + ['validFrom' => $validFrom, 'validTo' => $validTo],
                'subpermitTypes'  => SubpermitType::cases(),
            ]);
        }

        PermitRecord::updateAll(
            [
                'contractorCompany'         => $values['contractorCompany'] !== '' ? $values['contractorCompany'] : null,
                'contractorPersonName'      => $values['contractorPersonName'] !== '' ? $values['contractorPersonName'] : null,
                'contractorEmail'           => $values['contractorEmail'] !== '' ? $values['contractorEmail'] : null,
                'workLocation'              => $values['workLocation'],
                'workOverview'              => $values['workOverview'],
                'validFrom'                 => $validFrom,
                'validTo'                   => $validTo,
                'conditionsSuitable'        => $this->ynToBool($prep['conditionsSuitable']),
                'toolsInGoodCondition'      => $this->ynToBool($prep['toolsInGoodCondition']),
                'hasStopConditions'         => $this->ynToBool($prep['hasStopConditions']),
                'stopConditionsDescription' => $prep['stopConditionsDescription'] !== '' ? $prep['stopConditionsDescription'] : null,
                'lotoImplemented'           => $this->ynToBool($prep['lotoImplemented']),
                'emergencyPlan'             => $prep['emergencyPlan'] !== '' ? $prep['emergencyPlan'] : null,
                'requiresHighRisk'          => $values['requiresHighRisk'] !== [] ? json_encode($values['requiresHighRisk']) : null,
                'zoneId'                    => $values['zoneId'],
                'approvalComment'           => $values['approvalComment'] !== '' ? $values['approvalComment'] : null,
            ],
            ['id' => $permit->id],
        );

        // Regenerate the PDF so it reflects the edited data.
        /** @var Module $module */
        $module = Craft::$app->getModule('bozp');
        $fresh = PermitRecord::findOne(['id' => $permit->id]) ?? $permit;
        try {
            $module->permitPdfService->generateForPermit($fresh);
        } catch (Throwable $e) {
            Craft::error('Permit PDF regen after edit failed: ' . $e->getMessage(), __METHOD__);
        }

        Craft::$app->getSession()->setNotice(Craft::t('bozp', 'Zmeny boli uložené.'));
        return $this->redirect("permits/{$permit->id}");
    }

    /**
     * Build the template `values` array from a permit record so the CP
     * edit form pre-populates.
     *
     * @return array<string, mixed>
     */
    private function permitToValues(PermitRecord $permit): array
    {
        return [
            'contractorCompany'         => $permit->contractorCompany,
            'contractorPersonName'      => $permit->contractorPersonName,
            'contractorEmail'           => $permit->contractorEmail,
            'workLocation'              => $permit->workLocation,
            'workOverview'              => $permit->workOverview,
            'validFrom'                 => $permit->validFrom,
            'validTo'                   => $permit->validTo,
            'conditionsSuitable'        => $permit->conditionsSuitable,
            'toolsInGoodCondition'      => $permit->toolsInGoodCondition,
            'hasStopConditions'         => $permit->hasStopConditions,
            'stopConditionsDescription' => $permit->stopConditionsDescription,
            'lotoImplemented'           => $permit->lotoImplemented,
            'emergencyPlan'             => $permit->emergencyPlan,
            'approvalComment'           => $permit->approvalComment,
        ];
    }

    /**
     * Read a Craft dateField / dateTimeField posted value (which may be a
     * nested array) into a formatted string, or null. Never casts an array
     * to string (HANDOUT §18).
     */
    private function readPostedDate(mixed $raw, string $format): ?string
    {
        if ($raw === null || $raw === '' || $raw === []) {
            return null;
        }
        try {
            $dt = DateTimeHelper::toDateTime($raw);
        } catch (Throwable) {
            return null;
        }
        return $dt ? $dt->format($format) : null;
    }

    /**
     * @param array<string, mixed> $raw
     * @return array<string, string>
     */
    private function normalizePreparation(array $raw): array
    {
        $yn = static function ($v): string {
            $v = is_string($v) ? strtolower(trim($v)) : '';
            return in_array($v, ['yes', 'no'], true) ? $v : '';
        };

        return [
            'conditionsSuitable'        => $yn($raw['conditionsSuitable'] ?? ''),
            'toolsInGoodCondition'      => $yn($raw['toolsInGoodCondition'] ?? ''),
            'hasStopConditions'         => $yn($raw['hasStopConditions'] ?? ''),
            'stopConditionsDescription' => trim((string) ($raw['stopConditionsDescription'] ?? '')),
            'lotoImplemented'           => $yn($raw['lotoImplemented'] ?? ''),
            'emergencyPlan'             => trim((string) ($raw['emergencyPlan'] ?? '')),
        ];
    }

    /**
     * @param array<mixed> $raw
     * @return string[]
     */
    private function normalizeRequiresHighRisk(array $raw): array
    {
        $valid = array_map(fn(SubpermitType $t) => $t->value, SubpermitType::cases());
        return array_values(array_intersect(array_map('strval', $raw), $valid));
    }

    private function ynToBool(string $v): ?bool
    {
        return match ($v) {
            'yes' => true,
            'no' => false,
            default => null,
        };
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
            return $this->redirect("permits/{$permitId}");
        }

        /** @var Module $module */
        $module = Craft::$app->getModule('bozp');
        if (!$module->subpermitSignatureService->isPreworkComplete((int) $subpermit->id)) {
            Craft::$app->getSession()->setError(
                Craft::t('bozp', 'Pred schválením musia byť podpísané obidva predpracovné podpisy (vydavateľ + dodávateľ).')
            );
            return $this->redirect("permits/{$permitId}");
        }

        $now = date('Y-m-d H:i:s');

        // Subpermit validity now anchors to the issuer-entered work date
        // (data.date, Y-m-d). The window ends at end-of-day on that date.
        // Fallback to "+8h from now" if the form value is missing or
        // malformed so the subpermit still gets a defined expiry.
        $rawData = is_string($subpermit->data)
            ? (json_decode($subpermit->data, true) ?? [])
            : (is_array($subpermit->data) ? $subpermit->data : []);
        $workDate = (string) ($rawData['date'] ?? '');
        if ($workDate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $workDate)) {
            $expiresAt = $workDate . ' 23:59:59';
        } else {
            $expiresAt = date('Y-m-d H:i:s', strtotime('+8 hours'));
        }

        $subpermit->status = SubpermitStatus::Approved->value;
        $subpermit->approverId = Craft::$app->getUser()->getId();
        $subpermit->approvedAt = $now;
        $subpermit->expiresAt = $expiresAt;

        if (!$subpermit->save()) {
            Craft::error('Subpermit approve failed: ' . print_r($subpermit->getErrors(), true), __METHOD__);
            Craft::$app->getSession()->setError(Craft::t('bozp', 'Subpermit sa nepodarilo schváliť.'));
            return $this->redirect("permits/{$permitId}");
        }

        // Generate subpermit PDF
        $module->permitPdfService->generateForSubpermit($subpermit, $permit);

        // Notify contractor + issuer.
        try {
            $module->permitMailer->notifyContractorOfSubpermitApproval($permit, $subpermit);
        } catch (Throwable $mailErr) {
            Craft::error(
                'Subpermit approval notification failed: ' . $mailErr->getMessage(),
                __METHOD__,
            );
        }

        Craft::$app->getSession()->setNotice(Craft::t('bozp', 'Subpermit bol schválený.'));
        return $this->redirect("permits/{$permitId}");
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
            return $this->redirect("permits/{$permitId}");
        }

        $permit = $this->findPermit($permitId);
        $subpermit = $this->findSubpermit($id, $permitId);

        if ($subpermit->status !== SubpermitStatus::Pending->value) {
            Craft::$app->getSession()->setError(Craft::t('bozp', 'Subpermit nie je v stave na zamietnutie.'));
            return $this->redirect("permits/{$permitId}");
        }

        $subpermit->status = SubpermitStatus::Rejected->value;
        $subpermit->approverId = Craft::$app->getUser()->getId();
        $subpermit->rejectedAt = date('Y-m-d H:i:s');
        $subpermit->rejectionNote = $note;

        if (!$subpermit->save()) {
            Craft::error('Subpermit reject failed: ' . print_r($subpermit->getErrors(), true), __METHOD__);
            Craft::$app->getSession()->setError(Craft::t('bozp', 'Subpermit sa nepodarilo zamietnuť.'));
            return $this->redirect("permits/{$permitId}");
        }

        // Generate subpermit PDF
        /** @var Module $module */
        $module = Craft::$app->getModule('bozp');
        $module->permitPdfService->generateForSubpermit($subpermit, $permit);

        Craft::$app->getSession()->setNotice(Craft::t('bozp', 'Subpermit bol zamietnutý.'));
        return $this->redirect("permits/{$permitId}");
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
            return $this->redirect("permits/{$permit->id}");
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
            return $this->redirect("permits/{$permit->id}");
        }

        // Generate PDF after transaction commit (silently on failure)
        $module->permitPdfService->generateForPermit($permit);

        Craft::$app->getSession()->setNotice(Craft::t('bozp', 'Permit {n} bol schválený.', ['n' => $permit->permitNumber]));
        return $this->redirect('permits');
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
            return $this->redirect("permits/{$permit->id}");
        }

        $comment = trim((string) Craft::$app->getRequest()->getBodyParam('comment', ''));

        if ($comment === '') {
            Craft::$app->getSession()->setError(Craft::t('bozp', 'Pri zamietnutí je komentár povinný.'));
            return $this->redirect("permits/{$permit->id}");
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
            return $this->redirect("permits/{$permit->id}");
        }

        // Generate PDF after transaction commit
        $module->permitPdfService->generateForPermit($permit);

        Craft::$app->getSession()->setNotice(Craft::t('bozp', 'Permit {n} bol zamietnutý.', ['n' => $permit->permitNumber]));
        return $this->redirect('permits');
    }

    /**
     * HSE final closure signature — fully closes the permit, regenerates the
     * PDF with all signatures, and emails the final PDF to issuer + contractor.
     *
     * POST bozp/permit/<id>/hse-close
     * Body: signerName, signatureDate (Y-m-d), signatureData (data URI)
     */
    public function actionHseClose(): ?Response
    {
        $this->requirePostRequest();
        $this->requireLogin();
        $this->requirePermission('bozp:approve');

        $request = Craft::$app->getRequest();
        $id = (int) $request->getRequiredBodyParam('id');
        $permit = $this->findPermit($id);

        if ($permit->status !== PermitStatus::AwaitingHseClosure->value) {
            Craft::$app->getSession()->setError(Craft::t('bozp', 'Permit nie je v stave čakania na HSE.'));
            return $this->redirect("permits/{$permit->id}");
        }

        $signerName = trim((string) $request->getBodyParam('signerName', ''));
        $signatureDate = trim((string) $request->getBodyParam('signatureDate', ''));
        $signatureData = (string) $request->getBodyParam('signatureData', '');

        $errors = [];
        if ($signerName === '') {
            $errors[] = (string) Craft::t('bozp', 'Meno je povinné.');
        }
        if ($signatureDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $signatureDate)) {
            $errors[] = (string) Craft::t('bozp', 'Dátum je povinný.');
        }
        if (!str_starts_with($signatureData, 'data:image/')) {
            $errors[] = (string) Craft::t('bozp', 'Chýba podpis.');
        }
        if ($errors !== []) {
            Craft::$app->getSession()->setError(implode(' ', $errors));
            return $this->redirect("permits/{$permit->id}");
        }

        $userId = Craft::$app->getUser()->getId();

        /** @var Module $module */
        $module = Craft::$app->getModule('bozp');

        try {
            $module->signatureService->capture(
                $permit,
                SignatureRole::HseClosure,
                $signerName,
                null, // signerEmployer — HSE officer has no employer string here
                $signatureDate,
                $signatureData,
            );
            $module->permitWorkflow->closeByHse($permit, $userId);

            // Re-fetch so the PDF reflects the final closed state.
            $closed = PermitRecord::findOne(['id' => $permit->id]) ?? $permit;
            $module->permitPdfService->generateForPermit($closed);
            $module->permitMailer->notifyParticipantsOfClosure($closed, $signerName);

            Craft::$app->getSession()->setNotice(
                Craft::t('bozp', 'Permit {n} bol uzavretý.', ['n' => $permit->permitNumber])
            );
        } catch (Throwable $e) {
            Craft::error('BOZP HSE close failed: ' . $e->getMessage() . "\n" . $e->getTraceAsString(), __METHOD__);
            $msg = (string) Craft::t('bozp', 'Permit sa nepodarilo uzavrieť. Skúste znova.');
            if (Craft::$app->getConfig()->getGeneral()->devMode) {
                $msg .= ' [debug: ' . $e->getMessage() . ']';
            }
            Craft::$app->getSession()->setError($msg);
            return $this->redirect("permits/{$permit->id}");
        }

        return $this->redirect('permits');
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
                return $this->redirect("permits/{$permit->id}");
            }
        } catch (Throwable $e) {
            Craft::error('Permit resend failed: ' . $e->getMessage(), __METHOD__);
            $error = (string) Craft::t('bozp', 'Notifikáciu sa nepodarilo odoslať.');
            if (Craft::$app->getConfig()->getGeneral()->devMode) {
                $error .= ' [dev] ' . $e->getMessage();
            }
            Craft::$app->getSession()->setError($error);
            return $this->redirect("permits/{$permit->id}");
        }

        Craft::$app->getSession()->setNotice($msg);
        return $this->redirect("permits/{$permit->id}");
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
            return $this->redirect("permits/{$id}");
        }

        Craft::$app->getSession()->setNotice(
            Craft::t('bozp', 'Permit {n} bol zmazaný.', ['n' => $number])
        );
        return $this->redirect('permits/all');
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

    /**
     * Return the single zone for a permit, wrapped in an array so existing
     * templates that iterate zones keep working unchanged.
     *
     * @return ZoneRecord[]
     */
    private function loadZonesFor(int $permitId): array
    {
        $permit = \modules\bozp\records\PermitRecord::findOne(['id' => $permitId]);
        if (!$permit || empty($permit->zoneId)) {
            return [];
        }
        $zone = ZoneRecord::findOne(['id' => (int) $permit->zoneId]);
        return $zone ? [$zone] : [];
    }
}
