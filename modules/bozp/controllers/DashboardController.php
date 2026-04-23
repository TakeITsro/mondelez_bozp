<?php

declare(strict_types=1);

namespace modules\bozp\controllers;

use Craft;
use craft\web\Controller;
use craft\web\View;
use modules\bozp\enums\PermitStatus;
use modules\bozp\records\PermitRecord;
use yii\web\Response;

/**
 * DashboardController
 *
 * Front-end (site request) employee dashboard. Visible at /bozp.
 * Lists the logged-in user's recent permits and provides the entry point
 * to create a new one.
 *
 * Front-end == outside the CP. Anyone with a Craft account that has
 * `bozp:createPermit` lands here. HSE officers also use the CP queue
 * for approvals; this dashboard is the issuer's view.
 */
class DashboardController extends Controller
{
    protected array|bool|int $allowAnonymous = false;

    public function actionIndex(): Response
    {
        $this->requireLogin();
        $this->requirePermission('bozp:createPermit');

        $userId = Craft::$app->getUser()->getId();

        $myPermits = PermitRecord::find()
            ->where(['issuerId' => $userId])
            ->orderBy(['dateCreated' => SORT_DESC])
            ->limit(25)
            ->all();

        $this->view->setTemplateMode(View::TEMPLATE_MODE_SITE);

        return $this->renderTemplate('bozp/site/dashboard', [
            'myPermits' => $myPermits,
            'statusEnum' => PermitStatus::class,
        ]);
    }
}
