<?php

declare(strict_types=1);

namespace modules\bozp\services;

use Craft;
use craft\elements\Asset;
use craft\helpers\Assets;
use modules\bozp\controllers\ContractorController;
use modules\bozp\records\SubpermitRecord;
use modules\bozp\records\SubpermitSignatureRecord;
use yii\base\Component;
use yii\web\Request;

/**
 * Captures and retrieves drawn signatures for subpermits.
 *
 * Roles: 'issuer' (signed at creation) | 'contractor' (signed before work starts)
 *
 * Signature data is expected as a "data:image/png;base64,..." URI.
 */
class SubpermitSignatureService extends Component
{
    public const ROLE_ISSUER     = 'issuer';
    public const ROLE_CONTRACTOR = 'contractor';

    /**
     * Decode the data URI, save the PNG as an Asset, write the
     * SubpermitSignatureRecord row, return the saved record.
     *
     * @throws \RuntimeException on any failure.
     */
    public function capture(
        SubpermitRecord $subpermit,
        string $role,
        string $signerName,
        ?string $signerEmployer,
        string $signatureDate,
        string $dataUri,
        ?Request $request = null,
    ): SubpermitSignatureRecord {
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
            'signature-subpermit-' . $subpermit->id . '-' . $role . '.png'
        );
        $asset->newFolderId = $rootFolder->id;
        $asset->volumeId = $volume->id;
        $asset->avoidFilenameConflicts = true;
        $asset->setScenario(Asset::SCENARIO_CREATE);

        if (!Craft::$app->getElements()->saveElement($asset)) {
            throw new \RuntimeException('Asset save failed: ' . print_r($asset->getErrors(), true));
        }

        $req = $request ?? Craft::$app->getRequest();

        $sig = new SubpermitSignatureRecord();
        $sig->subpermitId = (int) $subpermit->id;
        $sig->role = $role;
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

    public function findSignature(int $subpermitId, string $role): ?SubpermitSignatureRecord
    {
        return SubpermitSignatureRecord::find()
            ->where(['subpermitId' => $subpermitId, 'role' => $role])
            ->one();
    }

    /**
     * @return SubpermitSignatureRecord[] keyed by role
     */
    public function findAllForSubpermit(int $subpermitId): array
    {
        $rows = SubpermitSignatureRecord::find()
            ->where(['subpermitId' => $subpermitId])
            ->all();

        $out = [];
        foreach ($rows as $row) {
            $out[$row->role] = $row;
        }
        return $out;
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
