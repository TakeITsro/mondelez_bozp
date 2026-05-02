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
use modules\bozp\services\PermitMailer;
use modules\bozp\services\PermitNumberGenerator;
use modules\bozp\services\PermitWorkflow;
use modules\bozp\services\SignatureService;
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
 * @property-read SignatureService $signatureService
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
            'signatureService' => SignatureService::class,
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
                $event->rules['POST bozp/permit/<id:\d+>/resend'] = 'bozp/queue/resend';
                $event->rules['POST bozp/permit/<id:\d+>/delete'] = 'bozp/queue/delete';
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

                // Contractor (token-gated, password-protected)
                $event->rules['bozp/c/<token:[A-Za-z0-9_\-]+>'] = 'bozp/contractor/view';
                $event->rules['POST bozp/c/<token:[A-Za-z0-9_\-]+>/auth'] = 'bozp/contractor/auth';
                $event->rules['POST bozp/c/<token:[A-Za-z0-9_\-]+>/upload'] = 'bozp/contractor/upload';
                $event->rules['POST bozp/c/<token:[A-Za-z0-9_\-]+>/close'] = 'bozp/contractor/close';
                $event->rules['POST bozp/c/<token:[A-Za-z0-9_\-]+>/cancel'] = 'bozp/contractor/cancel';

                // Issuer cancel / close (front-end issuer detail page)
                $event->rules['POST bozp/permits/<id:\d+>/cancel'] = 'bozp/permits/cancel';
                $event->rules['POST bozp/permits/<id:\d+>/close'] = 'bozp/permits/close';
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

                if (!$user->getIdentity() || !$user->checkPermission('bozp:viewQueue')) {
                    return;
                }

                $subnav = [
                    'queue' => [
                        'label' => Craft::t('bozp', 'Schvaľovacia fronta'),
                        'url' => 'bozp/queue',
                    ],
                ];

                if ($user->checkPermission('bozp:viewAll')) {
                    $subnav['all'] = [
                        'label' => Craft::t('bozp', 'Všetky permity'),
                        'url' => 'bozp/all',
                    ];
                }

                $event->navItems[] = [
                    'url' => 'bozp',
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
                    ],
                ];
            }
        );
    }
}
