<?php

declare(strict_types=1);

namespace modules\bozp;

use Craft;
use craft\events\RegisterCpNavItemsEvent;
use craft\events\RegisterTemplateRootsEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\events\RegisterUserPermissionsEvent;
use craft\services\UserPermissions;
use craft\web\twig\variables\Cp;
use craft\web\UrlManager;
use craft\web\View;
use modules\bozp\services\AuditLogger;
use modules\bozp\services\ClickUpClient;
use modules\bozp\services\PermitMailer;
use modules\bozp\services\PermitNumberGenerator;
use modules\bozp\services\PermitPdfService;
use modules\bozp\services\PermitWorkflow;
use modules\bozp\services\SignatureService;
use modules\bozp\services\SubpermitSignatureService;
use modules\bozp\services\SubpermitSigningService;
use yii\base\Event;
use yii\base\Module as BaseModule;

/**
 * BOZP Permits Module
 *
 * Work permit (povolenie na prácu) lifecycle for Mondelez SR Production.
 * v1: General permit (GPTW). High-risk sub-permits arrive in v2.
 *
 * @property-read PermitNumberGenerator $permitNumberGenerator
 * @property-read PermitWorkflow $permitWorkflow
 * @property-read AuditLogger $auditLogger
 * @property-read PermitMailer $permitMailer
 * @property-read PermitPdfService $permitPdfService
 * @property-read SignatureService $signatureService
 * @property-read SubpermitSignatureService $subpermitSignatureService
 * @property-read SubpermitSigningService $subpermitSigningService
 * @property-read ClickUpClient $clickUpClient
 */
class Module extends BaseModule
{
    public function init(): void
    {
        Craft::setAlias('@modules/bozp', __DIR__);

        $this->controllerNamespace = Craft::$app->getRequest()->getIsConsoleRequest()
            ? 'modules\\bozp\\console\\controllers'
            : 'modules\\bozp\\controllers';

        $this->setComponents([
            'permitNumberGenerator' => PermitNumberGenerator::class,
            'permitWorkflow' => PermitWorkflow::class,
            'auditLogger' => AuditLogger::class,
            'permitMailer' => PermitMailer::class,
            'permitPdfService' => PermitPdfService::class,
            'signatureService' => SignatureService::class,
            'subpermitSignatureService' => SubpermitSignatureService::class,
            'subpermitSigningService'   => SubpermitSigningService::class,
            'clickUpClient'             => ClickUpClient::class,
        ]);

        parent::init();

        $this->registerTranslations();
        $this->registerTemplateRoots();
        $this->registerCpUrlRules();
        $this->registerSiteUrlRules();
        $this->registerCpNavItem();
        $this->registerUserPermissions();

        Craft::info('BOZP module loaded.', __METHOD__);
    }

    private function registerTranslations(): void
    {
        Craft::$app->getI18n()->translations['bozp'] = [
            'class' => \craft\i18n\PhpMessageSource::class,
            'sourceLanguage' => 'sk',
            'basePath' => __DIR__ . '/translations',
            'forceTranslation' => true,
            'allowOverrides' => true,
        ];
    }

    private function registerTemplateRoots(): void
    {
        // CP templates
        Event::on(
            View::class,
            View::EVENT_REGISTER_CP_TEMPLATE_ROOTS,
            static function (RegisterTemplateRootsEvent $event): void {
                $event->roots['bozp'] = __DIR__ . '/templates';
            }
        );

        // Site (front-end) templates
        Event::on(
            View::class,
            View::EVENT_REGISTER_SITE_TEMPLATE_ROOTS,
            static function (RegisterTemplateRootsEvent $event): void {
                $event->roots['bozp'] = __DIR__ . '/templates';
            }
        );
    }

    private function registerCpUrlRules(): void
    {
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_CP_URL_RULES,
            static function (RegisterUrlRulesEvent $event): void {
                $event->rules['bozp'] = 'bozp/queue/index';
                $event->rules['bozp/queue'] = 'bozp/queue/index';
                $event->rules['bozp/all'] = 'bozp/queue/all';
                $event->rules['bozp/permit/<id:\d+>'] = 'bozp/queue/view';
                $event->rules['bozp/permit/<permitId:\d+>/edit'] = 'bozp/queue/edit-permit';
                $event->rules['POST bozp/permit/<id:\d+>/resend'] = 'bozp/queue/resend';
                $event->rules['POST bozp/permit/<id:\d+>/hse-close'] = 'bozp/queue/hse-close';
                $event->rules['POST bozp/permit/<id:\d+>/delete'] = 'bozp/queue/delete';
                $event->rules['POST bozp/permit/<permitId:\d+>/subpermits/<id:\d+>/approve'] = 'bozp/queue/approve-subpermit';
                $event->rules['POST bozp/permit/<permitId:\d+>/subpermits/<id:\d+>/reject'] = 'bozp/queue/reject-subpermit';
                $event->rules['bozp/permit/<permitId:\d+>/subpermit/<id:\d+>'] = 'bozp/queue/subpermit-view';
                $event->rules['bozp/permit/<permitId:\d+>/subpermit/<id:\d+>/edit'] = 'bozp/queue/edit-subpermit';

                // CP PDF download
                $event->rules['bozp/permit/<id:\d+>/pdf'] = 'bozp/queue/permit-pdf';
                $event->rules['bozp/permit/<permitId:\d+>/subpermit/<id:\d+>/pdf'] = 'bozp/queue/subpermit-pdf';

                // Bug report → ClickUp
                $event->rules['bozp/bug-report']      = 'bozp/bug-report/index';
                $event->rules['POST bozp/bug-report'] = 'bozp/bug-report/submit';
            }
        );
    }

    private function registerSiteUrlRules(): void
    {
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_SITE_URL_RULES,
            static function (RegisterUrlRulesEvent $event): void {
                // Auth
                $event->rules['bozp/login'] = 'bozp/auth/login';
                $event->rules['POST bozp/login'] = 'bozp/auth/login';
                $event->rules['POST bozp/logout'] = 'bozp/auth/logout';

                // Permits
                $event->rules['bozp'] = 'bozp/dashboard/index';
                $event->rules['bozp/permits'] = 'bozp/dashboard/index';
                $event->rules['bozp/permits/new'] = 'bozp/permits/new';
                $event->rules['POST bozp/permits/save'] = 'bozp/permits/save';
                $event->rules['bozp/permits/<id:\d+>'] = 'bozp/permits/view';

                // Facility map
                $event->rules['bozp/map'] = 'bozp/map/index';
                $event->rules['bozp/map/zone/<id:\d+>'] = 'bozp/map/zone';

                // Contractor (token-gated, password-protected)
                $event->rules['bozp/c/<token:[A-Za-z0-9_\-]+>'] = 'bozp/contractor/view';
                $event->rules['POST bozp/c/<token:[A-Za-z0-9_\-]+>/auth'] = 'bozp/contractor/auth';
                $event->rules['POST bozp/c/<token:[A-Za-z0-9_\-]+>/upload'] = 'bozp/contractor/upload';
                $event->rules['POST bozp/c/<token:[A-Za-z0-9_\-]+>/close'] = 'bozp/contractor/close';
                $event->rules['POST bozp/c/<token:[A-Za-z0-9_\-]+>/cancel'] = 'bozp/contractor/cancel';
                $event->rules['POST bozp/c/<token:[A-Za-z0-9_\-]+>/subpermits/<id:\d+>/sign'] = 'bozp/contractor/sign-subpermit';
                $event->rules['POST bozp/c/<token:[A-Za-z0-9_\-]+>/subpermits/<id:\d+>/sign-prework'] = 'bozp/contractor/sign-subpermit-prework';

                // Control visits (Mondelez employee, logged-in with Craft account)
                $event->rules['bozp/c/<token:[A-Za-z0-9_\-]+>/control'] = 'bozp/contractor/control-view';
                $event->rules['POST bozp/c/<token:[A-Za-z0-9_\-]+>/control'] = 'bozp/contractor/save-control';

                // Issuer cancel / close (front-end issuer detail page)
                $event->rules['POST bozp/permits/<id:\d+>/cancel'] = 'bozp/permits/cancel';
                $event->rules['POST bozp/permits/<id:\d+>/close'] = 'bozp/permits/close';

                // Issuer attachment upload
                $event->rules['POST bozp/permits/<id:\d+>/upload'] = 'bozp/permits/upload-attachment';

                // Subpermit multi-signer token signing
                $event->rules['bozp/sp-sign/<token:[A-Za-z0-9]+>'] = 'bozp/subpermit-signing/view';
                $event->rules['POST bozp/sp-sign/sign']             = 'bozp/subpermit-signing/sign';

                // Subpermits (high-risk, attached to a general permit)
                $event->rules['bozp/permits/<permitId:\d+>/subpermits/new'] = 'bozp/subpermits/new';
                $event->rules['bozp/permits/<permitId:\d+>/subpermits/new/<type:[a-z_]+>'] = 'bozp/subpermits/form';
                $event->rules['POST bozp/permits/<permitId:\d+>/subpermits/save'] = 'bozp/subpermits/save';
                $event->rules['bozp/permits/<permitId:\d+>/subpermits/<id:\d+>'] = 'bozp/subpermits/view';
                $event->rules['POST bozp/permits/<permitId:\d+>/subpermits/<id:\d+>/cancel'] = 'bozp/subpermits/cancel';
                $event->rules['POST bozp/permits/<permitId:\d+>/subpermits/<id:\d+>/sign-prework'] = 'bozp/subpermits/sign-prework';
                $event->rules['POST bozp/permits/<permitId:\d+>/subpermits/<id:\d+>/sign-closure'] = 'bozp/subpermits/sign-closure';

                // PDF download (issuer side)
                $event->rules['bozp/permits/<id:\d+>/pdf'] = 'bozp/permits/pdf';
                $event->rules['bozp/permits/<permitId:\d+>/subpermits/<id:\d+>/pdf'] = 'bozp/subpermits/pdf';

                // PDF download (contractor portal — password-gated)
                $event->rules['bozp/c/<token:[A-Za-z0-9_\-]+>/pdf'] = 'bozp/contractor/permit-pdf';
                $event->rules['bozp/c/<token:[A-Za-z0-9_\-]+>/subpermits/<id:\d+>/pdf'] = 'bozp/contractor/subpermit-pdf';

                // Dev-only PDF preview routes (no auth, no asset write — for CSS iteration)
                if (Craft::$app->getConfig()->getGeneral()->devMode) {
                    $event->rules['bozp/debug']                          = 'bozp/debug/index';
                    $event->rules['bozp/debug/permit/<id:\d+>']          = 'bozp/debug/permit';
                    $event->rules['bozp/debug/permit/<id:\d+>/pdf']      = 'bozp/debug/permit-pdf';
                    $event->rules['bozp/debug/subpermit/<id:\d+>']       = 'bozp/debug/subpermit';
                    $event->rules['bozp/debug/subpermit/<id:\d+>/pdf']   = 'bozp/debug/subpermit-pdf';
                }
            }
        );
    }

    private function registerCpNavItem(): void
    {
        Event::on(
            Cp::class,
            Cp::EVENT_REGISTER_CP_NAV_ITEMS,
            static function (RegisterCpNavItemsEvent $event): void {
                $user = Craft::$app->getUser();
                if (!$user->getIdentity()) {
                    return;
                }

                $canViewQueue = $user->checkPermission('bozp:viewQueue');
                $canViewAll   = $user->checkPermission('bozp:viewAll');
                $canReportBug = $user->checkPermission('bozp:reportBug');

                if (!$canViewQueue && !$canReportBug) {
                    return;
                }

                $subnav = [];
                if ($canViewQueue) {
                    $subnav['queue'] = [
                        'label' => Craft::t('bozp', 'Schvaľovacia fronta'),
                        'url' => 'bozp/queue',
                    ];
                }
                if ($canViewAll) {
                    $subnav['all'] = [
                        'label' => Craft::t('bozp', 'Všetky permity'),
                        'url' => 'bozp/all',
                    ];
                }
                if ($canReportBug) {
                    $subnav['bug-report'] = [
                        'label' => Craft::t('bozp', 'Nahlásiť chybu'),
                        'url' => 'bozp/bug-report',
                    ];
                }

                // Default landing URL = first available subnav target.
                $defaultUrl = $canViewQueue ? 'bozp' : 'bozp/bug-report';

                $event->navItems[] = [
                    'url' => $defaultUrl,
                    'label' => Craft::t('bozp', 'BOZP Permity'),
                    'icon' => '@modules/bozp/icon-mask.svg',
                    'subnav' => $subnav,
                ];
            }
        );
    }

    private function registerUserPermissions(): void
    {
        Event::on(
            UserPermissions::class,
            UserPermissions::EVENT_REGISTER_PERMISSIONS,
            static function (RegisterUserPermissionsEvent $event): void {
                $event->permissions[] = [
                    'heading' => Craft::t('bozp', 'BOZP Permity'),
                    'permissions' => [
                        'bozp:createPermit' => [
                            'label' => Craft::t('bozp', 'Vytvárať permity'),
                        ],
                        'bozp:viewQueue' => [
                            'label' => Craft::t('bozp', 'Zobraziť schvaľovaciu frontu HSE'),
                        ],
                        'bozp:approve' => [
                            'label' => Craft::t('bozp', 'Schvaľovať / zamietať permity'),
                        ],
                        'bozp:viewAll' => [
                            'label' => Craft::t('bozp', 'Zobraziť všetky permity'),
                        ],
                        'bozp:manageZones' => [
                            'label' => Craft::t('bozp', 'Spravovať zóny'),
                        ],
                        'bozp:deletePermit' => [
                            'label' => Craft::t('bozp', 'Mazať permity'),
                        ],
                        'bozp:control' => [
                            'label' => Craft::t('bozp', 'Vykonávať kontroly prevádzky'),
                        ],
                        'bozp:viewMap' => [
                            'label' => Craft::t('bozp', 'Zobraziť mapu zón'),
                        ],
                        'bozp:reportBug' => [
                            'label' => Craft::t('bozp', 'Nahlásiť chybu (vytvorí úlohu v ClickUp)'),
                        ],
                    ],
                ];
            }
        );
    }
}
