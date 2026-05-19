<?php

declare(strict_types=1);

namespace craft\contentmigrations;

use craft\db\Migration;
use craft\helpers\StringHelper;

/**
 * Replace the many-to-many bozp_permit_zones join with a single zoneId column
 * on bozp_permits, and seed five predefined zones (Zone 1 .. Zone 5).
 *
 * All existing permits are migrated to Zone 1 (per project decision — dev data).
 */
class m260518_120000_add_bozp_permit_zone_id extends Migration
{
    public function safeUp(): bool
    {
        $now = date('Y-m-d H:i:s');

        // 1. Reset & seed zones ---------------------------------------------
        // Delete any existing zones so the seeded set is canonical.
        $this->delete('{{%bozp_zones}}');

        $zones = [];
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
            $zones[$i] = (int) $this->db->getLastInsertID('{{%bozp_zones}}_id_seq');
        }

        // MySQL fallback: getLastInsertID without sequence
        if (!$zones[1]) {
            $rows = (new \yii\db\Query())
                ->select(['id', 'code'])
                ->from('{{%bozp_zones}}')
                ->orderBy(['sortOrder' => SORT_ASC])
                ->all($this->db);
            foreach ($rows as $r) {
                $n = (int) substr((string) $r['code'], 1);
                if ($n >= 1 && $n <= 5) {
                    $zones[$n] = (int) $r['id'];
                }
            }
        }

        $zone1Id = $zones[1] ?? null;

        // 2. Add zoneId column to bozp_permits ------------------------------
        if (!$this->db->columnExists('{{%bozp_permits}}', 'zoneId')) {
            $this->addColumn(
                '{{%bozp_permits}}',
                'zoneId',
                $this->integer()->null()->defaultValue(null)->after('workLocation')
            );
            $this->createIndex(null, '{{%bozp_permits}}', ['zoneId']);
            $this->addForeignKey(
                null,
                '{{%bozp_permits}}',
                ['zoneId'],
                '{{%bozp_zones}}',
                ['id'],
                'SET NULL',
                'CASCADE'
            );
        }

        // 3. Backfill all existing permits to Zone 1 ------------------------
        if ($zone1Id !== null) {
            $this->update('{{%bozp_permits}}', ['zoneId' => $zone1Id], '1=1');
        }

        // 4. Drop the legacy join table -------------------------------------
        if ($this->db->tableExists('{{%bozp_permit_zones}}')) {
            $this->dropTableIfExists('{{%bozp_permit_zones}}');
        }

        return true;
    }

    public function safeDown(): bool
    {
        // Recreate join table
        if (!$this->db->tableExists('{{%bozp_permit_zones}}')) {
            $this->createTable('{{%bozp_permit_zones}}', [
                'id'          => $this->primaryKey(),
                'permitId'    => $this->integer()->notNull(),
                'zoneId'      => $this->integer()->notNull(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid'         => $this->uid(),
            ]);
            $this->createIndex(null, '{{%bozp_permit_zones}}', ['permitId', 'zoneId'], true);
            $this->addForeignKey(null, '{{%bozp_permit_zones}}', ['permitId'], '{{%bozp_permits}}', ['id'], 'CASCADE', 'CASCADE');
            $this->addForeignKey(null, '{{%bozp_permit_zones}}', ['zoneId'], '{{%bozp_zones}}', ['id'], 'CASCADE', 'CASCADE');
        }

        if ($this->db->columnExists('{{%bozp_permits}}', 'zoneId')) {
            $this->dropColumn('{{%bozp_permits}}', 'zoneId');
        }

        return true;
    }
}
