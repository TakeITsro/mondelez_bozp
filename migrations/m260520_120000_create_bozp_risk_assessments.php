<?php

declare(strict_types=1);

namespace craft\contentmigrations;

use craft\db\Migration;

/**
 * Risk assessment ("Hodnotenie rizík") infrastructure.
 *
 *   bozp_risk_assessments
 *     One per permit. In Flow A the issuer fills it inline on the new-permit
 *     form (status = submitted, no token). In Flow B the issuer first creates
 *     an RA in `pending` status with a contractor token; the contractor fills
 *     it via the public link, then the permit goes from
 *     `awaiting_assessment` to `draft`.
 *
 *   bozp_risk_assessment_rows
 *     Variable number of rows per RA, matching the contractor's Excel
 *     template: source / hazard / risk / probability / consequence /
 *     result / measures.
 */
class m260520_120000_create_bozp_risk_assessments extends Migration
{
    public function safeUp(): bool
    {
        if (!$this->db->tableExists('{{%bozp_risk_assessments}}')) {
            $this->createTable('{{%bozp_risk_assessments}}', [
                'id'                  => $this->primaryKey(),
                'permitId'            => $this->integer()->null(),
                'contractorToken'     => $this->string(64)->null(),
                'contractorEmail'     => $this->string(191)->null(),
                'status'              => $this->string(32)->notNull()->defaultValue('submitted'),
                'expiresAt'           => $this->dateTime()->null(),
                'submittedAt'         => $this->dateTime()->null(),
                'submittedByName'     => $this->string(191)->null(),
                'submittedByEmail'    => $this->string(191)->null(),
                'pdfAssetId'          => $this->integer()->null(),
                'dateCreated'         => $this->dateTime()->notNull(),
                'dateUpdated'         => $this->dateTime()->notNull(),
                'uid'                 => $this->uid(),
            ]);

            $this->createIndex(null, '{{%bozp_risk_assessments}}', ['permitId']);
            $this->createIndex(null, '{{%bozp_risk_assessments}}', ['contractorToken'], true);
            $this->createIndex(null, '{{%bozp_risk_assessments}}', ['status']);

            $this->addForeignKey(
                null,
                '{{%bozp_risk_assessments}}',
                ['permitId'],
                '{{%bozp_permits}}',
                ['id'],
                'CASCADE',
                'CASCADE'
            );
        }

        if (!$this->db->tableExists('{{%bozp_risk_assessment_rows}}')) {
            $this->createTable('{{%bozp_risk_assessment_rows}}', [
                'id'                 => $this->primaryKey(),
                'riskAssessmentId'   => $this->integer()->notNull(),
                'sortOrder'          => $this->integer()->notNull()->defaultValue(0),
                'source'             => $this->text()->null(),
                'hazard'             => $this->text()->null(),
                'risk'               => $this->text()->null(),
                // Probability 1..5 (Pravdepodobnosť)
                'probability'        => $this->tinyInteger()->null(),
                // Consequence 1..4 (Dôsledok)
                'consequence'        => $this->tinyInteger()->null(),
                // Computed result 1..20 (lookup in matrix on save)
                'result'             => $this->tinyInteger()->null(),
                'measures'           => $this->text()->null(),
                'dateCreated'        => $this->dateTime()->notNull(),
                'dateUpdated'        => $this->dateTime()->notNull(),
                'uid'                => $this->uid(),
            ]);

            $this->createIndex(null, '{{%bozp_risk_assessment_rows}}', ['riskAssessmentId', 'sortOrder']);

            $this->addForeignKey(
                null,
                '{{%bozp_risk_assessment_rows}}',
                ['riskAssessmentId'],
                '{{%bozp_risk_assessments}}',
                ['id'],
                'CASCADE',
                'CASCADE'
            );
        }

        return true;
    }

    public function safeDown(): bool
    {
        $this->dropTableIfExists('{{%bozp_risk_assessment_rows}}');
        $this->dropTableIfExists('{{%bozp_risk_assessments}}');
        return true;
    }
}
