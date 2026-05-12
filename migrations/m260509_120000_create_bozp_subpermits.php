<?php

namespace craft\contentmigrations;

use craft\db\Migration;

/**
 * Creates the bozp_subpermits table.
 *
 * Subpermits are high-risk work authorisations attached to a general
 * permit (GPTW). They have their own lifecycle (pending → approved →
 * expired/cancelled/rejected) and are approved separately by an HSE
 * officer directly on the parent permit's CP detail view.
 *
 * Type-specific form data (checklists, measurements, names) is stored
 * as a JSON blob in the `data` column to avoid one table per type.
 *
 * Note: bozp_permits.requiresHighRisk (JSON array of required types)
 * already exists from the initial migration — no change needed there.
 */
class m260509_120000_create_bozp_subpermits extends Migration
{
    public function safeUp(): bool
    {
        $this->createTable('{{%bozp_subpermits}}', [
            'id'             => $this->primaryKey(),

            // Parent general permit
            'parentPermitId' => $this->integer()->notNull(),

            // SubpermitType enum value (e.g. 'hot_work', 'confined_space', ...)
            'type'           => $this->string(30)->notNull(),

            // SubpermitStatus enum value
            'status'         => $this->string(20)->notNull()->defaultValue('pending'),

            // Type-specific form fields serialised as JSON
            'data'           => $this->json()->null(),

            // Who created it (front-end issuer)
            'issuerId'       => $this->integer()->notNull(),

            // HSE officer who approved/rejected
            'approverId'     => $this->integer()->null(),
            'approvedAt'     => $this->dateTime()->null(),
            // approvedAt + 8 hours, computed on approval
            'expiresAt'      => $this->dateTime()->null(),
            'rejectedAt'     => $this->dateTime()->null(),
            'rejectionNote'  => $this->text()->null(),
            'cancelledAt'    => $this->dateTime()->null(),

            'dateCreated'    => $this->dateTime()->notNull(),
            'dateUpdated'    => $this->dateTime()->notNull(),
            'uid'            => $this->uid(),
        ]);

        // Foreign keys
        $this->addForeignKey(
            null,
            '{{%bozp_subpermits}}', ['parentPermitId'],
            '{{%bozp_permits}}',    ['id'],
            'CASCADE',
        );
        $this->addForeignKey(
            null,
            '{{%bozp_subpermits}}', ['issuerId'],
            '{{%users}}',           ['id'],
            'RESTRICT',
        );
        $this->addForeignKey(
            null,
            '{{%bozp_subpermits}}', ['approverId'],
            '{{%users}}',           ['id'],
            'SET NULL',
        );

        // Indexes
        $this->createIndex(null, '{{%bozp_subpermits}}', ['parentPermitId']);
        $this->createIndex(null, '{{%bozp_subpermits}}', ['parentPermitId', 'type']);
        $this->createIndex(null, '{{%bozp_subpermits}}', ['status']);
        $this->createIndex(null, '{{%bozp_subpermits}}', ['expiresAt']);

        return true;
    }

    public function safeDown(): bool
    {
        $this->dropTableIfExists('{{%bozp_subpermits}}');
        return true;
    }
}
