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
use modules\bozp\Module;
use modules\bozp\records\PermitAttachmentRecord;
use modules\bozp\records\PermitHazardRecord;
use modules\bozp\records\PermitRecord;
use modules\bozp\records\PermitSignatureRecord;
use modules\bozp\records\PermitZoneRecord;
use modules\bozp\records\ZoneRecord;
use modules\bozp\services\SignatureService;
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

    protected array|bool|int $allowAnonymous = true;
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

        $zoneIds = PermitZoneRecord::find()
            ->select(['zoneId'])
            ->where(['permitId' => $permit->id])
            ->orderBy(['sortOrder' => SORT_ASC])
            ->column();
        $zones = $zoneIds
            ? ZoneRecord::find()->where(['id' => $zoneIds])->all()
            : [];

        $hazards = [];
        foreach (PermitHazardRecord::find()->where(['permitId' => $permit->id])->all() as $row) {
            $hazards[$row->hazardKey] = $row;
        }

        $attachments = PermitAttachmentRecord::find()
            ->where(['permitId' => $permit->id, 'attachmentType' => 'contractor_upload'])
            ->orderBy(['dateCreated' => SORT_DESC])
            ->all();

        $recipientClosure = $this->signatures()->findSignature((int) $permit->id, SignatureRole::RecipientClosure);

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
            'canClose' => $actionable && !$recipientClosure,
            'canCancel' => $actionable && !$recipientClosure,
            'closeErrors' => $closeErrors,
            'closeValues' => array_merge($defaultSign, ['closureStatus' => []], $closeValues),
            'cancelErrors' => $cancelErrors,
            'cancelValues' => array_merge($defaultSign, ['reason' => ''], $cancelValues),
        ]);
    }

    private function renderExpired(): Response
    {
        $this->view->setTemplateMode(View::TEMPLATE_MODE_SITE);
        Craft::$app->getResponse()->setStatusCode(410);
        return $this->renderTemplate('bozp/site/contractor/expired');
    }
}
