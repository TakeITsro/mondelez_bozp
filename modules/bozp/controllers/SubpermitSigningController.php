<?php

declare(strict_types=1);

namespace modules\bozp\controllers;

use Craft;
use craft\web\Controller;
use craft\web\View;
use modules\bozp\enums\SubpermitSigningRole;
use modules\bozp\Module;
use modules\bozp\records\PermitRecord;
use modules\bozp\records\SubpermitRecord;
use modules\bozp\records\SubpermitSigningRequestRecord;
use Throwable;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * SubpermitSigningController
 *
 * Fully public — no Craft login required.
 * Signers receive a unique token URL by email and sign from any device.
 *
 * Routes (registered in Module::registerSiteUrlRules):
 *   GET  bozp/sp-sign/<token>       → actionView
 *   POST bozp/sp-sign/sign          → actionSign  (token in POST body)
 */
class SubpermitSigningController extends Controller
{
    public array|bool|int $allowAnonymous = true;
    public $enableCsrfValidation = false;

    // -------------------------------------------------------------------------
    // GET — show signing form (or already-signed confirmation)
    // -------------------------------------------------------------------------

    public function actionView(string $token): Response
    {
        /** @var Module $module */
        $module  = Craft::$app->getModule('bozp');
        $request = $module->subpermitSigningService->findByToken($token);

        if (!$request) {
            throw new NotFoundHttpException('Neplatný alebo expirovaný odkaz na podpis.');
        }

        [$subpermit, $permit] = $this->loadContext((int) $request->subpermitId);
        $role = SubpermitSigningRole::tryFrom($request->role);
        $data = $this->decodeSubpermitData($subpermit);

        Craft::$app->getView()->setTemplateMode(View::TEMPLATE_MODE_SITE);

        return $this->renderTemplate('bozp/site/subpermit-sign', [
            'request'      => $request,
            'subpermit'    => $subpermit,
            'permit'       => $permit,
            'role'         => $role,
            'data'         => $data,
            'alreadySigned' => $request->signedAt !== null,
            'errors'       => [],
            'values'       => [],
        ]);
    }

    // -------------------------------------------------------------------------
    // POST — process signature
    // -------------------------------------------------------------------------

    public function actionSign(): Response
    {
        $this->requirePostRequest();

        $req           = Craft::$app->getRequest();
        $token         = trim((string) $req->getRequiredBodyParam('token'));
        $signerName    = trim((string) $req->getBodyParam('signerName', ''));
        $signatureData = (string) $req->getBodyParam('signatureData', '');

        /** @var Module $module */
        $module         = Craft::$app->getModule('bozp');
        $signingRequest = $module->subpermitSigningService->findByToken($token);

        if (!$signingRequest) {
            throw new NotFoundHttpException('Neplatný alebo expirovaný odkaz na podpis.');
        }

        [$subpermit, $permit] = $this->loadContext((int) $signingRequest->subpermitId);
        $role = SubpermitSigningRole::tryFrom($signingRequest->role);
        $data = $this->decodeSubpermitData($subpermit);

        // Client-side validation
        $errors = [];
        if ($signingRequest->signedAt !== null) {
            $errors['general'] = (string) Craft::t('bozp', 'Tento odkaz bol už použitý na podpis.');
        }
        if ($signerName === '') {
            $errors['signerName'] = (string) Craft::t('bozp', 'Meno je povinné.');
        }
        if (!preg_match('#^data:image/png;base64,#', $signatureData)) {
            $errors['signatureData'] = (string) Craft::t('bozp', 'Podpis je povinný.');
        }

        if ($errors !== []) {
            Craft::$app->getView()->setTemplateMode(View::TEMPLATE_MODE_SITE);
            return $this->renderTemplate('bozp/site/subpermit-sign', [
                'request'      => $signingRequest,
                'subpermit'    => $subpermit,
                'permit'       => $permit,
                'role'         => $role,
                'data'         => $data,
                'alreadySigned' => false,
                'errors'       => $errors,
                'values'       => ['signerName' => $signerName],
            ]);
        }

        try {
            $module->subpermitSigningService->processSignature(
                $token,
                $signerName,
                $signatureData,
                (string) ($req->getUserIP() ?? ''),
            );
        } catch (Throwable $e) {
            Craft::error('BOZP subpermit sign failed: ' . $e->getMessage(), __METHOD__);
            Craft::$app->getView()->setTemplateMode(View::TEMPLATE_MODE_SITE);
            return $this->renderTemplate('bozp/site/subpermit-sign', [
                'request'      => $signingRequest,
                'subpermit'    => $subpermit,
                'permit'       => $permit,
                'role'         => $role,
                'data'         => $data,
                'alreadySigned' => false,
                'errors'       => ['general' => Craft::t('bozp', 'Uloženie podpisu zlyhalo. Skúste znova.')],
                'values'       => ['signerName' => $signerName],
            ]);
        }

        // Success — re-render with alreadySigned=true
        Craft::$app->getView()->setTemplateMode(View::TEMPLATE_MODE_SITE);
        return $this->renderTemplate('bozp/site/subpermit-sign', [
            'request'      => $signingRequest,
            'subpermit'    => $subpermit,
            'permit'       => $permit,
            'role'         => $role,
            'data'         => $data,
            'alreadySigned' => true,
            'errors'       => [],
            'values'       => [],
        ]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /** @return array{SubpermitRecord, PermitRecord} */
    private function loadContext(int $subpermitId): array
    {
        $subpermit = SubpermitRecord::findOne(['id' => $subpermitId]);
        if (!$subpermit) {
            throw new NotFoundHttpException('Subpermit not found.');
        }
        $permit = PermitRecord::findOne(['id' => $subpermit->parentPermitId]);
        if (!$permit) {
            throw new NotFoundHttpException('Parent permit not found.');
        }
        return [$subpermit, $permit];
    }

    /** @return array<string, mixed> */
    private function decodeSubpermitData(SubpermitRecord $subpermit): array
    {
        $raw = $subpermit->data;
        if (is_string($raw)) {
            return json_decode($raw, true) ?? [];
        }
        return is_array($raw) ? $raw : [];
    }
}
