<?php

declare(strict_types=1);

namespace modules\bozp\controllers;

use Craft;
use craft\web\View;
use modules\bozp\enums\PermitStatus;
use modules\bozp\records\PermitRecord;
use modules\bozp\records\ZoneRecord;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * MapController
 *
 * Front-end facility map. Renders an SVG of the production site overlaid with
 * five zone hot-spots. Each zone shows a live count of "active" permits
 * (statuses: approved, signed, active, pending_closure). Clicking a zone
 * opens a list of the permits currently in that zone.
 *
 * Access: anyone with bozp:viewMap (granted to issuers and HSE officers).
 */
class MapController extends BaseSiteController
{
    public array|bool|int $allowAnonymous = false;

    /**
     * Permit statuses counted as "currently on-site" for the map badges.
     */
    private const ACTIVE_STATUSES = [
        PermitStatus::Approved,
        PermitStatus::Signed,
        PermitStatus::Active,
        PermitStatus::PendingClosure,
    ];

    public function actionIndex(): Response
    {
        if ($redirect = $this->requireBozpLogin()) {
            return $redirect;
        }
        $this->requirePermission('bozp:viewMap');

        $zones = ZoneRecord::find()
            ->where(['archived' => false])
            ->orderBy(['sortOrder' => SORT_ASC, 'name' => SORT_ASC])
            ->all();

        $counts = $this->activePermitCountsByZone();

        $this->view->setTemplateMode(View::TEMPLATE_MODE_SITE);
        return $this->renderTemplate('bozp/site/map', [
            'zones'  => $zones,
            'counts' => $counts,
        ]);
    }

    public function actionZone(int $id): Response
    {
        if ($redirect = $this->requireBozpLogin()) {
            return $redirect;
        }
        $this->requirePermission('bozp:viewMap');

        $zone = ZoneRecord::findOne(['id' => $id]);
        if (!$zone) {
            throw new NotFoundHttpException("Zone #{$id} not found.");
        }

        $statusValues = array_map(static fn(PermitStatus $s) => $s->value, self::ACTIVE_STATUSES);

        /** @var PermitRecord[] $permits */
        $permits = PermitRecord::find()
            ->where(['zoneId' => $id, 'status' => $statusValues])
            ->orderBy(['dateCreated' => SORT_DESC])
            ->all();

        $this->view->setTemplateMode(View::TEMPLATE_MODE_SITE);
        return $this->renderTemplate('bozp/site/map-zone', [
            'zone'    => $zone,
            'permits' => $permits,
        ]);
    }

    /**
     * Return [zoneId => count] for permits currently in an "active" status.
     * Zones with no active permits are present with count 0.
     *
     * @return array<int, int>
     */
    private function activePermitCountsByZone(): array
    {
        $statusValues = array_map(static fn(PermitStatus $s) => $s->value, self::ACTIVE_STATUSES);

        $rows = (new \yii\db\Query())
            ->select(['zoneId', 'cnt' => 'COUNT(*)'])
            ->from('{{%bozp_permits}}')
            ->where(['status' => $statusValues])
            ->andWhere(['not', ['zoneId' => null]])
            ->groupBy(['zoneId'])
            ->all(Craft::$app->getDb());

        $counts = [];
        foreach ($rows as $r) {
            $counts[(int) $r['zoneId']] = (int) $r['cnt'];
        }
        return $counts;
    }
}
