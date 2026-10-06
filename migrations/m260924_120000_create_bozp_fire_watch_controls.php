<?php

namespace craft\contentmigrations;

use craft\db\Migration;

/**
 * m260924_120000_create_bozp_fire_watch_controls migration.
 *
 * Post-hot-work fire watch. When a contractor signs the closure of a
 * `hot_work` subpermit, four rows are created here — due at +1h, +2h, +3h
 * and +4h. The issuer performs each one; the issuer closure of that
 * subpermit is blocked until all four carry a performedAt.
 *
 * `reminderSentAt` keeps the cron idempotent (same pattern as
 * expirationWarningSentAt on permits/subpermits): a row is mailed once,
 * and a missed cron tick simply mails late rather than not at all.
 */
class m260924_120000_create_bozp_fire_watch_controls extends Migration
{
    public function safeUp(): bool
    {
        $table = '{{%bozp_fire_watch_controls}}';

        if ($this->db->tableExists($table)) {
            return true;
        }

        $this->createTable($table, [
            'id'               => $this->primaryKey(),

            'subpermitId'      => $this->integer()->notNull(),
            // Denormalised so the closure gate and permit-level views can
            // query without joining through subpermits.
            'permitId'         => $this->integer()->notNull(),

            // 1..4 — which hourly slot this row represents.
            'sequence'         => $this->tinyInteger()->notNull(),
            'dueAt'            => $this->dateTime()->notNull(),

            // Null until the issuer performs the control.
            'performedAt'      => $this->dateTime()->null(),
            'performerUserId'  => $this->integer()->null(),
            'performerName'    => $this->string(255)->null(),
            'result'           => $this->string(20)->null(),
            'notes'            => $this->text()->null(),
            'signatureAssetId' => $this->integer()->null(),
            'ipAddress'        => $this->string(45)->null(),

            // Stamped when the cron has mailed the issuer for this row.
            'reminderSentAt'   => $this->dateTime()->null(),

            'dateCreated'      => $this->dateTime()->notNull(),
            'dateUpdated'      => $this->dateTime()->notNull(),
            'uid'              => $this->uid(),
        ]);

        $this->createIndex(null, $table, ['subpermitId']);
        $this->createIndex(null, $table, ['permitId']);
        // Drives the cron pick-up query.
        $this->createIndex(null, $table, ['dueAt', 'performedAt', 'reminderSentAt']);
        // One row per slot per subpermit — makes row creation idempotent.
        $this->createIndex(null, $table, ['subpermitId', 'sequence'], true);

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
        $table = '{{%bozp_fire_watch_controls}}';

        if ($this->db->tableExists($table)) {
            $this->dropTable($table);
        }

        return true;
    }
}
