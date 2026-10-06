<?php

declare(strict_types=1);

namespace modules\bozp\services;

use Craft;
use craft\elements\Asset;
use craft\helpers\Assets;
use modules\bozp\controllers\ContractorController;
use modules\bozp\enums\SubpermitStatus;
use modules\bozp\enums\SubpermitType;
use modules\bozp\records\EquipmentInspectionRecord;
use modules\bozp\records\SubpermitRecord;
use Throwable;
use yii\base\Component;

/**
 * EquipmentInspectionService
 *
 * Daily equipment safety condition inspection for `energized` subpermits
 * (Príloha č. 10, page 2).
 *
 * One row per calendar day while the subpermit is open, generated at 16:00
 * and mailed to the issuer. The issuer closure is blocked while any row is
 * unperformed — so doing today's inspection unblocks closure, and leaving
 * it until tomorrow produces a new one that blocks again.
 *
 * "Open" means: an energized subpermit that is approved, not cancelled or
 * expired, and carries no issuer_closure signature. Closure is recorded as
 * a signature rather than a status, so that is what has to be checked.
 */
class EquipmentInspectionService extends Component
{
    /** Only this subpermit type gets a daily inspection. */
    public const WATCHED_TYPES = ['energized'];

    /** Hour of day the inspection falls due. */
    public const DUE_HOUR = 16;

    /**
     * The 14 checklist items, in the four groups printed on the form.
     * Keys are stored in the `items` JSON column.
     *
     * @return array<string, array<string, string>> group => [key => label]
     */
    public static function checklist(): array
    {
        return [
            'mechanical' => [
                'guards_installed'           => 'Všetky ochranné kryty sú nainštalované a pevne upevnené.',
                'no_moving_parts_accessible' => 'Nie sú prístupné žiadne pohyblivé ani nebezpečné časti.',
                'components_fixed'           => 'Všetky mechanické komponenty sú pevne upevnené.',
                'no_tools_left'              => 'Na zariadení nezostalo žiadne náradie, voľné diely ani inštalačné materiály.',
            ],
            'safety_devices' => [
                'limit_switches' => 'Koncové spínače / senzory sú nainštalované a funkčné.',
                'interlocks'     => 'Bezpečnostné prvky (interlocky) sú nainštalované a funkčné.',
                'estop'          => 'Núdzové zastavovacie zariadenia sú nainštalované a funkčné.',
            ],
            'electrical' => [
                'panels_closed'  => 'Elektrické rozvádzače a kryty sú zatvorené a zabezpečené.',
                'no_live_parts'  => 'Nie sú prístupné žiadne živé elektrické súčiastky.',
                'cables_secured' => 'Káble a konektory sú správne zabezpečené a chránené.',
            ],
            'final' => [
                'equipment_safe'   => 'Zariadenie je ponechané v bezpečnom stave.',
                'area_clean'       => 'Pracovisko je čisté a bez nebezpečenstiev.',
                'no_temp_unsafe'   => 'Nezostali žiadne dočasné nebezpečné podmienky.',
                'defects_recorded' => 'Všetky identifikované chyby boli zaznamenané a primerane zabezpečené.',
            ],
        ];
    }

    /** Group labels for the form and the PDF. */
    public static function groupLabels(): array
    {
        return [
            'mechanical'     => 'Mechanická bezpečnosť',
            'safety_devices' => 'Bezpečnostné zariadenia',
            'electrical'     => 'Elektrická bezpečnosť',
            'final'          => 'Konečná podmienka',
        ];
    }

    /** Flat key => label map across all groups. */
    public static function checklistFlat(): array
    {
        $out = [];
        foreach (self::checklist() as $items) {
            foreach ($items as $key => $label) {
                $out[$key] = $label;
            }
        }
        return $out;
    }

    /**
     * Generate today's inspection row for every open energized subpermit,
     * once the due hour has passed.
     *
     * Idempotent: the unique (subpermitId, dueDate) index plus the lookup
     * mean repeated cron ticks on the same day create nothing new.
     *
     * @return EquipmentInspectionRecord[] rows created on this run
     */
    public function generateDue(?\DateTimeImmutable $now = null): array
    {
        $now = $now ?? new \DateTimeImmutable();

        // Before the due hour there is nothing to raise for today.
        if ((int) $now->format('G') < self::DUE_HOUR) {
            return [];
        }

        $dueDate = $now->format('Y-m-d');
        $dueAt   = $now->setTime(self::DUE_HOUR, 0, 0)->format('Y-m-d H:i:s');

        $created = [];
        foreach ($this->openSubpermits() as $subpermit) {
            $exists = EquipmentInspectionRecord::find()
                ->where(['subpermitId' => $subpermit->id, 'dueDate' => $dueDate])
                ->exists();

            if ($exists) {
                continue;
            }

            $row = new EquipmentInspectionRecord();
            $row->subpermitId = (int) $subpermit->id;
            $row->permitId    = (int) $subpermit->parentPermitId;
            $row->dueDate     = $dueDate;
            $row->dueAt       = $dueAt;

            if (!$row->save()) {
                Craft::error(
                    "Equipment inspection row failed for subpermit {$subpermit->id}: "
                    . print_r($row->getErrors(), true),
                    __METHOD__,
                );
                continue;
            }

            $created[] = $row;
        }

        return $created;
    }

    /**
     * Energized subpermits still awaiting their issuer closure.
     *
     * @return SubpermitRecord[]
     */
    private function openSubpermits(): array
    {
        $subpermits = SubpermitRecord::find()
            ->where(['type' => self::WATCHED_TYPES])
            ->andWhere(['status' => SubpermitStatus::Approved->value])
            ->all();

        if ($subpermits === []) {
            return [];
        }

        /** @var \modules\bozp\Module $module */
        $module = Craft::$app->getModule('bozp');

        return array_values(array_filter(
            $subpermits,
            static fn(SubpermitRecord $s) => !$module->subpermitSignatureService->findSignature(
                (int) $s->id,
                SubpermitSignatureService::ROLE_ISSUER_CLOSURE
            )
        ));
    }

    /**
     * @return EquipmentInspectionRecord[] newest first
     */
    public function findFor(int $subpermitId): array
    {
        return EquipmentInspectionRecord::find()
            ->where(['subpermitId' => $subpermitId])
            ->orderBy(['dueAt' => SORT_DESC])
            ->all();
    }

    public function outstandingCount(int $subpermitId): int
    {
        return (int) EquipmentInspectionRecord::find()
            ->where(['subpermitId' => $subpermitId, 'performedAt' => null])
            ->count();
    }

    /** The oldest unperformed inspection, or null when all are done. */
    public function nextOutstanding(int $subpermitId): ?EquipmentInspectionRecord
    {
        /** @var EquipmentInspectionRecord|null $row */
        $row = EquipmentInspectionRecord::find()
            ->where(['subpermitId' => $subpermitId, 'performedAt' => null])
            ->orderBy(['dueAt' => SORT_ASC])
            ->one();

        return $row;
    }

    /**
     * Record one inspection.
     *
     * @param array<string, string> $items key => 'ok' | 'nok' | 'na'
     * @throws \RuntimeException when the row cannot be saved
     */
    public function perform(
        EquipmentInspectionRecord $inspection,
        string $performerName,
        string $status,
        array $items,
        ?string $notes = null,
        ?string $signatureData = null,
    ): void {
        $user = Craft::$app->getUser()->getIdentity();

        // Keep only known checklist keys with an allowed value.
        $allowedKeys   = array_keys(self::checklistFlat());
        $allowedValues = ['ok', 'nok', 'na'];
        $clean = [];
        foreach ($items as $key => $value) {
            if (in_array($key, $allowedKeys, true) && in_array($value, $allowedValues, true)) {
                $clean[$key] = $value;
            }
        }

        $inspection->performedAt     = date('Y-m-d H:i:s');
        $inspection->performerUserId = $user ? (int) $user->id : null;
        $inspection->performerName   = $performerName;
        $inspection->status          = $status;
        $inspection->items           = json_encode($clean);
        $inspection->notes           = ($notes !== null && $notes !== '') ? $notes : null;
        $inspection->ipAddress       = Craft::$app->getRequest()->getUserIP();

        if ($signatureData !== null && $signatureData !== '') {
            $inspection->signatureAssetId = $this->saveSignatureAsset($inspection, $signatureData);
        }

        if (!$inspection->save()) {
            throw new \RuntimeException(
                'Equipment inspection save failed: ' . print_r($inspection->getErrors(), true)
            );
        }
    }

    /**
     * Drop outstanding inspections when the work is called off. Performed
     * ones are kept — they are a safety record.
     */
    public function voidFor(int $subpermitId): int
    {
        return EquipmentInspectionRecord::deleteAll([
            'subpermitId' => $subpermitId,
            'performedAt' => null,
        ]);
    }

    public function voidForPermit(int $permitId): int
    {
        return EquipmentInspectionRecord::deleteAll([
            'permitId'    => $permitId,
            'performedAt' => null,
        ]);
    }

    /** True when this subpermit type takes a daily inspection. */
    public static function appliesTo(SubpermitRecord $subpermit): bool
    {
        return in_array($subpermit->type, self::WATCHED_TYPES, true);
    }

    /**
     * Decode the signature data URI into a Craft Asset. Returns null on any
     * failure — a missing image must not lose the inspection record.
     */
    private function saveSignatureAsset(EquipmentInspectionRecord $inspection, string $dataUri): ?int
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
                    'Equipment inspection: bozpAttachments volume not found — signature not stored.',
                    __METHOD__,
                );
                return null;
            }

            $tempPath = Craft::$app->getPath()->getTempPath()
                . '/sig-inspection-' . bin2hex(random_bytes(8)) . '.png';
            file_put_contents($tempPath, $png);

            $asset = new Asset();
            $asset->tempFilePath = $tempPath;
            $asset->filename = Assets::prepareAssetName(
                'inspection-' . $inspection->subpermitId . '-' . $inspection->dueDate . '.png'
            );
            $asset->newFolderId = $rootFolder->id;
            $asset->volumeId = $volume->id;
            $asset->avoidFilenameConflicts = true;
            $asset->setScenario(Asset::SCENARIO_CREATE);

            if (!Craft::$app->getElements()->saveElement($asset)) {
                Craft::warning(
                    'Equipment inspection signature save failed: ' . print_r($asset->getErrors(), true),
                    __METHOD__,
                );
                return null;
            }

            return (int) $asset->id;
        } catch (Throwable $e) {
            Craft::error('Equipment inspection signature save failed: ' . $e->getMessage(), __METHOD__);
            return null;
        }
    }
}
