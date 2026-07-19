<?php

namespace craft\contentmigrations;

use craft\db\Migration;

/**
 * Adds subpermitId to bozp_permit_attachments.
 *
 * Issuer-side subpermit attachments (e.g. hot-work permit photo, confined-space
 * gas-measurement record, atex hourly verification sheet) are now scoped to a
 * single subpermit instead of the parent permit. The new attachmentType used
 * by the issuer subpermit upload route is 'subpermit_issuer_upload'.
 *
 * Existing rows keep subpermitId NULL — they remain bound only to the parent
 * permit, which matches the pre-existing 'contractor_upload' / 'issuer_upload'
 * / 'risk_assessment' semantics.
 */
class m260601_120000_add_subpermit_id_to_permit_attachments extends Migration
{
    public function safeUp(): bool
    {
        if (!$this->db->columnExists('{{%bozp_permit_attachments}}', 'subpermitId')) {
            $this->addColumn(
                '{{%bozp_permit_attachments}}',
                'subpermitId',
                $this->integer()->null()->after('permitId'),
            );

            $this->createIndex(
                null,
                '{{%bozp_permit_attachments}}',
                ['subpermitId'],
            );

            $this->addForeignKey(
                null,
                '{{%bozp_permit_attachments}}', ['subpermitId'],
                '{{%bozp_subpermits}}',         ['id'],
                'CASCADE',
            );
        }
        return true;
    }

    public function safeDown(): bool
    {
        if ($this->db->columnExists('{{%bozp_permit_attachments}}', 'subpermitId')) {
            // FK + index dropped automatically when column is dropped on MySQL.
            $this->dropColumn('{{%bozp_permit_attachments}}', 'subpermitId');
        }
        return true;
    }
}
