<?php

namespace craft\contentmigrations;

use craft\db\Migration;

/**
 * m261005_120000_create_bozp_equipment_inspections migration.
 *
 * Daily equipment safety condition inspection for `energized` subpermits
 * (Príloha č. 10, page 2). One row per day while the subpermit is still
 * open; generated at 16:00 and mailed to the issuer, who ticks the 14
 * checklist items and signs.
 *
 * The issuer closure is blocked while any row is still unperformed.
 *
 * `reminderSentAt` keeps the cron idempotent, the same way
 * expirationWarningSentAt and the fire watch rows do.
 */
class m261005_120000_create_bozp_equipment_inspections extends Migration
{
    public function safeUp(): bool
    {
        $table = '{{%bozp_equipment_inspections}}';

        if ($this->db->tableExists($table)) {
            return true;
        }

        $this->createTable($table, [
            'id'               => $this->primaryKey(),

            'subpermitId'      => $this->integer()->notNull(),
            // Denormalised so the closure gate and permit views can query
            // without joining through subpermits.
            'permitId'         => $this->integer()->notNull(),

            // The day this inspection covers, and when it fell due (16:00).
            'dueDate'          => $this->date()->notNull(),
            'dueAt'            => $this->dateTime()->notNull(),

            'performedAt'      => $this->dateTime()->null(),
            'performerUserId'  => $this->integer()->null(),
            'performerName'    => $this->string(255)->null(),
            // Overall outcome: 'ok' | 'issue'
            'status'           => $this->string(20)->null(),
            // Per-item results, keyed by checklist item: {key: 'ok'|'nok'|'na'}
            'items'            => $this->text()->null(),
            'notes'            => $this->text()->null(),
            'signatureAssetId' => $this->integer()->null(),
            'ipAddress'        => $this->string(45)->null(),

            'reminderSentAt'   => $this->dateTime()->null(),

            'dateCreated'      => $this->dateTime()->notNull(),
            'dateUpdated'      => $this->dateTime()->notNull(),
            'uid'              => $this->uid(),
        ]);

        $this->createIndex(null, $table, ['subpermitId']);
        $this->createIndex(null, $table, ['permitId']);
        // Drives the cron pick-up.
        $this->createIndex(null, $table, ['dueAt', 'performedAt', 'reminderSentAt']);
        // One inspection per subpermit per day — makes generation idempotent.
        $this->createIndex(null, $table, ['subpermitId', 'dueDate'], true);

        $this->addForeignKey(
            null,
            $table, 'subpermitId',
            '{{%bozp_subpermits}}', 'id',
            'CASCADE', 'CASCADE',
        );

        $this->addForeignKey(
            null,
            $table, 'permitId',
            '{{%bozp_permits}}', 'id',
            'CASCADE', 'CASCADE',
        );

        $this->addForeignKey(
            null,
            $table, 'performerUserId',
            '{{%users}}', 'id',
            'SET NULL', 'CASCADE',
        );

        return true;
    }

    public function safeDown(): bool
    {
        $table = '{{%bozp_equipment_inspections}}';

        if ($this->db->tableExists($table)) {
            $this->dropTable($table);
        }

        return true;
    }
}
