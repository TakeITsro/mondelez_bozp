<?php

declare(strict_types=1);

namespace modules\bozp\enums;

use Craft;

/**
 * Signing roles for multi-signer subpermits.
 *
 * Originally introduced for Appendix 6 (Electrical), now reused across types:
 *   - Electrical: 3 required + 4 conditional roles (the original set).
 *   - Excavation: 2 optional roles — HSE approval + authorizing-authority
 *     approval (PHASE 2 + PHASE 3 on the printed Appendix 7 form).
 *
 * `forType()` returns the role list for a given subpermit type. Any role
 * with a non-empty email at save time gets a SubpermitSigningRequestRecord
 * and a tokenised invitation mail. Roles flagged via `isRequired()` block
 * save when their email is missing.
 */
enum SubpermitSigningRole: string
{
    // Electrical roster
    case ThirdParty             = 'third_party';
    case MdlzQualifiedEmergency = 'mdlz_qualified_emergency';
    case AreaSupervisor         = 'area_supervisor';
    case MdlzQualifiedApproval  = 'mdlz_qualified_approval';
    case SecondTechnician       = 'second_technician';
    case SafetyRep              = 'safety_rep';
    case MaintenanceManager     = 'maintenance_manager';

    // Excavation roster
    case ExcavationHseApproval       = 'excavation_hse_approval';
    case ExcavationAuthorityApproval = 'excavation_authority_approval';

    // Confined-space roster (all required for entry into a confined space)
    case CsEntrant            = 'cs_entrant';
    case CsSupervisorEntrant  = 'cs_supervisor_entrant';
    case CsSupervisorCse      = 'cs_supervisor_cse';
    case CsMdlzOrderant       = 'cs_mdlz_orderant';
    case CsHseDepartment      = 'cs_hse_department';

    public function label(): string
    {
        return match ($this) {
            self::ThirdParty                  => Craft::t('bozp', 'Dodávateľ (tretia strana)'),
            self::MdlzQualifiedEmergency      => Craft::t('bozp', 'Kvalifikovaná osoba MDLZ — havarijný plán'),
            self::AreaSupervisor              => Craft::t('bozp', 'Vedúci pracoviska'),
            self::MdlzQualifiedApproval       => Craft::t('bozp', 'Kvalifikovaná osoba MDLZ — schválenie SSoW'),
            self::SecondTechnician            => Craft::t('bozp', 'Druhý technik (dohľad, >750 VAC)'),
            self::SafetyRep                   => Craft::t('bozp', 'Zástupca pre bezpečnosť'),
            self::MaintenanceManager          => Craft::t('bozp', 'Vedúci údržby'),
            self::ExcavationHseApproval       => Craft::t('bozp', 'FÁZA 2 — Schválenie HSE oddelením'),
            self::ExcavationAuthorityApproval => Craft::t('bozp', 'FÁZA 3 — Schválenie oprávneným orgánom'),
            self::CsEntrant                   => Craft::t('bozp', 'Vstupujúci (entrant)'),
            self::CsSupervisorEntrant         => Craft::t('bozp', 'Dozorujúci pri vstupe (supervisor entrant)'),
            self::CsSupervisorCse             => Craft::t('bozp', 'Vedúci vstupu do uzavretého priestoru (supervisor in CSE)'),
            self::CsMdlzOrderant              => Craft::t('bozp', 'Objednávateľ MDLZ (review)'),
            self::CsHseDepartment             => Craft::t('bozp', 'HSE oddelenie (review)'),
        };
    }

    /**
     * Always-required roles — email must be provided or save is blocked.
     */
    public function isRequired(): bool
    {
        return in_array($this, [
            // Electrical
            self::MdlzQualifiedEmergency,
            self::AreaSupervisor,
            self::MdlzQualifiedApproval,
            // Confined space (all 5)
            self::CsEntrant,
            self::CsSupervisorEntrant,
            self::CsSupervisorCse,
            self::CsMdlzOrderant,
            self::CsHseDepartment,
        ], true);
    }

    /**
     * Role roster for a given subpermit type. Empty array means the type
     * doesn't use the multi-signer infrastructure (it relies solely on the
     * standard prework / closure signature flow).
     *
     * @return self[]
     */
    public static function forType(SubpermitType $type): array
    {
        return match ($type) {
            SubpermitType::Electrical => [
                self::MdlzQualifiedEmergency,
                self::AreaSupervisor,
                self::MdlzQualifiedApproval,
                self::ThirdParty,
                self::SecondTechnician,
                self::SafetyRep,
                self::MaintenanceManager,
            ],
            SubpermitType::Excavation => [
                self::ExcavationHseApproval,
                self::ExcavationAuthorityApproval,
            ],
            SubpermitType::ConfinedSpace => [
                self::CsEntrant,
                self::CsSupervisorEntrant,
                self::CsSupervisorCse,
                self::CsMdlzOrderant,
                self::CsHseDepartment,
            ],
            default => [],
        };
    }
}
