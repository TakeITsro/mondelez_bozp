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
use modules\bozp\Module;
use modules\bozp\records\PermitAttachmentRecord;
use modules\bozp\records\PermitHazardRecord;
use modules\bozp\records\PermitRecord;
use modules\bozp\records\PermitZoneRecord;
use modules\bozp\records\ZoneRecord;
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

    private function renderDetail(PermitRecord $permit): Response
    {
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

        return $this->renderTemplate('bozp/site/contractor/detail', [
            'permit' => $permit,
            'token' => $permit->accessToken,
            'zones' => $zones,
            'hazards' => $hazards,
            'hazardCategories' => HazardCategory::pdfOrder(),
            'attachments' => $attachments,
        ]);
    }

    private function renderExpired(): Response
    {
        $this->view->setTemplateMode(View::TEMPLATE_MODE_SITE);
        Craft::$app->getResponse()->setStatusCode(410);
        return $this->renderTemplate('bozp/site/contractor/expired');
    }
}
