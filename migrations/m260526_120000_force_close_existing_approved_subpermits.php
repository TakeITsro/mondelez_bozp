<?php

namespace craft\contentmigrations;

use craft\db\Migration;

/**
 * Force-close all currently approved subpermits.
 *
 * The subpermit signing workflow changed: both pre-work signatures now must
 * be captured BEFORE HSE approval (previously they were captured AFTER).
 * Subpermits that are already in 'approved' state pre-date this rule and
 * cannot satisfy the new gate retroactively, so we mark them cancelled at
 * deploy. No PDFs are regenerated here — operators can re-issue if needed.
 */
class m260526_120000_force_close_existing_approved_subpermits extends Migration
{
    public function safeUp(): bool
    {
        $now = date('Y-m-d H:i:s');

        $this->update(
            '{{%bozp_subpermits}}',
            [
                'status'        => 'cancelled',
                'cancelledAt'   => $now,
                'rejectionNote' => 'Auto-cancelled by workflow migration: pre-work signatures now required before HSE approval.',
            ],
            ['status' => 'approved'],
        );

        return true;
    }

    public function safeDown(): bool
    {
        // One-way data migration — cannot recover the prior 'approved' state.
        echo "m260526_120000_force_close_existing_approved_subpermits cannot be reverted.\n";
        return false;
    }
}
