<?php

declare(strict_types=1);

namespace craft\contentmigrations;

use craft\db\Migration;

/**
 * Adds pdfAssetId column to bozp_permits and bozp_subpermits.
 *
 * On every status change the previous PDF is replaced; this column stores
 * the Craft Asset ID of the most recent generated PDF for that record.
 */
class m260515_100000_add_bozp_pdf_asset_ids extends Migration
{
    public function safeUp(): bool
    {
        // bozp_permits
        if (!$this->db->columnExists('{{%bozp_permits}}', 'pdfAssetId')) {
            $this->addColumn(
                '{{%bozp_permits}}',
                'pdfAssetId',
                $this->integer()->null()->defaultValue(null)->after('cancelledAt')
            );
        }

        // bozp_subpermits
        if (!$this->db->columnExists('{{%bozp_subpermits}}', 'pdfAssetId')) {
            $this->addColumn(
                '{{%bozp_subpermits}}',
                'pdfAssetId',
                $this->integer()->null()->defaultValue(null)->after('cancelledAt')
            );
        }

        return true;
    }

    public function safeDown(): bool
    {
        if ($this->db->columnExists('{{%bozp_permits}}', 'pdfAssetId')) {
            $this->dropColumn('{{%bozp_permits}}', 'pdfAssetId');
        }
        if ($this->db->columnExists('{{%bozp_subpermits}}', 'pdfAssetId')) {
            $this->dropColumn('{{%bozp_subpermits}}', 'pdfAssetId');
        }
        return true;
    }
}
