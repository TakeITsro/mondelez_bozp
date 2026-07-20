<?php

declare(strict_types=1);

namespace modules\bozp\controllers;

use Craft;
use craft\elements\User;
use craft\web\View;
use modules\bozp\enums\HazardCategory;
use modules\bozp\enums\PermitStatus;
use modules\bozp\enums\PermitType;
use modules\bozp\enums\SignatureRole;
use modules\bozp\enums\SubpermitType;
use modules\bozp\Module;
use craft\elements\Asset;
use craft\helpers\Assets;
use craft\helpers\FileHelper;
use craft\helpers\UrlHelper;
use craft\web\UploadedFile;
use modules\bozp\records\AuditLogRecord;
use modules\bozp\records\PermitAttachmentRecord;
use modules\bozp\records\PermitControlRecord;
use modules\bozp\records\PermitHazardRecord;
use modules\bozp\records\PermitRecord;
use modules\bozp\records\SubpermitRecord;
use modules\bozp\records\ZoneRecord;
use Throwable;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * PermitsController
 *
 * Front-end create + save flow. Two actions in Phase 2B:
 *
 *   GET  /bozp/permits/new   → blank form
 *   POST /bozp/permits/save  → validates, allocates number, inserts as draft,
 *                              optionally submits, redirects back to dashboard.
 *
 * Phase 2B intentionally keeps the form to the minimum fields needed to land
 * in the HSE queue: contractor, work location, overview, validity window,
 * (optionally) zones. Hazard matrix + preparation checks come in 2C.
 *
 * Column mapping (DB schema on the left, form field on the right):
 *   contractorCompany      <- contractorCompany
 *   contractorPersonName   <- contractorPersonName
 *   contractorEmail        <- contractorEmail
 *   workLocation           <- workLocation
 *   workOverview           <- workOverview
 *   validFrom              <- validFrom
 *   validTo                <- validTo
 */
class PermitsController extends BaseSiteController
{
    public array|bool|int $allowAnonymous = ['view', 'new', 'save', 'cancel', 'close', 'pdf'];

    /**
     * GET bozp/permits/<id>/pdf — stream or redirect to the stored permit PDF.
     * Generates on-demand if not yet available.
     */
    public function actionPdf(int $id): Response
    {
        if ($redirect = $this->requireBozpLogin()) {
            return $redirect;
        }

        $permit = PermitRecord::findOne(['id' => $id]);
        if (!$permit) {
            throw new NotFoundHttpException('Permit not found.');
        }

        $user = Craft::$app->getUser();
        $isIssuer = (int) $permit->issuerId === (int) $user->getId();
        if (!$isIssuer && !$user->checkPermission('bozp:viewAll')) {
            throw new ForbiddenHttpException();
        }

        /** @var Module $module */
        $module = Craft::$app->getModule('bozp');

        // Generate on-demand if no PDF stored yet
        if (empty($permit->pdfAssetId)) {
            $module->permitPdfService->generateForPermit($permit);
            $permit = PermitRecord::findOne(['id' => $id]);
        }

        if (empty($permit->pdfAssetId)) {
            throw new NotFoundHttpException('PDF is not available yet.');
        }

        $asset = Craft::$app->getAssets()->getAssetById((int) $permit->pdfAssetId);
        if (!$asset || !$asset->url) {
            throw new NotFoundHttpException('PDF asset not found.');
        }

        return $this->redirect($asset->url);
    }

    public function actionView(int $id): Response
    {
        if ($redirect = $this->requireBozpLogin()) {
            return $redirect;
        }

        $permit = PermitRecord::findOne(['id' => $id]);
        if (!$permit) {
            throw new NotFoundHttpException('Permit not found.');
        }

        $user = Craft::$app->getUser();
        $userId = $user->getId();

        $isIssuer = (int) $permit->issuerId === (int) $userId;
        if (!$isIssuer && !$user->checkPermission('bozp:viewAll')) {
            throw new ForbiddenHttpException();
        }

        return $this->renderDetail($permit, $isIssuer);
    }

    /**
     * Issuer cancels the permit (with their IssuerClosure signature).
     * Allowed any time after approval.
     */
    public function actionCancel(int $id): ?Response
    {
        $this->requirePostRequest();
        if ($redirect = $this->requireBozpLogin()) {
            return $redirect;
        }
        $permit = PermitRecord::findOne(['id' => $id]);
        if (!$permit) {
            throw new NotFoundHttpException('Permit not found.');
        }
        $userId = (int) Craft::$app->getUser()->getId();
        if ((int) $permit->issuerId !== $userId) {
            throw new ForbiddenHttpException();
        }

        $request = Craft::$app->getRequest();
        $reason = trim((string) $request->getBodyParam('reason', ''));

        [$values, $errors] = $this->collectIssuerSignatureFields(false);
        if ($reason === '') {
            $errors['reason'] = (string) Craft::t('bozp', 'Dôvod zrušenia je povinný.');
        }
        if ($errors !== []) {
            return $this->renderDetail($permit, true, cancelErrors: $errors, cancelValues: $values + ['reason' => $reason]);
        }

        try {
            /** @var Module $module */
            $module = Craft::$app->getModule('bozp');
            $module->signatureService->capture(
                $permit,
                SignatureRole::IssuerClosure,
                $values['signerName'],
                null,
                $values['signatureDate'],
                $values['signatureData'],
            );
            $module->permitWorkflow->cancelByIssuer($permit, $userId, $reason);
            $module->permitMailer->notifyContractorOfIssuerSignature($permit, 'cancel', $values['signerName']);
            $module->permitPdfService->generateForPermit($permit);

            Craft::$app->getSession()->setNotice(
                Craft::t('bozp', 'Permit bol zrušený.')
            );
        } catch (Throwable $e) {
            Craft::error('BOZP issuer cancel failed: ' . $e->getMessage() . "\n" . $e->getTraceAsString(), __METHOD__);
            $msg = (string) Craft::t('bozp', 'Permit sa nepodarilo zrušiť. Skúste znova.');
            if (Craft::$app->getConfig()->getGeneral()->devMode) {
                $msg .= ' [debug: ' . $e->getMessage() . ']';
            }
            Craft::$app->getSession()->setError($msg);
        }

        return $this->redirect("permits/{$permit->id}");
    }

    /**
     * Issuer final closure (with IssuerClosure signature).
     * Only allowed once contractor has signed RecipientClosure
     * (status === pending_closure).
     */
    public function actionClose(int $id): ?Response
    {
        $this->requirePostRequest();
        if ($redirect = $this->requireBozpLogin()) {
            return $redirect;
        }
        $permit = PermitRecord::findOne(['id' => $id]);
        if (!$permit) {
            throw new NotFoundHttpException('Permit not found.');
        }
        $userId = (int) Craft::$app->getUser()->getId();
        if ((int) $permit->issuerId !== $userId) {
            throw new ForbiddenHttpException();
        }
        if ($permit->status !== PermitStatus::PendingClosure->value) {
            Craft::$app->getSession()->setError(
                Craft::t('bozp', 'Permit nie je v stave, v ktorom je možné dokončiť. Dodávateľ ho musí najprv podpísať.')
            );
            return $this->redirect("permits/{$permit->id}");
        }

        $request = Craft::$app->getRequest();
        $requiresTrial = $request->getBodyParam('requiresTrialOperation', '') === 'yes';

        [$values, $errors] = $this->collectIssuerSignatureFields(false);
        if ($errors !== []) {
            return $this->renderDetail($permit, true, closeErrors: $errors, closeValues: $values + ['requiresTrialOperation' => $requiresTrial ? 'yes' : 'no']);
        }

        try {
            /** @var Module $module */
            $module = Craft::$app->getModule('bozp');
            $module->signatureService->capture(
                $permit,
                SignatureRole::IssuerClosure,
                $values['signerName'],
                null,
                $values['signatureDate'],
                $values['signatureData'],
            );
            $module->permitWorkflow->closeByIssuer($permit, $userId, $requiresTrial);
            // Re-fetch so the PDF reflects the latest signatures.
            $awaiting = PermitRecord::findOne(['id' => $permit->id]) ?? $permit;
            $module->permitPdfService->generateForPermit($awaiting);
            // Notify HSE that final countersignature is required in CP.
            $module->permitMailer->notifyHseOfClosurePending($awaiting, $values['signerName']);

            Craft::$app->getSession()->setNotice(
                Craft::t('bozp', 'Podpis bol uložený. Permit čaká na podpis HSE.')
            );
        } catch (Throwable $e) {
            Craft::error('BOZP issuer close failed: ' . $e->getMessage() . "\n" . $e->getTraceAsString(), __METHOD__);
            $msg = (string) Craft::t('bozp', 'Permit sa nepodarilo uzavrieť. Skúste znova.');
            if (Craft::$app->getConfig()->getGeneral()->devMode) {
                $msg .= ' [debug: ' . $e->getMessage() . ']';
            }
            Craft::$app->getSession()->setError($msg);
        }

        return $this->redirect("permits/{$permit->id}");
    }

    /**
     * @param array<string, string> $cancelErrors
     * @param array<string, string> $cancelValues
     * @param array<string, string> $closeErrors
     * @param array<string, string> $closeValues
     */
    private function renderDetail(
        PermitRecord $permit,
        bool $isIssuer,
        array $cancelErrors = [],
        array $cancelValues = [],
        array $closeErrors = [],
        array $closeValues = [],
    ): Response {
        $zones = $this->loadZonesFor((int) $permit->id);
        $approver = $permit->approverId ? User::find()->id($permit->approverId)->one() : null;
        $auditEntries = AuditLogRecord::find()
            ->where(['permitId' => $permit->id])
            ->orderBy(['dateCreated' => SORT_DESC])
            ->limit(20)
            ->all();

        /** @var Module $module */
        $module = Craft::$app->getModule('bozp');

        $recipientClosure = $module->signatureService->findSignature((int) $permit->id, SignatureRole::RecipientClosure);
        $issuerClosure = $module->signatureService->findSignature((int) $permit->id, SignatureRole::IssuerClosure);

        $statusOpenForCancel = in_array($permit->status, [
            PermitStatus::Approved->value, PermitStatus::Signed->value,
            PermitStatus::Active->value, PermitStatus::PendingClosure->value,
        ], true);
        $statusReadyForClose = $permit->status === PermitStatus::PendingClosure->value;

        $issuerUser = Craft::$app->getUser()->getIdentity();
        $defaultName = $issuerUser?->getFullName() ?: ($issuerUser?->username ?: '');

        $defaultIssuerSign = [
            'signerName' => $defaultName,
            'signatureDate' => date('Y-m-d'),
        ];

        $this->view->setTemplateMode(View::TEMPLATE_MODE_SITE);

        $subpermits = SubpermitRecord::find()
            ->where(['parentPermitId' => $permit->id])
            ->orderBy(['dateCreated' => SORT_ASC])
            ->all();

        $rawRequired = $permit->requiresHighRisk;
        $requiredTypes = is_string($rawRequired)
            ? (json_decode($rawRequired, true) ?? [])
            : ($rawRequired ?? []);

        return $this->renderTemplate('bozp/site/permit-detail', [
            'permit' => $permit,
            'zones' => $zones,
            'approver' => $approver,
            'auditEntries' => $auditEntries,
            'hazardCategories' => HazardCategory::pdfOrder(),
            'hazards' => $this->loadHazardsFor((int) $permit->id),
            'attachments' => PermitAttachmentRecord::find()
                ->where(['permitId' => $permit->id])
                ->orderBy(['dateCreated' => SORT_DESC])
                ->all(),
            'recipientClosureSignature' => $recipientClosure,
            'issuerClosureSignature' => $issuerClosure,
            'isIssuer' => $isIssuer,
            'canCancel' => $isIssuer && $statusOpenForCancel && !$issuerClosure,
            'canClose' => $isIssuer && $statusReadyForClose && !$issuerClosure,
            'cancelErrors' => $cancelErrors,
            'cancelValues' => array_merge($defaultIssuerSign, ['reason' => ''], $cancelValues),
            'closeErrors' => $closeErrors,
            'closeValues' => array_merge($defaultIssuerSign, ['requiresTrialOperation' => 'no'], $closeValues),
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
     * Pull + validate the signature fields from the request (issuer side).
     *
     * @return array{0: array<string,string>, 1: array<string,string>}
     */
    private function collectIssuerSignatureFields(bool $requireEmployer): array
    {
        $request = Craft::$app->getRequest();
        $values = [
            'signerName' => trim((string) $request->getBodyParam('signerName', '')),
            'signatureDate' => trim((string) $request->getBodyParam('signatureDate', '')),
            'signatureData' => (string) $request->getBodyParam('signatureData', ''),
        ];
        $errors = [];
        if ($values['signerName'] === '') {
            $errors['signerName'] = (string) Craft::t('bozp', 'Meno podpisujúceho je povinné.');
        }
        if ($values['signatureDate'] === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $values['signatureDate'])) {
            $errors['signatureDate'] = (string) Craft::t('bozp', 'Dátum podpisu je povinný.');
        }
        if (!preg_match('#^data:image/png;base64,#', $values['signatureData'])) {
            $errors['signatureData'] = (string) Craft::t('bozp', 'Podpis je povinný.');
        }
        return [$values, $errors];
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

    /**
     * POST bozp/permits/<id>/upload — issuer uploads a supplementary attachment.
     */
    public function actionUploadAttachment(int $id): Response
    {
        $this->requirePostRequest();
        if ($redirect = $this->requireBozpLogin()) {
            return $redirect;
        }

        $permit = PermitRecord::findOne(['id' => $id]);
        if (!$permit) {
            throw new NotFoundHttpException('Permit not found.');
        }

        $userId = (int) Craft::$app->getUser()->getId();
        $isIssuer = (int) $permit->issuerId === $userId;
        $hasViewAll = Craft::$app->getUser()->checkPermission('bozp:viewAll');
        if (!$isIssuer && !$hasViewAll) {
            throw new ForbiddenHttpException();
        }

        $uploaded = UploadedFile::getInstanceByName('attachment');
        if (!$uploaded || $uploaded->getHasError()) {
            Craft::$app->getSession()->setError(Craft::t('bozp', 'Nepodarilo sa nahrať súbor. Skúste znova.'));
            return $this->redirect(UrlHelper::siteUrl('permits/' . $id));
        }

        if ($uploaded->size > 10 * 1024 * 1024) {
            Craft::$app->getSession()->setError(Craft::t('bozp', 'Súbor je príliš veľký. Maximálna veľkosť je 10 MB.'));
            return $this->redirect(UrlHelper::siteUrl('permits/' . $id));
        }

        $allowedExt  = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
        $allowedMime = [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'image/jpeg', 'image/png',
        ];
        $ext  = strtolower((string) pathinfo($uploaded->name, PATHINFO_EXTENSION));
        $mime = (string) FileHelper::getMimeType($uploaded->tempName);

        if (!in_array($ext, $allowedExt, true) || !in_array($mime, $allowedMime, true)) {
            Craft::$app->getSession()->setError(Craft::t('bozp', 'Nepodporovaný typ súboru. Povolené: PDF, DOCX, JPG, PNG.'));
            return $this->redirect(UrlHelper::siteUrl('permits/' . $id));
        }

        try {
            $volume = Craft::$app->getVolumes()->getVolumeByHandle('bozpAttachments');
            if (!$volume) {
                throw new \RuntimeException("Missing asset volume 'bozpAttachments'");
            }
            $rootFolder = Craft::$app->getAssets()->getRootFolderByVolumeId($volume->id);
            if (!$rootFolder) {
                throw new \RuntimeException('No root folder for bozpAttachments.');
            }

            $tempPath = $uploaded->saveAsTempFile();
            if ($tempPath === false) {
                throw new \RuntimeException('Could not copy uploaded file to temp location.');
            }

            $asset = new Asset();
            $asset->tempFilePath = $tempPath;
            $asset->filename = Assets::prepareAssetName($uploaded->name);
            $asset->newFolderId = $rootFolder->id;
            $asset->volumeId = $volume->id;
            $asset->avoidFilenameConflicts = true;
            $asset->setScenario(Asset::SCENARIO_CREATE);

            if (!Craft::$app->getElements()->saveElement($asset)) {
                throw new \RuntimeException('Asset save failed: ' . print_r($asset->getErrors(), true));
            }

            $user = Craft::$app->getUser()->getIdentity();

            $att = new PermitAttachmentRecord();
            $att->permitId        = (int) $permit->id;
            $att->attachmentType  = 'issuer_upload';
            $att->assetId         = (int) $asset->id;
            $att->uploadedById    = $userId;
            $att->uploadedByName  = $user?->getFullName() ?: ($user?->username ?: '');
            if (!$att->save()) {
                throw new \RuntimeException('Attachment row save failed: ' . print_r($att->getErrors(), true));
            }

            /** @var Module $module */
            $module = Craft::$app->getModule('bozp');
            $module->auditLogger->log(
                permitId: (int) $permit->id,
                userId: $userId,
                action: 'issuer_upload',
                note: $asset->filename,
            );

            Craft::$app->getSession()->setNotice(Craft::t('bozp', 'Súbor bol nahraný.'));
        } catch (Throwable $e) {
            Craft::error('BOZP issuer upload failed: ' . $e->getMessage() . "\n" . $e->getTraceAsString(), __METHOD__);
            Craft::$app->getSession()->setError(Craft::t('bozp', 'Nahrávanie súboru zlyhalo. Skúste znova.'));
        }

        return $this->redirect(UrlHelper::siteUrl('permits/' . $id));
    }

    public function actionNew(): Response
    {
        if ($redirect = $this->requireBozpLogin()) {
            return $redirect;
        }
        $this->requirePermission('bozp:createPermit');

        $zones = ZoneRecord::find()
            ->where(['archived' => false])
            ->orderBy(['sortOrder' => SORT_ASC, 'name' => SORT_ASC])
            ->all();

        $this->view->setTemplateMode(View::TEMPLATE_MODE_SITE);

        return $this->renderTemplate('bozp/site/permit-form', [
            'permit' => null,
            'zones' => $zones,
            'hazardCategories' => HazardCategory::pdfOrder(),
            'subpermitTypes' => SubpermitType::cases(),
            'errors' => [],
            'values' => $this->defaultValues(),
        ]);
    }

    /**
     * Default values for a brand-new permit form — empty strings for scalars,
     * and the pre-populated default measure for each hazard row.
     *
     * @return array<string, mixed>
     */
    private function defaultValues(): array
    {
        $hazards = [];
        foreach (HazardCategory::pdfOrder() as $cat) {
            $hazards[$cat->value] = [
                // Default to "no" so the issuer only has to flip the rows
                // that actually apply. They can still change to "yes" or
                // unset before submitting.
                'exposed' => 'no',
                'measure' => $cat->defaultMeasure(),
                'control' => '',
                'controlOther' => '',
            ];
        }

        return [
            'zoneId' => null,
            'preparation' => [
                'conditionsSuitable' => '',
                'toolsInGoodCondition' => '',
                'hasStopConditions' => '',
                'stopConditionsDescription' => '',
                'lotoImplemented' => '',
                'emergencyPlan' => '',
            ],
            'hazards' => $hazards,
            'requiresHighRisk' => [],
        ];
    }

    public function actionSave(): ?Response
    {
        $this->requirePostRequest();
        if ($redirect = $this->requireBozpLogin()) {
            return $redirect;
        }
        $this->requirePermission('bozp:createPermit');

        $request = Craft::$app->getRequest();
        $userId = Craft::$app->getUser()->getId();

        // "save" = stay in draft. "submit" = save then transition to submitted.
        $intent = $request->getBodyParam('intent', 'save');

        $values = [
            'contractorCompany'    => trim((string) $request->getBodyParam('contractorCompany', '')),
            'contractorPersonName' => trim((string) $request->getBodyParam('contractorPersonName', '')),
            'contractorEmail'      => trim((string) $request->getBodyParam('contractorEmail', '')),
            'contactPersonPhone'   => trim((string) $request->getBodyParam('contactPersonPhone', '')),
            'contractorPhone'      => trim((string) $request->getBodyParam('contractorPhone', '')),
            'workersNames'         => trim((string) $request->getBodyParam('workersNames', '')),
            'workLocation'         => trim((string) $request->getBodyParam('workLocation', '')),
            'workOverview'         => trim((string) $request->getBodyParam('workOverview', '')),
            'validFrom'            => trim((string) $request->getBodyParam('validFrom', '')),
            'zoneId'               => (int) $request->getBodyParam('zoneId', 0) ?: null,
            'preparation'          => $this->normalizePreparation((array) $request->getBodyParam('preparation', [])),
            'hazards'              => $this->normalizeHazards((array) $request->getBodyParam('hazards', [])),
            'requiresHighRisk'     => $this->normalizeRequiresHighRisk((array) $request->getBodyParam('requiresHighRisk', [])),
        ];

        $errors = $this->validate($values, $intent);

        // Risk-assessment attachment (PDF / DOCX / XLSX). Required at submit time.
        $riskAssessmentFile = UploadedFile::getInstanceByName('riskAssessment');
        $riskAssessmentError = $this->validateRiskAssessmentFile($riskAssessmentFile, $intent);
        if ($riskAssessmentError !== null) {
            $errors['riskAssessment'] = $riskAssessmentError;
        }

        // Optional additional attachment. Validate only type/size if provided.
        $additionalAttachmentFile = UploadedFile::getInstanceByName('additionalAttachment');
        $additionalAttachmentError = $this->validateAdditionalAttachmentFile($additionalAttachmentFile);
        if ($additionalAttachmentError !== null) {
            $errors['additionalAttachment'] = $additionalAttachmentError;
        }

        if ($errors !== []) {
            Craft::$app->getSession()->setError(Craft::t('bozp', 'Skontrolujte chyby vo formulári.'));
            $zones = ZoneRecord::find()
                ->where(['archived' => false])
                ->orderBy(['sortOrder' => SORT_ASC, 'name' => SORT_ASC])
                ->all();

            $this->view->setTemplateMode(View::TEMPLATE_MODE_SITE);
            return $this->renderTemplate('bozp/site/permit-form', [
                'permit' => null,
                'zones' => $zones,
                'hazardCategories' => HazardCategory::pdfOrder(),
                'subpermitTypes' => SubpermitType::cases(),
                'errors' => $errors,
                'values' => $values,
            ]);
        }

        /** @var Module $module */
        $module = Craft::$app->getModule('bozp');

        $db = Craft::$app->getDb();
        $transaction = $db->beginTransaction();

        try {
            $permit = new PermitRecord();
            $permit->permitNumber = $module->permitNumberGenerator->next(PermitType::General);
            $permit->permitType = PermitType::General->value;
            $permit->status = PermitStatus::Draft->value;
            $permit->issuerId = $userId;
            $permit->contractorCompany = $values['contractorCompany'] !== '' ? $values['contractorCompany'] : null;
            $permit->contractorPersonName = $values['contractorPersonName'] !== '' ? $values['contractorPersonName'] : null;
            $permit->contractorEmail = $values['contractorEmail'] !== '' ? $values['contractorEmail'] : null;
            $permit->contactPersonPhone = $values['contactPersonPhone'] !== '' ? $values['contactPersonPhone'] : null;
            $permit->contractorPhone = $values['contractorPhone'] !== '' ? $values['contractorPhone'] : null;
            $permit->workersNames = $values['workersNames'] !== '' ? $values['workersNames'] : null;
            $permit->workLocation = $values['workLocation'];
            $permit->workOverview = $values['workOverview'];
            $permit->validFrom = $values['validFrom'] !== '' ? $values['validFrom'] : null;
            // validTo is auto-assigned on HSE approval (approvedAt + 7 days) — not user-editable.

            // Preparation checks — booleans get null when "not answered".
            $prep = $values['preparation'];
            $permit->conditionsSuitable = $this->ynToBool($prep['conditionsSuitable']);
            $permit->toolsInGoodCondition = $this->ynToBool($prep['toolsInGoodCondition']);
            $permit->hasStopConditions = $this->ynToBool($prep['hasStopConditions']);
            $permit->stopConditionsDescription = $prep['stopConditionsDescription'] !== '' ? $prep['stopConditionsDescription'] : null;
            $permit->lotoImplemented = $this->ynToBool($prep['lotoImplemented']);
            $permit->emergencyPlan = $prep['emergencyPlan'] !== '' ? $prep['emergencyPlan'] : null;

            // JSON array of SubpermitType values, e.g. ["hot_work","heights"]
            $permit->requiresHighRisk = $values['requiresHighRisk'] !== []
                ? json_encode($values['requiresHighRisk'])
                : null;

            if (!$permit->save()) {
                throw new \RuntimeException('Save failed: ' . print_r($permit->getErrors(), true));
            }

            // Persist single-zone assignment.
            if ($values['zoneId'] !== null) {
                PermitRecord::updateAll(
                    ['zoneId' => $values['zoneId']],
                    ['id' => $permit->id]
                );
                $permit->zoneId = $values['zoneId'];
            }
            $this->syncHazards((int) $permit->id, $values['hazards']);

            // Save risk-assessment attachment (if uploaded).
            if ($riskAssessmentFile !== null && !$riskAssessmentFile->getHasError()) {
                $this->saveRiskAssessmentAttachment($permit, $riskAssessmentFile, $userId);
            }

            // Save additional optional attachment (if uploaded).
            if ($additionalAttachmentFile !== null && !$additionalAttachmentFile->getHasError()) {
                $this->saveAdditionalAttachment($permit, $additionalAttachmentFile, $userId);
            }

            $module->auditLogger->log(
                permitId: (int) $permit->id,
                userId: $userId,
                action: 'created',
                toStatus: PermitStatus::Draft->value,
            );

            if ($intent === 'submit') {
                $module->permitWorkflow->submit($permit, $userId);
            }

            $transaction->commit();
        } catch (Throwable $e) {
            $transaction->rollBack();
            Craft::error('Permit save failed: ' . $e->getMessage() . "\n" . $e->getTraceAsString(), __METHOD__);

            $message = (string) Craft::t('bozp', 'Permit sa nepodarilo uložiť. Skúste znova.');
            if (Craft::$app->getConfig()->getGeneral()->devMode) {
                $message .= ' [dev] ' . $e->getMessage();
            }
            Craft::$app->getSession()->setError($message);
            return $this->redirect('permits/new');
        }

        // Notifications and PDF generation fire AFTER the transaction has committed,
        // and OUTSIDE the try/catch — failures here don't break the save flow.
        if ($intent === 'submit') {
            $module->permitMailer->notifyHseOfSubmission($permit);
            $module->permitPdfService->generateForPermit($permit);
        }

        $msg = $intent === 'submit'
            ? Craft::t('bozp', 'Permit {n} bol odoslaný na schválenie HSE.', ['n' => $permit->permitNumber])
            : Craft::t('bozp', 'Permit {n} bol uložený ako koncept.', ['n' => $permit->permitNumber]);

        Craft::$app->getSession()->setNotice($msg);

        // Submitted permit with required high-risk types not yet covered by
        // subpermits → land the issuer on the subpermit section so they can
        // create them right away.
        if ($intent === 'submit' && $module->permitWorkflow->missingRequiredSubpermitTypes($permit) !== []) {
            return $this->redirect(UrlHelper::siteUrl("permits/{$permit->id}") . '#subpermits');
        }

        return $this->redirect('dashboard');
    }

    private const RA_ALLOWED_EXT  = ['pdf', 'docx', 'xlsx'];
    private const RA_ALLOWED_MIME = [
        'application/pdf',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ];
    private const RA_MAX_BYTES = 10 * 1024 * 1024; // 10 MB

    /** Additional optional attachment — wider type set. */
    private const ATT_ALLOWED_EXT  = ['pdf', 'docx', 'jpg', 'jpeg', 'png'];
    private const ATT_ALLOWED_MIME = [
        'application/pdf',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'image/jpeg',
        'image/png',
    ];
    private const ATT_MAX_BYTES = 10 * 1024 * 1024; // 10 MB

    /**
     * Validate the uploaded risk-assessment file. Required at submit intent,
     * optional (but still type/size-checked) on draft save.
     *
     * Returns an error message string, or null when the file passes.
     */
    private function validateRiskAssessmentFile(?UploadedFile $file, string $intent): ?string
    {
        if ($file === null || $file->getHasError()) {
            if ($intent === 'submit') {
                return (string) Craft::t('bozp', 'Príloha s hodnotením rizík je povinná pri odoslaní.');
            }
            return null;
        }

        if ($file->size > self::RA_MAX_BYTES) {
            return (string) Craft::t('bozp', 'Súbor je príliš veľký. Maximálna veľkosť je 10 MB.');
        }

        $ext = strtolower((string) pathinfo($file->name, PATHINFO_EXTENSION));
        $mime = (string) FileHelper::getMimeType($file->tempName);

        if (!in_array($ext, self::RA_ALLOWED_EXT, true) || !in_array($mime, self::RA_ALLOWED_MIME, true)) {
            return (string) Craft::t('bozp', 'Nepodporovaný typ súboru. Povolené: PDF, DOCX, XLSX.');
        }
        return null;
    }

    /**
     * Persist the uploaded risk-assessment file as an Asset and link it to
     * the permit via a PermitAttachmentRecord row of type='risk_assessment'.
     * Throws on any failure — caller's transaction will roll back.
     */
    private function saveRiskAssessmentAttachment(PermitRecord $permit, UploadedFile $file, ?int $userId): void
    {
        $volume = Craft::$app->getVolumes()->getVolumeByHandle('bozpAttachments');
        if (!$volume) {
            throw new \RuntimeException("Missing asset volume 'bozpAttachments'");
        }
        $rootFolder = Craft::$app->getAssets()->getRootFolderByVolumeId($volume->id);
        if (!$rootFolder) {
            throw new \RuntimeException("No root folder for volume 'bozpAttachments'");
        }

        $tempPath = $file->saveAsTempFile();
        if ($tempPath === false) {
            throw new \RuntimeException('Could not copy uploaded risk-assessment file to temp location.');
        }

        $asset = new Asset();
        $asset->tempFilePath = $tempPath;
        $asset->filename = Assets::prepareAssetName($file->name);
        $asset->newFolderId = $rootFolder->id;
        $asset->volumeId = $volume->id;
        $asset->avoidFilenameConflicts = true;
        $asset->setScenario(Asset::SCENARIO_CREATE);

        if (!Craft::$app->getElements()->saveElement($asset)) {
            throw new \RuntimeException('Risk-assessment asset save failed: ' . print_r($asset->getErrors(), true));
        }

        $att = new PermitAttachmentRecord();
        $att->permitId = (int) $permit->id;
        $att->attachmentType = 'risk_assessment';
        $att->assetId = (int) $asset->id;
        $att->uploadedById = $userId;
        if (!$att->save()) {
            throw new \RuntimeException('Risk-assessment attachment row save failed: ' . print_r($att->getErrors(), true));
        }
    }

    /**
     * Validate the optional additional attachment. Always optional — no
     * required check, only type + size. Returns error message or null.
     */
    private function validateAdditionalAttachmentFile(?UploadedFile $file): ?string
    {
        if ($file === null || $file->getHasError()) {
            return null;
        }
        if ($file->size > self::ATT_MAX_BYTES) {
            return (string) Craft::t('bozp', 'Súbor je príliš veľký. Maximálna veľkosť je 10 MB.');
        }
        $ext = strtolower((string) pathinfo($file->name, PATHINFO_EXTENSION));
        $mime = (string) FileHelper::getMimeType($file->tempName);
        if (!in_array($ext, self::ATT_ALLOWED_EXT, true) || !in_array($mime, self::ATT_ALLOWED_MIME, true)) {
            return (string) Craft::t('bozp', 'Nepodporovaný typ súboru. Povolené: PDF, DOCX, JPG, PNG.');
        }
        return null;
    }

    /**
     * Save the additional attachment as an Asset + PermitAttachmentRecord
     * with attachmentType='additional'.
     */
    private function saveAdditionalAttachment(PermitRecord $permit, UploadedFile $file, ?int $userId): void
    {
        $volume = Craft::$app->getVolumes()->getVolumeByHandle('bozpAttachments');
        if (!$volume) {
            throw new \RuntimeException("Missing asset volume 'bozpAttachments'");
        }
        $rootFolder = Craft::$app->getAssets()->getRootFolderByVolumeId($volume->id);
        if (!$rootFolder) {
            throw new \RuntimeException("No root folder for volume 'bozpAttachments'");
        }

        $tempPath = $file->saveAsTempFile();
        if ($tempPath === false) {
            throw new \RuntimeException('Could not copy uploaded additional attachment to temp location.');
        }

        $asset = new Asset();
        $asset->tempFilePath = $tempPath;
        $asset->filename = Assets::prepareAssetName($file->name);
        $asset->newFolderId = $rootFolder->id;
        $asset->volumeId = $volume->id;
        $asset->avoidFilenameConflicts = true;
        $asset->setScenario(Asset::SCENARIO_CREATE);

        if (!Craft::$app->getElements()->saveElement($asset)) {
            throw new \RuntimeException('Additional attachment asset save failed: ' . print_r($asset->getErrors(), true));
        }

        $att = new PermitAttachmentRecord();
        $att->permitId = (int) $permit->id;
        $att->attachmentType = 'additional';
        $att->assetId = (int) $asset->id;
        $att->uploadedById = $userId;
        if (!$att->save()) {
            throw new \RuntimeException('Additional attachment row save failed: ' . print_r($att->getErrors(), true));
        }
    }

    /**
     * @param array<string, mixed> $values
     * @return array<string, string>
     */
    private function validate(array $values, string $intent): array
    {
        $errors = [];

        if ($values['contractorCompany'] === '') {
            $errors['contractorCompany'] = (string) Craft::t('bozp', 'Názov dodávateľa je povinný.');
        }
        if ($values['contractorPersonName'] === '') {
            $errors['contractorPersonName'] = (string) Craft::t('bozp', 'Kontaktná osoba je povinná.');
        }
        if ($values['contactPersonPhone'] === '') {
            $errors['contactPersonPhone'] = (string) Craft::t('bozp', 'Telefón kontaktnej osoby je povinný.');
        }
        if ($values['contractorPhone'] === '') {
            $errors['contractorPhone'] = (string) Craft::t('bozp', 'Telefón dodávateľa je povinný.');
        }
        if ($values['workLocation'] === '') {
            $errors['workLocation'] = (string) Craft::t('bozp', 'Miesto výkonu je povinné.');
        }
        if ($values['workOverview'] === '') {
            $errors['workOverview'] = (string) Craft::t('bozp', 'Popis prác je povinný.');
        }
        if (empty($values['zoneId'])) {
            $errors['zoneId'] = (string) Craft::t('bozp', 'Zóna je povinná.');
        }

        if ($values['contractorEmail'] !== '' && !filter_var($values['contractorEmail'], FILTER_VALIDATE_EMAIL)) {
            $errors['contractorEmail'] = (string) Craft::t('bozp', 'Neplatná e-mailová adresa.');
        }

        // Submitting requires a planned start date. The end of the validity
        // window is auto-calculated on HSE approval (approvedAt + 7 days).
        if ($intent === 'submit') {
            if ($values['validFrom'] === '') {
                $errors['validFrom'] = (string) Craft::t('bozp', 'Plánovaný začiatok je povinný pri odoslaní.');
            }
            // Contractor email is required at submit so the contractor can
            // receive approval / rejection notifications.
            if ($values['contractorEmail'] === '' && !isset($errors['contractorEmail'])) {
                $errors['contractorEmail'] = (string) Craft::t('bozp', 'E-mail dodávateľa je povinný pri odoslaní.');
            }
        }

        return $errors;
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
     * Keyed by hazard category enum value. Unknown keys are dropped.
     *
     * @param array<string, mixed> $raw
     * @return array<string, array{exposed: string, measure: string, control: string, controlOther: string}>
     */
    private function normalizeHazards(array $raw): array
    {
        $allowedControls = ['used', 'not_used', 'other'];
        $out = [];

        foreach (HazardCategory::pdfOrder() as $cat) {
            $row = (array) ($raw[$cat->value] ?? []);
            $exposed = is_string($row['exposed'] ?? null) ? strtolower(trim($row['exposed'])) : '';
            $control = is_string($row['control'] ?? null) ? strtolower(trim($row['control'])) : '';

            $out[$cat->value] = [
                'exposed' => in_array($exposed, ['yes', 'no'], true) ? $exposed : '',
                'measure' => trim((string) ($row['measure'] ?? '')),
                'control' => in_array($control, $allowedControls, true) ? $control : '',
                'controlOther' => trim((string) ($row['controlOther'] ?? '')),
            ];
        }

        return $out;
    }

    /**
     * Filters the posted requiresHighRisk[] array to valid SubpermitType values only.
     *
     * @param array<mixed> $raw
     * @return string[]
     */
    private function normalizeRequiresHighRisk(array $raw): array
    {
        $valid = array_map(fn(SubpermitType $t) => $t->value, SubpermitType::cases());
        return array_values(array_intersect(
            array_map('strval', $raw),
            $valid,
        ));
    }

    private function ynToBool(string $v): ?bool
    {
        return match ($v) {
            'yes' => true,
            'no' => false,
            default => null,
        };
    }

    /**
     * Insert one bozp_permit_hazards row per HazardCategory. Only rows where
     * the user has actually touched something (exposed set, measure changed,
     * or a control value chosen) get persisted — otherwise the matrix would
     * be spammed with 16 no-op rows on every permit.
     *
     * @param array<string, array{exposed: string, measure: string, control: string, controlOther: string}> $hazards
     */
    private function syncHazards(int $permitId, array $hazards): void
    {
        $db = Craft::$app->getDb();
        $now = date('Y-m-d H:i:s');
        $rows = [];
        $sort = 0;

        foreach (HazardCategory::pdfOrder() as $cat) {
            $row = $hazards[$cat->value] ?? null;
            if ($row === null) {
                continue;
            }

            $defaultMeasure = $cat->defaultMeasure();
            $hasCustomMeasure = $row['measure'] !== '' && $row['measure'] !== $defaultMeasure;
            $touched = $row['exposed'] !== ''
                || $row['control'] !== ''
                || $hasCustomMeasure
                || $row['controlOther'] !== '';

            if (!$touched) {
                continue;
            }

            $rows[] = [
                $permitId,
                $cat->value,
                $this->ynToBool($row['exposed']),
                $row['measure'] !== '' ? $row['measure'] : null,
                $row['control'] !== '' ? $row['control'] : null,
                $row['controlOther'] !== '' ? $row['controlOther'] : null,
                $sort++,
                $now,
                $now,
                \craft\helpers\StringHelper::UUID(),
            ];
        }

        if ($rows === []) {
            return;
        }

        $db->createCommand()
            ->batchInsert(
                '{{%bozp_permit_hazards}}',
                ['permitId', 'hazardKey', 'exposed', 'measure', 'controlDuringActivity', 'controlDuringActivityOther', 'sortOrder', 'dateCreated', 'dateUpdated', 'uid'],
                $rows,
            )
            ->execute();
    }

    /**
     * Return the single zone for a permit, wrapped in an array so existing
     * detail/PDF templates that iterate `{% for zone in zones %}` keep working.
     *
     * @return ZoneRecord[]
     */
    private function loadZonesFor(int $permitId): array
    {
        /** @var PermitRecord|null $permit */
        $permit = PermitRecord::findOne(['id' => $permitId]);
        if (!$permit || empty($permit->zoneId)) {
            return [];
        }
        $zone = ZoneRecord::findOne(['id' => (int) $permit->zoneId]);
        return $zone ? [$zone] : [];
    }
}
