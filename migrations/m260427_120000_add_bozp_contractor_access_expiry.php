<?php

namespace craft\contentmigrations;

use craft\db\Migration;

/**
 * Adds accessExpiresAt to bozp_permits.
 *
 * Once a permit is approved, the contractor receives a QR + password
 * link. That link must stop working after the permit's validity ends —
 * accessExpiresAt is set to validTo at approval time and checked on
 * every contractor request.
 */
class m260427_120000_add_bozp_contractor_access_expiry extends Migration
{
    public function safeUp(): bool
    {
        if (!$this->db->columnExists('{{%bozp_permits}}', 'accessExpiresAt')) {
            $this->addColumn(
                '{{%bozp_permits}}',
                'accessExpiresAt',
                $this->dateTime()->null()->after('accessPasswordHash'),
            );
        }
        return true;
    }

    public function safeDown(): bool
    {
        if ($this->db->columnExists('{{%bozp_permits}}', 'accessExpiresAt')) {
            $this->dropColumn('{{%bozp_permits}}', 'accessExpiresAt');
        }
        return true;
    }
}
