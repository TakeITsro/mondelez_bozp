<?php

declare(strict_types=1);

namespace modules\bozp\records;

use craft\db\ActiveRecord;

/**
 * One row of a risk assessment. The contractor can add as many of these as
 * needed via the public form.
 *
 *   probability  — 1..5 (Pravdepodobnosť)
 *   consequence  — 1..4 (Dôsledok)
 *   result       — 1..20, server-computed via RiskAssessmentScale::matrix()
 *                  on save. NEVER trust the client-submitted value.
 *
 * @property int         $id
 * @property int         $riskAssessmentId   FK -> bozp_risk_assessments.id (CASCADE)
 * @property int         $sortOrder
 * @property string|null $source             Zdroj
 * @property string|null $hazard             Nebezpečenstvo
 * @property string|null $risk               Ohrozenie
 * @property int|null    $probability        P, 1..5
 * @property int|null    $consequence        D, 1..4
 * @property int|null    $result             R, 1..20 (computed)
 * @property string|null $measures           Bezpečnostné opatrenia
 * @property string      $dateCreated
 * @property string      $dateUpdated
 * @property string      $uid
 */
class RiskAssessmentRowRecord extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%bozp_risk_assessment_rows}}';
    }
}
