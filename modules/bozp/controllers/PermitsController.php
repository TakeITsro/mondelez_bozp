<?php

declare(strict_types=1);

namespace modules\bozp\controllers;

use Craft;
use craft\web\Controller;
use craft\web\View;
use modules\bozp\enums\PermitStatus;
use modules\bozp\enums\PermitType;
use modules\bozp\Module;
use modules\bozp\records\PermitRecord;
use modules\bozp\records\ZoneRecord;
use Throwable;
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
class PermitsController extends Controller
{
    protected array|bool|int $allowAnonymous = false;

    public function actionNew(): Response
    {
        $this->requireLogin();
        $this->requirePermission('bozp:createPermit');

        $zones = ZoneRecord::find()
            ->where(['archived' => false])
            ->orderBy(['sortOrder' => SORT_ASC, 'name' => SORT_ASC])
            ->all();

        $this->view->setTemplateMode(View::TEMPLATE_MODE_SITE);

        return $this->renderTemplate('bozp/site/permit-form', [
            'permit' => null,
            'zones' => $zones,
            'errors' => [],
            'values' => [],
        ]);
    }

    public function actionSave(): ?Response
    {
        $this->requirePostRequest();
        $this->requireLogin();
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
            'validTo'              => trim((string) $request->getBodyParam('validTo', '')),
            'zoneIds'              => (array) $request->getBodyParam('zoneIds', []),
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
            $permit->validTo = $values['validTo'] !== '' ? $values['validTo'] : null;

            if (!$permit->save()) {
                throw new \RuntimeException('Save failed: ' . print_r($permit->getErrors(), true));
            }

            $this->syncZones($permit->id, $values['zoneIds']);

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

        // Submitting requires the time window. Drafts can be incomplete.
        if ($intent === 'submit') {
            if ($values['validFrom'] === '') {
                $errors['validFrom'] = (string) Craft::t('bozp', 'Plánovaný začiatok je povinný pri odoslaní.');
            }
            if ($values['validTo'] === '') {
                $errors['validTo'] = (string) Craft::t('bozp', 'Plánovaný koniec je povinný pri odoslaní.');
            }
            if (
                !isset($errors['validFrom'])
                && !isset($errors['validTo'])
                && strtotime($values['validTo']) <= strtotime($values['validFrom'])
            ) {
                $errors['validTo'] = (string) Craft::t('bozp', 'Koniec musí byť po začiatku.');
            }
        }

        return $errors;
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
