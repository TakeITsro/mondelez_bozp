<?php

declare(strict_types=1);

namespace modules\bozp\controllers;

use Craft;
use craft\helpers\UrlHelper;
use craft\web\Controller;
use yii\web\Response;

/**
 * BaseSiteController
 *
 * Shared base for BOZP front-end (site) controllers. Provides a
 * BOZP-specific login redirect so anonymous visitors land on
 * /bozp/login instead of Craft's CP login (/admin/login) — which is
 * what would otherwise happen depending on `config/general.php`'s
 * `loginPath`. The CP queue controllers are unaffected and keep using
 * Craft's standard CP auth.
 */
abstract class BaseSiteController extends Controller
{
    /**
     * Like Controller::requireLogin(), but bounces anonymous requests to
     * /bozp/login (preserving the requested URL as returnUrl) instead of
     * the site-wide login path.
     *
     * Returns a redirect Response when not logged in; controller actions
     * should `return $this->requireBozpLogin() ?? <normal flow>` — but
     * because Yii throws to short-circuit on redirects from beforeAction,
     * we use the simpler pattern of calling this at the top of the action
     * and `return`ing its result if non-null.
     */
    protected function requireBozpLogin(): ?Response
    {
        $userService = Craft::$app->getUser();
        if ($userService->getIdentity()) {
            return null;
        }

        // Stash the originally-requested URL so AuthController can send the
        // user back there after a successful login.
        $request = Craft::$app->getRequest();
        if (!$request->getIsPost()) {
            $userService->setReturnUrl($request->getAbsoluteUrl());
        }

        return $this->redirect(UrlHelper::siteUrl('bozp/login'));
    }
}
