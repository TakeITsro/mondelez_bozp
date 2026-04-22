<?php

namespace craft\contentmigrations;

use craft\db\Migration;

/**
 * Creates the schema for the BOZP permit system.
 *
 * Tables:
 *   bozp_zones                - factory zones (referenced by permits, shown on map)
 *   bozp_permits              - the central permit record
 *   bozp_permit_zones         - M2M permit ↔ zone
 *   bozp_permit_hazards       - one row per hazard category per permit (PPE matrix)
 *   bozp_permit_signatures    - signatures captured at issuance and closure
 *   bozp_permit_attachments   - uploaded files (risk assessment, evidence, etc.)
 *   bozp_audit_log            - append-only audit trail
 */
class m260422_120000_create_bozp_tables extends Migration
{
    public function safeUp(): bool
    {
        $this->createZonesTable();
        $this->createPermitsTable();
        $this->createPermitZonesTable();
        $this->createPermitHazardsTable();
        $this->createPermitSignaturesTable();
        $this->createPermitAttachmentsTable();
        $this->createAuditLogTable();
        $this->addForeignKeys();
        $this->addIndexes();

        return true;
    }

    public function safeDown(): bool
    {
        $this->dropTableIfExists('{{%bozp_audit_log}}');
        $this->dropTableIfExists('{{%bozp_permit_attachments}}');
        $this->dropTableIfExists('{{%bozp_permit_signatures}}');
        $this->dropTableIfExists('{{%bozp_permit_hazards}}');
        $this->dropTableIfExists('{{%bozp_permit_zones}}');
        $this->dropTableIfExists('{{%bozp_permits}}');
        $this->dropTableIfExists('{{%bozp_zones}}');

        return true;
    }

    private function createZonesTable(): void
    {
        $this->createTable('{{%bozp_zones}}', [
            'id' => $this->primaryKey(),
            'name' => $this->string(255)->notNull(),
            'code' => $this->string(50)->null(),
            'description' => $this->text()->null(),
            // Geometry stored as JSON — polygon points or SVG path data for the map overlay
            'geometry' => $this->json()->null(),
            'sortOrder' => $this->smallInteger()->null(),
            'archived' => $this->boolean()->defaultValue(false),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);
    }

    private function createPermitsTable(): void
    {
        $this->createTable('{{%bozp_permits}}', [
            'id' => $this->primaryKey(),

            // Identity & type
            'permitNumber' => $this->string(50)->notNull(),
            'permitType' => $this->string(20)->notNull()->defaultValue('general'),
            'parentPermitId' => $this->integer()->null(), // for high-risk children (v2)
            'status' => $this->string(30)->notNull()->defaultValue('draft'),

            // Issuer (Vydavateľ povolenia / Zodpovedná osoba MDLZ)
            'issuerId' => $this->integer()->notNull(),

            // HSE approval (officer review gate)
            'approverId' => $this->integer()->null(),
            'approvedAt' => $this->dateTime()->null(),
            'approvalComment' => $this->text()->null(),
            'rejectedAt' => $this->dateTime()->null(),

            // Work info
            'workDate' => $this->date()->null(),
            'workLocation' => $this->string(500)->null(),
            'workOverview' => $this->text()->null(),
            'workStep1' => $this->string(500)->null(),
            'workStep2' => $this->string(500)->null(),
            'workStep3' => $this->string(500)->null(),
            'workStep4' => $this->string(500)->null(),
            'workStep5' => $this->string(500)->null(),

            // Recipient / contractor (Prijímateľ povolenia)
            'contractorCompany' => $this->string(255)->null(),
            'contractorPersonName' => $this->string(255)->null(),
            'contractorEmail' => $this->string(255)->null(),

            // Risk assessment
            'riskAssessmentComplete' => $this->boolean()->defaultValue(false),

            // Preparation checks
            'conditionsSuitable' => $this->boolean()->null(),
            'toolsInGoodCondition' => $this->boolean()->null(),
            'hasStopConditions' => $this->boolean()->null(),
            'stopConditionsDescription' => $this->text()->null(),
            'lotoImplemented' => $this->boolean()->null(),
            // JSON array of high-risk types required, e.g. ["cse","wah"]
            'requiresHighRisk' => $this->json()->null(),
            'emergencyPlan' => $this->text()->null(),

            // Validity window
            'workCanStartAt' => $this->dateTime()->null(),
            'validFrom' => $this->dateTime()->null(),
            'validTo' => $this->dateTime()->null(),
            'requiresTrialOperation' => $this->boolean()->null(),

            // Public access (QR + password)
            'accessToken' => $this->string(64)->null(),
            'accessPasswordHash' => $this->string(255)->null(),

            // Closure — recipient side
            // JSON array of checked items, e.g. ["work_completed","equipment_operational"]
            'recipientClosureStatus' => $this->json()->null(),
            'recipientClosureSignedAt' => $this->dateTime()->null(),
            'recipientClosureBy' => $this->string(255)->null(),

            // Closure — issuer side
            'issuerClosureStatus' => $this->string(50)->null(),
            'issuerClosureSignedAt' => $this->dateTime()->null(),

            // Lifecycle timestamps
            'submittedAt' => $this->dateTime()->null(),
            'signedAt' => $this->dateTime()->null(),
            'activatedAt' => $this->dateTime()->null(),
            'closedAt' => $this->dateTime()->null(),
            'cancelledAt' => $this->dateTime()->null(),
            'expiredAt' => $this->dateTime()->null(),

            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);
    }

    private function createPermitZonesTable(): void
    {
        $this->createTable('{{%bozp_permit_zones}}', [
            'id' => $this->primaryKey(),
            'permitId' => $this->integer()->notNull(),
            'zoneId' => $this->integer()->notNull(),
            'sortOrder' => $this->smallInteger()->null(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);
    }

    private function createPermitHazardsTable(): void
    {
        $this->createTable('{{%bozp_permit_hazards}}', [
            'id' => $this->primaryKey(),
            'permitId' => $this->integer()->notNull(),
            // Maps to modules\bozp\enums\HazardCategory (e.g. 'noise','skin','eyes',...)
            'hazardKey' => $this->string(50)->notNull(),
            'exposed' => $this->boolean()->null(),
            'measure' => $this->text()->null(),
            // 'used' | 'not_used' | 'other'
            'controlDuringActivity' => $this->string(20)->null(),
            'controlDuringActivityOther' => $this->text()->null(),
            'sortOrder' => $this->smallInteger()->null(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);
    }

    private function createPermitSignaturesTable(): void
    {
        $this->createTable('{{%bozp_permit_signatures}}', [
            'id' => $this->primaryKey(),
            'permitId' => $this->integer()->notNull(),
            // Maps to modules\bozp\enums\SignatureRole
            'role' => $this->string(50)->notNull(),
            'signerName' => $this->string(255)->notNull(),
            'signerEmployer' => $this->string(255)->null(),
            // Asset id of the signature PNG
            'signatureAssetId' => $this->integer()->null(),
            'signedAt' => $this->dateTime()->notNull(),
            'ipAddress' => $this->string(45)->null(),
            'userAgent' => $this->string(255)->null(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);
    }

    private function createPermitAttachmentsTable(): void
    {
        $this->createTable('{{%bozp_permit_attachments}}', [
            'id' => $this->primaryKey(),
            'permitId' => $this->integer()->notNull(),
            // 'risk_assessment' | 'supporting' | 'contractor_evidence'
            'attachmentType' => $this->string(50)->notNull(),
            'assetId' => $this->integer()->notNull(),
            'uploadedById' => $this->integer()->null(), // Craft user, null when contractor
            'uploadedByName' => $this->string(255)->null(),
            'note' => $this->text()->null(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);
    }

    private function createAuditLogTable(): void
    {
        $this->createTable('{{%bozp_audit_log}}', [
            'id' => $this->primaryKey(),
            'permitId' => $this->integer()->null(),
            'userId' => $this->integer()->null(),
            // 'created','submitted','approved','rejected','signed','activated','closed','cancelled','expired',...
            'action' => $this->string(50)->notNull(),
            'fromStatus' => $this->string(30)->null(),
            'toStatus' => $this->string(30)->null(),
            'payload' => $this->json()->null(),
            'ipAddress' => $this->string(45)->null(),
            'userAgent' => $this->string(255)->null(),
            'dateCreated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);
    }

    private function addForeignKeys(): void
    {
        // Self-reference for high-risk children
        $this->addForeignKey(null, '{{%bozp_permits}}', ['parentPermitId'], '{{%bozp_permits}}', ['id'], 'SET NULL');

        // Issuer/Approver are Craft users
        $this->addForeignKey(null, '{{%bozp_permits}}', ['issuerId'], '{{%users}}', ['id'], 'RESTRICT');
        $this->addForeignKey(null, '{{%bozp_permits}}', ['approverId'], '{{%users}}', ['id'], 'SET NULL');

        // Permit zones M2M
        $this->addForeignKey(null, '{{%bozp_permit_zones}}', ['permitId'], '{{%bozp_permits}}', ['id'], 'CASCADE');
        $this->addForeignKey(null, '{{%bozp_permit_zones}}', ['zoneId'], '{{%bozp_zones}}', ['id'], 'CASCADE');

        // Hazards
        $this->addForeignKey(null, '{{%bozp_permit_hazards}}', ['permitId'], '{{%bozp_permits}}', ['id'], 'CASCADE');

        // Signatures
        $this->addForeignKey(null, '{{%bozp_permit_signatures}}', ['permitId'], '{{%bozp_permits}}', ['id'], 'CASCADE');
        $this->addForeignKey(null, '{{%bozp_permit_signatures}}', ['signatureAssetId'], '{{%assets}}', ['id'], 'SET NULL');

        // Attachments
        $this->addForeignKey(null, '{{%bozp_permit_attachments}}', ['permitId'], '{{%bozp_permits}}', ['id'], 'CASCADE');
        $this->addForeignKey(null, '{{%bozp_permit_attachments}}', ['assetId'], '{{%assets}}', ['id'], 'CASCADE');
        $this->addForeignKey(null, '{{%bozp_permit_attachments}}', ['uploadedById'], '{{%users}}', ['id'], 'SET NULL');

        // Audit log
        $this->addForeignKey(null, '{{%bozp_audit_log}}', ['permitId'], '{{%bozp_permits}}', ['id'], 'SET NULL');
        $this->addForeignKey(null, '{{%bozp_audit_log}}', ['userId'], '{{%users}}', ['id'], 'SET NULL');
    }

    private function addIndexes(): void
    {
        $this->createIndex(null, '{{%bozp_permits}}', ['permitNumber'], true);
        $this->createIndex(null, '{{%bozp_permits}}', ['status']);
        $this->createIndex(null, '{{%bozp_permits}}', ['permitType']);
        $this->createIndex(null, '{{%bozp_permits}}', ['parentPermitId']);
        $this->createIndex(null, '{{%bozp_permits}}', ['accessToken'], true);
        $this->createIndex(null, '{{%bozp_permits}}', ['validFrom', 'validTo']);

        $this->createIndex(null, '{{%bozp_permit_zones}}', ['permitId', 'zoneId'], true);
        $this->createIndex(null, '{{%bozp_permit_zones}}', ['zoneId']);

        $this->createIndex(null, '{{%bozp_permit_hazards}}', ['permitId', 'hazardKey'], true);

        $this->createIndex(null, '{{%bozp_permit_signatures}}', ['permitId']);
        $this->createIndex(null, '{{%bozp_permit_signatures}}', ['permitId', 'role']);

        $this->createIndex(null, '{{%bozp_permit_attachments}}', ['permitId']);
        $this->createIndex(null, '{{%bozp_permit_attachments}}', ['permitId', 'attachmentType']);

        $this->createIndex(null, '{{%bozp_audit_log}}', ['permitId']);
        $this->createIndex(null, '{{%bozp_audit_log}}', ['userId']);
        $this->createIndex(null, '{{%bozp_audit_log}}', ['action']);
        $this->createIndex(null, '{{%bozp_audit_log}}', ['dateCreated']);
    }
}
