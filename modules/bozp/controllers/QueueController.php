<?php

declare(strict_types=1);

namespace modules\bozp\controllers;

use Craft;
use craft\elements\User;
use craft\web\Controller;
use craft\web\View;
use modules\bozp\enums\HazardCategory;
use modules\bozp\enums\PermitStatus;
use modules\bozp\Module;
use modules\bozp\records\AuditLogRecord;
use modules\bozp\records\PermitAttachmentRecord;
use modules\bozp\records\PermitHazardRecord;
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

    public function actionAll(): Response
    {
        $this->requireLogin();
        $this->requirePermission('bozp:viewAll');

        $request = Craft::$app->getRequest();
        $statusFilter = (string) $request->getQueryParam('status', '');

        $validStatuses = array_map(static fn (PermitStatus $s) => $s->value, PermitStatus::cases());

        $query = PermitRecord::find()->orderBy(['dateCreated' => SORT_DESC])->limit(200);

        if ($statusFilter !== '' && in_array($statusFilter, $validStatuses, true)) {
            $query->andWhere(['status' => $statusFilter]);
        }

        $permits = $query->all();

        $this->view->setTemplateMode(View::TEMPLATE_MODE_CP);

        return $this->renderTemplate('bozp/cp/all-permits', [
            'permits' => $permits,
            'statusFilter' => $statusFilter,
            'validStatuses' => $validStatuses,
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
            'hazardCategories' => HazardCategory::pdfOrder(),
            'hazards' => $this->loadHazardsFor((int) $permit->id),
            'attachments' => $this->loadAttachmentsFor((int) $permit->id),
            'canApprove' => Craft::$app->getUser()->checkPermission('bozp:approve'),
            'canDelete' => Craft::$app->getUser()->checkPermission('bozp:deletePermit'),
        ]);
    }

    /** @return PermitAttachmentRecord[] */
    private function loadAttachmentsFor(int $permitId): array
    {
        return PermitAttachmentRecord::find()
            ->where(['permitId' => $permitId])
            ->orderBy(['dateCreated' => SORT_DESC])
            ->all();
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

    /**
     * Resend the approval / rejection notification for a permit.
     * Approval resend regenerates the contractor token + password
     * (per requirement); rejection resend just re-sends the existing
     * reason from the audit trail / approvalComment.
     */
    public function actionResend(int $id): ?Response
    {
        $this->requirePostRequest();
        $this->requireLogin();
        $this->requirePermission('bozp:approve');

        $permit = $this->findPermit($id);

        /** @var Module $module */
        $module = Craft::$app->getModule('bozp');

        try {
            if ($permit->status === PermitStatus::Approved->value
                || $permit->status === PermitStatus::Signed->value
                || $permit->status === PermitStatus::Active->value
            ) {
                $newPassword = $module->permitWorkflow->regenerateContractorAccess($permit);
                $module->permitMailer->notifyParticipantsOfApproval($permit, $newPassword);
                $msg = (string) Craft::t(
                    'bozp',
                    'Notifikácia o schválení bola znova odoslaná. Vygenerované nové prístupové údaje pre dodávateľa.'
                );
            } elseif ($permit->status === PermitStatus::Rejected->value) {
                $reason = (string) ($permit->approvalComment ?? '');
                $module->permitMailer->notifyParticipantsOfRejection($permit, $reason);
                $msg = (string) Craft::t('bozp', 'Notifikácia o zamietnutí bola znova odoslaná.');
            } else {
                Craft::$app->getSession()->setError(
                    Craft::t('bozp', 'Notifikáciu možno znova odoslať len pre schválené alebo zamietnuté permity.')
                );
                return $this->redirect("bozp/permit/{$permit->id}");
            }
        } catch (Throwable $e) {
            Craft::error('Permit resend failed: ' . $e->getMessage(), __METHOD__);
            $error = (string) Craft::t('bozp', 'Notifikáciu sa nepodarilo odoslať.');
            if (Craft::$app->getConfig()->getGeneral()->devMode) {
                $error .= ' [dev] ' . $e->getMessage();
            }
            Craft::$app->getSession()->setError($error);
            return $this->redirect("bozp/permit/{$permit->id}");
        }

        Craft::$app->getSession()->setNotice($msg);
        return $this->redirect("bozp/permit/{$permit->id}");
    }

    /**
     * Hard-delete a permit. Requires bozp:deletePermit. The DB FK
     * definitions cascade-delete dependent rows (zones, hazards,
     * signatures, attachments); audit log rows survive (SET NULL on
     * permitId) so the trail isn't fully erased.
     */
    public function actionDelete(int $id): ?Response
    {
        $this->requirePostRequest();
        $this->requireLogin();
        $this->requirePermission('bozp:deletePermit');

        $permit = $this->findPermit($id);
        $number = $permit->permitNumber;

        try {
            if (!$permit->delete()) {
                throw new \RuntimeException('Failed to delete permit row.');
            }
        } catch (Throwable $e) {
            Craft::error('Permit delete failed: ' . $e->getMessage(), __METHOD__);
            $msg = (string) Craft::t('bozp', 'Permit sa nepodarilo zmazať.');
            if (Craft::$app->getConfig()->getGeneral()->devMode) {
                $msg .= ' [dev] ' . $e->getMessage();
            }
            Craft::$app->getSession()->setError($msg);
            return $this->redirect("bozp/permit/{$id}");
        }

        Craft::$app->getSession()->setNotice(
            Craft::t('bozp', 'Permit {n} bol zmazaný.', ['n' => $number])
        );
        return $this->redirect('bozp/all');
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
