<?php

declare(strict_types=1);

namespace modules\bozp\services;

use Craft;
use craft\elements\Asset;
use craft\helpers\Assets;
use modules\bozp\controllers\ContractorController;
use modules\bozp\records\FireWatchControlRecord;
use modules\bozp\records\PermitRecord;
use modules\bozp\records\SubpermitRecord;
use Throwable;
use yii\base\Component;

/**
 * FireWatchService
 *
 * Post-hot-work fire watch: four hourly checks the issuer must perform
 * after the contractor closes a `hot_work` subpermit.
 *
 *   openFor()      — create the four rows (idempotent)
 *   findFor()      — the rows for a subpermit, in slot order
 *   isComplete()   — closure gate
 *   perform()      — record one check
 *   voidFor()      — drop outstanding rows when work is cancelled
 *
 * Only hot_work is covered. Subpermits closed before this shipped have no
 * rows at all, and isComplete() treats "no rows" as complete so they stay
 * closable — the gate is forward-only by design.
 */
class FireWatchService extends Component
{
    /** Subpermit types that require a post-work fire watch. */
    public const WATCHED_TYPES = ['hot_work'];

    /** Number of hourly checks, one per hour after the contractor closure. */
    public const CONTROL_COUNT = 4;

    /**
     * Create the four fire watch rows for a hot-work subpermit, due one
     * hour apart starting an hour after $closedAt.
     *
     * Safe to call repeatedly — the unique (subpermitId, sequence) index
     * plus the existence check mean a re-signed closure won't duplicate.
     *
     * @param string|null $closedAt 'Y-m-d H:i:s'; defaults to now.
     * @return int rows created (0 when already present or not applicable)
     */
    public function openFor(SubpermitRecord $subpermit, ?string $closedAt = null): int
    {
        if (!in_array($subpermit->type, self::WATCHED_TYPES, true)) {
            return 0;
        }

        $existing = FireWatchControlRecord::find()
            ->where(['subpermitId' => $subpermit->id])
            ->count();

        if ((int) $existing > 0) {
            return 0;
        }

        try {
            $start = new \DateTimeImmutable($closedAt ?? 'now');
        } catch (Throwable) {
            $start = new \DateTimeImmutable();
        }

        $created = 0;
        for ($i = 1; $i <= self::CONTROL_COUNT; $i++) {
            $row = new FireWatchControlRecord();
            $row->subpermitId = (int) $subpermit->id;
            $row->permitId    = (int) $subpermit->parentPermitId;
            $row->sequence    = $i;
            $row->dueAt       = $start->modify("+{$i} hours")->format('Y-m-d H:i:s');

            if (!$row->save()) {
                Craft::error(
                    "Fire watch row {$i} failed for subpermit {$subpermit->id}: "
                    . print_r($row->getErrors(), true),
                    __METHOD__,
                );
                continue;
            }
            $created++;
        }

        return $created;
    }

    /**
     * @return FireWatchControlRecord[] ordered by slot
     */
    public function findFor(int $subpermitId): array
    {
        return FireWatchControlRecord::find()
            ->where(['subpermitId' => $subpermitId])
            ->orderBy(['sequence' => SORT_ASC])
            ->all();
    }

    /**
     * True when every fire watch row for this subpermit has been performed.
     *
     * A subpermit with no rows counts as complete: either it isn't a
     * hot-work subpermit, or it was closed before this feature existed.
     */
    public function isComplete(int $subpermitId): bool
    {
        return $this->outstandingCount($subpermitId) === 0;
    }

    public function outstandingCount(int $subpermitId): int
    {
        return (int) FireWatchControlRecord::find()
            ->where(['subpermitId' => $subpermitId, 'performedAt' => null])
            ->count();
    }

    /**
     * The next unperformed row, or null when the watch is finished.
     */
    public function nextOutstanding(int $subpermitId): ?FireWatchControlRecord
    {
        /** @var FireWatchControlRecord|null $row */
        $row = FireWatchControlRecord::find()
            ->where(['subpermitId' => $subpermitId, 'performedAt' => null])
            ->orderBy(['sequence' => SORT_ASC])
            ->one();

        return $row;
    }

    /**
     * Record one fire watch check. A late check still satisfies its slot —
     * the actual time is stored in performedAt, the scheduled time stays in
     * dueAt, so the record shows both.
     *
     * @param string      $result        self::RESULT_* on FireWatchControlRecord
     * @param string|null $signatureData data:image/png;base64,... or null
     * @throws \RuntimeException when the row cannot be saved
     */
    public function perform(
        FireWatchControlRecord $control,
        string $performerName,
        string $result,
        ?string $notes = null,
        ?string $signatureData = null,
    ): void {
        $user = Craft::$app->getUser()->getIdentity();

        $control->performedAt     = date('Y-m-d H:i:s');
        $control->performerUserId = $user ? (int) $user->id : null;
        $control->performerName   = $performerName;
        $control->result          = $result;
        $control->notes           = ($notes !== null && $notes !== '') ? $notes : null;
        $control->ipAddress       = Craft::$app->getRequest()->getUserIP();

        if ($signatureData !== null && $signatureData !== '') {
            $control->signatureAssetId = $this->saveSignatureAsset($control, $signatureData);
        }

        if (!$control->save()) {
            throw new \RuntimeException(
                'Fire watch control save failed: ' . print_r($control->getErrors(), true)
            );
        }
    }

    /**
     * Drop outstanding rows when the work is called off. Performed checks
     * are kept — they are a safety record and shouldn't vanish.
     *
     * @return int rows removed
     */
    public function voidFor(int $subpermitId): int
    {
        return FireWatchControlRecord::deleteAll([
            'subpermitId' => $subpermitId,
            'performedAt' => null,
        ]);
    }

    /**
     * Same, for every subpermit under a permit (permit cancelled).
     */
    public function voidForPermit(int $permitId): int
    {
        return FireWatchControlRecord::deleteAll([
            'permitId'    => $permitId,
            'performedAt' => null,
        ]);
    }

    /**
     * Decode the signature data URI into a Craft Asset. Returns null on any
     * failure — a missing signature image must not lose the control record.
     */
    private function saveSignatureAsset(FireWatchControlRecord $control, string $dataUri): ?int
    {
        try {
            if (!preg_match('#^data:image/png;base64,(.+)$#s', $dataUri, $m)) {
                return null;
            }
            $png = base64_decode($m[1], true);
            if ($png === false || strlen($png) < 100) {
                return null;
            }

            $volume = Craft::$app->getVolumes()
                ->getVolumeByHandle(ContractorController::ASSET_VOLUME_HANDLE);
            $rootFolder = $volume
                ? Craft::$app->getAssets()->getRootFolderByVolumeId($volume->id)
                : null;

            if (!$volume || !$rootFolder) {
                Craft::warning(
                    'Fire watch: bozpAttachments volume not found — signature not stored.',
                    __METHOD__,
                );
                return null;
            }

            $tempPath = Craft::$app->getPath()->getTempPath()
                . '/sig-firewatch-' . bin2hex(random_bytes(8)) . '.png';
            file_put_contents($tempPath, $png);

            $asset = new Asset();
            $asset->tempFilePath = $tempPath;
            $asset->filename = Assets::prepareAssetName(
                'firewatch-' . $control->subpermitId . '-' . $control->sequence . '.png'
            );
            $asset->newFolderId = $rootFolder->id;
            $asset->volumeId = $volume->id;
            $asset->avoidFilenameConflicts = true;
            $asset->setScenario(Asset::SCENARIO_CREATE);

            if (!Craft::$app->getElements()->saveElement($asset)) {
                Craft::warning(
                    'Fire watch signature asset save failed: ' . print_r($asset->getErrors(), true),
                    __METHOD__,
                );
                return null;
            }

            return (int) $asset->id;
        } catch (Throwable $e) {
            Craft::error('Fire watch signature save failed: ' . $e->getMessage(), __METHOD__);
            return null;
        }
    }

    /**
     * Convenience for templates/PDF: the permit a control belongs to.
     */
    public function permitFor(FireWatchControlRecord $control): ?PermitRecord
    {
        return PermitRecord::findOne(['id' => $control->permitId]);
    }
}
