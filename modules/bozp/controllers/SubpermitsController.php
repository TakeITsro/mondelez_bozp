<?php

declare(strict_types=1);

namespace modules\bozp\controllers;

use Craft;
use craft\elements\Asset;
use craft\helpers\Assets;
use craft\helpers\FileHelper;
use craft\web\UploadedFile;
use craft\web\View;
use modules\bozp\enums\PermitStatus;
use modules\bozp\enums\SubpermitSigningRole;
use modules\bozp\enums\SubpermitStatus;
use modules\bozp\enums\SubpermitType;
use modules\bozp\Module;
use modules\bozp\records\PermitRecord;
use modules\bozp\records\SubpermitRecord;
use modules\bozp\services\SubpermitSignatureService;
use Throwable;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Front-end subpermit controller (issuer-facing).
 *
 * Routes (registered in Module::registerSiteUrlRules):
 *   GET  bozp/permits/<permitId>/subpermits/new               → type selector
 *   GET  bozp/permits/<permitId>/subpermits/new/<type>        → form for one type
 *   POST bozp/permits/<permitId>/subpermits/save              → persist
 *   GET  bozp/permits/<permitId>/subpermits/<id>              → read-only view
 *   POST bozp/permits/<permitId>/subpermits/<id>/cancel       → cancel
 */
class SubpermitsController extends BaseSiteController
{
    public array|bool|int $allowAnonymous = ['new', 'form', 'save', 'view', 'cancel', 'pdf', 'sign-prework'];

    // -------------------------------------------------------------------------
    // Type selector
    // -------------------------------------------------------------------------

    public function actionNew(int $permitId): Response
    {
        if ($redirect = $this->requireBozpLogin()) {
            return $redirect;
        }
        $this->requirePermission('bozp:createPermit');

        $permit = $this->findPermit($permitId);
        $this->requireIsIssuer($permit);

        // If the general permit already specifies required types, pre-select those.
        $requiredTypes = $this->decodeRequiredTypes($permit);

        $this->view->setTemplateMode(View::TEMPLATE_MODE_SITE);

        return $this->renderTemplate('bozp/site/subpermits/type-select', [
            'permit' => $permit,
            'subpermitTypes' => SubpermitType::cases(),
            'requiredTypes' => $requiredTypes,
        ]);
    }

    // -------------------------------------------------------------------------
    // Subpermit form (specific type)
    // -------------------------------------------------------------------------

    public function actionForm(int $permitId, string $type): Response
    {
        if ($redirect = $this->requireBozpLogin()) {
            return $redirect;
        }
        $this->requirePermission('bozp:createPermit');

        $permit = $this->findPermit($permitId);
        $this->requireIsIssuer($permit);

        $subpermitType = SubpermitType::tryFrom($type);
        if ($subpermitType === null) {
            throw new NotFoundHttpException("Unknown subpermit type: {$type}");
        }

        $issuer = Craft::$app->getUser()->getIdentity();
        $defaultName = $issuer?->getFullName() ?: ($issuer?->username ?: '');

        $this->view->setTemplateMode(View::TEMPLATE_MODE_SITE);

        return $this->renderTemplate('bozp/site/subpermits/form', [
            'permit' => $permit,
            'subpermitType' => $subpermitType,
            'errors' => [],
            'values' => $this->defaultValues($subpermitType, $permit, $defaultName),
        ]);
    }

    // -------------------------------------------------------------------------
    // Save
    // -------------------------------------------------------------------------

    public function actionSave(): ?Response
    {
        $this->requirePostRequest();
        if ($redirect = $this->requireBozpLogin()) {
            return $redirect;
        }
        $this->requirePermission('bozp:createPermit');

        $permitId = (int) Craft::$app->getRequest()->getRequiredBodyParam('permitId');
        $permit = $this->findPermit($permitId);
        $this->requireIsIssuer($permit);

        $request = Craft::$app->getRequest();
        $typeValue = trim((string) $request->getRequiredBodyParam('subpermitType'));
        $subpermitType = SubpermitType::tryFrom($typeValue);
        if ($subpermitType === null) {
            throw new NotFoundHttpException("Unknown subpermit type: {$typeValue}");
        }

        $values = $this->collectValues($subpermitType, $request);
        $errors = $this->validate($values);

        if ($errors !== []) {
            Craft::$app->getSession()->setError(Craft::t('bozp', 'Skontrolujte chyby vo formulári.'));
            $this->view->setTemplateMode(View::TEMPLATE_MODE_SITE);
            return $this->renderTemplate('bozp/site/subpermits/form', [
                'permit' => $permit,
                'subpermitType' => $subpermitType,
                'errors' => $errors,
                'values' => $values,
            ]);
        }

        // Validate issuer signature
        $signatureData = trim((string) $request->getBodyParam('signatureData', ''));
        $signerName = trim((string) $request->getBodyParam('signerName', ''));
        $signatureDate = trim((string) $request->getBodyParam('signatureDate', date('Y-m-d')));

        if ($signatureData === '' || !str_starts_with($signatureData, 'data:image/png;base64,')) {
            $errors['signature'] = Craft::t('bozp', 'Podpis vydavateľa je povinný.');
        }
        if ($signerName === '') {
            $errors['signerName'] = Craft::t('bozp', 'Meno podpisujúceho je povinné.');
        }

        if ($errors !== []) {
            Craft::$app->getSession()->setError(Craft::t('bozp', 'Skontrolujte chyby vo formulári.'));
            $this->view->setTemplateMode(View::TEMPLATE_MODE_SITE);
            return $this->renderTemplate('bozp/site/subpermits/form', [
                'permit' => $permit,
                'subpermitType' => $subpermitType,
                'errors' => $errors,
                'values' => $values,
            ]);
        }

        $userId = Craft::$app->getUser()->getId();

        // Electrical-specific: SSoW is either an uploaded attachment OR an
        // inline description. The form renders one or the other based on the
        // `ssowAttached` checkbox. Validate the active branch + persist the
        // uploaded asset id into $values so it lands in subpermit.data.
        if ($subpermitType === SubpermitType::Electrical) {
            $ssowErr = $this->handleElectricalSsow($values);
            if ($ssowErr !== []) {
                foreach ($ssowErr as $k => $v) {
                    $errors[$k] = $v;
                }
                Craft::$app->getSession()->setError(Craft::t('bozp', 'Skontrolujte chyby vo formulári.'));
                $this->view->setTemplateMode(View::TEMPLATE_MODE_SITE);
                return $this->renderTemplate('bozp/site/subpermits/form', [
                    'permit' => $permit,
                    'subpermitType' => $subpermitType,
                    'errors' => $errors,
                    'values' => $values,
                ]);
            }
        }

        // For any type that uses the multi-signer infrastructure, validate
        // that REQUIRED signing emails are present. Optional roles can be
        // left blank — they simply won't trigger a token invite.
        $signingRoles = SubpermitSigningRole::forType($subpermitType);
        if ($signingRoles !== []) {
            foreach ($signingRoles as $role) {
                if ($role->isRequired() && empty(trim($values['signEmail_' . $role->value] ?? ''))) {
                    $errors['signEmail_' . $role->value] = (string) Craft::t(
                        'bozp',
                        'E-mail pre rolu „{role}" je povinný.',
                        ['role' => $role->label()]
                    );
                }
            }
            if ($errors !== []) {
                Craft::$app->getSession()->setError(Craft::t('bozp', 'Skontrolujte chyby vo formulári.'));
                $this->view->setTemplateMode(View::TEMPLATE_MODE_SITE);
                return $this->renderTemplate('bozp/site/subpermits/form', [
                    'permit' => $permit,
                    'subpermitType' => $subpermitType,
                    'errors' => $errors,
                    'values' => $values,
                ]);
            }
        }

        try {
            $subpermit = new SubpermitRecord();
            $subpermit->parentPermitId = $permit->id;
            $subpermit->type = $subpermitType->value;
            $subpermit->status = SubpermitStatus::Pending->value;
            $subpermit->issuerId = $userId;
            $subpermit->data = json_encode($values);

            if (!$subpermit->save()) {
                throw new \RuntimeException('Save failed: ' . print_r($subpermit->getErrors(), true));
            }

            // Capture issuer pre-work signature (Step 2 of the new workflow:
            // create → issuer prework → contractor prework → HSE approve …).
            /** @var Module $module */
            $module = Craft::$app->getModule('bozp');
            $module->subpermitSignatureService->capture(
                $subpermit,
                SubpermitSignatureService::ROLE_ISSUER_PREWORK,
                $signerName,
                null,
                $signatureDate,
                $signatureData,
            );

            // Token-based signing requests for any type that defines roles
            // (Electrical, Excavation, etc.). Only non-empty emails generate
            // a request + invite mail.
            if ($signingRoles !== []) {
                $emails = [];
                foreach ($signingRoles as $role) {
                    $email = trim($values['signEmail_' . $role->value] ?? '');
                    if ($email !== '') {
                        $emails[$role->value] = $email;
                    }
                }
                if ($emails !== []) {
                    $module->subpermitSigningService->createRequestsForSubpermit($subpermit, $permit, $emails);
                }
            }

            // Generate initial subpermit PDF (silently skips on failure)
            $module->permitPdfService->generateForSubpermit($subpermit, $permit);

            // Provision contractor access (token + password) if not already
            // present, then mail the contractor an invite to come sign their
            // pre-work. Required before HSE approval under the new flow.
            try {
                $freshPassword = $module->permitWorkflow->ensureContractorAccess($permit);
                $module->permitMailer->notifyContractorOfSubpermitInvite(
                    $permit,
                    $subpermit,
                    $freshPassword,
                );
            } catch (Throwable $mailErr) {
                Craft::error(
                    'Subpermit contractor invite failed: ' . $mailErr->getMessage(),
                    __METHOD__,
                );
            }
        } catch (Throwable $e) {
            Craft::error('Subpermit save failed: ' . $e->getMessage() . "\n" . $e->getTraceAsString(), __METHOD__);
            $msg = (string) Craft::t('bozp', 'Subpermit sa nepodarilo uložiť. Skúste znova.');
            if (Craft::$app->getConfig()->getGeneral()->devMode) {
                $msg .= ' [debug: ' . $e->getMessage() . ']';
            }
            Craft::$app->getSession()->setError($msg);
            return $this->redirect("bozp/permits/{$permitId}/subpermits/new/{$typeValue}");
        }

        $notice = ($signingRoles !== [])
            ? Craft::t('bozp', 'Subpermit bol uložený. Pozvania na podpis boli odoslané e-mailom.')
            : Craft::t('bozp', 'Subpermit bol uložený a podpísaný.');
        Craft::$app->getSession()->setNotice($notice);
        return $this->redirect("bozp/permits/{$permitId}");
    }

    // -------------------------------------------------------------------------
    // View
    // -------------------------------------------------------------------------

    public function actionView(int $permitId, int $id): Response
    {
        if ($redirect = $this->requireBozpLogin()) {
            return $redirect;
        }

        $permit = $this->findPermit($permitId);
        $subpermit = $this->findSubpermit($id, $permitId);

        $user = Craft::$app->getUser();
        $isIssuer = (int) $permit->issuerId === (int) $user->getId();
        if (!$isIssuer && !$user->checkPermission('bozp:viewAll')) {
            throw new ForbiddenHttpException();
        }

        $subpermitType = SubpermitType::tryFrom($subpermit->type);
        $data = is_string($subpermit->data)
            ? (json_decode($subpermit->data, true) ?? [])
            : (is_array($subpermit->data) ? $subpermit->data : []);

        $canCancel = $isIssuer
            && $subpermit->status === SubpermitStatus::Pending->value;

        /** @var Module $module */
        $module = Craft::$app->getModule('bozp');
        $signatures = $module->subpermitSignatureService->findAllForSubpermit((int) $subpermit->id);

        $this->view->setTemplateMode(View::TEMPLATE_MODE_SITE);

        return $this->renderTemplate('bozp/site/subpermits/view', [
            'permit' => $permit,
            'subpermit' => $subpermit,
            'subpermitType' => $subpermitType,
            'values' => $data,
            'isIssuer' => $isIssuer,
            'canCancel' => $canCancel,
            'signatures' => $signatures,
        ]);
    }

    // -------------------------------------------------------------------------
    // Cancel
    // -------------------------------------------------------------------------

    public function actionCancel(): ?Response
    {
        $this->requirePostRequest();
        if ($redirect = $this->requireBozpLogin()) {
            return $redirect;
        }

        $request = Craft::$app->getRequest();
        $permitId = (int) $request->getRequiredBodyParam('permitId');
        $id = (int) $request->getRequiredBodyParam('id');
        $permit = $this->findPermit($permitId);
        $this->requireIsIssuer($permit);

        $subpermit = $this->findSubpermit($id, $permitId);

        if ($subpermit->status === SubpermitStatus::Approved->value) {
            Craft::$app->getSession()->setError(
                Craft::t('bozp', 'Schválený subpermit nie je možné zrušiť.')
            );
            return $this->redirect("bozp/permits/{$permitId}");
        }

        try {
            $subpermit->status = SubpermitStatus::Cancelled->value;
            $subpermit->cancelledAt = date('Y-m-d H:i:s');
            if (!$subpermit->save()) {
                throw new \RuntimeException('Cancel failed: ' . print_r($subpermit->getErrors(), true));
            }
            /** @var Module $module */
            $module = Craft::$app->getModule('bozp');
            $module->permitPdfService->generateForSubpermit($subpermit, $permit);
        } catch (Throwable $e) {
            Craft::error('Subpermit cancel failed: ' . $e->getMessage(), __METHOD__);
            $msg = (string) Craft::t('bozp', 'Subpermit sa nepodarilo zrušiť. Skúste znova.');
            if (Craft::$app->getConfig()->getGeneral()->devMode) {
                $msg .= ' [debug: ' . $e->getMessage() . ']';
            }
            Craft::$app->getSession()->setError($msg);
        }

        Craft::$app->getSession()->setNotice(Craft::t('bozp', 'Subpermit bol zrušený.'));
        return $this->redirect("bozp/permits/{$permitId}");
    }

    // -------------------------------------------------------------------------
    // Pre-work signature (issuer) — signed BEFORE HSE approval. HSE approve is
    // gated on both preworks being present.
    // -------------------------------------------------------------------------

    public function actionSignPrework(): ?Response
    {
        $this->requirePostRequest();
        if ($redirect = $this->requireBozpLogin()) {
            return $redirect;
        }

        $request = Craft::$app->getRequest();
        $permitId = (int) $request->getRequiredBodyParam('permitId');
        $id       = (int) $request->getRequiredBodyParam('id');

        $permit    = $this->findPermit($permitId);
        $this->requireIsIssuer($permit);
        $subpermit = $this->findSubpermit($id, $permitId);

        if ($subpermit->status !== SubpermitStatus::Pending->value) {
            Craft::$app->getSession()->setError(
                Craft::t('bozp', 'Predpracovný podpis je možný len pred schválením (stav „čaká na schválenie").')
            );
            return $this->redirect("bozp/permits/{$permitId}/subpermits/{$id}");
        }

        /** @var Module $module */
        $module = Craft::$app->getModule('bozp');

        // Already signed?
        if ($module->subpermitSignatureService->findSignature(
            (int) $subpermit->id,
            SubpermitSignatureService::ROLE_ISSUER_PREWORK
        )) {
            Craft::$app->getSession()->setNotice(
                Craft::t('bozp', 'Predpracovný podpis vydavateľa už bol zaznamenaný.')
            );
            return $this->redirect("bozp/permits/{$permitId}/subpermits/{$id}");
        }

        $signatureData = trim((string) $request->getBodyParam('signatureData', ''));
        $signerName    = trim((string) $request->getBodyParam('signerName', ''));
        $signatureDate = trim((string) $request->getBodyParam('signatureDate', date('Y-m-d')));

        if ($signerName === '' || !str_starts_with($signatureData, 'data:image/png;base64,')) {
            Craft::$app->getSession()->setError(
                Craft::t('bozp', 'Podpis a meno sú povinné.')
            );
            return $this->redirect("bozp/permits/{$permitId}/subpermits/{$id}");
        }

        try {
            $module->subpermitSignatureService->capture(
                $subpermit,
                SubpermitSignatureService::ROLE_ISSUER_PREWORK,
                $signerName,
                null,
                $signatureDate,
                $signatureData,
            );
            // Regenerate PDF so the new signature appears
            $module->permitPdfService->generateForSubpermit($subpermit, $permit);
        } catch (Throwable $e) {
            Craft::error('Pre-work issuer sign failed: ' . $e->getMessage(), __METHOD__);
            $msg = (string) Craft::t('bozp', 'Podpis sa nepodarilo uložiť. Skúste znova.');
            if (Craft::$app->getConfig()->getGeneral()->devMode) {
                $msg .= ' [debug: ' . $e->getMessage() . ']';
            }
            Craft::$app->getSession()->setError($msg);
            return $this->redirect("bozp/permits/{$permitId}/subpermits/{$id}");
        }

        Craft::$app->getSession()->setNotice(
            Craft::t('bozp', 'Predpracovný podpis vydavateľa bol zaznamenaný.')
        );
        return $this->redirect("bozp/permits/{$permitId}/subpermits/{$id}");
    }

    // -------------------------------------------------------------------------
    // Issuer closure signature — signed AFTER contractor closure.
    // Marks subpermit as fully closed by the issuer.
    // -------------------------------------------------------------------------

    public function actionSignClosure(): ?Response
    {
        $this->requirePostRequest();
        if ($redirect = $this->requireBozpLogin()) {
            return $redirect;
        }

        $request = Craft::$app->getRequest();
        $permitId = (int) $request->getRequiredBodyParam('permitId');
        $id       = (int) $request->getRequiredBodyParam('id');

        $permit    = $this->findPermit($permitId);
        $this->requireIsIssuer($permit);
        $subpermit = $this->findSubpermit($id, $permitId);

        /** @var Module $module */
        $module = Craft::$app->getModule('bozp');

        // Contractor must have signed closure first.
        $contractorSig = $module->subpermitSignatureService->findSignature(
            (int) $subpermit->id,
            SubpermitSignatureService::ROLE_CONTRACTOR
        );
        if (!$contractorSig) {
            Craft::$app->getSession()->setError(
                Craft::t('bozp', 'Dodávateľ musí najprv podpísať uzavretie.')
            );
            return $this->redirect("bozp/permits/{$permitId}/subpermits/{$id}");
        }

        // Already signed?
        if ($module->subpermitSignatureService->findSignature(
            (int) $subpermit->id,
            SubpermitSignatureService::ROLE_ISSUER_CLOSURE
        )) {
            Craft::$app->getSession()->setNotice(
                Craft::t('bozp', 'Uzávierkový podpis vydavateľa už bol zaznamenaný.')
            );
            return $this->redirect("bozp/permits/{$permitId}/subpermits/{$id}");
        }

        $signatureData = trim((string) $request->getBodyParam('signatureData', ''));
        $signerName    = trim((string) $request->getBodyParam('signerName', ''));
        $signatureDate = trim((string) $request->getBodyParam('signatureDate', date('Y-m-d')));

        if ($signerName === '' || !str_starts_with($signatureData, 'data:image/png;base64,')) {
            Craft::$app->getSession()->setError(
                Craft::t('bozp', 'Podpis a meno sú povinné.')
            );
            return $this->redirect("bozp/permits/{$permitId}/subpermits/{$id}");
        }

        try {
            $module->subpermitSignatureService->capture(
                $subpermit,
                SubpermitSignatureService::ROLE_ISSUER_CLOSURE,
                $signerName,
                null,
                $signatureDate,
                $signatureData,
            );
            $module->permitPdfService->generateForSubpermit($subpermit, $permit);
        } catch (Throwable $e) {
            Craft::error('Issuer closure sign failed: ' . $e->getMessage(), __METHOD__);
            $msg = (string) Craft::t('bozp', 'Podpis sa nepodarilo uložiť. Skúste znova.');
            if (Craft::$app->getConfig()->getGeneral()->devMode) {
                $msg .= ' [debug: ' . $e->getMessage() . ']';
            }
            Craft::$app->getSession()->setError($msg);
            return $this->redirect("bozp/permits/{$permitId}/subpermits/{$id}");
        }

        Craft::$app->getSession()->setNotice(
            Craft::t('bozp', 'Uzávierkový podpis vydavateľa bol zaznamenaný.')
        );
        return $this->redirect("bozp/permits/{$permitId}/subpermits/{$id}");
    }

    // -------------------------------------------------------------------------
    // PDF
    // -------------------------------------------------------------------------

    public function actionPdf(int $permitId, int $id): Response
    {
        if ($redirect = $this->requireBozpLogin()) {
            return $redirect;
        }

        $permit = $this->findPermit($permitId);
        $subpermit = $this->findSubpermit($id, $permitId);

        $user = Craft::$app->getUser();
        $isIssuer = (int) $permit->issuerId === (int) $user->getId();
        if (!$isIssuer && !$user->checkPermission('bozp:viewAll')) {
            throw new \yii\web\ForbiddenHttpException();
        }

        /** @var Module $module */
        $module = Craft::$app->getModule('bozp');

        if (empty($subpermit->pdfAssetId)) {
            $module->permitPdfService->generateForSubpermit($subpermit, $permit);
            $subpermit = $this->findSubpermit($id, $permitId);
        }

        if (empty($subpermit->pdfAssetId)) {
            throw new \yii\web\NotFoundHttpException('PDF is not available yet.');
        }

        $asset = Craft::$app->getAssets()->getAssetById((int) $subpermit->pdfAssetId);
        if (!$asset || !$asset->url) {
            throw new \yii\web\NotFoundHttpException('PDF asset not found.');
        }

        return $this->redirect($asset->url);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function findPermit(int $permitId): PermitRecord
    {
        $permit = PermitRecord::findOne(['id' => $permitId]);
        if (!$permit) {
            throw new NotFoundHttpException('Permit not found.');
        }
        return $permit;
    }

    private function findSubpermit(int $id, int $permitId): SubpermitRecord
    {
        $subpermit = SubpermitRecord::findOne(['id' => $id, 'parentPermitId' => $permitId]);
        if (!$subpermit) {
            throw new NotFoundHttpException('Subpermit not found.');
        }
        return $subpermit;
    }

    private function requireIsIssuer(PermitRecord $permit): void
    {
        $userId = (int) Craft::$app->getUser()->getId();
        if ((int) $permit->issuerId !== $userId) {
            throw new ForbiddenHttpException('Only the permit issuer can manage subpermits.');
        }
    }

    /** @return string[] */
    private function decodeRequiredTypes(PermitRecord $permit): array
    {
        $raw = $permit->requiresHighRisk;
        if (is_string($raw)) {
            $raw = json_decode($raw, true);
        }
        return is_array($raw) ? $raw : [];
    }

    /**
     * Default values for a blank subpermit form.
     * Common fields are pre-populated from the parent permit where possible.
     *
     * @return array<string, mixed>
     */
    private function defaultValues(SubpermitType $type, PermitRecord $permit, string $issuerName): array
    {
        $common = [
            'date'              => date('Y-m-d'),
            'placeOfWork'       => (string) ($permit->workLocation ?? ''),
            'responsiblePerson' => $issuerName,
            'participants'      => (string) ($permit->contractorPersonName ?? ''),
            'workDescription'   => (string) ($permit->workOverview ?? ''),
            'workStep1'         => (string) ($permit->workStep1 ?? ''),
            'workStep2'         => (string) ($permit->workStep2 ?? ''),
            'workStep3'         => (string) ($permit->workStep3 ?? ''),
            'workStep4'         => (string) ($permit->workStep4 ?? ''),
            'workStep5'         => (string) ($permit->workStep5 ?? ''),
        ];

        return array_merge($common, $this->typeDefaults($type));
    }

    /** @return array<string, mixed> */
    private function typeDefaults(SubpermitType $type): array
    {
        return match ($type) {
            SubpermitType::HotWork => [
                'issuedBy' => '', 'jobOrderNumber' => '',
                'workStartTime' => '', 'workExpiryTime' => '',
                'checklist' => [], 'firePatrolName' => '',
                'monitoringHours' => '2', 'additionalMeasures' => '',
            ],
            SubpermitType::ConfinedSpace => [
                'expiryDate' => '', 'expiryTime' => '',
                'emergencyPlan' => '', 'trainingComplete' => '',
                'lotoApplied' => '', 'pumpsBlinded' => '',
                'ventilationRequired' => '',
                'ventilationStartTime' => '', 'ventilationStopTime' => '',
                'o2Percent' => '', 'lelPercent' => '',
                'coPpm' => '', 'h2sPpm' => '',
                'communicationConfirmed' => '',
            ],
            SubpermitType::Heights => [
                'avoidFallPossible' => '', 'fallProtection' => [],
                'fallProtectionOther' => '', 'workerTrained' => '',
                'constructionSuitable' => '', 'fenceRequired' => '',
                'anchorPointUsed' => '', 'anchorPointDescription' => '',
                'equipmentUsed' => [], 'equipmentOther' => '',
                'fallProtectionValidInspection' => '', 'workDiscussed' => '',
            ],
            SubpermitType::CommandB => [
                'riskAssessmentComplete' => '', 'orderNumber' => '',
                'supervisorName' => '', 'groupWorkers' => '',
                'workWithVoltage' => '', 'turnedOffSecured' => '',
                'remainsUnderVoltage' => '', 'orderDeliveredBy' => '',
                'issuedByName' => '', 'receivedByName' => '',
                'checkDeenergizedMethod' => '', 'groundingMethod' => '',
                'workplaceMarking' => '', 'workplaceDefinition' => '',
                'additionalSafetyPrecautions' => '',
                'nearestVoltageParts' => '', 'atmosphericConditions' => '',
            ],
            SubpermitType::Electrical => [
                'mdlzIsQualifiedElectrician' => false,
                'mdlzTrainedToIssue' => false,
                'mdlzWillPerform' => false,
                'thirdPartyRequest' => false,
                'whoPerforms' => '', 'switchboardVoltage' => '',
                'switchboardDesign' => '', 'ssowIssued' => '',
                'workRequest' => '',
                'plannedStartDateTime' => '', 'plannedEndDateTime' => '',
                'reasonWorkUnderVoltage' => '', 'workDescriptionElec' => '',
                'mdlzApplicantName' => '', 'mdlzApplicantRole' => '',
                'thirdPartyName' => '', 'thirdPartyRole' => '',
                'checklist' => [], 'ssowDescription' => '',
                'aedInArea' => '', 'cprTrained' => '', 'aedTrained' => '',
                'areaSupervisorName' => '', 'secondTechnicianName' => '',
                // Signing request emails (one per required/optional signer role)
                'signEmail_third_party'              => '',
                'signEmail_mdlz_qualified_emergency' => '',
                'signEmail_area_supervisor'          => '',
                'signEmail_mdlz_qualified_approval'  => '',
                'signEmail_second_technician'        => '',
                'signEmail_safety_rep'               => '',
                'signEmail_maintenance_manager'      => '',
            ],
            SubpermitType::Excavation => [
                'workerTrained' => '', 'checklist' => [],
                'meetingName' => '', 'meetingPosition' => '',
                'meetingDateTime' => '',
            ],
            SubpermitType::Lifting => [
                'workplaceEnclosable' => '', 'workerTrained' => '',
                'specialTools' => '', 'otherWorkAffecting' => '',
                'processConditionsSuitable' => '', 'checklist' => [],
                'fallProtectionRequired' => '', 'liftingPlanEstablished' => '',
                'workDiscussed' => '',
            ],
            SubpermitType::Atex => [
                'exZone' => [], 'workplaceSecured' => '',
                'securedMethod' => [], 'additionalMeasures' => '',
                'nonSparkingTools' => '', 'atexPowerTools' => '',
                'antistaticClothing' => '', 'hazardousEnergyPlan' => '',
                'ppeUsed' => [], 'toolsUsed' => [],
                'insulationMethod' => [], 'insulationOther' => '',
                'workStartTime' => '', 'workFinishTime' => '',
            ],
        };
    }

    /**
     * Collect and normalise all POST values for a subpermit form.
     *
     * @return array<string, mixed>
     */
    private function collectValues(SubpermitType $type, \craft\web\Request $request): array
    {
        $str  = fn(string $k, string $d = '') => trim((string) $request->getBodyParam($k, $d));
        $arr  = fn(string $k) => (array) $request->getBodyParam($k, []);
        $bool = fn(string $k) => $request->getBodyParam($k) === 'yes';

        $common = [
            'date'              => $str('date'),
            'placeOfWork'       => $str('placeOfWork'),
            'responsiblePerson' => $str('responsiblePerson'),
            'participants'      => $str('participants'),
            'workDescription'   => $str('workDescription'),
            'workStep1'         => $str('workStep1'),
            'workStep2'         => $str('workStep2'),
            'workStep3'         => $str('workStep3'),
            'workStep4'         => $str('workStep4'),
            'workStep5'         => $str('workStep5'),
        ];

        $specific = match ($type) {
            SubpermitType::HotWork => [
                // Header
                'permitIssuedByName'              => $str('permitIssuedByName'),
                'hwPerformerType'                 => $str('hwPerformerType'), // 'employee' | 'contractor'
                'jobOrderNumber'                  => $str('jobOrderNumber'),

                // Work timing (expiry is auto-set at approval: approvedAt + 8h)
                'workStartTime'                   => $str('workStartTime'),
                'workCompletionTime'              => $str('workCompletionTime'),

                // Base checklist (tri-state ok|nok|na)
                'sprinklersOperational'           => $str('sprinklersOperational'),
                'workEquipmentInGoodCondition'    => $str('workEquipmentInGoodCondition'),

                // 11m zone
                'dustEquipmentShutDown'           => $str('dustEquipmentShutDown'),
                'conveyorsIsolated'               => $str('conveyorsIsolated'),
                'combustibleMaterialsRemoved'     => $str('combustibleMaterialsRemoved'),
                'explosiveAtmosphereEliminated'   => $str('explosiveAtmosphereEliminated'),
                'floorSweptClean'                 => $str('floorSweptClean'),
                'combustibleFloorWetDown'         => $str('combustibleFloorWetDown'),
                'openingsCovered'                 => $str('openingsCovered'),
                'tarpaulinsSuspended'             => $str('tarpaulinsSuspended'),

                // Walls / ceilings / enclosed
                'constructionNonCombustible'      => $str('constructionNonCombustible'),
                'combustiblesOnOtherSideMoved'    => $str('combustiblesOnOtherSideMoved'),
                'enclosedEquipmentCleaned'        => $str('enclosedEquipmentCleaned'),
                'containersPurged'                => $str('containersPurged'),

                // Fire patrol monitoring
                'bucketOfWater'                   => $str('bucketOfWater'),
                'fireBrigadeHasExtinguishers'     => $str('fireBrigadeHasExtinguishers'),
                'extinguisherCountAndType'        => $str('extinguisherCountAndType'),
                'firePatrolTrained'               => $str('firePatrolTrained'),
                'additionalSupervisionIncluded'   => $str('additionalSupervisionIncluded'),
                'monitoringHoursAfter'            => $str('monitoringHoursAfter'),

                // Other
                'confinedSpacePermitsOrLoto'      => $str('confinedSpacePermitsOrLoto'),
                'smokeOrHeatDetectionDeactivated' => $str('smokeOrHeatDetectionDeactivated'),
                'otherPrecautions'                => $str('otherPrecautions'),
            ],
            SubpermitType::ConfinedSpace => [
                // Header
                'authorizedPersonName'              => $str('authorizedPersonName'),
                'department'                        => $str('department'),
                'emergencyRescuePlan'               => $str('emergencyRescuePlan'),

                // Isolation
                'pumpsLinesBlinded'                 => $str('pumpsLinesBlinded'),

                // Ventilation
                'ventilationModificationRequired'   => $str('ventilationModificationRequired'),
                'ventilationNotRequiredReason'      => $str('ventilationNotRequiredReason'),
                'forcedVentilationStart'            => $str('forcedVentilationStart'),
                'forcedVentilationStop'             => $str('forcedVentilationStop'),

                // Atmosphere control (initial reading; periodic tests = paper attachment)
                'atmosphereTestTime'                => $str('atmosphereTestTime'),
                'oxygenPercent'                     => $str('oxygenPercent'),
                'lelPercent'                        => $str('lelPercent'),
                'coOrH2sPpm'                        => $str('coOrH2sPpm'),
                'otherChemical'                     => $str('otherChemical'),

                // Communication
                'communicationEntrantSupervisor'    => $str('communicationEntrantSupervisor'),
                'communicationSupervisorEmergency'  => $str('communicationSupervisorEmergency'),
                'emergencyTeamMembers'              => $str('emergencyTeamMembers'),

                // Entry timing
                'timeOfFirstEnter'                  => $str('timeOfFirstEnter'),
                'timeOfLastExit'                    => $str('timeOfLastExit'),

                // Token-mailed signer emails (all required at submit)
                'signEmail_cs_entrant'              => $str('signEmail_cs_entrant'),
                'signEmail_cs_supervisor_entrant'   => $str('signEmail_cs_supervisor_entrant'),
                'signEmail_cs_supervisor_cse'       => $str('signEmail_cs_supervisor_cse'),
                'signEmail_cs_mdlz_orderant'        => $str('signEmail_cs_mdlz_orderant'),
                'signEmail_cs_hse_department'       => $str('signEmail_cs_hse_department'),
            ],
            SubpermitType::Heights => [
                'avoidFallPossible'          => $str('avoidFallPossible'),
                'fallProtection'             => $arr('fallProtection'),
                'fallProtectionOther'        => $str('fallProtectionOther'),
                'workerTrained'              => $str('workerTrained'),
                'constructionSuitable'       => $str('constructionSuitable'),
                'fenceRequired'              => $str('fenceRequired'),
                'anchorPointUsed'            => $str('anchorPointUsed'),
                'anchorPointDescription'     => $str('anchorPointDescription'),
                'equipmentUsed'              => $arr('equipmentUsed'),
                'equipmentOther'             => $str('equipmentOther'),
                'fallProtectionValidInspection' => $str('fallProtectionValidInspection'),
                'workDiscussed'              => $str('workDiscussed'),
            ],
            SubpermitType::CommandB => [
                // Identification
                'orderNumber'           => $str('orderNumber'),
                'supervisorName'        => $str('supervisorName'),
                'supervisorGroupSize'   => $str('supervisorGroupSize'),
                'supervisionName'       => $str('supervisionName'),
                'supervisionGroupSize'  => $str('supervisionGroupSize'),

                // Time + scope
                'day'                   => $str('day'),
                'fromTime'              => $str('fromTime'),
                'untilTime'             => $str('untilTime'),
                'locationType'          => $str('locationType'),   // 'on' | 'near'
                'voltageMode'           => $str('voltageMode'),    // 'with' | 'without'

                // Workplace securing
                'workplaceTurnOff'      => $str('workplaceTurnOff'),
                'workplaceSecure'       => $str('workplaceSecure'),
                'remainUnderVoltage'    => $str('remainUnderVoltage'),

                // Order delivery + signatories (printed names, dates, times)
                'deliveryMethod'        => $str('deliveryMethod'), // 'in_person' | 'other' | 'email'
                'issuedByName'          => $str('issuedByName'),
                'issuedByDate'          => $str('issuedByDate'),
                'issuedByTime'          => $str('issuedByTime'),
                'receivedByName'        => $str('receivedByName'),
                'receivedByDate'        => $str('receivedByDate'),
                'receivedByTime'        => $str('receivedByTime'),

                // Order book entry
                'orderBookNumber'       => $str('orderBookNumber'),
                'commandNumber'         => $str('commandNumber'),

                // Action grid — actions[groupKey][rowIdx][field]. Stored raw;
                // template-side iteration over $actionGroups defines the shape.
                'actions'               => $this->normalizeCommandBActions((array) $request->getBodyParam('actions', [])),

                // Environment
                'nearestVoltageParts'   => $str('nearestVoltageParts'),
                'atmosphericConditions' => $str('atmosphericConditions'),
            ],
            SubpermitType::Electrical => [
                // Section 1 — confirmations (each checkbox: value '1' if ticked)
                'chkApplicantQualified'        => $request->getBodyParam('chkApplicantQualified') === '1',
                'chkApplicantTrained'          => $request->getBodyParam('chkApplicantTrained') === '1',
                'chkApplicantWillPerform'      => $request->getBodyParam('chkApplicantWillPerform') === '1',
                'chkRequestForThirdParty'      => $request->getBodyParam('chkRequestForThirdParty') === '1',

                // Section 1 — work classification
                'whoPerforms'                  => $str('whoPerforms'),           // internal_single | internal_multiple | third_party
                'switchboardVoltage'           => $str('switchboardVoltage'),    // under_50vac | between_50_750vac | over_750vac
                'switchboardDesign'            => $str('switchboardDesign'),     // ip2x_comp | ip2x_non_comp
                'ssowAttached'                 => $request->getBodyParam('ssowAttached') === '1',

                // Section 1 — work details
                'workOrder'                    => $str('workOrder'),
                'plannedStartDateTime'         => $str('plannedStartDateTime'),
                'plannedEndDateTime'           => $str('plannedEndDateTime'),
                'reasonForLiveWork'            => $str('reasonForLiveWork'),

                // Section 2 — Arc Flash boundaries (m + cm pairs)
                'arcFlashBoundaryM'            => $str('arcFlashBoundaryM'),
                'arcFlashBoundaryCm'           => $str('arcFlashBoundaryCm'),
                'limitedAccessBoundaryM'       => $str('limitedAccessBoundaryM'),
                'limitedAccessBoundaryCm'      => $str('limitedAccessBoundaryCm'),
                'prohibitedAccessBoundaryM'    => $str('prohibitedAccessBoundaryM'),
                'prohibitedAccessBoundaryCm'   => $str('prohibitedAccessBoundaryCm'),

                // Section 2 — arc-flash sticker / fallback boundary mode
                'arcFlashStickerAvailable'     => $str('arcFlashStickerAvailable'),
                'arcFlashUnavailableMode'      => $str('arcFlashUnavailableMode'), // always_3m_over_750vac | under_3m_restricted_proof_safe

                // Section 2 — access controls
                'accessControls'               => $arr('accessControls'),
                'accessControlsOther'          => $str('accessControlsOther'),

                // Section 2 — site safety yes/no (all must be 'yes' to proceed)
                'flammablesRemovedAndExtinguisher' => $str('flammablesRemovedAndExtinguisher'),
                'sufficientLighting'               => $str('sufficientLighting'),
                'doorsSecured'                     => $str('doorsSecured'),
                'emergencyExitClear'               => $str('emergencyExitClear'),
                'switchboardNoForeignObjects'      => $str('switchboardNoForeignObjects'),

                // Section 5 — SSoW description (when not attached)
                'ssowDescription'              => $str('ssowDescription'),

                // Emergency situation plan flags
                'aedInArea'                    => $str('aedInArea'),
                'cprTrained'                   => $str('cprTrained'),
                'aedTrained'                   => $str('aedTrained'),

                // Signing request emails (3 required + 4 conditional)
                'signEmail_mdlz_qualified_emergency' => $str('signEmail_mdlz_qualified_emergency'),
                'signEmail_area_supervisor'          => $str('signEmail_area_supervisor'),
                'signEmail_mdlz_qualified_approval'  => $str('signEmail_mdlz_qualified_approval'),
                'signEmail_third_party'              => $str('signEmail_third_party'),
                'signEmail_second_technician'        => $str('signEmail_second_technician'),
                'signEmail_safety_rep'               => $str('signEmail_safety_rep'),
                'signEmail_maintenance_manager'      => $str('signEmail_maintenance_manager'),
            ],
            SubpermitType::Excavation => [
                // Worker competency (header row)
                'workerTrained'                   => $str('workerTrained'),

                // FÁZA 1 — MDLZ issuer safety checklist (Yes/No grid)
                'needsElectricalIsolation'        => $str('needsElectricalIsolation'),
                'needsBarriersSignage'            => $str('needsBarriersSignage'),
                'equipmentIsolated'               => $str('equipmentIsolated'),
                'adequateLighting'                => $str('adequateLighting'),
                'designatedArea'                  => $str('designatedArea'),
                'noSmokingOrOpenFire'             => $str('noSmokingOrOpenFire'),
                'warningSignsPlaced'              => $str('warningSignsPlaced'),
                'shoringRequired'                 => $str('shoringRequired'),
                'supervisionPresent'              => $str('supervisionPresent'),
                'firstAidKit'                     => $str('firstAidKit'),
                'explosivityCheck'                => $str('explosivityCheck'),
                'fireExtinguisher'                => $str('fireExtinguisher'),
                'toxicityCheck'                   => $str('toxicityCheck'),
                'rescueLineWithOperator'          => $str('rescueLineWithOperator'),
                'oxygenAbove195'                  => $str('oxygenAbove195'),
                'safeAccessAndExit'               => $str('safeAccessAndExit'),
                'selfContainedBreathingApparatus' => $str('selfContainedBreathingApparatus'),
                'noUndergroundUtilities'          => $str('noUndergroundUtilities'),
                'dailyInspectionsRecorded'        => $str('dailyInspectionsRecorded'),
                'supportRequiredOver15m'          => $str('supportRequiredOver15m'),

                // Planned activity
                'plannedActivityMeetingDate'      => $str('plannedActivityMeetingDate'),
                'barricadesWalkways'              => $str('barricadesWalkways'),

                // PHASE 2 / PHASE 3 — optional token-mailed approval signers.
                // Empty email = no invite. Signer fills name + jobTitle + date
                // + signature on their token page.
                'signEmail_excavation_hse_approval'       => $str('signEmail_excavation_hse_approval'),
                'signEmail_excavation_authority_approval' => $str('signEmail_excavation_authority_approval'),
            ],
            SubpermitType::Lifting => [
                // Príprava na činnosť — všetky pracovné činnosti
                'canFenceOffWorkplace'             => $str('canFenceOffWorkplace'),
                'workerTrained'                    => $str('workerTrained'),
                'specialToolsUsed'                 => $str('specialToolsUsed'),
                'processConditionsSuitable'        => $str('processConditionsSuitable'),
                'otherWorkAffectingPermit'         => $str('otherWorkAffectingPermit'),

                // Bezpečnostné požiadavky
                'craneInSafestPosition'            => $str('craneInSafestPosition'),
                'safeAccessEnsured'                => $str('safeAccessEnsured'),
                'craneDangerZoneFenced'            => $str('craneDangerZoneFenced'),
                'employeesAwareOfFencingAndBan'    => $str('employeesAwareOfFencingAndBan'),
                'craneControlsHaveLoadBinderView'  => $str('craneControlsHaveLoadBinderView'),
                'craneInGoodCondition'             => $str('craneInGoodCondition'),
                'otherCollisionRisks'              => $str('otherCollisionRisks'),
                'noOtherWorkNearby'                => $str('noOtherWorkNearby'),
                'bindingEquipmentInspected'        => $str('bindingEquipmentInspected'),
                'communicationAgreed'              => $str('communicationAgreed'),
                'fallProtectionRequired'           => $str('fallProtectionRequired'),

                // Záverečné potvrdenia
                'liftingPlanEstablished'           => $str('liftingPlanEstablished'),
                'workChecksDiscussedOnSite'        => $str('workChecksDiscussedOnSite'),
            ],
            SubpermitType::Atex => [
                // EX zóna (multi-select)
                'exZone'                          => $arr('exZone'),

                // Príprava na činnosť — workplace securing
                'securingStatus'                  => $str('securingStatus'), // 'secured' | 'not_possible'
                'securingMethods'                 => $arr('securingMethods'),
                'securingMethodsOther'            => $str('securingMethodsOther'),

                // Additional measures (yes/no flags). Hourly verification
                // happens offline; a control attachment is required at closure
                // (see ContractorController::actionSignSubpermit).
                'measureNonSparkingHandTools'     => $str('measureNonSparkingHandTools'),
                'measurePortablePowerToolsAtex'   => $str('measurePortablePowerToolsAtex'),
                'measureAntistaticClothing'       => $str('measureAntistaticClothing'),

                // Plan + PPE
                'hazardousEnergyPlanEstablished'  => $str('hazardousEnergyPlanEstablished'),
                'usedPpe'                         => $arr('usedPpe'),
                'usedPpeOther'                    => $str('usedPpeOther'),

                // Tools + insulation
                'toolsUsed'                       => $arr('toolsUsed'),
                'workspaceInsulation'             => $arr('workspaceInsulation'),
                'workspaceInsulationOther'        => $str('workspaceInsulationOther'),
            ],
        };

        return array_merge($common, $specific);
    }

    private const SSOW_ALLOWED_EXT  = ['pdf', 'docx', 'xlsx'];
    private const SSOW_ALLOWED_MIME = [
        'application/pdf',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ];
    private const SSOW_MAX_BYTES = 10 * 1024 * 1024; // 10 MB

    /**
     * Validate + persist the Electrical SSoW input:
     *   - If ssowAttached === true → uploaded `ssowFile` must be valid;
     *     save it as a Craft Asset and inject the asset id + filename into
     *     $values so it lands in subpermit.data. Clear ssowDescription.
     *   - If ssowAttached === false → ssowDescription must be non-empty.
     *     Clear any stale asset references.
     *
     * Returns an array of errors (empty when validation passes). $values is
     * mutated by reference.
     *
     * @param array<string, mixed> $values
     * @return array<string, string>
     */
    private function handleElectricalSsow(array &$values): array
    {
        $errors = [];
        $attached = !empty($values['ssowAttached']);

        if ($attached) {
            $file = UploadedFile::getInstanceByName('ssowFile');

            // Allow keeping an already-uploaded file across re-renders (no new upload).
            $hasExistingAsset = !empty($values['ssowAttachmentAssetId']);

            if ($file === null || $file->getHasError()) {
                if (!$hasExistingAsset) {
                    $errors['ssowFile'] = (string) Craft::t('bozp', 'Nahrajte súbor SSoW alebo zrušte výber prílohy a vyplňte popis.');
                    return $errors;
                }
            } else {
                if ($file->size > self::SSOW_MAX_BYTES) {
                    $errors['ssowFile'] = (string) Craft::t('bozp', 'Súbor je príliš veľký. Maximálna veľkosť je 10 MB.');
                    return $errors;
                }
                $ext  = strtolower((string) pathinfo($file->name, PATHINFO_EXTENSION));
                $mime = (string) FileHelper::getMimeType($file->tempName);
                if (!in_array($ext, self::SSOW_ALLOWED_EXT, true) || !in_array($mime, self::SSOW_ALLOWED_MIME, true)) {
                    $errors['ssowFile'] = (string) Craft::t('bozp', 'Nepodporovaný typ súboru. Povolené: PDF, DOCX, XLSX.');
                    return $errors;
                }

                $assetId = $this->saveSsowAsset($file);
                if ($assetId === null) {
                    $errors['ssowFile'] = (string) Craft::t('bozp', 'Uloženie súboru zlyhalo. Skúste znova.');
                    return $errors;
                }
                $values['ssowAttachmentAssetId'] = $assetId;
                $values['ssowAttachmentFilename'] = $file->name;
            }

            // Attached branch wins — clear inline description.
            $values['ssowDescription'] = '';
        } else {
            // Inline branch: description required, clear any old asset reference.
            if (empty(trim((string) ($values['ssowDescription'] ?? '')))) {
                $errors['ssowDescription'] = (string) Craft::t('bozp', 'Popis SSoW je povinný, ak nie je priložený samostatný súbor.');
                return $errors;
            }
            unset($values['ssowAttachmentAssetId'], $values['ssowAttachmentFilename']);
        }

        return $errors;
    }

    /**
     * Persist the uploaded SSoW file as a Craft Asset in the bozpAttachments
     * volume. Returns the asset id, or null on failure (caller decides how
     * to surface the error).
     */
    private function saveSsowAsset(UploadedFile $file): ?int
    {
        $volume = Craft::$app->getVolumes()->getVolumeByHandle('bozpAttachments');
        if (!$volume) {
            Craft::error("BOZP: missing volume 'bozpAttachments' for SSoW upload.", __METHOD__);
            return null;
        }
        $rootFolder = Craft::$app->getAssets()->getRootFolderByVolumeId($volume->id);
        if (!$rootFolder) {
            return null;
        }

        $tempPath = $file->saveAsTempFile();
        if ($tempPath === false) {
            return null;
        }

        $asset = new Asset();
        $asset->tempFilePath = $tempPath;
        $asset->filename = Assets::prepareAssetName('ssow-' . $file->name);
        $asset->newFolderId = $rootFolder->id;
        $asset->volumeId = $volume->id;
        $asset->avoidFilenameConflicts = true;
        $asset->setScenario(Asset::SCENARIO_CREATE);

        if (!Craft::$app->getElements()->saveElement($asset)) {
            Craft::error('BOZP: SSoW asset save failed: ' . print_r($asset->getErrors(), true), __METHOD__);
            return null;
        }
        return (int) $asset->id;
    }

    /**
     * Normalize the Command-"B" actions grid: actions[groupKey][rowIdx][field].
     * Only the field set defined by the template is retained — anything else
     * (extra keys posted by hand) is dropped.
     *
     * @param array<mixed> $raw
     * @return array<string, array<int, array<string, string>>>
     */
    private function normalizeCommandBActions(array $raw): array
    {
        $allowedFields = ['place', 'description', 'serialNumber', 'responsible', 'performedAt', 'signatureName'];
        $out = [];
        foreach ($raw as $groupKey => $rows) {
            if (!is_array($rows)) {
                continue;
            }
            $cleanGroup = [];
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $cleanRow = [];
                foreach ($allowedFields as $f) {
                    $cleanRow[$f] = trim((string) ($row[$f] ?? ''));
                }
                $cleanGroup[] = $cleanRow;
            }
            if ($cleanGroup !== []) {
                $out[(string) $groupKey] = $cleanGroup;
            }
        }
        return $out;
    }

    /**
     * Basic validation — only common required fields enforced here.
     * Type-specific required fields can be added as needed.
     *
     * @param array<string, mixed> $values
     * @return array<string, string>
     */
    private function validate(array $values): array
    {
        $errors = [];
        if (empty($values['date'])) {
            $errors['date'] = (string) Craft::t('bozp', 'Dátum je povinný.');
        }
        if (empty($values['placeOfWork'])) {
            $errors['placeOfWork'] = (string) Craft::t('bozp', 'Miesto výkonu je povinné.');
        }
        if (empty($values['responsiblePerson'])) {
            $errors['responsiblePerson'] = (string) Craft::t('bozp', 'Zodpovedná osoba je povinná.');
        }
        return $errors;
    }
}
