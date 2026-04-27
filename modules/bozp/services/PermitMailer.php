<?php

declare(strict_types=1);

namespace modules\bozp\services;

use Craft;
use craft\elements\User;
use craft\helpers\App;
use craft\helpers\UrlHelper;
use craft\web\View;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;
use modules\bozp\records\PermitRecord;
use Throwable;
use yii\base\Component;

/**
 * PermitMailer
 *
 * Notification emails for permit lifecycle events. Intentionally short:
 * subject + a one-paragraph message + a link to the permit. The full
 * permit content is NOT included — recipients open the link to see it.
 *
 * All sends are wrapped in try/catch so a mailer failure never blocks
 * the underlying state transition (approving a permit must not fail
 * because the SMTP server is down).
 *
 * Templates live under templates/email/. Each template receives:
 *   - permit:        PermitRecord
 *   - permitUrl:     absolute URL to view the permit (front-end or CP)
 *   - reason:        rejection reason (rejected.twig only)
 *
 * Recipient language: for Craft users we temporarily switch to their
 * preferred language before rendering; for the contractor (no account)
 * we use the site's default language (Slovak).
 */
class PermitMailer extends Component
{
    /**
     * Permit moved to "submitted" — notify HSE officer.
     * HSE recipient: first user with bozp:approve permission, or fall
     * back to env BOZP_HSE_EMAIL if no such user exists.
     */
    public function notifyHseOfSubmission(PermitRecord $permit): void
    {
        $hseEmail = $this->resolveHseEmail();
        if (!$hseEmail) {
            Craft::warning("BOZP mailer: no HSE recipient resolved for permit #{$permit->id}", __METHOD__);
            return;
        }

        $hseUser = $this->findHseUser();
        $language = $hseUser?->getPreferredLanguage() ?? Craft::$app->language;

        $this->send(
            to: $hseEmail,
            language: $language,
            subjectKey: 'Nový permit čaká na schválenie: {n}',
            subjectParams: ['n' => $permit->permitNumber],
            template: 'submitted-hse',
            vars: [
                'permit' => $permit,
                'permitUrl' => UrlHelper::cpUrl('bozp/permit/' . $permit->id),
            ],
        );
    }

    /**
     * Permit approved — notify issuer (simple link) and contractor
     * (link + plaintext password + QR code embedded inline).
     *
     * The plaintext password is passed in by the workflow ONCE at
     * approval; we never read it from the DB (only the hash is stored).
     */
    public function notifyParticipantsOfApproval(PermitRecord $permit, ?string $contractorPassword = null): void
    {
        $issuer = $permit->issuerId ? User::find()->id($permit->issuerId)->one() : null;
        $issuerUrl = UrlHelper::siteUrl('bozp/permits/' . $permit->id);

        // Issuer — simple internal link to the front-end detail page.
        if ($issuer && $issuer->email) {
            $this->send(
                to: $issuer->email,
                language: $issuer->getPreferredLanguage() ?? Craft::$app->language,
                subjectKey: 'Permit {n} bol schválený',
                subjectParams: ['n' => $permit->permitNumber],
                template: 'approved',
                vars: ['permit' => $permit, 'permitUrl' => $issuerUrl],
            );
        }

        // Contractor — token URL + password + QR. Skip if we don't have
        // both the email and the access credentials (defensive).
        if (
            !empty($permit->contractorEmail)
            && !empty($permit->accessToken)
            && $contractorPassword !== null
        ) {
            $contractorUrl = UrlHelper::siteUrl('bozp/c/' . $permit->accessToken);
            $qrDataUri = $this->buildQrDataUri($contractorUrl);

            $this->send(
                to: $permit->contractorEmail,
                language: Craft::$app->getSites()->getPrimarySite()->language,
                subjectKey: 'Permit {n} bol schválený',
                subjectParams: ['n' => $permit->permitNumber],
                template: 'approved-contractor',
                vars: [
                    'permit' => $permit,
                    'permitUrl' => $contractorUrl,
                    'password' => $contractorPassword,
                    'qrDataUri' => $qrDataUri,
                ],
            );
        }
    }

    /**
     * Render the contractor link as a base64 PNG data URI.
     * Returns null on failure so the email still sends without QR.
     */
    private function buildQrDataUri(string $url): ?string
    {
        try {
            $result = Builder::create()
                ->writer(new PngWriter())
                ->data($url)
                ->size(220)
                ->margin(8)
                ->build();
            return 'data:image/png;base64,' . base64_encode($result->getString());
        } catch (Throwable $e) {
            Craft::error('BOZP mailer: QR generation failed: ' . $e->getMessage(), __METHOD__);
            return null;
        }
    }

    /**
     * Permit rejected — notify contractor and issuer with the reason.
     */
    public function notifyParticipantsOfRejection(PermitRecord $permit, string $reason): void
    {
        $issuer = $permit->issuerId ? User::find()->id($permit->issuerId)->one() : null;
        $permitUrl = UrlHelper::siteUrl('bozp/permits/' . $permit->id);

        if ($issuer && $issuer->email) {
            $this->send(
                to: $issuer->email,
                language: $issuer->getPreferredLanguage() ?? Craft::$app->language,
                subjectKey: 'Permit {n} bol zamietnutý',
                subjectParams: ['n' => $permit->permitNumber],
                template: 'rejected',
                vars: ['permit' => $permit, 'permitUrl' => $permitUrl, 'reason' => $reason],
            );
        }

        if (!empty($permit->contractorEmail)) {
            $this->send(
                to: $permit->contractorEmail,
                language: Craft::$app->getSites()->getPrimarySite()->language,
                subjectKey: 'Permit {n} bol zamietnutý',
                subjectParams: ['n' => $permit->permitNumber],
                template: 'rejected',
                vars: ['permit' => $permit, 'permitUrl' => $permitUrl, 'reason' => $reason],
            );
        }
    }

    // -- internals -------------------------------------------------------

    /**
     * Render and send. Renders subject + HTML body in $language,
     * restoring the previous app language afterwards. Failures are
     * logged, never thrown.
     *
     * @param array<string, mixed> $subjectParams
     * @param array<string, mixed> $vars
     */
    private function send(
        string $to,
        string $language,
        string $subjectKey,
        array $subjectParams,
        string $template,
        array $vars,
    ): void {
        $previousLanguage = Craft::$app->language;
        $view = Craft::$app->getView();
        $previousMode = $view->getTemplateMode();

        try {
            Craft::$app->language = $language;
            $view->setTemplateMode(View::TEMPLATE_MODE_SITE);

            $subject = (string) Craft::t('bozp', $subjectKey, $subjectParams);
            $html = $view->renderTemplate('bozp/email/' . $template, $vars);

            Craft::$app->getMailer()
                ->compose()
                ->setTo($to)
                ->setSubject($subject)
                ->setHtmlBody($html)
                ->send();
        } catch (Throwable $e) {
            Craft::error(
                "BOZP mailer: send to {$to} (template={$template}) failed: " . $e->getMessage(),
                __METHOD__,
            );
        } finally {
            Craft::$app->language = $previousLanguage;
            $view->setTemplateMode($previousMode);
        }
    }

    private function findHseUser(): ?User
    {
        return User::find()->can('bozp:approve')->status(null)->one();
    }

    private function resolveHseEmail(): ?string
    {
        $user = $this->findHseUser();
        if ($user && $user->email) {
            return $user->email;
        }
        $envEmail = trim((string) App::env('BOZP_HSE_EMAIL'));
        return $envEmail !== '' ? $envEmail : null;
    }
}
