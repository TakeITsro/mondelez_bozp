<?php

declare(strict_types=1);

namespace modules\bozp\controllers;

use craft\web\Controller;
use craft\web\View;
use modules\bozp\enums\PermitStatus;
use modules\bozp\records\PermitRecord;
use yii\web\Response;

/**
 * HSE approval queue — landing page for "BOZP Permity" in the CP.
 */
class QueueController extends Controller
{
    public function actionIndex(): Response
    {
        $this->requireLogin();
        $this->requirePermission('bozp:viewQueue');

        $pendingPermits = PermitRecord::find()
            ->where(['status' => PermitStatus::Submitted->value])
            ->orderBy(['submittedAt' => SORT_ASC])
            ->limit(50)
            ->all();

        $pendingCount = (int) PermitRecord::find()
            ->where(['status' => PermitStatus::Submitted->value])
            ->count();

        $this->view->setTemplateMode(View::TEMPLATE_MODE_CP);

        return $this->renderTemplate('bozp/cp/queue', [
            'pendingCount' => $pendingCount,
            'pendingPermits' => $pendingPermits,
        ]);
    }
}
