<?php

declare(strict_types=1);

namespace modules\bozp\records;

use craft\db\ActiveRecord;

/**
 * @property int $id
 * @property string $permitNumber
 * @property string $permitType
 * @property int|null $parentPermitId
 * @property string $status
 *
 * @property int $issuerId
 * @property int|null $approverId
 * @property string|null $approvedAt
 * @property string|null $approvalComment
 * @property string|null $rejectedAt
 *
 * @property string|null $workDate
 * @property string|null $workLocation
 * @property string|null $workOverview
 * @property string|null $workStep1
 * @property string|null $workStep2
 * @property string|null $workStep3
 * @property string|null $workStep4
 * @property string|null $workStep5
 *
 * @property string|null $contractorCompany
 * @property string|null $contractorPersonName
 * @property string|null $contractorEmail
 *
 * @property bool $riskAssessmentComplete
 * @property bool|null $conditionsSuitable
 * @property bool|null $toolsInGoodCondition
 * @property bool|null $hasStopConditions
 * @property string|null $stopConditionsDescription
 * @property bool|null $lotoImplemented
 * @property array|null $requiresHighRisk
 * @property string|null $emergencyPlan
 *
 * @property string|null $workCanStartAt
 * @property string|null $validFrom
 * @property string|null $validTo
 * @property bool|null $requiresTrialOperation
 *
 * @property string|null $accessToken
 * @property string|null $accessPasswordHash
 * @property string|null $accessExpiresAt
 *
 * @property array|null $recipientClosureStatus
 * @property string|null $recipientClosureSignedAt
 * @property string|null $recipientClosureBy
 * @property string|null $issuerClosureStatus
 * @property string|null $issuerClosureSignedAt
 *
 * @property string|null $submittedAt
 * @property string|null $signedAt
 * @property string|null $activatedAt
 * @property string|null $closedAt
 * @property string|null $cancelledAt
 * @property string|null $expiredAt
 *
 * @property string $dateCreated
 * @property string $dateUpdated
 * @property string $uid
 */
class PermitRecord extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%bozp_permits}}';
    }
}
