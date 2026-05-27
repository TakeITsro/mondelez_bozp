<?php

declare(strict_types=1);

namespace modules\bozp\controllers;

use Craft;
use craft\elements\Asset;
use craft\helpers\Assets;
use craft\helpers\FileHelper;
use craft\helpers\UrlHelper;
use craft\web\Controller;
use craft\web\UploadedFile;
use craft\web\View;
use modules\bozp\enums\HazardCategory;
use modules\bozp\enums\PermitStatus;
use modules\bozp\enums\SignatureRole;
use modules\bozp\enums\SubpermitType;
use modules\bozp\Module;
use modules\bozp\records\PermitAttachmentRecord;
use modules\bozp\records\PermitControlRecord;
use modules\bozp\records\PermitHazardRecord;
use modules\bozp\records\PermitRecord;
use modules\bozp\records\PermitSignatureRecord;
use modules\bozp\records\SubpermitRecord;
use modules\bozp\records\ZoneRecord;
use yii\web\ForbiddenHttpException;
use modules\bozp\services\SignatureService;
use modules\bozp\services\SubpermitSignatureService;
use Throwable;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * ContractorController
 *
 * Public, password-gated access for the external contractor named on a
 * permit. Reached via /bozp/c/<token> — the token is mailed at HSE
 * approval time. The token alone is not enough; the contractor must
 * also enter the one-time password from the same email. Once
 * authenticated, the session remembers it for the life of the browser
 * session (or until expiry).
 *
 * No Craft user account is required. Anonymous traffic is welcome on
 * these routes (allowAnonymous = true).
 *
 * Routes (registered in Module.php):
 *   GET  bozp/c/<token>          → actionView
 *   POST bozp/c/<token>/auth     → actionAuth
 *   POST bozp/c/<token>/upload   → actionUpload
 */
class ContractorController extends Controller
{
    public const ASSET_VOLUME_HANDLE = 'bozpAttachments';

    public const ALLOWED_MIME = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'image/jpeg',
        'image/png',
    ];

    public const ALLOWED_EXT = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];

    public const MAX_UPLOAD_BYTES = 10 * 1024 * 1024; // 10 MB

    public array|bool|int $allowAnonymous = true;
    public $enableCsrfValidation = true;

    public function actionView(string $token): Response
    {
        $permit = $this->lookupPermit($token);

        if ($this->isExpired($permit)) {
            return $this->renderExpired();
        }

        if (!$this->isAuthedFor($token)) {
            return $this->renderPasswordPrompt($permit, []);
        }

        return $this->renderDetail($permit);
    }

    public function actionAuth(string $token): Response
    {
        $this->requirePostRequest();

        $permit = $this->lookupPermit($token);

        if ($this->isExpired($permit)) {
            return $this->renderExpired();
        }

        $password = (string) Craft::$app->getRequest()->getBodyParam('password', '');

        if ($password === '' || !$this->verifyPassword($permit, $password)) {
            return $this->renderPasswordPrompt($permit, [
                'password' => (string) Craft::t('bozp', 'Nesprávne heslo.'),
            ]);
        }

        $this->markAuthed($token);
        return $this->redirect(UrlHelper::siteUrl('bozp/c/' . $token));
    }

    public function actionUpload(string $token): Response
    {
        $this->requirePostRequest();

        $permit = $this->lookupPermit($token);

        if ($this->isExpired($permit)) {
            return $this->renderExpired();
        }

        if (!$this->isAuthedFor($token)) {
            return $this->renderPasswordPrompt($permit, []);
        }

        // Once the contractor has signed (complete or cancel) the permit
        // is locked and no further attachments can be added.
        if ($this->signatures()->findSignature((int) $permit->id, SignatureRole::RecipientClosure)) {
            Craft::$app->getSession()->setError(
                Craft::t('bozp', 'Permit je uzamknutý — ďalšie prílohy už nie je možné pridávať.')
            );
            return $this->redirect(UrlHelper::siteUrl('bozp/c/' . $token));
        }

        $uploaded = UploadedFile::getInstanceByName('attachment');

        if (!$uploaded || $uploaded->getHasError()) {
            Craft::$app->getSession()->setError(
                Craft::t('bozp', 'Nepodarilo sa nahrať súbor. Skúste znova.')
            );
            return $this->redirect(UrlHelper::siteUrl('bozp/c/' . $token));
        }

        // Type + size validation.
        if ($uploaded->size > self::MAX_UPLOAD_BYTES) {
            Craft::$app->getSession()->setError(
                Craft::t('bozp', 'Súbor je príliš veľký. Maximálna veľkosť je 10 MB.')
            );
            return $this->redirect(UrlHelper::siteUrl('bozp/c/' . $token));
        }

        $ext = strtolower((string) pathinfo($uploaded->name, PATHINFO_EXTENSION));
        $mime = (string) FileHelper::getMimeType($uploaded->tempName);

        if (!in_array($ext, self::ALLOWED_EXT, true) || !in_array($mime, self::ALLOWED_MIME, true)) {
            Craft::$app->getSession()->setError(
                Craft::t('bozp', 'Nepodporovaný typ súboru. Povolené: PDF, DOCX, JPG, PNG.')
            );
            return $this->redirect(UrlHelper::siteUrl('bozp/c/' . $token));
        }

        // Resolve volume + create asset.
        $volume = Craft::$app->getVolumes()->getVolumeByHandle(self::ASSET_VOLUME_HANDLE);
        if (!$volume) {
            Craft::error("BOZP contractor upload: missing asset volume '" . self::ASSET_VOLUME_HANDLE . "'", __METHOD__);
            Craft::$app->getSession()->setError(
                Craft::t('bozp', 'Úložisko súborov nie je nastavené. Kontaktujte HSE.')
            );
            return $this->redirect(UrlHelper::siteUrl('bozp/c/' . $token));
        }

        try {
            $rootFolder = Craft::$app->getAssets()->getRootFolderByVolumeId($volume->id);
            if (!$rootFolder) {
                throw new \RuntimeException("No root folder for volume '" . self::ASSET_VOLUME_HANDLE . "'");
            }

            // Copy the PHP upload to Craft's runtime temp dir BEFORE saving
            // the asset. Going via /tmp directly can fail on shared hosts
            // where /tmp lives on a different filesystem from web/.
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

            $att = new PermitAttachmentRecord();
            $att->permitId = (int) $permit->id;
            $att->attachmentType = 'contractor_upload';
            $att->assetId = (int) $asset->id;
            $att->uploadedById = null;
            $att->uploadedByName = $permit->contractorPersonName ?: $permit->contractorCompany;
            if (!$att->save()) {
                throw new \RuntimeException('Attachment row save failed: ' . print_r($att->getErrors(), true));
            }

            /** @var Module $module */
            $module = Craft::$app->getModule('bozp');
            $module->auditLogger->log(
                permitId: (int) $permit->id,
                userId: null,
                action: 'contractor_upload',
                note: $asset->filename,
            );

            Craft::$app->getSession()->setNotice(
                Craft::t('bozp', 'Súbor bol nahraný.')
            );
        } catch (Throwable $e) {
            $chain = [];
            for ($cur = $e; $cur !== null; $cur = $cur->getPrevious()) {
                $chain[] = get_class($cur) . ': ' . $cur->getMessage();
            }
            Craft::error(
                'BOZP contractor upload failed: ' . implode(' | ', $chain) . "\n" . $e->getTraceAsString(),
                __METHOD__,
            );
            $msg = (string) Craft::t('bozp', 'Nahrávanie súboru zlyhalo. Skúste znova.');
            // Surface the full causal chain during development / testing.
            // Strip this suffix once stable.
            $msg .= ' [debug: ' . implode(' <- ', $chain) . ']';
            Craft::$app->getSession()->setError($msg);
        }

        return $this->redirect(UrlHelper::siteUrl('bozp/c/' . $token));
    }

    /**
     * Capture the contractor's RecipientClosure signature for the
     * "work done" path. Permit transitions to pending_closure.
     */
    public function actionClose(string $token): Response
    {
        $this->requirePostRequest();
        $permit = $this->lookupPermit($token);

        if ($this->isExpired($permit)) {
            return $this->renderExpired();
        }
        if (!$this->isAuthedFor($token)) {
            return $this->renderPasswordPrompt($permit, []);
        }

        if (!in_array($permit->status, [PermitStatus::Approved->value, PermitStatus::Signed->value, PermitStatus::Active->value], true)) {
            Craft::$app->getSession()->setError(
                Craft::t('bozp', 'Permit nie je v stave, v ktorom je možné dokončiť.')
            );
            return $this->redirect(UrlHelper::siteUrl('bozp/c/' . $token));
        }
        if ($this->signatures()->findSignature((int) $permit->id, SignatureRole::RecipientClosure)) {
            Craft::$app->getSession()->setError(Craft::t('bozp', 'Dokončenie už bolo podpísané.'));
            return $this->redirect(UrlHelper::siteUrl('bozp/c/' . $token));
        }

        $request = Craft::$app->getRequest();
        $statusFlags = (array) $request->getBodyParam('closureStatus', []);
        // Cancellation flag ('work_suspended') is NOT accepted here —
        // that path lives in actionCancel().
        $allowedFlags = [
            'work_completed', 'equipment_operational', 'equipment_not_operational',
            'personnel_and_materials_removed',
        ];
        $statusFlags = array_values(array_intersect($statusFlags, $allowedFlags));

        [$values, $errors] = $this->collectSignatureFields();
        if ($statusFlags === []) {
            $errors['closureStatus'] = (string) Craft::t('bozp', 'Vyberte aspoň jednu možnosť.');
        }
        if ($errors !== []) {
            return $this->renderDetail($permit, closeErrors: $errors, closeValues: $values + ['closureStatus' => $statusFlags]);
        }

        try {
            $this->signatures()->capture(
                $permit,
                SignatureRole::RecipientClosure,
                $values['signerName'],
                $values['signerEmployer'],
                $values['signatureDate'],
                $values['signatureData'],
            );

            /** @var Module $module */
            $module = Craft::$app->getModule('bozp');
            $module->permitWorkflow->closeByRecipient($permit, $statusFlags, $values['signerName']);
            $module->permitMailer->notifyIssuerOfContractorSignature($permit, 'close', $values['signerName']);
            $module->permitPdfService->generateForPermit($permit);

            Craft::$app->getSession()->setNotice(Craft::t('bozp', 'Dokončenie bolo zaznamenané.'));
        } catch (Throwable $e) {
            Craft::error('BOZP recipient close failed: ' . $e->getMessage() . "\n" . $e->getTraceAsString(), __METHOD__);
            $msg = (string) Craft::t('bozp', 'Dokončenie sa nepodarilo uložiť. Skúste znova.');
            if (Craft::$app->getConfig()->getGeneral()->devMode) {
                $msg .= ' [debug: ' . $e->getMessage() . ']';
            }
            Craft::$app->getSession()->setError($msg);
        }

        return $this->redirect(UrlHelper::siteUrl('bozp/c/' . $token));
    }

    /**
     * Contractor cancellation — they sign that work cannot be done.
     * Permit transitions straight to "cancelled". No further issuer
     * action required.
     */
    public function actionCancel(string $token): Response
    {
        $this->requirePostRequest();
        $permit = $this->lookupPermit($token);

        if ($this->isExpired($permit)) {
            return $this->renderExpired();
        }
        if (!$this->isAuthedFor($token)) {
            return $this->renderPasswordPrompt($permit, []);
        }

        if (!in_array($permit->status, [PermitStatus::Approved->value, PermitStatus::Signed->value, PermitStatus::Active->value], true)) {
            Craft::$app->getSession()->setError(
                Craft::t('bozp', 'Permit nie je v stave, v ktorom je možné zrušiť.')
            );
            return $this->redirect(UrlHelper::siteUrl('bozp/c/' . $token));
        }
        if ($this->signatures()->findSignature((int) $permit->id, SignatureRole::RecipientClosure)) {
            Craft::$app->getSession()->setError(Craft::t('bozp', 'Dokončenie už bolo podpísané.'));
            return $this->redirect(UrlHelper::siteUrl('bozp/c/' . $token));
        }

        $request = Craft::$app->getRequest();
        $reason = trim((string) $request->getBodyParam('reason', ''));

        [$values, $errors] = $this->collectSignatureFields();
        if ($errors !== []) {
            return $this->renderDetail(
                $permit,
                cancelErrors: $errors,
                cancelValues: $values + ['reason' => $reason],
            );
        }

        try {
            $this->signatures()->capture(
                $permit,
                SignatureRole::RecipientClosure,
                $values['signerName'],
                $values['signerEmployer'],
                $values['signatureDate'],
                $values['signatureData'],
            );

            /** @var Module $module */
            $module = Craft::$app->getModule('bozp');
            $module->permitWorkflow->cancelByRecipient($permit, $reason !== '' ? $reason : null, $values['signerName']);
            $module->permitMailer->notifyIssuerOfContractorSignature($permit, 'cancel', $values['signerName']);
            $module->permitPdfService->generateForPermit($permit);

            Craft::$app->getSession()->setNotice(Craft::t('bozp', 'Permit bol zrušený dodávateľom.'));
        } catch (Throwable $e) {
            Craft::error('BOZP recipient cancel failed: ' . $e->getMessage() . "\n" . $e->getTraceAsString(), __METHOD__);
            $msg = (string) Craft::t('bozp', 'Zrušenie sa nepodarilo uložiť. Skúste znova.');
            if (Craft::$app->getConfig()->getGeneral()->devMode) {
                $msg .= ' [debug: ' . $e->getMessage() . ']';
            }
            Craft::$app->getSession()->setError($msg);
        }

        return $this->redirect(UrlHelper::siteUrl('bozp/c/' . $token));
    }

    /**
     * GET bozp/c/<token>/pdf — redirect to the permit PDF (requires password auth).
     */
    public function actionPermitPdf(string $token): Response
    {
        $permit = $this->lookupPermit($token);

        if ($this->isExpired($permit)) {
            return $this->renderExpired();
        }
        if (!$this->isAuthedFor($token)) {
            return $this->renderPasswordPrompt($permit, []);
        }

        /** @var \modules\bozp\Module $module */
        $module = Craft::$app->getModule('bozp');

        if (empty($permit->pdfAssetId)) {
            $module->permitPdfService->generateForPermit($permit);
            $permit = \modules\bozp\records\PermitRecord::findOne(['id' => $permit->id]);
        }

        if (empty($permit->pdfAssetId)) {
            Craft::$app->getSession()->setError(Craft::t('bozp', 'PDF nie je k dispozícii.'));
            return $this->redirect(UrlHelper::siteUrl('bozp/c/' . $token));
        }

        $asset = Craft::$app->getAssets()->getAssetById((int) $permit->pdfAssetId);
        if (!$asset || !$asset->url) {
            Craft::$app->getSession()->setError(Craft::t('bozp', 'PDF súbor nebol nájdený.'));
            return $this->redirect(UrlHelper::siteUrl('bozp/c/' . $token));
        }

        return $this->redirect($asset->url);
    }

    /**
     * GET bozp/c/<token>/subpermits/<id>/pdf — redirect to a subpermit PDF (requires password auth).
     *
     * `$id` is bound by Yii from the URL placeholder — declaring it as a
     * method param is the only reliable way to receive it. `getParam('id')`
     * returns null for route placeholders.
     */
    public function actionSubpermitPdf(string $token, int $id): Response
    {
        $permit = $this->lookupPermit($token);

        if ($this->isExpired($permit)) {
            return $this->renderExpired();
        }
        if (!$this->isAuthedFor($token)) {
            return $this->renderPasswordPrompt($permit, []);
        }

        $subpermit = \modules\bozp\records\SubpermitRecord::findOne(['id' => $id, 'parentPermitId' => $permit->id]);
        if (!$subpermit) {
            throw new \yii\web\NotFoundHttpException('Subpermit not found.');
        }

        /** @var \modules\bozp\Module $module */
        $module = Craft::$app->getModule('bozp');

        if (empty($subpermit->pdfAssetId)) {
            $module->permitPdfService->generateForSubpermit($subpermit, $permit);
            $subpermit = \modules\bozp\records\SubpermitRecord::findOne(['id' => $id]);
        }

        if (empty($subpermit->pdfAssetId)) {
            Craft::$app->getSession()->setError(Craft::t('bozp', 'PDF nie je k dispozícii.'));
            return $this->redirect(UrlHelper::siteUrl('bozp/c/' . $token));
        }

        $asset = Craft::$app->getAssets()->getAssetById((int) $subpermit->pdfAssetId);
        if (!$asset || !$asset->url) {
            Craft::$app->getSession()->setError(Craft::t('bozp', 'PDF súbor nebol nájdený.'));
            return $this->redirect(UrlHelper::siteUrl('bozp/c/' . $token));
        }

        return $this->redirect($asset->url);
    }

    /**
     * GET bozp/c/<token>/control
     * Shows the control-visit form. Requires a logged-in Craft user with
     * bozp:control permission. If not logged in, redirects to bozp/login.
     */
    public function actionControlView(string $token): Response
    {
        if ($redirect = $this->requireControlLogin()) {
            return $redirect;
        }

        $permit = $this->lookupPermit($token);

        $controls = PermitControlRecord::find()
            ->where(['permitId' => $permit->id])
            ->orderBy(['controlledAt' => SORT_DESC])
            ->all();

        $this->view->setTemplateMode(View::TEMPLATE_MODE_SITE);
        return $this->renderTemplate('bozp/site/contractor/control', [
            'permit'   => $permit,
            'token'    => $token,
            'controls' => $controls,
            'errors'   => [],
            'values'   => [],
        ]);
    }

    /**
     * POST bozp/c/<token>/control
     * Saves a control-visit record. Token is read from the POST body (Yii2
     * route params are not bound to POST actions).
     */
    public function actionSaveControl(): Response
    {
        $this->requirePostRequest();

        if ($redirect = $this->requireControlLogin()) {
            return $redirect;
        }

        $token  = Craft::$app->getRequest()->getRequiredBodyParam('token');
        $permit = $this->lookupPermit($token);

        $request = Craft::$app->getRequest();

        $values = [
            'controlledAt' => trim((string) $request->getBodyParam('controlledAt', '')),
            'controllerName' => trim((string) $request->getBodyParam('controllerName', '')),
            'result'       => trim((string) $request->getBodyParam('result', 'ok')),
            'notes'        => trim((string) $request->getBodyParam('notes', '')),
            'signatureData' => (string) $request->getBodyParam('signatureData', ''),
            'signatureDate' => trim((string) $request->getBodyParam('signatureDate', '')),
        ];

        $errors = [];

        if ($values['controlledAt'] === '') {
            $errors['controlledAt'] = (string) Craft::t('bozp', 'Dátum a čas kontroly je povinný.');
        }
        if ($values['controllerName'] === '') {
            $errors['controllerName'] = (string) Craft::t('bozp', 'Meno kontrolóra je povinné.');
        }
        if (!in_array($values['result'], ['ok', 'issues', 'stopped'], true)) {
            $errors['result'] = (string) Craft::t('bozp', 'Vyberte výsledok kontroly.');
        }
        if (!preg_match('#^data:image/png;base64,#', $values['signatureData'])) {
            $errors['signatureData'] = (string) Craft::t('bozp', 'Podpis je povinný.');
        }

        if ($errors !== []) {
            $controls = PermitControlRecord::find()
                ->where(['permitId' => $permit->id])
                ->orderBy(['controlledAt' => SORT_DESC])
                ->all();

            $this->view->setTemplateMode(View::TEMPLATE_MODE_SITE);
            return $this->renderTemplate('bozp/site/contractor/control', [
                'permit'   => $permit,
                'token'    => $token,
                'controls' => $controls,
                'errors'   => $errors,
                'values'   => $values,
            ]);
        }

        try {
            // Save signature PNG as a Craft Asset
            $signatureAssetId = null;
            $pngData = base64_decode(substr($values['signatureData'], strpos($values['signatureData'], ',') + 1));
            if ($pngData !== false && strlen($pngData) >= 100) {
                $volume = Craft::$app->getVolumes()->getVolumeByHandle(self::ASSET_VOLUME_HANDLE);
                $rootFolder = $volume ? Craft::$app->getAssets()->getRootFolderByVolumeId($volume->id) : null;
                if ($volume && $rootFolder) {
                    $tempPath = Craft::$app->getPath()->getTempPath()
                        . '/sig-control-' . bin2hex(random_bytes(8)) . '.png';
                    file_put_contents($tempPath, $pngData);

                    $asset = new \craft\elements\Asset();
                    $asset->tempFilePath = $tempPath;
                    $asset->filename = \craft\helpers\Assets::prepareAssetName(
                        'control-' . $permit->id . '-' . time() . '.png'
                    );
                    $asset->newFolderId = $rootFolder->id;
                    $asset->volumeId = $volume->id;
                    $asset->avoidFilenameConflicts = true;
                    $asset->setScenario(\craft\elements\Asset::SCENARIO_CREATE);

                    if (Craft::$app->getElements()->saveElement($asset)) {
                        $signatureAssetId = (int) $asset->id;
                    }
                }
            }

            $user = Craft::$app->getUser()->getIdentity();

            $control = new PermitControlRecord();
            $control->permitId         = (int) $permit->id;
            $control->controllerUserId = $user ? (int) $user->id : null;
            $control->controllerName   = $values['controllerName'];
            $control->controlledAt     = $values['controlledAt'];
            $control->result           = $values['result'];
            $control->notes            = $values['notes'] !== '' ? $values['notes'] : null;
            $control->signatureAssetId = $signatureAssetId;
            $control->signedAt         = date('Y-m-d H:i:s');
            $control->ipAddress        = Craft::$app->getRequest()->getUserIP();

            if (!$control->save()) {
                throw new \RuntimeException('Control record save failed: ' . print_r($control->getErrors(), true));
            }

            /** @var Module $module */
            $module = Craft::$app->getModule('bozp');
            $module->auditLogger->log(
                permitId: (int) $permit->id,
                userId: $user ? (int) $user->id : null,
                action: 'control_visit',
                note: 'Result: ' . $values['result'] . ($values['notes'] !== '' ? ' — ' . mb_substr($values['notes'], 0, 100) : ''),
            );
            $module->permitMailer->notifyParticipantsOfControl($permit, $control);

            Craft::$app->getSession()->setNotice(
                Craft::t('bozp', 'Kontrola bola zaznamenaná.')
            );
        } catch (Throwable $e) {
            Craft::error('BOZP control save failed: ' . $e->getMessage() . "\n" . $e->getTraceAsString(), __METHOD__);
            Craft::$app->getSession()->setError(
                Craft::t('bozp', 'Uloženie kontroly zlyhalo. Skúste znova.')
            );
        }

        return $this->redirect(UrlHelper::siteUrl('bozp/c/' . $token . '/control'));
    }

    /**
     * Require a logged-in Craft user with bozp:control permission.
     * Returns a redirect Response if the check fails, null if OK.
     */
    private function requireControlLogin(): ?Response
    {
        $userService = Craft::$app->getUser();

        if ($userService->getIsGuest()) {
            $userService->setReturnUrl(Craft::$app->getRequest()->getAbsoluteUrl());
            return $this->redirect(UrlHelper::siteUrl('bozp/login'));
        }

        if (!$userService->checkPermission('bozp:control')) {
            throw new ForbiddenHttpException(
                Craft::t('bozp', 'Nemáte oprávnenie na vykonávanie kontrol.')
            );
        }

        return null;
    }

    /**
     * Pull + validate the four signature-form fields from the request.
     * Returns [values, errors].
     *
     * @return array{0: array<string,string>, 1: array<string,string>}
     */
    private function collectSignatureFields(): array
    {
        $request = Craft::$app->getRequest();
        $values = [
            'signerName' => trim((string) $request->getBodyParam('signerName', '')),
            'signerEmployer' => trim((string) $request->getBodyParam('signerEmployer', '')),
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

    private function signatures(): SignatureService
    {
        /** @var Module $module */
        $module = Craft::$app->getModule('bozp');
        return $module->signatureService;
    }

    // -- internals -------------------------------------------------------

    private function lookupPermit(string $token): PermitRecord
    {
        // Tokens are 48-char URL-safe random strings — anything wildly off
        // doesn't even hit the DB.
        if ($token === '' || strlen($token) > 64) {
            throw new NotFoundHttpException();
        }

        $permit = PermitRecord::findOne(['accessToken' => $token]);
        if (!$permit) {
            throw new NotFoundHttpException();
        }
        return $permit;
    }

    private function isExpired(PermitRecord $permit): bool
    {
        if (empty($permit->accessExpiresAt)) {
            // No expiry stored = treat as expired (safer default).
            return true;
        }
        try {
            $expires = new \DateTimeImmutable((string) $permit->accessExpiresAt);
        } catch (Throwable) {
            return true;
        }
        return $expires <= new \DateTimeImmutable();
    }

    private function verifyPassword(PermitRecord $permit, string $password): bool
    {
        if (empty($permit->accessPasswordHash)) {
            return false;
        }
        return Craft::$app->getSecurity()->validatePassword($password, (string) $permit->accessPasswordHash);
    }

    private function sessionKey(string $token): string
    {
        return 'bozp.contractor.' . $token;
    }

    private function isAuthedFor(string $token): bool
    {
        return (bool) Craft::$app->getSession()->get($this->sessionKey($token));
    }

    private function markAuthed(string $token): void
    {
        Craft::$app->getSession()->set($this->sessionKey($token), true);
    }

    /**
     * @param array<string, string> $errors
     */
    private function renderPasswordPrompt(PermitRecord $permit, array $errors): Response
    {
        $this->view->setTemplateMode(View::TEMPLATE_MODE_SITE);
        return $this->renderTemplate('bozp/site/contractor/password', [
            'permit' => $permit,
            'token' => $permit->accessToken,
            'errors' => $errors,
        ]);
    }

    /**
     * Contractor signs an approved subpermit before starting work.
     * Route: POST bozp/c/<token>/subpermits/<id>/sign
     */
    public function actionSignSubpermit(): Response
    {
        $this->requirePostRequest();

        $token = Craft::$app->getRequest()->getRequiredBodyParam('token');
        $permit = $this->lookupPermit($token);

        if ($this->isExpired($permit)) {
            return $this->renderExpired();
        }

        if (!$this->isAuthedFor($token)) {
            return $this->renderPasswordPrompt($permit, []);
        }

        $request = Craft::$app->getRequest();
        $id = (int) $request->getRequiredBodyParam('id');

        $subpermit = SubpermitRecord::findOne(['id' => $id, 'parentPermitId' => $permit->id]);
        if (!$subpermit) {
            throw new NotFoundHttpException('Subpermit not found.');
        }

        if ($subpermit->status !== 'approved') {
            Craft::$app->getSession()->setError(
                Craft::t('bozp', 'Subpermit musí byť schválený pred podpísom.')
            );
            return $this->redirect(UrlHelper::siteUrl('bozp/c/' . $token));
        }

        /** @var Module $module */
        $module = Craft::$app->getModule('bozp');

        // Both pre-work signatures must exist before closure can be signed.
        if (!$module->subpermitSignatureService->isPreworkComplete($id)) {
            Craft::$app->getSession()->setError(
                Craft::t('bozp', 'Najprv musia byť podpísané obidva podpisy pred začatím prác.')
            );
            return $this->redirect(UrlHelper::siteUrl('bozp/c/' . $token));
        }

        // Check not already signed by contractor
        if ($module->subpermitSignatureService->findSignature($id, SubpermitSignatureService::ROLE_CONTRACTOR)) {
            Craft::$app->getSession()->setError(
                Craft::t('bozp', 'Subpermit bol už podpísaný dodávateľom.')
            );
            return $this->redirect(UrlHelper::siteUrl('bozp/c/' . $token));
        }

        $signatureData = trim((string) $request->getBodyParam('signatureData', ''));
        $signerName = trim((string) $request->getBodyParam('signerName', $permit->contractorPersonName ?? ''));
        $signerEmployer = trim((string) $request->getBodyParam('signerEmployer', $permit->contractorCompany ?? ''));
        $signatureDate = trim((string) $request->getBodyParam('signatureDate', date('Y-m-d')));

        // Closure status (multi-select) + trial-operation flag.
        $closureStatusJson = (string) $request->getBodyParam('closureStatusJson', '');
        $closureStatus = $closureStatusJson !== '' ? (json_decode($closureStatusJson, true) ?: []) : [];
        $closureStatus = is_array($closureStatus) ? array_values(array_filter(array_map('strval', $closureStatus))) : [];

        $allowedClosure = [
            'work_completed',
            'equipment_operational',
            'equipment_not_operational',
            'personnel_and_materials_removed',
            'work_suspended',
        ];
        $closureStatus = array_values(array_intersect($closureStatus, $allowedClosure));

        $requiresTestRun = $request->getBodyParam('requiresTestRun');
        if (!in_array($requiresTestRun, ['yes', 'no'], true)) {
            $requiresTestRun = null;
        }

        if ($signatureData === '' || !str_starts_with($signatureData, 'data:image/png;base64,')) {
            Craft::$app->getSession()->setError(Craft::t('bozp', 'Podpis dodávateľa je povinný.'));
            return $this->redirect(UrlHelper::siteUrl('bozp/c/' . $token));
        }
        if ($signerName === '') {
            Craft::$app->getSession()->setError(Craft::t('bozp', 'Meno podpisujúceho je povinné.'));
            return $this->redirect(UrlHelper::siteUrl('bozp/c/' . $token));
        }
        if (count($closureStatus) === 0) {
            Craft::$app->getSession()->setError(Craft::t('bozp', 'Vyberte aspoň jeden stav po dokončení.'));
            return $this->redirect(UrlHelper::siteUrl('bozp/c/' . $token));
        }
        if ($requiresTestRun === null) {
            Craft::$app->getSession()->setError(Craft::t('bozp', 'Označte, či sa vyžaduje skúšobná prevádzka.'));
            return $this->redirect(UrlHelper::siteUrl('bozp/c/' . $token));
        }

        // Hot work + confined space require at least one contractor attachment
        // on the parent permit before closure can be signed.
        if (in_array($subpermit->type, ['hot_work', 'confined_space'], true)) {
            $attachmentCount = (int) PermitAttachmentRecord::find()
                ->where(['permitId' => $permit->id, 'attachmentType' => 'contractor_upload'])
                ->count();
            if ($attachmentCount < 1) {
                Craft::$app->getSession()->setError(
                    Craft::t('bozp', 'Pred uzavretím tohto subpermitu je potrebné nahrať aspoň jednu prílohu.')
                );
                return $this->redirect(UrlHelper::siteUrl('bozp/c/' . $token));
            }
        }

        try {
            $module->subpermitSignatureService->capture(
                $subpermit,
                SubpermitSignatureService::ROLE_CONTRACTOR,
                $signerName,
                $signerEmployer ?: null,
                $signatureDate,
                $signatureData,
            );

            // Persist closure status and trial-operation flag into subpermit.data
            $data = is_string($subpermit->data)
                ? (json_decode($subpermit->data, true) ?? [])
                : (is_array($subpermit->data) ? $subpermit->data : []);
            $data['closureStatus']   = $closureStatus;
            $data['requiresTestRun'] = $requiresTestRun;
            $subpermit->data = json_encode($data);
            if (!$subpermit->save()) {
                throw new \RuntimeException('Subpermit data save failed: ' . print_r($subpermit->getErrors(), true));
            }

            // Regenerate PDF so closure info appears
            $module->permitPdfService->generateForSubpermit($subpermit, $permit);
        } catch (Throwable $e) {
            Craft::error('Subpermit contractor sign failed: ' . $e->getMessage(), __METHOD__);
            Craft::$app->getSession()->setError(Craft::t('bozp', 'Podpis sa nepodarilo uložiť. Skúste znova.'));
            return $this->redirect(UrlHelper::siteUrl('bozp/c/' . $token));
        }

        Craft::$app->getSession()->setNotice(Craft::t('bozp', 'Subpermit bol podpísaný.'));
        return $this->redirect(UrlHelper::siteUrl('bozp/c/' . $token));
    }

    // ------------------------------------------------------------------
    // Contractor pre-work signature — signed BEFORE HSE approval, AFTER
    // issuer pre-work. HSE approval is gated on both preworks.
    // ------------------------------------------------------------------
    public function actionSignSubpermitPrework(): Response
    {
        $this->requirePostRequest();

        $token  = Craft::$app->getRequest()->getRequiredBodyParam('token');
        $permit = $this->lookupPermit($token);

        if ($this->isExpired($permit)) {
            return $this->renderExpired();
        }
        if (!$this->isAuthedFor($token)) {
            return $this->renderPasswordPrompt($permit, []);
        }

        $request = Craft::$app->getRequest();
        $id      = (int) $request->getRequiredBodyParam('id');

        $subpermit = SubpermitRecord::findOne(['id' => $id, 'parentPermitId' => $permit->id]);
        if (!$subpermit) {
            throw new NotFoundHttpException('Subpermit not found.');
        }

        if ($subpermit->status !== 'pending') {
            Craft::$app->getSession()->setError(
                Craft::t('bozp', 'Predpracovný podpis je možný len pred schválením.')
            );
            return $this->redirect(UrlHelper::siteUrl('bozp/c/' . $token));
        }

        /** @var Module $module */
        $module = Craft::$app->getModule('bozp');

        // Issuer must have signed pre-work first
        $issuerPrework = $module->subpermitSignatureService->findSignature(
            $id,
            SubpermitSignatureService::ROLE_ISSUER_PREWORK
        );
        if (!$issuerPrework) {
            Craft::$app->getSession()->setError(
                Craft::t('bozp', 'Vydavateľ musí najprv podpísať pred začatím prác.')
            );
            return $this->redirect(UrlHelper::siteUrl('bozp/c/' . $token));
        }

        // Already signed?
        if ($module->subpermitSignatureService->findSignature($id, SubpermitSignatureService::ROLE_CONTRACTOR_PREWORK)) {
            Craft::$app->getSession()->setNotice(
                Craft::t('bozp', 'Predpracovný podpis dodávateľa už bol zaznamenaný.')
            );
            return $this->redirect(UrlHelper::siteUrl('bozp/c/' . $token));
        }

        $signatureData  = trim((string) $request->getBodyParam('signatureData', ''));
        $signerName     = trim((string) $request->getBodyParam('signerName', $permit->contractorPersonName ?? ''));
        $signerEmployer = trim((string) $request->getBodyParam('signerEmployer', $permit->contractorCompany ?? ''));
        $signatureDate  = trim((string) $request->getBodyParam('signatureDate', date('Y-m-d')));

        if ($signerName === '' || !str_starts_with($signatureData, 'data:image/png;base64,')) {
            Craft::$app->getSession()->setError(
                Craft::t('bozp', 'Podpis a meno sú povinné.')
            );
            return $this->redirect(UrlHelper::siteUrl('bozp/c/' . $token));
        }

        try {
            $module->subpermitSignatureService->capture(
                $subpermit,
                SubpermitSignatureService::ROLE_CONTRACTOR_PREWORK,
                $signerName,
                $signerEmployer ?: null,
                $signatureDate,
                $signatureData,
            );
            $module->permitPdfService->generateForSubpermit($subpermit, $permit);
        } catch (Throwable $e) {
            Craft::error('Subpermit prework sign failed: ' . $e->getMessage(), __METHOD__);
            Craft::$app->getSession()->setError(Craft::t('bozp', 'Podpis sa nepodarilo uložiť. Skúste znova.'));
            return $this->redirect(UrlHelper::siteUrl('bozp/c/' . $token));
        }

        Craft::$app->getSession()->setNotice(
            Craft::t('bozp', 'Predpracovný podpis dodávateľa bol zaznamenaný.')
        );
        return $this->redirect(UrlHelper::siteUrl('bozp/c/' . $token));
    }

    /**
     * @param array<string, string> $closeErrors
     * @param array<string, mixed>  $closeValues
     * @param array<string, string> $cancelErrors
     * @param array<string, mixed>  $cancelValues
     */
    private function renderDetail(
        PermitRecord $permit,
        array $closeErrors = [],
        array $closeValues = [],
        array $cancelErrors = [],
        array $cancelValues = [],
    ): Response {
        $this->view->setTemplateMode(View::TEMPLATE_MODE_SITE);

        // Single zone (FK on permit). Wrapped in an array so templates that
        // iterate {% for zone in zones %} keep working.
        $zones = [];
        if (!empty($permit->zoneId)) {
            $zone = ZoneRecord::findOne(['id' => (int) $permit->zoneId]);
            if ($zone) {
                $zones[] = $zone;
            }
        }

        $hazards = [];
        foreach (PermitHazardRecord::find()->where(['permitId' => $permit->id])->all() as $row) {
            $hazards[$row->hazardKey] = $row;
        }

        $attachments = PermitAttachmentRecord::find()
            ->where(['permitId' => $permit->id, 'attachmentType' => 'contractor_upload'])
            ->orderBy(['dateCreated' => SORT_DESC])
            ->all();

        $recipientClosure = $this->signatures()->findSignature((int) $permit->id, SignatureRole::RecipientClosure);

        // Load subpermits the contractor needs to see. Under the new workflow
        // they sign pre-work BEFORE HSE approval, so 'pending' rows must be
        // visible too. Cancelled / rejected / expired stay hidden.
        $subpermits = SubpermitRecord::find()
            ->where(['parentPermitId' => $permit->id])
            ->andWhere(['in', 'status', ['pending', 'approved']])
            ->orderBy(['dateCreated' => SORT_ASC])
            ->all();

        /** @var Module $module */
        $module = Craft::$app->getModule('bozp');
        $subpermitSignatures = [];
        $allSubpermitsSigned = true;
        foreach ($subpermits as $sp) {
            $sigs = $module->subpermitSignatureService->findAllForSubpermit((int) $sp->id);
            $subpermitSignatures[(int) $sp->id] = $sigs;
            // Parent permit closure requires every subpermit fully closed:
            // both contractor closure AND issuer closure signatures present.
            if (
                !isset($sigs[SubpermitSignatureService::ROLE_CONTRACTOR])
                || !isset($sigs[SubpermitSignatureService::ROLE_ISSUER_CLOSURE])
            ) {
                $allSubpermitsSigned = false;
            }
        }

        $controls = PermitControlRecord::find()
            ->where(['permitId' => $permit->id])
            ->orderBy(['controlledAt' => SORT_DESC])
            ->all();

        $defaultSign = [
            'signerName' => $permit->contractorPersonName ?: '',
            'signerEmployer' => $permit->contractorCompany ?: '',
            'signatureDate' => date('Y-m-d'),
        ];

        $actionable = in_array($permit->status, [
            PermitStatus::Approved->value,
            PermitStatus::Signed->value,
            PermitStatus::Active->value,
        ], true);

        return $this->renderTemplate('bozp/site/contractor/detail', [
            'permit' => $permit,
            'token' => $permit->accessToken,
            'zones' => $zones,
            'hazards' => $hazards,
            'hazardCategories' => HazardCategory::pdfOrder(),
            'attachments' => $attachments,
            'recipientClosureSignature' => $recipientClosure,
            'canClose' => $actionable && !$recipientClosure && $allSubpermitsSigned,
            'canCancel' => $actionable && !$recipientClosure,
            'allSubpermitsSigned' => $allSubpermitsSigned,
            'unsignedSubpermitCount' => count(array_filter(
                $subpermits,
                fn($sp) => !isset($subpermitSignatures[(int)$sp->id][SubpermitSignatureService::ROLE_CONTRACTOR])
                       || !isset($subpermitSignatures[(int)$sp->id][SubpermitSignatureService::ROLE_ISSUER_CLOSURE])
            )),
            'closeErrors' => $closeErrors,
            'closeValues' => array_merge($defaultSign, ['closureStatus' => []], $closeValues),
            'cancelErrors' => $cancelErrors,
            'cancelValues' => array_merge($defaultSign, ['reason' => ''], $cancelValues),
            'subpermits' => $subpermits,
            'subpermitSignatures' => $subpermitSignatures,
            'subpermitTypes' => SubpermitType::cases(),
            'controls' => $controls,
        ]);
    }

    private function renderExpired(): Response
    {
        $this->view->setTemplateMode(View::TEMPLATE_MODE_SITE);
        Craft::$app->getResponse()->setStatusCode(410);
        return $this->renderTemplate('bozp/site/contractor/expired');
    }
}
