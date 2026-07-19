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
        $this->registerCpAccessGuard();

        Craft::info('BOZP module loaded.', __METHOD__);
    }

    /**
     * Intercept any CP request from a user without `accessCp` and redirect
     * to the friendly /403 page. Login + logout + Craft asset paths
     * are allowed through so HSE officers can still authenticate.
     */
    private function registerCpAccessGuard(): void
    {
        Event::on(
            \yii\base\Application::class,
            \yii\base\Application::EVENT_BEFORE_REQUEST,
            static function (): void {
                $app     = Craft::$app;
                $request = $app->getRequest();

                if ($request->getIsConsoleRequest() || !$request->getIsCpRequest()) {
                    return;
                }

                // Path inside cpTrigger (e.g. 'login', 'permits/12').
                $path = $request->getPathInfo();

                // Always-allowed CP paths so login + asset loading still work.
                if (
                    $path === ''
                    || $path === 'login'
                    || $path === 'logout'
                    || str_starts_with($path, 'resources/')
                    || str_starts_with($path, 'cpresources/')
                    || str_starts_with($path, 'actions/users/')
                ) {
                    return;
                }

                $userComponent = $app->getUser();
                $identity      = $userComponent->getIdentity();

                if ($identity !== null && $userComponent->checkPermission('accessCp')) {
                    return;
                }

                $response = $app->getResponse();
                $response->redirect(\craft\helpers\UrlHelper::siteUrl('403'))->send();
                $app->end();
            }
        );
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
                // CP paths are relative to the cpTrigger (e.g. /hse/permits).
                // Targets keep the internal module handle (bozp/<controller>/<action>).
                $event->rules['permits'] = 'bozp/queue/index';
                $event->rules['permits/all'] = 'bozp/queue/all';
                $event->rules['permits/<id:\d+>'] = 'bozp/queue/view';
                $event->rules['permits/<permitId:\d+>/edit'] = 'bozp/queue/edit-permit';
                $event->rules['POST permits/<id:\d+>/resend'] = 'bozp/queue/resend';
                $event->rules['POST permits/<id:\d+>/hse-close'] = 'bozp/queue/hse-close';
                $event->rules['POST permits/<id:\d+>/delete'] = 'bozp/queue/delete';
                $event->rules['POST permits/<permitId:\d+>/subpermits/<id:\d+>/approve'] = 'bozp/queue/approve-subpermit';
                $event->rules['POST permits/<permitId:\d+>/subpermits/<id:\d+>/reject'] = 'bozp/queue/reject-subpermit';
                $event->rules['permits/<permitId:\d+>/subpermit/<id:\d+>'] = 'bozp/queue/subpermit-view';
                $event->rules['permits/<permitId:\d+>/subpermit/<id:\d+>/edit'] = 'bozp/queue/edit-subpermit';

                // CP PDF download
                $event->rules['permits/<id:\d+>/pdf'] = 'bozp/queue/permit-pdf';
                $event->rules['permits/<permitId:\d+>/subpermit/<id:\d+>/pdf'] = 'bozp/queue/subpermit-pdf';

                // Bug report → ClickUp
                $event->rules['bug-report']      = 'bozp/bug-report/index';
                $event->rules['POST bug-report'] = 'bozp/bug-report/submit';
            }
        );
    }

    private function registerSiteUrlRules(): void
    {
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_SITE_URL_RULES,
            static function (RegisterUrlRulesEvent $event): void {
                // PWA shell assets (served from module, scope = site root)
                $event->rules['manifest.webmanifest']            = 'bozp/pwa/manifest';
                $event->rules['sw.js']                           = 'bozp/pwa/service-worker';
                $event->rules['bozp-pwa-icon.svg']               = 'bozp/pwa/icon';
                // iOS Safari "Add to Home Screen" probes both filenames.
                $event->rules['apple-touch-icon.png']            = 'bozp/pwa/apple-touch-icon';
                $event->rules['apple-touch-icon-precomposed.png']= 'bozp/pwa/apple-touch-icon';

                // Root → dashboard (DashboardController bounces anonymous
                // visitors to /login via requireBozpLogin).
                $event->rules[''] = 'bozp/dashboard/index';

                // Auth
                $event->rules['login'] = 'bozp/auth/login';
                $event->rules['POST login'] = 'bozp/auth/login';
                $event->rules['POST logout'] = 'bozp/auth/logout';

                // Friendly 403 page for users without CP access
                $event->rules['403'] = 'bozp/auth/forbidden';

                // Permits (issuer)
                $event->rules['dashboard'] = 'bozp/dashboard/index';
                $event->rules['permits'] = 'bozp/dashboard/index';
                $event->rules['permits/new'] = 'bozp/permits/new';
                $event->rules['POST permits/save'] = 'bozp/permits/save';
                $event->rules['permits/<id:\d+>'] = 'bozp/permits/view';

                // Facility map
                $event->rules['map'] = 'bozp/map/index';
                $event->rules['map/zone/<id:\d+>'] = 'bozp/map/zone';

                // Contractor portal (token-gated, password-protected)
                $event->rules['contractor/<token:[A-Za-z0-9_\-]+>'] = 'bozp/contractor/view';
                $event->rules['POST contractor/<token:[A-Za-z0-9_\-]+>/auth'] = 'bozp/contractor/auth';
                $event->rules['POST contractor/<token:[A-Za-z0-9_\-]+>/upload'] = 'bozp/contractor/upload';
                $event->rules['POST contractor/<token:[A-Za-z0-9_\-]+>/close'] = 'bozp/contractor/close';
                $event->rules['POST contractor/<token:[A-Za-z0-9_\-]+>/cancel'] = 'bozp/contractor/cancel';
                $event->rules['POST contractor/<token:[A-Za-z0-9_\-]+>/subpermits/<id:\d+>/sign'] = 'bozp/contractor/sign-subpermit';
                $event->rules['POST contractor/<token:[A-Za-z0-9_\-]+>/subpermits/<id:\d+>/sign-prework'] = 'bozp/contractor/sign-subpermit-prework';

                // Control visits (Mondelez employee, logged-in with Craft account)
                $event->rules['contractor/<token:[A-Za-z0-9_\-]+>/control'] = 'bozp/contractor/control-view';
                $event->rules['POST contractor/<token:[A-Za-z0-9_\-]+>/control'] = 'bozp/contractor/save-control';

                // Issuer cancel / close (front-end issuer detail page)
                $event->rules['POST permits/<id:\d+>/cancel'] = 'bozp/permits/cancel';
                $event->rules['POST permits/<id:\d+>/close'] = 'bozp/permits/close';

                // Issuer attachment upload
                $event->rules['POST permits/<id:\d+>/upload'] = 'bozp/permits/upload-attachment';

                // Subpermit multi-signer token signing
                $event->rules['sign/<token:[A-Za-z0-9]+>']     = 'bozp/subpermit-signing/view';
                $event->rules['sign/<token:[A-Za-z0-9]+>/pdf'] = 'bozp/subpermit-signing/pdf';
                $event->rules['POST sign/submit']              = 'bozp/subpermit-signing/sign';

                // Subpermits (high-risk, attached to a general permit)
                $event->rules['permits/<permitId:\d+>/subpermits/new'] = 'bozp/subpermits/new';
                $event->rules['permits/<permitId:\d+>/subpermits/new/<type:[a-z_]+>'] = 'bozp/subpermits/form';
                $event->rules['POST permits/<permitId:\d+>/subpermits/save'] = 'bozp/subpermits/save';
                $event->rules['permits/<permitId:\d+>/subpermits/<id:\d+>'] = 'bozp/subpermits/view';
                $event->rules['POST permits/<permitId:\d+>/subpermits/<id:\d+>/cancel'] = 'bozp/subpermits/cancel';
                $event->rules['POST permits/<permitId:\d+>/subpermits/<id:\d+>/sign-prework'] = 'bozp/subpermits/sign-prework';
                $event->rules['POST permits/<permitId:\d+>/subpermits/<id:\d+>/sign-closure'] = 'bozp/subpermits/sign-closure';
                $event->rules['POST permits/<permitId:\d+>/subpermits/<id:\d+>/upload']      = 'bozp/subpermits/upload-attachment';

                // PDF download (issuer side)
                $event->rules['permits/<id:\d+>/pdf'] = 'bozp/permits/pdf';
                $event->rules['permits/<permitId:\d+>/subpermits/<id:\d+>/pdf'] = 'bozp/subpermits/pdf';

                // PDF download (contractor portal — password-gated)
                $event->rules['contractor/<token:[A-Za-z0-9_\-]+>/pdf'] = 'bozp/contractor/permit-pdf';
                $event->rules['contractor/<token:[A-Za-z0-9_\-]+>/subpermits/<id:\d+>/pdf'] = 'bozp/contractor/subpermit-pdf';

                // Dev-only PDF preview routes (no auth, no asset write — for CSS iteration)
                if (Craft::$app->getConfig()->getGeneral()->devMode) {
                    $event->rules['debug']                          = 'bozp/debug/index';
                    $event->rules['debug/permit/<id:\d+>']          = 'bozp/debug/permit';
                    $event->rules['debug/permit/<id:\d+>/pdf']      = 'bozp/debug/permit-pdf';
                    $event->rules['debug/subpermit/<id:\d+>']       = 'bozp/debug/subpermit';
                    $event->rules['debug/subpermit/<id:\d+>/pdf']   = 'bozp/debug/subpermit-pdf';
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
                        'url' => 'permits',
                    ];
                }
                if ($canViewAll) {
                    $subnav['all'] = [
                        'label' => Craft::t('bozp', 'Všetky permity'),
                        'url' => 'permits/all',
                    ];
                }
                if ($canReportBug) {
                    $subnav['bug-report'] = [
                        'label' => Craft::t('bozp', 'Nahlásiť chybu'),
                        'url' => 'bug-report',
                    ];
                }

                // Default landing URL = first available subnav target.
                $defaultUrl = $canViewQueue ? 'permits' : 'bug-report';

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
                        'bozp:receiveHseEmails' => [
                            'label' => Craft::t('bozp', 'Dostávať HSE notifikačné e-maily'),
                        ],
                    ],
                ];
            }
        );
    }
}
