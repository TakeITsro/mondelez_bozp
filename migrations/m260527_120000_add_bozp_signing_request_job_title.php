<?php

namespace craft\contentmigrations;

use craft\db\Migration;

/**
 * Adds jobTitle to bozp_subpermit_signing_requests.
 *
 * Excavation (and other types reusing the multi-signer infra) capture a
 * job title on the printed sign block alongside name + date/time + signature.
 * The signer fills it on their token page; it's nullable because legacy
 * Electrical sign requests don't have one.
 */
class m260527_120000_add_bozp_signing_request_job_title extends Migration
{
    public function safeUp(): bool
    {
        if (!$this->db->columnExists('{{%bozp_subpermit_signing_requests}}', 'jobTitle')) {
            $this->addColumn(
                '{{%bozp_subpermit_signing_requests}}',
                'jobTitle',
                $this->string(255)->null()->after('signerName'),
            );
        }
        return true;
    }

    public function safeDown(): bool
    {
        if ($this->db->columnExists('{{%bozp_subpermit_signing_requests}}', 'jobTitle')) {
            $this->dropColumn('{{%bozp_subpermit_signing_requests}}', 'jobTitle');
        }
        return true;
    }
}
