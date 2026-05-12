<?php

declare(strict_types=1);

namespace modules\bozp\controllers;

use Craft;
use craft\web\View;
use modules\bozp\enums\PermitStatus;
use modules\bozp\enums\SubpermitStatus;
use modules\bozp\enums\SubpermitType;
use modules\bozp\Module;
use modules\bozp\records\PermitRecord;
use modules\bozp\records\SubpermitRecord;
use modules\bozp\services\SubpermitSignatureService;
use Throwable;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Front-end subpermit controller (issuer-facing).
 *
 * Routes (registered in Module::registerSiteUrlRules):
 *   GET  bozp/permits/<permitId>/subpermits/new               → type selector
 *   GET  bozp/permits/<permitId>/subpermits/new/<type>        → form for one type
 *   POST bozp/permits/<permitId>/subpermits/save              → persist
 *   GET  bozp/permits/<permitId>/subpermits/<id>              → read-only view
 *   POST bozp/permits/<permitId>/subpermits/<id>/cancel       → cancel
 */
class SubpermitsController extends BaseSiteController
{
    protected array|bool|int $allowAnonymous = ['new', 'form', 'save', 'view', 'cancel'];

    // -------------------------------------------------------------------------
    // Type selector
    // -------------------------------------------------------------------------

    public function actionNew(int $permitId): Response
    {
        if ($redirect = $this->requireBozpLogin()) {
            return $redirect;
        }
        $this->requirePermission('bozp:createPermit');

        $permit = $this->findPermit($permitId);
        $this->requireIsIssuer($permit);

        // If the general permit already specifies required types, pre-select those.
        $requiredTypes = $this->decodeRequiredTypes($permit);

        $this->view->setTemplateMode(View::TEMPLATE_MODE_SITE);

        return $this->renderTemplate('bozp/site/subpermits/type-select', [
            'permit' => $permit,
            'subpermitTypes' => SubpermitType::cases(),
            'requiredTypes' => $requiredTypes,
        ]);
    }

    // -------------------------------------------------------------------------
    // Subpermit form (specific type)
    // -------------------------------------------------------------------------

    public function actionForm(int $permitId, string $type): Response
    {
        if ($redirect = $this->requireBozpLogin()) {
            return $redirect;
        }
        $this->requirePermission('bozp:createPermit');

        $permit = $this->findPermit($permitId);
        $this->requireIsIssuer($permit);

        $subpermitType = SubpermitType::tryFrom($type);
        if ($subpermitType === null) {
            throw new NotFoundHttpException("Unknown subpermit type: {$type}");
        }

        $issuer = Craft::$app->getUser()->getIdentity();
        $defaultName = $issuer?->getFullName() ?: ($issuer?->username ?: '');

        $this->view->setTemplateMode(View::TEMPLATE_MODE_SITE);

        return $this->renderTemplate('bozp/site/subpermits/form', [
            'permit' => $permit,
            'subpermitType' => $subpermitType,
            'errors' => [],
            'values' => $this->defaultValues($subpermitType, $permit, $defaultName),
        ]);
    }

    // -------------------------------------------------------------------------
    // Save
    // -------------------------------------------------------------------------

    public function actionSave(): ?Response
    {
        $this->requirePostRequest();
        if ($redirect = $this->requireBozpLogin()) {
            return $redirect;
        }
        $this->requirePermission('bozp:createPermit');

        $permitId = (int) Craft::$app->getRequest()->getRequiredBodyParam('permitId');
        $permit = $this->findPermit($permitId);
        $this->requireIsIssuer($permit);

        $request = Craft::$app->getRequest();
        $typeValue = trim((string) $request->getRequiredBodyParam('subpermitType'));
        $subpermitType = SubpermitType::tryFrom($typeValue);
        if ($subpermitType === null) {
            throw new NotFoundHttpException("Unknown subpermit type: {$typeValue}");
        }

        $values = $this->collectValues($subpermitType, $request);
        $errors = $this->validate($values);

        if ($errors !== []) {
            Craft::$app->getSession()->setError(Craft::t('bozp', 'Skontrolujte chyby vo formulári.'));
            $this->view->setTemplateMode(View::TEMPLATE_MODE_SITE);
            return $this->renderTemplate('bozp/site/subpermits/form', [
                'permit' => $permit,
                'subpermitType' => $subpermitType,
                'errors' => $errors,
                'values' => $values,
            ]);
        }

        // Validate issuer signature
        $signatureData = trim((string) $request->getBodyParam('signatureData', ''));
        $signerName = trim((string) $request->getBodyParam('signerName', ''));
        $signatureDate = trim((string) $request->getBodyParam('signatureDate', date('Y-m-d')));

        if ($signatureData === '' || !str_starts_with($signatureData, 'data:image/png;base64,')) {
            $errors['signature'] = Craft::t('bozp', 'Podpis vydavateľa je povinný.');
        }
        if ($signerName === '') {
            $errors['signerName'] = Craft::t('bozp', 'Meno podpisujúceho je povinné.');
        }

        if ($errors !== []) {
            Craft::$app->getSession()->setError(Craft::t('bozp', 'Skontrolujte chyby vo formulári.'));
            $this->view->setTemplateMode(View::TEMPLATE_MODE_SITE);
            return $this->renderTemplate('bozp/site/subpermits/form', [
                'permit' => $permit,
                'subpermitType' => $subpermitType,
                'errors' => $errors,
                'values' => $values,
            ]);
        }

        $userId = Craft::$app->getUser()->getId();

        try {
            $subpermit = new SubpermitRecord();
            $subpermit->parentPermitId = $permit->id;
            $subpermit->type = $subpermitType->value;
            $subpermit->status = SubpermitStatus::Pending->value;
            $subpermit->issuerId = $userId;
            $subpermit->data = json_encode($values);

            if (!$subpermit->save()) {
                throw new \RuntimeException('Save failed: ' . print_r($subpermit->getErrors(), true));
            }

            // Capture issuer signature
            /** @var Module $module */
            $module = Craft::$app->getModule('bozp');
            $module->subpermitSignatureService->capture(
                $subpermit,
                SubpermitSignatureService::ROLE_ISSUER,
                $signerName,
                null,
                $signatureDate,
                $signatureData,
            );
        } catch (Throwable $e) {
            Craft::error('Subpermit save failed: ' . $e->getMessage() . "\n" . $e->getTraceAsString(), __METHOD__);
            $msg = (string) Craft::t('bozp', 'Subpermit sa nepodarilo uložiť. Skúste znova.');
            if (Craft::$app->getConfig()->getGeneral()->devMode) {
                $msg .= ' [debug: ' . $e->getMessage() . ']';
            }
            Craft::$app->getSession()->setError($msg);
            return $this->redirect("bozp/permits/{$permitId}/subpermits/new/{$typeValue}");
        }

        Craft::$app->getSession()->setNotice(Craft::t('bozp', 'Subpermit bol uložený a podpísaný.'));
        return $this->redirect("bozp/permits/{$permitId}");
    }

    // -------------------------------------------------------------------------
    // View
    // -------------------------------------------------------------------------

    public function actionView(int $permitId, int $id): Response
    {
        if ($redirect = $this->requireBozpLogin()) {
            return $redirect;
        }

        $permit = $this->findPermit($permitId);
        $subpermit = $this->findSubpermit($id, $permitId);

        $user = Craft::$app->getUser();
        $isIssuer = (int) $permit->issuerId === (int) $user->getId();
        if (!$isIssuer && !$user->checkPermission('bozp:viewAll')) {
            throw new ForbiddenHttpException();
        }

        $subpermitType = SubpermitType::tryFrom($subpermit->type);
        $data = is_string($subpermit->data)
            ? (json_decode($subpermit->data, true) ?? [])
            : (is_array($subpermit->data) ? $subpermit->data : []);

        $canCancel = $isIssuer
            && $subpermit->status === SubpermitStatus::Pending->value;

        /** @var Module $module */
        $module = Craft::$app->getModule('bozp');
        $signatures = $module->subpermitSignatureService->findAllForSubpermit((int) $subpermit->id);

        $this->view->setTemplateMode(View::TEMPLATE_MODE_SITE);

        return $this->renderTemplate('bozp/site/subpermits/view', [
            'permit' => $permit,
            'subpermit' => $subpermit,
            'subpermitType' => $subpermitType,
            'values' => $data,
            'isIssuer' => $isIssuer,
            'canCancel' => $canCancel,
            'signatures' => $signatures,
        ]);
    }

    // -------------------------------------------------------------------------
    // Cancel
    // -------------------------------------------------------------------------

    public function actionCancel(): ?Response
    {
        $this->requirePostRequest();
        if ($redirect = $this->requireBozpLogin()) {
            return $redirect;
        }

        $request = Craft::$app->getRequest();
        $permitId = (int) $request->getRequiredBodyParam('permitId');
        $id = (int) $request->getRequiredBodyParam('id');
        $permit = $this->findPermit($permitId);
        $this->requireIsIssuer($permit);

        $subpermit = $this->findSubpermit($id, $permitId);

        if ($subpermit->status === SubpermitStatus::Approved->value) {
            Craft::$app->getSession()->setError(
                Craft::t('bozp', 'Schválený subpermit nie je možné zrušiť.')
            );
            return $this->redirect("bozp/permits/{$permitId}");
        }

        try {
            $subpermit->status = SubpermitStatus::Cancelled->value;
            $subpermit->cancelledAt = date('Y-m-d H:i:s');
            if (!$subpermit->save()) {
                throw new \RuntimeException('Cancel failed: ' . print_r($subpermit->getErrors(), true));
            }
        } catch (Throwable $e) {
            Craft::error('Subpermit cancel failed: ' . $e->getMessage(), __METHOD__);
            $msg = (string) Craft::t('bozp', 'Subpermit sa nepodarilo zrušiť. Skúste znova.');
            if (Craft::$app->getConfig()->getGeneral()->devMode) {
                $msg .= ' [debug: ' . $e->getMessage() . ']';
            }
            Craft::$app->getSession()->setError($msg);
        }

        Craft::$app->getSession()->setNotice(Craft::t('bozp', 'Subpermit bol zrušený.'));
        return $this->redirect("bozp/permits/{$permitId}");
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function findPermit(int $permitId): PermitRecord
    {
        $permit = PermitRecord::findOne(['id' => $permitId]);
        if (!$permit) {
            throw new NotFoundHttpException('Permit not found.');
        }
        return $permit;
    }

    private function findSubpermit(int $id, int $permitId): SubpermitRecord
    {
        $subpermit = SubpermitRecord::findOne(['id' => $id, 'parentPermitId' => $permitId]);
        if (!$subpermit) {
            throw new NotFoundHttpException('Subpermit not found.');
        }
        return $subpermit;
    }

    private function requireIsIssuer(PermitRecord $permit): void
    {
        $userId = (int) Craft::$app->getUser()->getId();
        if ((int) $permit->issuerId !== $userId) {
            throw new ForbiddenHttpException('Only the permit issuer can manage subpermits.');
        }
    }

    /** @return string[] */
    private function decodeRequiredTypes(PermitRecord $permit): array
    {
        $raw = $permit->requiresHighRisk;
        if (is_string($raw)) {
            $raw = json_decode($raw, true);
        }
        return is_array($raw) ? $raw : [];
    }

    /**
     * Default values for a blank subpermit form.
     * Common fields are pre-populated from the parent permit where possible.
     *
     * @return array<string, mixed>
     */
    private function defaultValues(SubpermitType $type, PermitRecord $permit, string $issuerName): array
    {
        $common = [
            'date'              => date('Y-m-d'),
            'placeOfWork'       => (string) ($permit->workLocation ?? ''),
            'responsiblePerson' => $issuerName,
            'participants'      => (string) ($permit->contractorPersonName ?? ''),
            'workDescription'   => (string) ($permit->workOverview ?? ''),
            'workStep1'         => (string) ($permit->workStep1 ?? ''),
            'workStep2'         => (string) ($permit->workStep2 ?? ''),
            'workStep3'         => (string) ($permit->workStep3 ?? ''),
            'workStep4'         => (string) ($permit->workStep4 ?? ''),
            'workStep5'         => (string) ($permit->workStep5 ?? ''),
        ];

        return array_merge($common, $this->typeDefaults($type));
    }

    /** @return array<string, mixed> */
    private function typeDefaults(SubpermitType $type): array
    {
        return match ($type) {
            SubpermitType::HotWork => [
                'issuedBy' => '', 'jobOrderNumber' => '',
                'workStartTime' => '', 'workExpiryTime' => '',
                'checklist' => [], 'firePatrolName' => '',
                'monitoringHours' => '2', 'additionalMeasures' => '',
            ],
            SubpermitType::ConfinedSpace => [
                'expiryDate' => '', 'expiryTime' => '',
                'emergencyPlan' => '', 'trainingComplete' => '',
                'lotoApplied' => '', 'pumpsBlinded' => '',
                'ventilationRequired' => '',
                'ventilationStartTime' => '', 'ventilationStopTime' => '',
                'o2Percent' => '', 'lelPercent' => '',
                'coPpm' => '', 'h2sPpm' => '',
                'communicationConfirmed' => '',
            ],
            SubpermitType::Heights => [
                'avoidFallPossible' => '', 'fallProtection' => [],
                'fallProtectionOther' => '', 'workerTrained' => '',
                'constructionSuitable' => '', 'fenceRequired' => '',
                'anchorPointUsed' => '', 'anchorPointDescription' => '',
                'equipmentUsed' => [], 'equipmentOther' => '',
                'fallProtectionValidInspection' => '', 'workDiscussed' => '',
            ],
            SubpermitType::CommandB => [
                'riskAssessmentComplete' => '', 'orderNumber' => '',
                'supervisorName' => '', 'groupWorkers' => '',
                'workWithVoltage' => '', 'turnedOffSecured' => '',
                'remainsUnderVoltage' => '', 'orderDeliveredBy' => '',
                'issuedByName' => '', 'receivedByName' => '',
                'checkDeenergizedMethod' => '', 'groundingMethod' => '',
                'workplaceMarking' => '', 'workplaceDefinition' => '',
                'additionalSafetyPrecautions' => '',
                'nearestVoltageParts' => '', 'atmosphericConditions' => '',
            ],
            SubpermitType::Electrical => [
                'mdlzIsQualifiedElectrician' => false,
                'mdlzTrainedToIssue' => false,
                'mdlzWillPerform' => false,
                'thirdPartyRequest' => false,
                'whoPerforms' => '', 'switchboardVoltage' => '',
                'switchboardDesign' => '', 'ssowIssued' => '',
                'workRequest' => '',
                'plannedStartDateTime' => '', 'plannedEndDateTime' => '',
                'reasonWorkUnderVoltage' => '', 'workDescriptionElec' => '',
                'mdlzApplicantName' => '', 'mdlzApplicantRole' => '',
                'thirdPartyName' => '', 'thirdPartyRole' => '',
                'checklist' => [], 'ssowDescription' => '',
                'aedInArea' => '', 'cprTrained' => '', 'aedTrained' => '',
                'areaSupervisorName' => '', 'secondTechnicianName' => '',
            ],
            SubpermitType::Excavation => [
                'workerTrained' => '', 'checklist' => [],
                'meetingName' => '', 'meetingPosition' => '',
                'meetingDateTime' => '',
            ],
            SubpermitType::Lifting => [
                'workplaceEnclosable' => '', 'workerTrained' => '',
                'specialTools' => '', 'otherWorkAffecting' => '',
                'processConditionsSuitable' => '', 'checklist' => [],
                'fallProtectionRequired' => '', 'liftingPlanEstablished' => '',
                'workDiscussed' => '',
            ],
            SubpermitType::Atex => [
                'exZone' => [], 'workplaceSecured' => '',
                'securedMethod' => [], 'additionalMeasures' => '',
                'nonSparkingTools' => '', 'atexPowerTools' => '',
                'antistaticClothing' => '', 'hazardousEnergyPlan' => '',
                'ppeUsed' => [], 'toolsUsed' => [],
                'insulationMethod' => [], 'insulationOther' => '',
                'workStartTime' => '', 'workFinishTime' => '',
            ],
        };
    }

    /**
     * Collect and normalise all POST values for a subpermit form.
     *
     * @return array<string, mixed>
     */
    private function collectValues(SubpermitType $type, \craft\web\Request $request): array
    {
        $str  = fn(string $k, string $d = '') => trim((string) $request->getBodyParam($k, $d));
        $arr  = fn(string $k) => (array) $request->getBodyParam($k, []);
        $bool = fn(string $k) => $request->getBodyParam($k) === 'yes';

        $common = [
            'date'              => $str('date'),
            'placeOfWork'       => $str('placeOfWork'),
            'responsiblePerson' => $str('responsiblePerson'),
            'participants'      => $str('participants'),
            'workDescription'   => $str('workDescription'),
            'workStep1'         => $str('workStep1'),
            'workStep2'         => $str('workStep2'),
            'workStep3'         => $str('workStep3'),
            'workStep4'         => $str('workStep4'),
            'workStep5'         => $str('workStep5'),
        ];

        $specific = match ($type) {
            SubpermitType::HotWork => [
                'issuedBy'         => $str('issuedBy'),
                'jobOrderNumber'   => $str('jobOrderNumber'),
                'workStartTime'    => $str('workStartTime'),
                'workExpiryTime'   => $str('workExpiryTime'),
                'checklist'        => $arr('checklist'),
                'firePatrolName'   => $str('firePatrolName'),
                'monitoringHours'  => $str('monitoringHours'),
                'additionalMeasures' => $str('additionalMeasures'),
            ],
            SubpermitType::ConfinedSpace => [
                'expiryDate'           => $str('expiryDate'),
                'expiryTime'           => $str('expiryTime'),
                'emergencyPlan'        => $str('emergencyPlan'),
                'trainingComplete'     => $str('trainingComplete'),
                'lotoApplied'          => $str('lotoApplied'),
                'pumpsBlinded'         => $str('pumpsBlinded'),
                'ventilationRequired'  => $str('ventilationRequired'),
                'ventilationStartTime' => $str('ventilationStartTime'),
                'ventilationStopTime'  => $str('ventilationStopTime'),
                'o2Percent'            => $str('o2Percent'),
                'lelPercent'           => $str('lelPercent'),
                'coPpm'                => $str('coPpm'),
                'h2sPpm'               => $str('h2sPpm'),
                'communicationConfirmed' => $str('communicationConfirmed'),
            ],
            SubpermitType::Heights => [
                'avoidFallPossible'          => $str('avoidFallPossible'),
                'fallProtection'             => $arr('fallProtection'),
                'fallProtectionOther'        => $str('fallProtectionOther'),
                'workerTrained'              => $str('workerTrained'),
                'constructionSuitable'       => $str('constructionSuitable'),
                'fenceRequired'              => $str('fenceRequired'),
                'anchorPointUsed'            => $str('anchorPointUsed'),
                'anchorPointDescription'     => $str('anchorPointDescription'),
                'equipmentUsed'              => $arr('equipmentUsed'),
                'equipmentOther'             => $str('equipmentOther'),
                'fallProtectionValidInspection' => $str('fallProtectionValidInspection'),
                'workDiscussed'              => $str('workDiscussed'),
            ],
            SubpermitType::CommandB => [
                'riskAssessmentComplete'     => $str('riskAssessmentComplete'),
                'orderNumber'                => $str('orderNumber'),
                'supervisorName'             => $str('supervisorName'),
                'groupWorkers'               => $str('groupWorkers'),
                'workWithVoltage'            => $str('workWithVoltage'),
                'turnedOffSecured'           => $str('turnedOffSecured'),
                'remainsUnderVoltage'        => $str('remainsUnderVoltage'),
                'orderDeliveredBy'           => $str('orderDeliveredBy'),
                'issuedByName'               => $str('issuedByName'),
                'receivedByName'             => $str('receivedByName'),
                'checkDeenergizedMethod'     => $str('checkDeenergizedMethod'),
                'groundingMethod'            => $str('groundingMethod'),
                'workplaceMarking'           => $str('workplaceMarking'),
                'workplaceDefinition'        => $str('workplaceDefinition'),
                'additionalSafetyPrecautions' => $str('additionalSafetyPrecautions'),
                'nearestVoltageParts'        => $str('nearestVoltageParts'),
                'atmosphericConditions'      => $str('atmosphericConditions'),
            ],
            SubpermitType::Electrical => [
                'mdlzIsQualifiedElectrician' => $request->getBodyParam('mdlzIsQualifiedElectrician') === '1',
                'mdlzTrainedToIssue'         => $request->getBodyParam('mdlzTrainedToIssue') === '1',
                'mdlzWillPerform'            => $request->getBodyParam('mdlzWillPerform') === '1',
                'thirdPartyRequest'          => $request->getBodyParam('thirdPartyRequest') === '1',
                'whoPerforms'                => $str('whoPerforms'),
                'switchboardVoltage'         => $str('switchboardVoltage'),
                'switchboardDesign'          => $str('switchboardDesign'),
                'ssowIssued'                 => $str('ssowIssued'),
                'workRequest'                => $str('workRequest'),
                'plannedStartDateTime'       => $str('plannedStartDateTime'),
                'plannedEndDateTime'         => $str('plannedEndDateTime'),
                'reasonWorkUnderVoltage'     => $str('reasonWorkUnderVoltage'),
                'workDescriptionElec'        => $str('workDescriptionElec'),
                'mdlzApplicantName'          => $str('mdlzApplicantName'),
                'mdlzApplicantRole'          => $str('mdlzApplicantRole'),
                'thirdPartyName'             => $str('thirdPartyName'),
                'thirdPartyRole'             => $str('thirdPartyRole'),
                'checklist'                  => $arr('checklist'),
                'ssowDescription'            => $str('ssowDescription'),
                'aedInArea'                  => $str('aedInArea'),
                'cprTrained'                 => $str('cprTrained'),
                'aedTrained'                 => $str('aedTrained'),
                'areaSupervisorName'         => $str('areaSupervisorName'),
                'secondTechnicianName'       => $str('secondTechnicianName'),
            ],
            SubpermitType::Excavation => [
                'workerTrained'   => $str('workerTrained'),
                'checklist'       => $arr('checklist'),
                'meetingName'     => $str('meetingName'),
                'meetingPosition' => $str('meetingPosition'),
                'meetingDateTime' => $str('meetingDateTime'),
            ],
            SubpermitType::Lifting => [
                'workplaceEnclosable'    => $str('workplaceEnclosable'),
                'workerTrained'          => $str('workerTrained'),
                'specialTools'           => $str('specialTools'),
                'otherWorkAffecting'     => $str('otherWorkAffecting'),
                'processConditionsSuitable' => $str('processConditionsSuitable'),
                'checklist'              => $arr('checklist'),
                'fallProtectionRequired' => $str('fallProtectionRequired'),
                'liftingPlanEstablished' => $str('liftingPlanEstablished'),
                'workDiscussed'          => $str('workDiscussed'),
            ],
            SubpermitType::Atex => [
                'exZone'              => $arr('exZone'),
                'workplaceSecured'    => $str('workplaceSecured'),
                'securedMethod'       => $arr('securedMethod'),
                'additionalMeasures'  => $str('additionalMeasures'),
                'nonSparkingTools'    => $str('nonSparkingTools'),
                'atexPowerTools'      => $str('atexPowerTools'),
                'antistaticClothing'  => $str('antistaticClothing'),
                'hazardousEnergyPlan' => $str('hazardousEnergyPlan'),
                'ppeUsed'             => $arr('ppeUsed'),
                'toolsUsed'           => $arr('toolsUsed'),
                'insulationMethod'    => $arr('insulationMethod'),
                'insulationOther'     => $str('insulationOther'),
                'workStartTime'       => $str('workStartTime'),
                'workFinishTime'      => $str('workFinishTime'),
            ],
        };

        return array_merge($common, $specific);
    }

    /**
     * Basic validation — only common required fields enforced here.
     * Type-specific required fields can be added as needed.
     *
     * @param array<string, mixed> $values
     * @return array<string, string>
     */
    private function validate(array $values): array
    {
        $errors = [];
        if (empty($values['date'])) {
            $errors['date'] = (string) Craft::t('bozp', 'Dátum je povinný.');
        }
        if (empty($values['placeOfWork'])) {
            $errors['placeOfWork'] = (string) Craft::t('bozp', 'Miesto výkonu je povinné.');
        }
        if (empty($values['responsiblePerson'])) {
            $errors['responsiblePerson'] = (string) Craft::t('bozp', 'Zodpovedná osoba je povinná.');
        }
        return $errors;
    }
}
