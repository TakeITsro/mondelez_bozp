<?php

namespace craft\contentmigrations;

use craft\db\Migration;

/**
 * Creates the bozp_permit_controls table.
 *
 * A control visit is a short on-site inspection performed by a Mondelez
 * employee while contractor work is in progress. Multiple controls can be
 * recorded against a single permit. Controls are read-only once saved and
 * do NOT trigger any permit-status changes.
 *
 * Access is gated by the bozp:control CP permission. The form is reached
 * via the contractor QR/token link — a "Kontrola" button on the password
 * page redirects to /bozp/c/<token>/control where the employee must be
 * logged in with their Craft account.
 */
class m260512_100000_create_bozp_permit_controls extends Migration
{
    public function safeUp(): bool
    {
        $this->createTable('{{%bozp_permit_controls}}', [
            'id'               => $this->primaryKey(),

            // Parent permit
            'permitId'         => $this->integer()->notNull(),

            // Craft user who performed the control (nullable for future-proofing)
            'controllerUserId' => $this->integer()->null(),

            // Display name captured at save time (survives user deletion)
            'controllerName'   => $this->string(255)->notNull(),

            // When the physical control took place
            'controlledAt'     => $this->dateTime()->notNull(),

            // Result enum: ok | issues | stopped
            'result'           => $this->string(20)->notNull()->defaultValue('ok'),

            // Free-text notes (optional)
            'notes'            => $this->text()->null(),

            // Drawn signature stored as a Craft Asset
            'signatureAssetId' => $this->integer()->null(),

            // When the form was submitted / signed
            'signedAt'         => $this->dateTime()->null(),

            // Remote IP for audit purposes
            'ipAddress'        => $this->string(45)->null(),

            'dateCreated'      => $this->dateTime()->notNull(),
            'dateUpdated'      => $this->dateTime()->notNull(),
            'uid'              => $this->uid(),
        ]);

        $this->createIndex(null, '{{%bozp_permit_controls}}', ['permitId']);
        $this->createIndex(null, '{{%bozp_permit_controls}}', ['controlledAt']);

        $this->addForeignKey(
            null,
            '{{%bozp_permit_controls}}', 'permitId',
            '{{%bozp_permits}}', 'id',
            'CASCADE', 'CASCADE',
        );

        $this->addForeignKey(
            null,
            '{{%bozp_permit_controls}}', 'controllerUserId',
            '{{%users}}', 'id',
            'SET NULL', 'CASCADE',
        );

        $this->addForeignKey(
            null,
            '{{%bozp_permit_controls}}', 'signatureAssetId',
            '{{%assets}}', 'id',
            'SET NULL', 'CASCADE',
        );

        return true;
    }

    public function safeDown(): bool
    {
        $this->dropTableIfExists('{{%bozp_permit_controls}}');
        return true;
    }
}
