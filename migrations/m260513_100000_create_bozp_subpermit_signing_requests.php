<?php

declare(strict_types=1);

namespace craft\contentmigrations;

use craft\db\Migration;

class m260513_100000_create_bozp_subpermit_signing_requests extends Migration
{
    public function safeUp(): bool
    {
        $this->createTable('{{%bozp_subpermit_signing_requests}}', [
            'id'               => $this->primaryKey(),
            'subpermitId'      => $this->integer()->notNull(),
            'role'             => $this->string(50)->notNull(),
            'signerEmail'      => $this->string(255)->notNull(),
            'signerName'       => $this->string(255)->null(),      // filled when signed
            'token'            => $this->string(64)->notNull(),    // unique hex token
            'signatureAssetId' => $this->integer()->null(),
            'signedAt'         => $this->dateTime()->null(),
            'ipAddress'        => $this->string(45)->null(),
            'dateCreated'      => $this->dateTime()->notNull(),
            'dateUpdated'      => $this->dateTime()->notNull(),
            'uid'              => $this->uid(),
        ]);

        $this->addForeignKey(
            null,
            '{{%bozp_subpermit_signing_requests}}', 'subpermitId',
            '{{%bozp_subpermits}}', 'id',
            'CASCADE', 'CASCADE'
        );
        $this->addForeignKey(
            null,
            '{{%bozp_subpermit_signing_requests}}', 'signatureAssetId',
            '{{%assets}}', 'id',
            'SET NULL', 'CASCADE'
        );

        // One request per role per subpermit
        $this->createIndex(null, '{{%bozp_subpermit_signing_requests}}', ['subpermitId', 'role'], true);
        // Token must be globally unique
        $this->createIndex(null, '{{%bozp_subpermit_signing_requests}}', ['token'], true);

        return true;
    }

    public function safeDown(): bool
    {
        $this->dropTableIfExists('{{%bozp_subpermit_signing_requests}}');
        return true;
    }
}
