<?php

declare(strict_types=1);

namespace modules\bozp\services;

use Craft;
use craft\elements\User;
use craft\helpers\App;
use craft\helpers\UrlHelper;
use craft\web\View;
use craft\elements\Asset;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;
use modules\bozp\enums\SubpermitType;
use modules\bozp\records\PermitControlRecord;
use modules\bozp\records\PermitRecord;
use modules\bozp\records\SubpermitRecord;
use Throwable;
use yii\base\Component;

/**
 * PermitMailer
 *
 * Notification emails for permit lifecycle events. All sends are wrapped in
 * try/catch so a mailer failure never blocks the underlying state transition.
 *
 * Templates live under templates/email/ and extend email/_layout.twig.
 */
class PermitMailer extends Component
{
    /**
     * New permit submitted — notify HSE officer.
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
                'permit'     => $permit,
                'permitUrl'  => UrlHelper::cpUrl('bozp/permit/' . $permit->id),
                'subpermits' => $this->loadSubpermitsForEmail($permit),
            ],
        );
    }

    /**
     * Permit approved — notify issuer (with subpermit list) and contractor
     * (with link + password + QR + subpermit list).
     */
    public function notifyParticipantsOfApproval(PermitRecord $permit, ?string $contractorPassword = null): void
    {
        $issuer = $permit->issuerId ? User::find()->id($permit->issuerId)->one() : null;
        $issuerUrl = UrlHelper::siteUrl('bozp/permits/' . $permit->id);
        $subpermits = $this->loadSubpermitsForEmail($permit);

        if ($issuer && $issuer->email) {
            $this->send(
                to: $issuer->email,
                language: $issuer->getPreferredLanguage() ?? Craft::$app->language,
                subjectKey: 'Permit {n} bol schválený',
                subjectParams: ['n' => $permit->permitNumber],
                template: 'approved',
                vars: ['permit' => $permit, 'permitUrl' => $issuerUrl, 'subpermits' => $subpermits],
            );
        }

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
                    'permit'     => $permit,
                    'permitUrl'  => $contractorUrl,
                    'password'   => $contractorPassword,
                    'qrDataUri'  => $qrDataUri,
                    'subpermits' => $subpermits,
                ],
            );
        }
    }

    /**
     * Permit rejected — notify issuer and contractor with the reason.
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

    /**
     * Contractor signed the permit (close or cancel) — notify the issuer.
     *
     * @param string $action  'close' | 'cancel'
     * @param string $signerName
     */
    public function notifyIssuerOfContractorSignature(
        PermitRecord $permit,
        string $action,
        string $signerName,
    ): void {
        $issuer = $permit->issuerId ? User::find()->id($permit->issuerId)->one() : null;
        if (!$issuer || !$issuer->email) {
            return;
        }

        $permitUrl = UrlHelper::siteUrl('bozp/permits/' . $permit->id);

        $this->send(
            to: $issuer->email,
            language: $issuer->getPreferredLanguage() ?? Craft::$app->language,
            subjectKey: $action === 'cancel'
                ? 'Dodávateľ zrušil permit {n}'
                : 'Dodávateľ podpísal dokončenie permitu {n}',
            subjectParams: ['n' => $permit->permitNumber],
            template: 'contractor-signed',
            vars: [
                'permit'      => $permit,
                'permitUrl'   => $permitUrl,
                'signerName'  => $signerName,
                'action'      => $action,
            ],
        );
    }

    /**
     * Issuer signed the permit (close or cancel) — notify the contractor.
     *
     * @param string $action  'close' | 'cancel'
     * @param string $signerName
     */
    public function notifyContractorOfIssuerSignature(
        PermitRecord $permit,
        string $action,
        string $signerName,
    ): void {
        if (empty($permit->contractorEmail) || empty($permit->accessToken)) {
            return;
        }

        $contractorUrl = UrlHelper::siteUrl('bozp/c/' . $permit->accessToken);

        $this->send(
            to: $permit->contractorEmail,
            language: Craft::$app->getSites()->getPrimarySite()->language,
            subjectKey: $action === 'cancel'
                ? 'Vydavateľ zrušil permit {n}'
                : 'Permit {n} bol uzavretý',
            subjectParams: ['n' => $permit->permitNumber],
            template: 'issuer-signed',
            vars: [
                'permit'      => $permit,
                'permitUrl'   => $contractorUrl,
                'signerName'  => $signerName,
                'action'      => $action,
            ],
        );
    }

    /**
     * Permit closed by the issuer — send the final signed PDF to both
     * the issuer (employee) and the contractor as an email attachment.
     *
     * Requires the closed PDF to have been (re)generated before this is called.
     */
    public function notifyParticipantsOfClosure(PermitRecord $permit, string $signerName): void
    {
        $attachment = $this->loadPermitPdfAttachment($permit);
        if ($attachment === null) {
            Craft::warning(
                "BOZP mailer: closure email for permit #{$permit->id} sent without PDF (asset missing).",
                __METHOD__,
            );
        }

        $issuer = $permit->issuerId ? User::find()->id($permit->issuerId)->one() : null;

        // Issuer (Mondelez employee)
        if ($issuer && $issuer->email) {
            $this->send(
                to: $issuer->email,
                language: $issuer->getPreferredLanguage() ?? Craft::$app->language,
                subjectKey: 'Permit {n} bol uzavretý',
                subjectParams: ['n' => $permit->permitNumber],
                template: 'closed',
                vars: [
                    'permit'     => $permit,
                    'permitUrl'  => UrlHelper::siteUrl('bozp/permits/' . $permit->id),
                    'signerName' => $signerName,
                    'recipient'  => 'issuer',
                ],
                attachments: $attachment ? [$attachment] : [],
            );
        }

        // Contractor
        if (!empty($permit->contractorEmail) && !empty($permit->accessToken)) {
            $this->send(
                to: $permit->contractorEmail,
                language: Craft::$app->getSites()->getPrimarySite()->language,
                subjectKey: 'Permit {n} bol uzavretý',
                subjectParams: ['n' => $permit->permitNumber],
                template: 'closed',
                vars: [
                    'permit'     => $permit,
                    'permitUrl'  => UrlHelper::siteUrl('bozp/c/' . $permit->accessToken),
                    'signerName' => $signerName,
                    'recipient'  => 'contractor',
                ],
                attachments: $attachment ? [$attachment] : [],
            );
        }
    }

    /**
     * Control visit recorded — notify both issuer and contractor.
     */
    public function notifyParticipantsOfControl(
        PermitRecord $permit,
        PermitControlRecord $control,
    ): void {
        $issuer = $permit->issuerId ? User::find()->id($permit->issuerId)->one() : null;
        $issuerUrl = UrlHelper::siteUrl('bozp/permits/' . $permit->id);

        $vars = [
            'permit'          => $permit,
            'controllerName'  => $control->controllerName,
            'controlledAt'    => $control->controlledAt,
            'result'          => $control->result,
            'notes'           => $control->notes,
        ];

        // Notify issuer
        if ($issuer && $issuer->email) {
            $this->send(
                to: $issuer->email,
                language: $issuer->getPreferredLanguage() ?? Craft::$app->language,
                subjectKey: 'Kontrola prevádzky — permit {n}',
                subjectParams: ['n' => $permit->permitNumber],
                template: 'control-visit',
                vars: array_merge($vars, ['permitUrl' => $issuerUrl]),
            );
        }

        // Notify contractor
        if (!empty($permit->contractorEmail) && !empty($permit->accessToken)) {
            $contractorUrl = UrlHelper::siteUrl('bozp/c/' . $permit->accessToken);
            $this->send(
                to: $permit->contractorEmail,
                language: Craft::$app->getSites()->getPrimarySite()->language,
                subjectKey: 'Kontrola prevádzky — permit {n}',
                subjectParams: ['n' => $permit->permitNumber],
                template: 'control-visit',
                vars: array_merge($vars, ['permitUrl' => $contractorUrl]),
            );
        }
    }

    // -- internals -------------------------------------------------------

    /**
     * Load subpermits for a permit, enriched with a typeLabel property
     * for easy use in email templates.
     *
     * @return array<int, object> plain objects with typeLabel added
     */
    private function loadSubpermitsForEmail(PermitRecord $permit): array
    {
        $rows = SubpermitRecord::find()
            ->where(['parentPermitId' => $permit->id])
            ->orderBy(['dateCreated' => SORT_ASC])
            ->all();

        foreach ($rows as $row) {
            $type = SubpermitType::tryFrom($row->type);
            $row->typeLabel = $type ? $type->label() : $row->type;
        }

        return $rows;
    }

    /**
     * Render the contractor link as a base64 PNG data URI.
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
     * @param array<string, mixed>                                    $subjectParams
     * @param array<string, mixed>                                    $vars
     * @param array<int, array{content: string, filename: string, contentType?: string}> $attachments
     */
    private function send(
        string $to,
        string $language,
        string $subjectKey,
        array $subjectParams,
        string $template,
        array $vars,
        array $attachments = [],
    ): void {
        $previousLanguage = Craft::$app->language;
        $view = Craft::$app->getView();
        $previousMode = $view->getTemplateMode();

        try {
            Craft::$app->language = $language;
            $view->setTemplateMode(View::TEMPLATE_MODE_SITE);

            $subject = (string) Craft::t('bozp', $subjectKey, $subjectParams);
            $html = $view->renderTemplate('bozp/email/' . $template, $vars);

            $message = Craft::$app->getMailer()
                ->compose()
                ->setTo($to)
                ->setSubject($subject)
                ->setHtmlBody($html);

            foreach ($attachments as $att) {
                if (empty($att['content']) || empty($att['filename'])) {
                    continue;
                }
                $message->attachContent($att['content'], [
                    'fileName'    => $att['filename'],
                    'contentType' => $att['contentType'] ?? 'application/pdf',
                ]);
            }

            $message->send();
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

    /**
     * Load the permit's stored PDF as an in-memory attachment payload.
     *
     * @return array{content: string, filename: string, contentType: string}|null
     */
    private function loadPermitPdfAttachment(PermitRecord $permit): ?array
    {
        if (empty($permit->pdfAssetId)) {
            return null;
        }

        /** @var Asset|null $asset */
        $asset = Craft::$app->getAssets()->getAssetById((int) $permit->pdfAssetId);
        if (!$asset) {
            return null;
        }

        try {
            $content = $asset->getContents();
            if ($content === '' || $content === false) {
                Craft::warning(
                    "BOZP mailer: PDF asset #{$asset->id} returned empty contents.",
                    __METHOD__,
                );
                return null;
            }
            return [
                'content'     => $content,
                'filename'    => $asset->filename ?: ('permit-' . $permit->permitNumber . '.pdf'),
                'contentType' => 'application/pdf',
            ];
        } catch (Throwable $e) {
            Craft::error('BOZP mailer: failed to read PDF asset: ' . $e->getMessage(), __METHOD__);
            return null;
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
