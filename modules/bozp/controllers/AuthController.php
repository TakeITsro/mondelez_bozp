<?php

declare(strict_types=1);

namespace modules\bozp\controllers;

use Craft;
use craft\elements\User;
use craft\helpers\UrlHelper;
use craft\web\Controller;
use craft\web\View;
use yii\web\Response;

/**
 * AuthController
 *
 * Front-end login + logout for BOZP issuers (typically contractor-facing,
 * non-CP users). HSE officers with CP permissions can still use Craft's CP
 * login at /admin/login; this controller exists purely so that issuers never
 * see the Craft back-office chrome.
 *
 * Routes (registered in Module.php):
 *   GET  bozp/login   → actionLogin    (render form)
 *   POST bozp/login   → actionLogin    (validate + authenticate)
 *   POST bozp/logout  → actionLogout
 *
 * Recommended site config:
 *   config/general.php → 'loginPath' => 'bozp/login'
 * so that any $this->requireLogin() call in DashboardController /
 * PermitsController lands the user here with a proper returnUrl.
 */
class AuthController extends Controller
{
    protected array|bool|int $allowAnonymous = ['login'];

    public function actionLogin(): ?Response
    {
        $userService = Craft::$app->getUser();
        $request = Craft::$app->getRequest();

        // Already logged in? Skip straight to whatever they were trying to reach.
        if ($userService->getIdentity()) {
            return $this->redirect($this->postLoginUrl());
        }

        // Language switcher (?lang=sk|en). Persisted in a 30-day cookie
        // so the contractor / issuer doesn't have to re-toggle on each
        // visit. The cookie name is 'bozp_lang'.
        $langParam = (string) $request->getQueryParam('lang', '');
        if (in_array($langParam, ['sk', 'en'], true)) {
            Craft::$app->getResponse()->getCookies()->add(new \yii\web\Cookie([
                'name' => 'bozp_lang',
                'value' => $langParam,
                'expire' => time() + 30 * 24 * 60 * 60,
                'httpOnly' => true,
                'secure' => $request->getIsSecureConnection(),
                'sameSite' => \yii\web\Cookie::SAME_SITE_LAX,
            ]));
            Craft::$app->language = $langParam;
        } else {
            $cookieLang = (string) $request->getCookies()->getValue('bozp_lang', '');
            if (in_array($cookieLang, ['sk', 'en'], true)) {
                Craft::$app->language = $cookieLang;
            }
        }

        $this->view->setTemplateMode(View::TEMPLATE_MODE_SITE);

        if ($request->getIsGet()) {
            return $this->renderTemplate('bozp/site/login', [
                'loginName' => '',
                'rememberMe' => false,
                'errors' => [],
                'currentLang' => Craft::$app->language,
            ]);
        }

        // POST ----------------------------------------------------------------

        $this->requirePostRequest();

        $loginName = trim((string) $request->getBodyParam('loginName', ''));
        $password = (string) $request->getBodyParam('password', '');
        $rememberMe = (bool) $request->getBodyParam('rememberMe', false);

        $errors = [];
        if ($loginName === '') {
            $errors['loginName'] = (string) Craft::t('bozp', 'Zadajte prihlasovacie meno alebo e-mail.');
        }
        if ($password === '') {
            $errors['password'] = (string) Craft::t('bozp', 'Zadajte heslo.');
        }

        if ($errors === []) {
            /** @var User|null $user */
            $user = Craft::$app->getUsers()->getUserByUsernameOrEmail($loginName);

            if (!$user) {
                Craft::error("BOZP login: no user found for '{$loginName}'", __METHOD__);
                $errors['password'] = (string) Craft::t('bozp', 'Nesprávne prihlasovacie údaje.')
                    . ' [debug: user_not_found]';
            } elseif (!$user->authenticate($password)) {
                $reason = sprintf(
                    'authError=%s status=%s locked=%s suspended=%s',
                    $user->authError ?? 'null',
                    $user->getStatus(),
                    $user->locked ? '1' : '0',
                    $user->suspended ? '1' : '0'
                );
                Craft::error("BOZP login: authenticate() failed for '{$loginName}' ({$reason})", __METHOD__);
                $errors['password'] = (string) Craft::t('bozp', 'Nesprávne prihlasovacie údaje.')
                    . ' [debug: ' . $reason . ']';
            } elseif ($user->getStatus() !== User::STATUS_ACTIVE) {
                $errors['password'] = (string) Craft::t('bozp', 'Účet nie je aktívny. Kontaktujte HSE.');
            } else {
                $duration = $rememberMe
                    ? Craft::$app->getConfig()->getGeneral()->userSessionDuration
                    : 0;

                if (!$userService->login($user, $duration)) {
                    $errors['password'] = (string) Craft::t('bozp', 'Prihlásenie zlyhalo. Skúste znova.');
                } else {
                    Craft::$app->getSession()->setNotice(
                        Craft::t('bozp', 'Prihlásenie bolo úspešné.')
                    );
                    return $this->redirect($this->postLoginUrl());
                }
            }
        }

        return $this->renderTemplate('bozp/site/login', [
            'loginName' => $loginName,
            'rememberMe' => $rememberMe,
            'errors' => $errors,
            'currentLang' => Craft::$app->language,
        ]);
    }

    public function actionLogout(): Response
    {
        $this->requirePostRequest();

        Craft::$app->getUser()->logout(false);
        Craft::$app->getSession()->setNotice(
            Craft::t('bozp', 'Boli ste odhlásení.')
        );

        return $this->redirect(UrlHelper::siteUrl('bozp/login'));
    }

    /**
     * Where to send a user after a successful login. Craft stores the
     * previously-requested URL in session if a protected page triggered the
     * redirect; otherwise we fall back to the dashboard.
     */
    private function postLoginUrl(): string
    {
        $userService = Craft::$app->getUser();
        $fallback = UrlHelper::siteUrl('bozp');
        $returnUrl = $userService->getReturnUrl($fallback);

        // Guard against getReturnUrl() echoing our own login URL (would loop).
        if (str_contains((string) $returnUrl, 'bozp/login')) {
            return $fallback;
        }

        return $returnUrl ?: $fallback;
    }
}
