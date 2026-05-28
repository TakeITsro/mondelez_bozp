<?php

declare(strict_types=1);

namespace modules\bozp\services;

use Craft;
use craft\elements\Asset;
use craft\helpers\Assets;
use craft\helpers\UrlHelper;
use craft\web\View;
use modules\bozp\controllers\ContractorController;
use modules\bozp\enums\SubpermitSigningRole;
use modules\bozp\enums\SubpermitStatus;
use modules\bozp\enums\SubpermitType;
use modules\bozp\records\PermitRecord;
use modules\bozp\records\SubpermitRecord;
use modules\bozp\records\SubpermitSigningRequestRecord;
use Throwable;
use yii\base\Component;

/**
 * SubpermitSigningService
 *
 * Handles the multi-signer token workflow for high-risk subpermits:
 *   1. createRequestsForSubpermit() — create rows, send invitation emails.
 *   2. processSignature()           — validate token, save signature, auto-transition when done.
 */
class SubpermitSigningService extends Component
{
    /**
     * Create one signing request per provided email, flip the subpermit to
     * `pending_signatures`, and dispatch invitation emails.
     *
     * Roles flagged `isRequired()` AND in the subpermit type's roster must
     * have a non-empty email or a RuntimeException is thrown. Roles outside
     * the type's roster are ignored even if marked required globally.
     *
     * @param array<string, string> $emails  role value => email address (empty values silently skipped)
     * @throws \RuntimeException if a required role for this type has no email
     */
    public function createRequestsForSubpermit(
        SubpermitRecord $subpermit,
        PermitRecord $permit,
        array $emails,
    ): void {
        // Narrow required-role validation to roles relevant to this subpermit type.
        $subpermitType = SubpermitType::tryFrom($subpermit->type);
        $typeRoles = $subpermitType ? SubpermitSigningRole::forType($subpermitType) : [];
        foreach ($typeRoles as $role) {
            if ($role->isRequired() && empty(trim($emails[$role->value] ?? ''))) {
                throw new \RuntimeException(
                    "Required signing role '{$role->value}' has no email address."
                );
            }
        }

        $created = [];

        foreach ($emails as $roleValue => $email) {
            $email = trim($email);
            if ($email === '') {
                continue;
            }
            $role = SubpermitSigningRole::tryFrom($roleValue);
            if ($role === null) {
                continue; // unknown role value — skip
            }

            $token = bin2hex(random_bytes(32)); // 64-char hex, cryptographically random

            $req = new SubpermitSigningRequestRecord();
            $req->subpermitId = (int) $subpermit->id;
            $req->role        = $roleValue;
            $req->signerEmail = $email;
            $req->token       = $token;

            if (!$req->save()) {
                throw new \RuntimeException(
                    "Signing request save failed (role={$roleValue}): " . print_r($req->getErrors(), true)
                );
            }

            $created[] = ['role' => $role, 'record' => $req];
        }

        if (empty($created)) {
            return;
        }

        // Flip subpermit status — it will return to `pending` once all sign
        $subpermit->status = SubpermitStatus::PendingSignatures->value;
        $subpermit->save(false);

        // Send an invitation email to each signer
        foreach ($created as $item) {
            $this->sendInvitation($item['record'], $item['role'], $subpermit, $permit);
        }
    }

    /**
     * Validate the token, save the drawn signature, and auto-transition the
     * subpermit to `pending` once all requests are signed.
     *
     * @throws \RuntimeException on any failure
     */
    public function processSignature(
        string $token,
        string $signerName,
        string $dataUri,
        string $ipAddress,
        ?string $jobTitle = null,
    ): SubpermitSigningRequestRecord {
        $request = $this->findByToken($token);
        if (!$request) {
            throw new \RuntimeException('Invalid signing token.');
        }
        if ($request->signedAt !== null) {
            throw new \RuntimeException('This signing request has already been completed.');
        }
        if ($signerName === '') {
            throw new \RuntimeException('Signer name is required.');
        }

        $assetId = $this->saveSignatureAsset($request, $dataUri);

        $request->signerName       = $signerName;
        $request->jobTitle         = $jobTitle !== null && $jobTitle !== '' ? $jobTitle : null;
        $request->signatureAssetId = $assetId;
        $request->signedAt         = date('Y-m-d H:i:s');
        $request->ipAddress        = substr($ipAddress, 0, 45) ?: null;

        if (!$request->save()) {
            throw new \RuntimeException(
                'Signing request update failed: ' . print_r($request->getErrors(), true)
            );
        }

        $this->checkAndTransition((int) $request->subpermitId);

        return $request;
    }

    public function findByToken(string $token): ?SubpermitSigningRequestRecord
    {
        return SubpermitSigningRequestRecord::find()
            ->where(['token' => $token])
            ->one();
    }

    /**
     * @return SubpermitSigningRequestRecord[]
     */
    public function findAllForSubpermit(int $subpermitId): array
    {
        return SubpermitSigningRequestRecord::find()
            ->where(['subpermitId' => $subpermitId])
            ->orderBy(['dateCreated' => SORT_ASC])
            ->all();
    }

    // -------------------------------------------------------------------------
    // Internals
    // -------------------------------------------------------------------------

    /**
     * If every signing request for the subpermit is now signed, flip it to
     * `pending` so the HSE officer can review and approve.
     */
    private function checkAndTransition(int $subpermitId): void
    {
        $total = (int) SubpermitSigningRequestRecord::find()
            ->where(['subpermitId' => $subpermitId])
            ->count();

        $signed = (int) SubpermitSigningRequestRecord::find()
            ->where(['subpermitId' => $subpermitId])
            ->andWhere(['not', ['signedAt' => null]])
            ->count();

        if ($total > 0 && $total === $signed) {
            SubpermitRecord::updateAll(
                ['status' => SubpermitStatus::Pending->value],
                ['id' => $subpermitId],
            );

            // Regenerate PDF to include all signatures
            $subpermit = SubpermitRecord::findOne(['id' => $subpermitId]);
            $permit = $subpermit
                ? \modules\bozp\records\PermitRecord::findOne(['id' => $subpermit->parentPermitId])
                : null;
            if ($subpermit && $permit) {
                /** @var \modules\bozp\Module $module */
                $module = Craft::$app->getModule('bozp');
                $module->permitPdfService->generateForSubpermit($subpermit, $permit);
            }
        }
    }

    /**
     * Decode the data URI and save the PNG as a Craft Asset.
     * Returns the asset ID, or null if anything fails (non-fatal).
     */
    private function saveSignatureAsset(SubpermitSigningRequestRecord $request, string $dataUri): ?int
    {
        if (!preg_match('#^data:image/png;base64,(.+)$#s', $dataUri, $m)) {
            return null;
        }
        $pngBytes = base64_decode($m[1], true);
        if ($pngBytes === false || strlen($pngBytes) < 100) {
            return null;
        }

        $volume     = Craft::$app->getVolumes()->getVolumeByHandle(ContractorController::ASSET_VOLUME_HANDLE);
        $rootFolder = $volume ? Craft::$app->getAssets()->getRootFolderByVolumeId($volume->id) : null;
        if (!$volume || !$rootFolder) {
            Craft::warning('BOZP: bozpAttachments volume not found — signature not saved as asset.', __METHOD__);
            return null;
        }

        $tempPath = Craft::$app->getPath()->getTempPath()
            . '/sig-spr-' . bin2hex(random_bytes(8)) . '.png';
        file_put_contents($tempPath, $pngBytes);

        $asset = new Asset();
        $asset->tempFilePath           = $tempPath;
        $asset->filename               = Assets::prepareAssetName(
            'sp-sign-' . $request->subpermitId . '-' . $request->role . '.png'
        );
        $asset->newFolderId            = $rootFolder->id;
        $asset->volumeId               = $volume->id;
        $asset->avoidFilenameConflicts = true;
        $asset->setScenario(Asset::SCENARIO_CREATE);

        if (!Craft::$app->getElements()->saveElement($asset)) {
            Craft::error('BOZP: signature asset save failed: ' . print_r($asset->getErrors(), true), __METHOD__);
            return null;
        }

        return (int) $asset->id;
    }

    /**
     * Send the signing invitation email to one signer.
     * Failures are logged but never bubble up — a mailer issue must not
     * prevent the subpermit from being created.
     */
    private function sendInvitation(
        SubpermitSigningRequestRecord $request,
        SubpermitSigningRole $role,
        SubpermitRecord $subpermit,
        PermitRecord $permit,
    ): void {
        $signingUrl       = UrlHelper::siteUrl('bozp/sp-sign/' . $request->token);
        $language         = Craft::$app->getSites()->getPrimarySite()->language;
        $previousLanguage = Craft::$app->language;
        $view             = Craft::$app->getView();
        $previousMode     = $view->getTemplateMode();

        try {
            Craft::$app->language = $language;
            $view->setTemplateMode(View::TEMPLATE_MODE_SITE);

            $subject = (string) Craft::t('bozp', 'Vyžaduje sa váš podpis — subpermit k povoleniu {n}', [
                'n' => $permit->permitNumber,
            ]);

            $html = $view->renderTemplate('bozp/email/subpermit-sign-request', [
                'permit'     => $permit,
                'subpermit'  => $subpermit,
                'role'       => $role,
                'signingUrl' => $signingUrl,
            ]);

            Craft::$app->getMailer()
                ->compose()
                ->setTo($request->signerEmail)
                ->setSubject($subject)
                ->setHtmlBody($html)
                ->send();
        } catch (Throwable $e) {
            Craft::error(
                "BOZP SubpermitSigning: invitation email to {$request->signerEmail} failed: " . $e->getMessage(),
                __METHOD__,
            );
        } finally {
            Craft::$app->language = $previousLanguage;
            $view->setTemplateMode($previousMode);
        }
    }
}
