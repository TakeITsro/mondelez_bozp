<?php

declare(strict_types=1);

namespace modules\bozp\controllers;

use Craft;
use craft\elements\User;
use craft\web\Controller;
use craft\web\View;
use modules\bozp\enums\PermitStatus;
use modules\bozp\Module;
use modules\bozp\records\AuditLogRecord;
use modules\bozp\records\PermitRecord;
use modules\bozp\records\ZoneRecord;
use Throwable;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * HSE approval queue — landing page for "BOZP Permity" in the CP.
 *
 * Phase 2C.1 adds:
 *   actionView($id)    — permit detail view
 *   actionApprove($id) — POST approve (requires bozp:approve)
 *   actionReject($id)  — POST reject with comment
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

    public function actionView(int $id): Response
    {
        $this->requireLogin();
        $this->requirePermission('bozp:viewQueue');

        $permit = $this->findPermit($id);

        $zones = $this->loadZonesFor($permit->id);
        $issuer = $permit->issuerId ? User::find()->id($permit->issuerId)->one() : null;
        $approver = $permit->approverId ? User::find()->id($permit->approverId)->one() : null;
        $auditEntries = AuditLogRecord::find()
            ->where(['permitId' => $permit->id])
            ->orderBy(['dateCreated' => SORT_DESC])
            ->limit(50)
            ->all();

        $this->view->setTemplateMode(View::TEMPLATE_MODE_CP);

        return $this->renderTemplate('bozp/cp/permit-view', [
            'permit' => $permit,
            'zones' => $zones,
            'issuer' => $issuer,
            'approver' => $approver,
            'auditEntries' => $auditEntries,
            'canApprove' => Craft::$app->getUser()->checkPermission('bozp:approve'),
        ]);
    }

    public function actionApprove(): ?Response
    {
        $this->requirePostRequest();
        $this->requireLogin();
        $this->requirePermission('bozp:approve');

        $id = (int) Craft::$app->getRequest()->getRequiredBodyParam('id');
        $permit = $this->findPermit($id);

        if ($permit->status !== PermitStatus::Submitted->value) {
            Craft::$app->getSession()->setError(Craft::t('bozp', 'Permit nie je v stave na schválenie.'));
            return $this->redirect("bozp/permit/{$permit->id}");
        }

        $comment = trim((string) Craft::$app->getRequest()->getBodyParam('comment', ''));
        $userId = Craft::$app->getUser()->getId();

        /** @var Module $module */
        $module = Craft::$app->getModule('bozp');

        $db = Craft::$app->getDb();
        $transaction = $db->beginTransaction();

        try {
            $module->permitWorkflow->approve($permit, $userId, $comment !== '' ? $comment : null);
            $transaction->commit();
        } catch (Throwable $e) {
            $transaction->rollBack();
            Craft::error('Permit approve failed: ' . $e->getMessage(), __METHOD__);

            $message = (string) Craft::t('bozp', 'Permit sa nepodarilo schváliť. Skúste znova.');
            if (Craft::$app->getConfig()->getGeneral()->devMode) {
                $message .= ' [dev] ' . $e->getMessage();
            }
            Craft::$app->getSession()->setError($message);
            return $this->redirect("bozp/permit/{$permit->id}");
        }

        Craft::$app->getSession()->setNotice(Craft::t('bozp', 'Permit {n} bol schválený.', ['n' => $permit->permitNumber]));
        return $this->redirect('bozp');
    }

    public function actionReject(): ?Response
    {
        $this->requirePostRequest();
        $this->requireLogin();
        $this->requirePermission('bozp:approve');

        $id = (int) Craft::$app->getRequest()->getRequiredBodyParam('id');
        $permit = $this->findPermit($id);

        if ($permit->status !== PermitStatus::Submitted->value) {
            Craft::$app->getSession()->setError(Craft::t('bozp', 'Permit nie je v stave na zamietnutie.'));
            return $this->redirect("bozp/permit/{$permit->id}");
        }

        $comment = trim((string) Craft::$app->getRequest()->getBodyParam('comment', ''));

        if ($comment === '') {
            Craft::$app->getSession()->setError(Craft::t('bozp', 'Pri zamietnutí je komentár povinný.'));
            return $this->redirect("bozp/permit/{$permit->id}");
        }

        $userId = Craft::$app->getUser()->getId();

        /** @var Module $module */
        $module = Craft::$app->getModule('bozp');

        $db = Craft::$app->getDb();
        $transaction = $db->beginTransaction();

        try {
            $module->permitWorkflow->reject($permit, $userId, $comment);
            $transaction->commit();
        } catch (Throwable $e) {
            $transaction->rollBack();
            Craft::error('Permit reject failed: ' . $e->getMessage(), __METHOD__);

            $message = (string) Craft::t('bozp', 'Permit sa nepodarilo zamietnuť. Skúste znova.');
            if (Craft::$app->getConfig()->getGeneral()->devMode) {
                $message .= ' [dev] ' . $e->getMessage();
            }
            Craft::$app->getSession()->setError($message);
            return $this->redirect("bozp/permit/{$permit->id}");
        }

        Craft::$app->getSession()->setNotice(Craft::t('bozp', 'Permit {n} bol zamietnutý.', ['n' => $permit->permitNumber]));
        return $this->redirect('bozp');
    }

    private function findPermit(int $id): PermitRecord
    {
        $permit = PermitRecord::findOne(['id' => $id]);
        if (!$permit) {
            throw new NotFoundHttpException('Permit not found.');
        }
        return $permit;
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
}
