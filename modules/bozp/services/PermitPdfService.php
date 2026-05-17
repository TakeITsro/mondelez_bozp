<?php

declare(strict_types=1);

namespace modules\bozp\services;

use Craft;
use craft\elements\Asset;
use craft\elements\User;
use craft\helpers\Assets as AssetsHelper;
use craft\web\View;
use modules\bozp\enums\HazardCategory;
use modules\bozp\enums\SignatureRole;
use modules\bozp\enums\SubpermitType;
use modules\bozp\records\AuditLogRecord;
use modules\bozp\records\PermitControlRecord;
use modules\bozp\records\PermitHazardRecord;
use modules\bozp\records\PermitRecord;
use modules\bozp\records\PermitSignatureRecord;
use modules\bozp\records\SubpermitRecord;
use modules\bozp\records\SubpermitSignatureRecord;
use modules\bozp\records\SubpermitSigningRequestRecord;
use modules\bozp\records\ZoneRecord;
use yii\base\Component;

/**
 * PermitPdfService
 *
 * Generates A4 PDF documents for permits and subpermits using mPDF.
 * On regeneration the existing asset's file content is replaced in place,
 * so the asset ID, filename, and public URL remain constant across updates.
 * Only the latest version (matching the current status + signatures) is stored.
 *
 * Requires: composer require mpdf/mpdf
 *
 * @property-read \Mpdf\Mpdf|null $mpdf
 */
class PermitPdfService extends Component
{
    private const VOLUME_HANDLE = 'bozpAttachments';

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Generate (or re-generate) the PDF for a general permit.
     * Silently logs errors — never throws, so callers don't need a wrapper.
     */
    public function generateForPermit(PermitRecord $permit): void
    {
        try {
            $html = $this->renderPermitHtml($permit);
            $pdfBytes = $this->htmlToPdf($html);
            $filename = 'permit-' . preg_replace('/[^A-Za-z0-9\-_]/', '-', (string) $permit->permitNumber) . '.pdf';
            $oldAssetId = isset($permit->pdfAssetId) ? (int) $permit->pdfAssetId ?: null : null;
            $assetId = $this->saveAsAsset($pdfBytes, $filename, $oldAssetId);
            PermitRecord::updateAll(['pdfAssetId' => $assetId], ['id' => $permit->id]);
            $permit->pdfAssetId = $assetId;
        } catch (\Throwable $e) {
            Craft::error(
                'BOZP PDF [permit ' . $permit->id . ']: ' . $e->getMessage() . "\n" . $e->getTraceAsString(),
                __METHOD__,
            );
        }
    }

    /**
     * Generate (or re-generate) the PDF for a subpermit.
     * Silently logs errors.
     */
    public function generateForSubpermit(SubpermitRecord $subpermit, PermitRecord $permit): void
    {
        try {
            $html = $this->renderSubpermitHtml($subpermit, $permit);
            $pdfBytes = $this->htmlToPdf($html);
            $safe = preg_replace('/[^A-Za-z0-9\-_]/', '-', (string) $permit->permitNumber);
            $filename = 'subpermit-' . $safe . '-' . $subpermit->type . '-' . $subpermit->id . '.pdf';
            $oldAssetId = isset($subpermit->pdfAssetId) ? (int) $subpermit->pdfAssetId ?: null : null;
            $assetId = $this->saveAsAsset($pdfBytes, $filename, $oldAssetId);
            SubpermitRecord::updateAll(['pdfAssetId' => $assetId], ['id' => $subpermit->id]);
            $subpermit->pdfAssetId = $assetId;
        } catch (\Throwable $e) {
            Craft::error(
                'BOZP PDF [subpermit ' . $subpermit->id . ']: ' . $e->getMessage(),
                __METHOD__,
            );
        }
    }

    /**
     * Return the public URL for the stored permit PDF, or null if none exists.
     */
    public function getPermitPdfUrl(PermitRecord $permit): ?string
    {
        if (empty($permit->pdfAssetId)) {
            return null;
        }
        $asset = Craft::$app->getAssets()->getAssetById((int) $permit->pdfAssetId);
        return $asset?->url;
    }

    /**
     * Return the public URL for the stored subpermit PDF, or null if none exists.
     */
    public function getSubpermitPdfUrl(SubpermitRecord $subpermit): ?string
    {
        if (empty($subpermit->pdfAssetId)) {
            return null;
        }
        $asset = Craft::$app->getAssets()->getAssetById((int) $subpermit->pdfAssetId);
        return $asset?->url;
    }

    // -------------------------------------------------------------------------
    // HTML rendering
    // -------------------------------------------------------------------------

    private function renderPermitHtml(PermitRecord $permit): string
    {
        // Zones
        $zoneIds = (new \yii\db\Query())
            ->select('zoneId')
            ->from('{{%bozp_permit_zones}}')
            ->where(['permitId' => $permit->id])
            ->column();
        $zones = $zoneIds
            ? ZoneRecord::find()->where(['id' => $zoneIds])->orderBy(['sortOrder' => SORT_ASC])->all()
            : [];

        // Hazards
        $hazardRows = PermitHazardRecord::find()
            ->where(['permitId' => $permit->id])
            ->orderBy(['sortOrder' => SORT_ASC])
            ->all();
        $hazards = [];
        foreach ($hazardRows as $row) {
            $hazards[$row->hazardKey] = $row;
        }

        // Signatures
        $sigRows = PermitSignatureRecord::find()
            ->where(['permitId' => $permit->id])
            ->all();
        $signatures = [];
        foreach ($sigRows as $row) {
            $signatures[$row->role] = $row;
        }

        // Signature images as data URIs
        $sigImages = [];
        foreach ($signatures as $role => $sig) {
            if (!empty($sig->signatureAssetId)) {
                $sigImages[$role] = $this->assetToDataUri((int) $sig->signatureAssetId);
            }
        }

        // Users
        $issuer = $permit->issuerId ? User::find()->id($permit->issuerId)->one() : null;
        $approver = $permit->approverId ? User::find()->id($permit->approverId)->one() : null;

        // Subpermits
        $subpermits = SubpermitRecord::find()
            ->where(['parentPermitId' => $permit->id])
            ->orderBy(['dateCreated' => SORT_ASC])
            ->all();

        // Controls
        $controls = PermitControlRecord::find()
            ->where(['permitId' => $permit->id])
            ->orderBy(['controlledAt' => SORT_ASC])
            ->all();

        // Control signature images
        $controlSigImages = [];
        foreach ($controls as $c) {
            if (!empty($c->signatureAssetId)) {
                $controlSigImages[(int) $c->id] = $this->assetToDataUri((int) $c->signatureAssetId);
            }
        }

        $view = Craft::$app->getView();
        $oldMode = $view->getTemplateMode();
        $view->setTemplateMode(View::TEMPLATE_MODE_SITE);

        $html = $view->renderTemplate('bozp/pdf/permit', [
            'permit'           => $permit,
            'zones'            => $zones,
            'hazards'          => $hazards,
            'hazardCategories' => HazardCategory::pdfOrder(),
            'signatures'       => $signatures,
            'sigImages'        => $sigImages,
            'issuer'           => $issuer,
            'approver'         => $approver,
            'subpermits'       => $subpermits,
            'subpermitTypes'   => SubpermitType::cases(),
            'controls'         => $controls,
            'controlSigImages' => $controlSigImages,
            'generatedAt'      => date('d.m.Y H:i'),
        ]);

        $view->setTemplateMode($oldMode);
        return $html;
    }

    private function renderSubpermitHtml(SubpermitRecord $subpermit, PermitRecord $permit): string
    {
        $data = is_string($subpermit->data)
            ? (json_decode($subpermit->data, true) ?? [])
            : (is_array($subpermit->data) ? $subpermit->data : []);

        $subpermitType = SubpermitType::tryFrom($subpermit->type);

        // Subpermit signatures
        $sigRows = SubpermitSignatureRecord::find()
            ->where(['subpermitId' => $subpermit->id])
            ->all();
        $signatures = [];
        foreach ($sigRows as $row) {
            $signatures[$row->role] = $row;
        }

        $sigImages = [];
        foreach ($signatures as $role => $sig) {
            if (!empty($sig->signatureAssetId)) {
                $sigImages[$role] = $this->assetToDataUri((int) $sig->signatureAssetId);
            }
        }

        // Multi-signer requests (Electrical)
        $signingRequests = [];
        $signingImages = [];
        if ($subpermitType === SubpermitType::Electrical) {
            $requests = SubpermitSigningRequestRecord::find()
                ->where(['subpermitId' => $subpermit->id])
                ->all();
            foreach ($requests as $req) {
                $signingRequests[$req->role] = $req;
                if (!empty($req->signatureAssetId)) {
                    $signingImages[$req->role] = $this->assetToDataUri((int) $req->signatureAssetId);
                }
            }
        }

        $issuer = $permit->issuerId ? User::find()->id($permit->issuerId)->one() : null;

        $view = Craft::$app->getView();
        $oldMode = $view->getTemplateMode();
        $view->setTemplateMode(View::TEMPLATE_MODE_SITE);

        $html = $view->renderTemplate('bozp/pdf/subpermit', [
            'permit'          => $permit,
            'subpermit'       => $subpermit,
            'subpermitType'   => $subpermitType,
            'values'          => $data,
            'signatures'      => $signatures,
            'sigImages'       => $sigImages,
            'signingRequests' => $signingRequests,
            'signingImages'   => $signingImages,
            'issuer'          => $issuer,
            'generatedAt'     => date('d.m.Y H:i'),
        ]);

        $view->setTemplateMode($oldMode);
        return $html;
    }

    // -------------------------------------------------------------------------
    // mPDF
    // -------------------------------------------------------------------------

    private function htmlToPdf(string $html): string
    {
        if (!class_exists(\Mpdf\Mpdf::class)) {
            throw new \RuntimeException(
                'mPDF library not found. Run: composer require mpdf/mpdf'
            );
        }

        $tmpDir = Craft::$app->getPath()->getTempPath() . DIRECTORY_SEPARATOR . 'mpdf';
        if (!is_dir($tmpDir)) {
            mkdir($tmpDir, 0755, true);
        }

        $mpdf = new \Mpdf\Mpdf([
            'format'       => 'A4',
            'margin_left'  => 12,
            'margin_right' => 12,
            'margin_top'   => 12,
            'margin_bottom'=> 12,
            'default_font' => 'dejavusans',
            'tempDir'      => $tmpDir,
        ]);

        $mpdf->showImageErrors = false;
        $mpdf->WriteHTML($html);

        return $mpdf->Output('', \Mpdf\Output\Destination::STRING_RETURN);
    }

    // -------------------------------------------------------------------------
    // Asset helpers
    // -------------------------------------------------------------------------

    private function saveAsAsset(string $pdfBytes, string $filename, ?int $oldAssetId): int
    {
        $volume = Craft::$app->getVolumes()->getVolumeByHandle(self::VOLUME_HANDLE);
        if (!$volume) {
            throw new \RuntimeException("Missing asset volume '" . self::VOLUME_HANDLE . "'");
        }
        $rootFolder = Craft::$app->getAssets()->getRootFolderByVolumeId($volume->id);
        if (!$rootFolder) {
            throw new \RuntimeException("No root folder for volume '" . self::VOLUME_HANDLE . "'");
        }

        $safeName = AssetsHelper::prepareAssetName($filename);

        // Write to temp file
        $tempPath = Craft::$app->getPath()->getTempPath()
            . DIRECTORY_SEPARATOR . 'bozp-pdf-' . bin2hex(random_bytes(8)) . '.pdf';
        file_put_contents($tempPath, $pdfBytes);

        // If an existing asset is provided, replace its file content in place.
        // This keeps the asset ID and filename — and therefore the public URL — stable
        // across regenerations.
        if ($oldAssetId !== null) {
            $existing = Craft::$app->getAssets()->getAssetById($oldAssetId);
            if ($existing) {
                try {
                    Craft::$app->getAssets()->replaceAssetFile($existing, $tempPath, $existing->filename);
                    @unlink($tempPath);
                    return (int) $existing->id;
                } catch (\Throwable $e) {
                    Craft::warning(
                        'BOZP PDF replace-in-place failed, falling back to new asset: ' . $e->getMessage(),
                        __METHOD__,
                    );
                    // fall through to create a new asset
                }
            }
        }

        // Create a new Craft Asset (first generation, or replacement failed)
        $asset = new Asset();
        $asset->tempFilePath = $tempPath;
        $asset->filename = $safeName;
        $asset->newFolderId = $rootFolder->id;
        $asset->volumeId = $volume->id;
        $asset->avoidFilenameConflicts = true;
        $asset->setScenario(Asset::SCENARIO_CREATE);

        if (!Craft::$app->getElements()->saveElement($asset)) {
            throw new \RuntimeException(
                'PDF asset save failed: ' . print_r($asset->getErrors(), true)
            );
        }

        return (int) $asset->id;
    }

    /**
     * Read an asset's file content and return as a data URI string,
     * so it can be embedded directly in mPDF without HTTP fetches.
     * Falls back to the public URL on any error.
     */
    private function assetToDataUri(int $assetId): ?string
    {
        $asset = Craft::$app->getAssets()->getAssetById($assetId);
        if (!$asset) {
            return null;
        }

        try {
            $fs = $asset->getVolume()->getFs();
            $stream = $fs->readStream($asset->getPath());
            if (!is_resource($stream)) {
                return $asset->url;
            }
            $data = stream_get_contents($stream);
            fclose($stream);
            if ($data === false || $data === '') {
                return $asset->url;
            }
            $mime = $asset->mimeType ?: 'image/png';
            return 'data:' . $mime . ';base64,' . base64_encode($data);
        } catch (\Throwable) {
            return $asset->url;
        }
    }
}
