<?php

namespace craft\contentmigrations;

use craft\db\Migration;

/**
 * Adds expirationWarningSentAt to bozp_permits and bozp_subpermits.
 *
 * Used by the bozp/notify/expiring-* console actions to ensure each row is
 * warned at most once. The cron picks rows whose expiry falls inside the
 * warning window AND whose expirationWarningSentAt IS NULL.
 */
class m260526_140000_add_bozp_expiration_warning_sent extends Migration
{
    public function safeUp(): bool
    {
        if (!$this->db->columnExists('{{%bozp_permits}}', 'expirationWarningSentAt')) {
            $this->addColumn(
                '{{%bozp_permits}}',
                'expirationWarningSentAt',
                $this->dateTime()->null()->after('validTo'),
            );
        }
        if (!$this->db->columnExists('{{%bozp_subpermits}}', 'expirationWarningSentAt')) {
            $this->addColumn(
                '{{%bozp_subpermits}}',
                'expirationWarningSentAt',
                $this->dateTime()->null()->after('expiresAt'),
            );
        }
        return true;
    }

    public function safeDown(): bool
    {
        if ($this->db->columnExists('{{%bozp_permits}}', 'expirationWarningSentAt')) {
            $this->dropColumn('{{%bozp_permits}}', 'expirationWarningSentAt');
        }
        if ($this->db->columnExists('{{%bozp_subpermits}}', 'expirationWarningSentAt')) {
            $this->dropColumn('{{%bozp_subpermits}}', 'expirationWarningSentAt');
        }
        return true;
    }
}
