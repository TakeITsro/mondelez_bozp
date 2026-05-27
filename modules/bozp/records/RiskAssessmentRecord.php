<?php

declare(strict_types=1);

namespace modules\bozp\records;

use craft\db\ActiveRecord;

/**
 * Header row for a permit's risk assessment.
 *
 * Two creation paths:
 *   Flow A — issuer fills inline on the new-permit form.
 *            status = 'submitted', no contractorToken/Email.
 *   Flow B — issuer creates an RA with a contractor token + email.
 *            status = 'pending' until the contractor submits, then
 *            'submitted'. The linked permit lives in
 *            PermitStatus::AwaitingAssessment until then.
 *
 * @property int           $id
 * @property int|null      $permitId           FK -> bozp_permits.id (CASCADE)
 * @property string|null   $contractorToken    Unique, only set for Flow B
 * @property string|null   $contractorEmail    Only set for Flow B
 * @property string        $status             'pending' | 'submitted'
 * @property string|null   $expiresAt          Token expiry (Flow B)
 * @property string|null   $submittedAt
 * @property string|null   $submittedByName
 * @property string|null   $submittedByEmail
 * @property int|null      $pdfAssetId         Asset ID of generated RA PDF
 * @property string        $dateCreated
 * @property string        $dateUpdated
 * @property string        $uid
 */
class RiskAssessmentRecord extends ActiveRecord
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_SUBMITTED = 'submitted';

    public static function tableName(): string
    {
        return '{{%bozp_risk_assessments}}';
    }
}
