<?php

declare(strict_types=1);

namespace modules\bozp\controllers;

use Craft;
use craft\elements\User;
use craft\web\View;
use modules\bozp\enums\HazardCategory;
use modules\bozp\enums\PermitStatus;
use modules\bozp\enums\PermitType;
use modules\bozp\Module;
use modules\bozp\records\AuditLogRecord;
use modules\bozp\records\PermitHazardRecord;
use modules\bozp\records\PermitRecord;
use modules\bozp\records\ZoneRecord;
use Throwable;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * PermitsController
 *
 * Front-end create + save flow. Two actions in Phase 2B:
 *
 *   GET  /bozp/permits/new   → blank form
 *   POST /bozp/permits/save  → validates, allocates number, inserts as draft,
 *                              optionally submits, redirects back to dashboard.
 *
 * Phase 2B intentionally keeps the form to the minimum fields needed to land
 * in the HSE queue: contractor, work location, overview, validity window,
 * (optionally) zones. Hazard matrix + preparation checks come in 2C.
 *
 * Column mapping (DB schema on the left, form field on the right):
 *   contractorCompany      <- contractorCompany
 *   contractorPersonName   <- contractorPersonName
 *   contractorEmail        <- contractorEmail
 *   workLocation           <- workLocation
 *   workOverview           <- workOverview
 *   validFrom              <- validFrom
 *   validTo                <- validTo
 */
class PermitsController extends BaseSiteController
{
    protected array|bool|int $allowAnonymous = ['view', 'new', 'save'];

    public function actionView(int $id): Response
    {
        if ($redirect = $this->requireBozpLogin()) {
            return $redirect;
        }

        $permit = PermitRecord::findOne(['id' => $id]);
        if (!$permit) {
            throw new NotFoundHttpException('Permit not found.');
        }

        $user = Craft::$app->getUser();
        $userId = $user->getId();

        // Issuer can always see their own permit; otherwise bozp:viewAll is required.
        $isIssuer = (int) $permit->issuerId === (int) $userId;
        if (!$isIssuer && !$user->checkPermission('bozp:viewAll')) {
            throw new ForbiddenHttpException();
        }

        $zones = $this->loadZonesFor((int) $permit->id);
        $approver = $permit->approverId ? User::find()->id($permit->approverId)->one() : null;
        $auditEntries = AuditLogRecord::find()
            ->where(['permitId' => $permit->id])
            ->orderBy(['dateCreated' => SORT_DESC])
            ->limit(20)
            ->all();

        $this->view->setTemplateMode(View::TEMPLATE_MODE_SITE);

        return $this->renderTemplate('bozp/site/permit-detail', [
            'permit' => $permit,
            'zones' => $zones,
            'approver' => $approver,
            'auditEntries' => $auditEntries,
            'hazardCategories' => HazardCategory::pdfOrder(),
            'hazards' => $this->loadHazardsFor((int) $permit->id),
        ]);
    }

    /**
     * @return array<string, PermitHazardRecord> keyed by hazardKey
     */
    private function loadHazardsFor(int $permitId): array
    {
        $rows = PermitHazardRecord::find()
            ->where(['permitId' => $permitId])
            ->orderBy(['sortOrder' => SORT_ASC, 'id' => SORT_ASC])
            ->all();

        $out = [];
        foreach ($rows as $row) {
            $out[$row->hazardKey] = $row;
        }
        return $out;
    }

    public function actionNew(): Response
    {
        if ($redirect = $this->requireBozpLogin()) {
            return $redirect;
        }
        $this->requirePermission('bozp:createPermit');

        $zones = ZoneRecord::find()
            ->where(['archived' => false])
            ->orderBy(['sortOrder' => SORT_ASC, 'name' => SORT_ASC])
            ->all();

        $this->view->setTemplateMode(View::TEMPLATE_MODE_SITE);

        return $this->renderTemplate('bozp/site/permit-form', [
            'permit' => null,
            'zones' => $zones,
            'hazardCategories' => HazardCategory::pdfOrder(),
            'errors' => [],
            'values' => $this->defaultValues(),
        ]);
    }

    /**
     * Default values for a brand-new permit form — empty strings for scalars,
     * and the pre-populated default measure for each hazard row.
     *
     * @return array<string, mixed>
     */
    private function defaultValues(): array
    {
        $hazards = [];
        foreach (HazardCategory::pdfOrder() as $cat) {
            $hazards[$cat->value] = [
                'exposed' => '',
                'measure' => $cat->defaultMeasure(),
                'control' => '',
                'controlOther' => '',
            ];
        }

        return [
            'preparation' => [
                'conditionsSuitable' => '',
                'toolsInGoodCondition' => '',
                'hasStopConditions' => '',
                'stopConditionsDescription' => '',
                'lotoImplemented' => '',
                'emergencyPlan' => '',
            ],
            'hazards' => $hazards,
        ];
    }

    public function actionSave(): ?Response
    {
        $this->requirePostRequest();
        if ($redirect = $this->requireBozpLogin()) {
            return $redirect;
        }
        $this->requirePermission('bozp:createPermit');

        $request = Craft::$app->getRequest();
        $userId = Craft::$app->getUser()->getId();

        // "save" = stay in draft. "submit" = save then transition to submitted.
        $intent = $request->getBodyParam('intent', 'save');

        $values = [
            'contractorCompany'    => trim((string) $request->getBodyParam('contractorCompany', '')),
            'contractorPersonName' => trim((string) $request->getBodyParam('contractorPersonName', '')),
            'contractorEmail'      => trim((string) $request->getBodyParam('contractorEmail', '')),
            'workLocation'         => trim((string) $request->getBodyParam('workLocation', '')),
            'workOverview'         => trim((string) $request->getBodyParam('workOverview', '')),
            'validFrom'            => trim((string) $request->getBodyParam('validFrom', '')),
            'zoneIds'              => (array) $request->getBodyParam('zoneIds', []),
            'preparation'          => $this->normalizePreparation((array) $request->getBodyParam('preparation', [])),
            'hazards'              => $this->normalizeHazards((array) $request->getBodyParam('hazards', [])),
        ];

        $errors = $this->validate($values, $intent);

        if ($errors !== []) {
            Craft::$app->getSession()->setError(Craft::t('bozp', 'Skontrolujte chyby vo formulári.'));
            $zones = ZoneRecord::find()
                ->where(['archived' => false])
                ->orderBy(['sortOrder' => SORT_ASC, 'name' => SORT_ASC])
                ->all();

            $this->view->setTemplateMode(View::TEMPLATE_MODE_SITE);
            return $this->renderTemplate('bozp/site/permit-form', [
                'permit' => null,
                'zones' => $zones,
                'hazardCategories' => HazardCategory::pdfOrder(),
                'errors' => $errors,
                'values' => $values,
            ]);
        }

        /** @var Module $module */
        $module = Craft::$app->getModule('bozp');

        $db = Craft::$app->getDb();
        $transaction = $db->beginTransaction();

        try {
            $permit = new PermitRecord();
            $permit->permitNumber = $module->permitNumberGenerator->next(PermitType::General);
            $permit->permitType = PermitType::General->value;
            $permit->status = PermitStatus::Draft->value;
            $permit->issuerId = $userId;
            $permit->contractorCompany = $values['contractorCompany'] !== '' ? $values['contractorCompany'] : null;
            $permit->contractorPersonName = $values['contractorPersonName'] !== '' ? $values['contractorPersonName'] : null;
            $permit->contractorEmail = $values['contractorEmail'] !== '' ? $values['contractorEmail'] : null;
            $permit->workLocation = $values['workLocation'];
            $permit->workOverview = $values['workOverview'];
            $permit->validFrom = $values['validFrom'] !== '' ? $values['validFrom'] : null;
            // validTo is auto-assigned on HSE approval (approvedAt + 7 days) — not user-editable.

            // Preparation checks — booleans get null when "not answered".
            $prep = $values['preparation'];
            $permit->conditionsSuitable = $this->ynToBool($prep['conditionsSuitable']);
            $permit->toolsInGoodCondition = $this->ynToBool($prep['toolsInGoodCondition']);
            $permit->hasStopConditions = $this->ynToBool($prep['hasStopConditions']);
            $permit->stopConditionsDescription = $prep['stopConditionsDescription'] !== '' ? $prep['stopConditionsDescription'] : null;
            $permit->lotoImplemented = $this->ynToBool($prep['lotoImplemented']);
            $permit->emergencyPlan = $prep['emergencyPlan'] !== '' ? $prep['emergencyPlan'] : null;

            if (!$permit->save()) {
                throw new \RuntimeException('Save failed: ' . print_r($permit->getErrors(), true));
            }

            $this->syncZones($permit->id, $values['zoneIds']);
            $this->syncHazards((int) $permit->id, $values['hazards']);

            $module->auditLogger->log(
                permitId: (int) $permit->id,
                userId: $userId,
                action: 'created',
                toStatus: PermitStatus::Draft->value,
            );

            if ($intent === 'submit') {
                $module->permitWorkflow->submit($permit, $userId);
            }

            $transaction->commit();
        } catch (Throwable $e) {
            $transaction->rollBack();
            Craft::error('Permit save failed: ' . $e->getMessage() . "\n" . $e->getTraceAsString(), __METHOD__);

            $message = (string) Craft::t('bozp', 'Permit sa nepodarilo uložiť. Skúste znova.');
            if (Craft::$app->getConfig()->getGeneral()->devMode) {
                $message .= ' [dev] ' . $e->getMessage();
            }
            Craft::$app->getSession()->setError($message);
            return $this->redirect('bozp/permits/new');
        }

        $msg = $intent === 'submit'
            ? Craft::t('bozp', 'Permit {n} bol odoslaný na schválenie HSE.', ['n' => $permit->permitNumber])
            : Craft::t('bozp', 'Permit {n} bol uložený ako koncept.', ['n' => $permit->permitNumber]);

        Craft::$app->getSession()->setNotice($msg);

        return $this->redirect('bozp');
    }

    /**
     * @param array<string, mixed> $values
     * @return array<string, string>
     */
    private function validate(array $values, string $intent): array
    {
        $errors = [];

        if ($values['contractorCompany'] === '') {
            $errors['contractorCompany'] = (string) Craft::t('bozp', 'Názov dodávateľa je povinný.');
        }
        if ($values['workLocation'] === '') {
            $errors['workLocation'] = (string) Craft::t('bozp', 'Miesto výkonu je povinné.');
        }
        if ($values['workOverview'] === '') {
            $errors['workOverview'] = (string) Craft::t('bozp', 'Popis prác je povinný.');
        }

        if ($values['contractorEmail'] !== '' && !filter_var($values['contractorEmail'], FILTER_VALIDATE_EMAIL)) {
            $errors['contractorEmail'] = (string) Craft::t('bozp', 'Neplatná e-mailová adresa.');
        }

        // Submitting requires a planned start date. The end of the validity
        // window is auto-calculated on HSE approval (approvedAt + 7 days).
        if ($intent === 'submit') {
            if ($values['validFrom'] === '') {
                $errors['validFrom'] = (string) Craft::t('bozp', 'Plánovaný začiatok je povinný pri odoslaní.');
            }
        }

        return $errors;
    }

    /**
     * @param array<string, mixed> $raw
     * @return array<string, string>
     */
    private function normalizePreparation(array $raw): array
    {
        $yn = static function ($v): string {
            $v = is_string($v) ? strtolower(trim($v)) : '';
            return in_array($v, ['yes', 'no'], true) ? $v : '';
        };

        return [
            'conditionsSuitable'        => $yn($raw['conditionsSuitable'] ?? ''),
            'toolsInGoodCondition'      => $yn($raw['toolsInGoodCondition'] ?? ''),
            'hasStopConditions'         => $yn($raw['hasStopConditions'] ?? ''),
            'stopConditionsDescription' => trim((string) ($raw['stopConditionsDescription'] ?? '')),
            'lotoImplemented'           => $yn($raw['lotoImplemented'] ?? ''),
            'emergencyPlan'             => trim((string) ($raw['emergencyPlan'] ?? '')),
        ];
    }

    /**
     * Keyed by hazard category enum value. Unknown keys are dropped.
     *
     * @param array<string, mixed> $raw
     * @return array<string, array{exposed: string, measure: string, control: string, controlOther: string}>
     */
    private function normalizeHazards(array $raw): array
    {
        $allowedControls = ['used', 'not_used', 'other'];
        $out = [];

        foreach (HazardCategory::pdfOrder() as $cat) {
            $row = (array) ($raw[$cat->value] ?? []);
            $exposed = is_string($row['exposed'] ?? null) ? strtolower(trim($row['exposed'])) : '';
            $control = is_string($row['control'] ?? null) ? strtolower(trim($row['control'])) : '';

            $out[$cat->value] = [
                'exposed' => in_array($exposed, ['yes', 'no'], true) ? $exposed : '',
                'measure' => trim((string) ($row['measure'] ?? '')),
                'control' => in_array($control, $allowedControls, true) ? $control : '',
                'controlOther' => trim((string) ($row['controlOther'] ?? '')),
            ];
        }

        return $out;
    }

    private function ynToBool(string $v): ?bool
    {
        return match ($v) {
            'yes' => true,
            'no' => false,
            default => null,
        };
    }

    /**
     * Insert one bozp_permit_hazards row per HazardCategory. Only rows where
     * the user has actually touched something (exposed set, measure changed,
     * or a control value chosen) get persisted — otherwise the matrix would
     * be spammed with 16 no-op rows on every permit.
     *
     * @param array<string, array{exposed: string, measure: string, control: string, controlOther: string}> $hazards
     */
    private function syncHazards(int $permitId, array $hazards): void
    {
        $db = Craft::$app->getDb();
        $now = date('Y-m-d H:i:s');
        $rows = [];
        $sort = 0;

        foreach (HazardCategory::pdfOrder() as $cat) {
            $row = $hazards[$cat->value] ?? null;
            if ($row === null) {
                continue;
            }

            $defaultMeasure = $cat->defaultMeasure();
            $hasCustomMeasure = $row['measure'] !== '' && $row['measure'] !== $defaultMeasure;
            $touched = $row['exposed'] !== ''
                || $row['control'] !== ''
                || $hasCustomMeasure
                || $row['controlOther'] !== '';

            if (!$touched) {
                continue;
            }

            $rows[] = [
                $permitId,
                $cat->value,
                $this->ynToBool($row['exposed']),
                $row['measure'] !== '' ? $row['measure'] : null,
                $row['control'] !== '' ? $row['control'] : null,
                $row['controlOther'] !== '' ? $row['controlOther'] : null,
                $sort++,
                $now,
                $now,
                \craft\helpers\StringHelper::UUID(),
            ];
        }

        if ($rows === []) {
            return;
        }

        $db->createCommand()
            ->batchInsert(
                '{{%bozp_permit_hazards}}',
                ['permitId', 'hazardKey', 'exposed', 'measure', 'controlDuringActivity', 'controlDuringActivityOther', 'sortOrder', 'dateCreated', 'dateUpdated', 'uid'],
                $rows,
            )
            ->execute();
    }

    /** @return ZoneRecord[] */
    private function loadZonesFor(int $permitId): array
    {
        $zoneIds = (new \yii\db\Query())
            ->select('zoneId')
            ->from('{{%bozp_permit_zones}}')
            ->where(['permitId' => $permitId])
            ->column();

        if ($zoneIds === []) {
            return [];
        }

        return ZoneRecord::find()
            ->where(['id' => $zoneIds])
            ->orderBy(['sortOrder' => SORT_ASC, 'name' => SORT_ASC])
            ->all();
    }

    /** @param array<int, string|int> $zoneIds */
    private function syncZones(int $permitId, array $zoneIds): void
    {
        $zoneIds = array_values(array_unique(array_map('intval', $zoneIds)));
        if ($zoneIds === []) {
            return;
        }

        $db = Craft::$app->getDb();
        $now = date('Y-m-d H:i:s');
        $rows = [];
        foreach ($zoneIds as $zoneId) {
            $rows[] = [$permitId, $zoneId, $now, $now, \craft\helpers\StringHelper::UUID()];
        }

        $db->createCommand()
            ->batchInsert(
                '{{%bozp_permit_zones}}',
                ['permitId', 'zoneId', 'dateCreated', 'dateUpdated', 'uid'],
                $rows,
            )
            ->execute();
    }
}
