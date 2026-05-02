<?php

declare(strict_types=1);

namespace modules\bozp\services;

use Craft;
use craft\elements\Asset;
use craft\helpers\Assets;
use modules\bozp\controllers\ContractorController;
use modules\bozp\enums\SignatureRole;
use modules\bozp\records\PermitRecord;
use modules\bozp\records\PermitSignatureRecord;
use yii\base\Component;
use yii\web\Request;

/**
 * SignatureService
 *
 * Centralizes the capture of drawn signatures (PNG via signature_pad)
 * for the four signature slots on a permit. Reused by both
 * ContractorController (recipient roles) and PermitsController
 * (issuer roles). Throws on any validation / storage failure so
 * callers can wrap the whole action in one try/catch and roll back
 * the surrounding state transition.
 *
 * Signature data is expected as a "data:image/png;base64,..." URI.
 */
class SignatureService extends Component
{
    /**
     * Decode the data URI, save the PNG as an Asset, write the
     * PermitSignatureRecord row, return the saved record.
     *
     * @throws \RuntimeException on any failure (no row created).
     */
    public function capture(
        PermitRecord $permit,
        SignatureRole $role,
        string $signerName,
        ?string $signerEmployer,
        string $signatureDate,
        string $dataUri,
        ?Request $request = null,
    ): PermitSignatureRecord {
        $pngBytes = $this->decodeDataUri($dataUri);
        if ($pngBytes === null || strlen($pngBytes) < 100) {
            throw new \RuntimeException('Invalid signature data.');
        }

        $volume = Craft::$app->getVolumes()->getVolumeByHandle(ContractorController::ASSET_VOLUME_HANDLE);
        if (!$volume) {
            throw new \RuntimeException("Missing asset volume '" . ContractorController::ASSET_VOLUME_HANDLE . "'");
        }

        $rootFolder = Craft::$app->getAssets()->getRootFolderByVolumeId($volume->id);
        if (!$rootFolder) {
            throw new \RuntimeException("No root folder for volume '" . ContractorController::ASSET_VOLUME_HANDLE . "'");
        }

        $tempPath = Craft::$app->getPath()->getTempPath() . '/sig-' . bin2hex(random_bytes(8)) . '.png';
        file_put_contents($tempPath, $pngBytes);

        $asset = new Asset();
        $asset->tempFilePath = $tempPath;
        $asset->filename = Assets::prepareAssetName(
            'signature-' . $permit->permitNumber . '-' . $role->value . '.png'
        );
        $asset->newFolderId = $rootFolder->id;
        $asset->volumeId = $volume->id;
        $asset->avoidFilenameConflicts = true;
        $asset->setScenario(Asset::SCENARIO_CREATE);

        if (!Craft::$app->getElements()->saveElement($asset)) {
            throw new \RuntimeException('Asset save failed: ' . print_r($asset->getErrors(), true));
        }

        $req = $request ?? Craft::$app->getRequest();

        $sig = new PermitSignatureRecord();
        $sig->permitId = (int) $permit->id;
        $sig->role = $role->value;
        $sig->signerName = $signerName;
        $sig->signerEmployer = $signerEmployer !== null && $signerEmployer !== '' ? $signerEmployer : null;
        $sig->signatureAssetId = (int) $asset->id;
        $sig->signatureDate = $signatureDate;
        $sig->signedAt = date('Y-m-d H:i:s');
        $sig->ipAddress = substr((string) ($req->getUserIP() ?? ''), 0, 45) ?: null;
        $sig->userAgent = substr((string) ($req->getUserAgent() ?? ''), 0, 255) ?: null;

        if (!$sig->save()) {
            throw new \RuntimeException('Signature save failed: ' . print_r($sig->getErrors(), true));
        }

        return $sig;
    }

    public function findSignature(int $permitId, SignatureRole $role): ?PermitSignatureRecord
    {
        return PermitSignatureRecord::find()
            ->where(['permitId' => $permitId, 'role' => $role->value])
            ->one();
    }

    private function decodeDataUri(string $uri): ?string
    {
        if (!preg_match('#^data:image/png;base64,(.+)$#', $uri, $m)) {
            return null;
        }
        $bytes = base64_decode(strtr($m[1], "\r\n\t ", ''), true);
        return $bytes === false ? null : $bytes;
    }
}
