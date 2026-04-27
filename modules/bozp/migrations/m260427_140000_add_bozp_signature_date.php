<?php

namespace craft\contentmigrations;

use craft\db\Migration;

/**
 * Adds signatureDate to bozp_permit_signatures.
 *
 * signedAt is the automatic timestamp captured at the moment the
 * signature was submitted. signatureDate is a separate user-entered
 * date the signer chose (defaults to today on the form). The two
 * may diverge — e.g. signing on Tuesday for an effective date of
 * Wednesday.
 */
class m260427_140000_add_bozp_signature_date extends Migration
{
    public function safeUp(): bool
    {
        if (!$this->db->columnExists('{{%bozp_permit_signatures}}', 'signatureDate')) {
            $this->addColumn(
                '{{%bozp_permit_signatures}}',
                'signatureDate',
                $this->date()->null()->after('signatureAssetId'),
            );
        }
        return true;
    }

    public function safeDown(): bool
    {
        if ($this->db->columnExists('{{%bozp_permit_signatures}}', 'signatureDate')) {
            $this->dropColumn('{{%bozp_permit_signatures}}', 'signatureDate');
        }
        return true;
    }
}
