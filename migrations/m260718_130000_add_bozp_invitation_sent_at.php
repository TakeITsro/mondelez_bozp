<?php

namespace craft\contentmigrations;

use craft\db\Migration;

/**
 * m260718_130000_add_bozp_invitation_sent_at migration.
 *
 * Adds invitationSentAt to bozp_subpermit_signing_requests so token-signer
 * invitations can be held while the parent permit awaits HSE approval and
 * released (idempotently) when it is approved.
 *
 * Existing rows are backfilled with dateCreated — under the old flow their
 * invitations were sent immediately at creation.
 */
class m260718_130000_add_bozp_invitation_sent_at extends Migration
{
    public function safeUp(): bool
    {
        $table = '{{%bozp_subpermit_signing_requests}}';

        if (!$this->db->columnExists($table, 'invitationSentAt')) {
            $this->addColumn($table, 'invitationSentAt', $this->dateTime()->null()->after('signerEmail'));
            $this->update($table, ['invitationSentAt' => new \yii\db\Expression('[[dateCreated]]')]);
        }

        return true;
    }

    public function safeDown(): bool
    {
        $table = '{{%bozp_subpermit_signing_requests}}';

        if ($this->db->columnExists($table, 'invitationSentAt')) {
            $this->dropColumn($table, 'invitationSentAt');
        }

        return true;
    }
}
