<?php

declare(strict_types=1);

namespace modules\bozp\enums;

use Craft;

/**
 * Signing roles for multi-signer subpermits (e.g. Appendix 6 — Electrical).
 *
 * The three "required" roles must always have an email supplied.
 * Conditional roles (third_party, second_technician, safety_rep, maintenance_manager)
 * are only created when a non-empty email is provided at subpermit creation.
 */
enum SubpermitSigningRole: string
{
    case ThirdParty             = 'third_party';
    case MdlzQualifiedEmergency = 'mdlz_qualified_emergency';
    case AreaSupervisor         = 'area_supervisor';
    case MdlzQualifiedApproval  = 'mdlz_qualified_approval';
    case SecondTechnician       = 'second_technician';
    case SafetyRep              = 'safety_rep';
    case MaintenanceManager     = 'maintenance_manager';

    public function label(): string
    {
        return match ($this) {
            self::ThirdParty             => Craft::t('bozp', 'Dodávateľ (tretia strana)'),
            self::MdlzQualifiedEmergency => Craft::t('bozp', 'Kvalifikovaná osoba MDLZ — havarijný plán'),
            self::AreaSupervisor         => Craft::t('bozp', 'Vedúci pracoviska'),
            self::MdlzQualifiedApproval  => Craft::t('bozp', 'Kvalifikovaná osoba MDLZ — schválenie SSoW'),
            self::SecondTechnician       => Craft::t('bozp', 'Druhý technik (dohľad, >750 VAC)'),
            self::SafetyRep              => Craft::t('bozp', 'Zástupca pre bezpečnosť'),
            self::MaintenanceManager     => Craft::t('bozp', 'Vedúci údržby'),
        };
    }

    /**
     * Always-required roles — email must be provided or save is blocked.
     */
    public function isRequired(): bool
    {
        return in_array($this, [
            self::MdlzQualifiedEmergency,
            self::AreaSupervisor,
            self::MdlzQualifiedApproval,
        ], true);
    }
}
