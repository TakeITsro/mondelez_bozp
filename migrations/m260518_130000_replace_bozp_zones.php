<?php

declare(strict_types=1);

namespace craft\contentmigrations;

use craft\db\Migration;
use craft\helpers\StringHelper;

/**
 * Replace the placeholder Zone 1..5 seed with the canonical 19-zone list
 * for the Mondelez Bratislava facility.
 *
 * Safe to run whether or not m260518_120000 has been applied:
 *   - DELETE on bozp_zones cascades to bozp_permits.zoneId via ON DELETE SET NULL,
 *     so existing permits temporarily lose their zone link.
 *   - All permits are then re-assigned to the first new zone (Courtyard).
 */
class m260518_130000_replace_bozp_zones extends Migration
{
    /**
     * Canonical zone list. Order matters — it drives sortOrder in the dropdown
     * and on the map. `code` doubles as a CSS-friendly slug for per-zone styling.
     */
    private const ZONES = [
        ['Courtyard',                'courtyard'],
        ['Main corridor',            'main-corridor'],
        ['Carton warehouse',         'carton-warehouse'],
        ['Dambach',                  'dambach'],
        ['Ares',                     'ares'],
        ['Offices',                  'offices'],
        ['Raw material warehouse',   'raw-material-warehouse'],
        ['Feedstock',                'feedstock'],
        ['CR1 Lamination',           'cr1-lamination'],
        ['CR1 Injection',            'cr1-injection'],
        ['CR1 Packaging',            'cr1-packaging'],
        ['BR Grotte',                'br-grotte'],
        ['BR Lamination',            'br-lamination'],
        ['BR Packaging',             'br-packaging'],
        ['Crumb',                    'crumb'],
        ['CR2 Packaging',            'cr2-packaging'],
        ['CR2 Injection',            'cr2-injection'],
        ['CR2 Lamination',           'cr2-lamination'],
        ['Water treatment plant',    'water-treatment-plant'],
    ];

    public function safeUp(): bool
    {
        $now = date('Y-m-d H:i:s');

        // 1. Wipe the existing seed (FK SET NULL clears permit.zoneId).
        $this->delete('{{%bozp_zones}}');

        // 2. Insert the 19 canonical zones.
        $sortOrder = 1;
        foreach (self::ZONES as [$name, $code]) {
            $this->insert('{{%bozp_zones}}', [
                'name'        => $name,
                'code'        => $code,
                'description' => null,
                'geometry'    => null,
                'sortOrder'   => $sortOrder++,
                'archived'    => false,
                'dateCreated' => $now,
                'dateUpdated' => $now,
                'uid'         => StringHelper::UUID(),
            ]);
        }

        // 3. Backfill permits to the first new zone (Courtyard).
        $courtyardId = (new \yii\db\Query())
            ->select('id')
            ->from('{{%bozp_zones}}')
            ->where(['code' => 'courtyard'])
            ->scalar($this->db);

        if ($courtyardId) {
            $this->update('{{%bozp_permits}}', ['zoneId' => (int) $courtyardId], '1=1');
        }

        return true;
    }

    public function safeDown(): bool
    {
        // Re-seed Zone 1..5 to leave the DB in the state m260518_120000 produced.
        $this->delete('{{%bozp_zones}}');
        $now = date('Y-m-d H:i:s');
        for ($i = 1; $i <= 5; $i++) {
            $this->insert('{{%bozp_zones}}', [
                'name'        => "Zone {$i}",
                'code'        => "Z{$i}",
                'description' => null,
                'geometry'    => null,
                'sortOrder'   => $i,
                'archived'    => false,
                'dateCreated' => $now,
                'dateUpdated' => $now,
                'uid'         => StringHelper::UUID(),
            ]);
        }
        return true;
    }
}
