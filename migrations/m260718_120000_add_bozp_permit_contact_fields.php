<?php

namespace craft\contentmigrations;

use craft\db\Migration;

/**
 * m260718_120000_add_bozp_permit_contact_fields migration.
 *
 * Adds contractor contact detail columns to bozp_permits:
 *   - contactPersonPhone  (required in the permit form)
 *   - contractorPhone     (required in the permit form)
 *   - workersNames        (optional free-text list of workers)
 */
class m260718_120000_add_bozp_permit_contact_fields extends Migration
{
    public function safeUp(): bool
    {
        $table = '{{%bozp_permits}}';

        if (!$this->db->columnExists($table, 'contactPersonPhone')) {
            $this->addColumn($table, 'contactPersonPhone', $this->string(50)->null()->after('contractorEmail'));
        }
        if (!$this->db->columnExists($table, 'contractorPhone')) {
            $this->addColumn($table, 'contractorPhone', $this->string(50)->null()->after('contactPersonPhone'));
        }
        if (!$this->db->columnExists($table, 'workersNames')) {
            $this->addColumn($table, 'workersNames', $this->text()->null()->after('contractorPhone'));
        }

        return true;
    }

    public function safeDown(): bool
    {
        $table = '{{%bozp_permits}}';

        foreach (['workersNames', 'contractorPhone', 'contactPersonPhone'] as $column) {
            if ($this->db->columnExists($table, $column)) {
                $this->dropColumn($table, $column);
            }
        }

        return true;
    }
}
