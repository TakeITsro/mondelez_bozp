<?php

declare(strict_types=1);

namespace craft\contentmigrations;

use craft\db\Migration;

class m260510_100000_create_bozp_subpermit_signatures extends Migration
{
    public function safeUp(): bool
    {
        $this->createTable('{{%bozp_subpermit_signatures}}', [
            'id'               => $this->primaryKey(),
            'subpermitId'      => $this->integer()->notNull(),
            'role'             => $this->string(20)->notNull(), // 'issuer' | 'contractor'
            'signerName'       => $this->string(255)->notNull(),
            'signerEmployer'   => $this->string(255)->null(),
            'signatureAssetId' => $this->integer()->null(),
            'signatureDate'    => $this->date()->null(),
            'signedAt'         => $this->dateTime()->notNull(),
            'ipAddress'        => $this->string(45)->null(),
            'userAgent'        => $this->string(255)->null(),
            'dateCreated'      => $this->dateTime()->notNull(),
            'dateUpdated'      => $this->dateTime()->notNull(),
            'uid'              => $this->uid(),
        ]);

        $this->addForeignKey(
            null,
            '{{%bozp_subpermit_signatures}}', 'subpermitId',
            '{{%bozp_subpermits}}', 'id',
            'CASCADE', 'CASCADE'
        );
        $this->addForeignKey(
            null,
            '{{%bozp_subpermit_signatures}}', 'signatureAssetId',
            '{{%assets}}', 'id',
            'SET NULL', 'CASCADE'
        );
        // One signature per role per subpermit
        $this->createIndex(null, '{{%bozp_subpermit_signatures}}', ['subpermitId', 'role'], true);

        return true;
    }

    public function safeDown(): bool
    {
        $this->dropTableIfExists('{{%bozp_subpermit_signatures}}');
        return true;
    }
}
