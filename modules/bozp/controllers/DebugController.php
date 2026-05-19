<?php

declare(strict_types=1);

namespace modules\bozp\controllers;

use Craft;
use craft\web\Controller;
use craft\web\Response as WebResponse;
use modules\bozp\Module;
use modules\bozp\records\PermitRecord;
use modules\bozp\records\SubpermitRecord;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * DebugController
 *
 * Developer-only preview of permit/subpermit PDFs. Renders the same templates
 * used for the production PDF, but on-demand, against existing records, with
 * no asset write and no auth checks — so you can iterate on the PDF CSS
 * without creating a new permit each time.
 *
 * Routes are only registered when Craft is in devMode. The controller still
 * double-checks devMode on every action as a safety net.
 *
 *   GET /bozp/debug                            — index, lists permits & subpermits
 *   GET /bozp/debug/permit/<id>                — HTML render (fast, hot-reload friendly)
 *   GET /bozp/debug/permit/<id>/pdf            — PDF render (inline in browser)
 *   GET /bozp/debug/subpermit/<id>             — HTML render
 *   GET /bozp/debug/subpermit/<id>/pdf         — PDF render
 *
 * Query params (HTML mode):
 *   ?reload=2   — auto-refresh page every N seconds while you edit CSS
 */
class DebugController extends Controller
{
    public array|bool|int $allowAnonymous = true;
    public $enableCsrfValidation = false;

    public function beforeAction($action): bool
    {
        if (!Craft::$app->getConfig()->getGeneral()->devMode) {
            throw new ForbiddenHttpException('Debug routes are disabled outside devMode.');
        }
        return parent::beforeAction($action);
    }

    // -------------------------------------------------------------------------
    // Index
    // -------------------------------------------------------------------------

    public function actionIndex(): WebResponse
    {
        $permits = PermitRecord::find()
            ->orderBy(['dateCreated' => SORT_DESC])
            ->limit(50)
            ->all();

        $subpermits = SubpermitRecord::find()
            ->orderBy(['dateCreated' => SORT_DESC])
            ->limit(50)
            ->all();

        $html = $this->renderShell('BOZP PDF debug', $this->indexBody($permits, $subpermits));

        $response = Craft::$app->getResponse();
        $response->format = Response::FORMAT_RAW;
        $response->headers->set('Content-Type', 'text/html; charset=UTF-8');
        $response->data = $html;
        return $response;
    }

    // -------------------------------------------------------------------------
    // Permit
    // -------------------------------------------------------------------------

    public function actionPermit(int $id): WebResponse
    {
        $permit = $this->findPermit($id);
        $reload = max(0, (int) Craft::$app->getRequest()->getQueryParam('reload', 0));

        /** @var Module $module */
        $module = Craft::$app->getModule('bozp');
        $body = $module->permitPdfService->previewPermitHtml($permit);

        return $this->sendHtml($this->wrapPreview($body, $reload, "Permit #{$permit->id} — {$permit->permitNumber}"));
    }

    public function actionPermitPdf(int $id): WebResponse
    {
        $permit = $this->findPermit($id);

        /** @var Module $module */
        $module = Craft::$app->getModule('bozp');
        $pdfBytes = $module->permitPdfService->previewPermitPdfBytes($permit);

        return $this->sendInlinePdf($pdfBytes, "permit-{$permit->permitNumber}-preview.pdf");
    }

    // -------------------------------------------------------------------------
    // Subpermit
    // -------------------------------------------------------------------------

    public function actionSubpermit(int $id): WebResponse
    {
        $subpermit = $this->findSubpermit($id);
        $permit = $this->findPermit((int) $subpermit->parentPermitId);
        $reload = max(0, (int) Craft::$app->getRequest()->getQueryParam('reload', 0));

        /** @var Module $module */
        $module = Craft::$app->getModule('bozp');
        $body = $module->permitPdfService->previewSubpermitHtml($subpermit, $permit);

        return $this->sendHtml($this->wrapPreview($body, $reload, "Subpermit #{$subpermit->id} ({$subpermit->type})"));
    }

    public function actionSubpermitPdf(int $id): WebResponse
    {
        $subpermit = $this->findSubpermit($id);
        $permit = $this->findPermit((int) $subpermit->parentPermitId);

        /** @var Module $module */
        $module = Craft::$app->getModule('bozp');
        $pdfBytes = $module->permitPdfService->previewSubpermitPdfBytes($subpermit, $permit);

        return $this->sendInlinePdf($pdfBytes, "subpermit-{$subpermit->id}-preview.pdf");
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function findPermit(int $id): PermitRecord
    {
        $permit = PermitRecord::findOne(['id' => $id]);
        if (!$permit) {
            throw new NotFoundHttpException("Permit #{$id} not found.");
        }
        return $permit;
    }

    private function findSubpermit(int $id): SubpermitRecord
    {
        $sp = SubpermitRecord::findOne(['id' => $id]);
        if (!$sp) {
            throw new NotFoundHttpException("Subpermit #{$id} not found.");
        }
        return $sp;
    }

    private function sendHtml(string $html): WebResponse
    {
        $response = Craft::$app->getResponse();
        $response->format = Response::FORMAT_RAW;
        $response->headers->set('Content-Type', 'text/html; charset=UTF-8');
        $response->headers->set('Cache-Control', 'no-store');
        $response->data = $html;
        return $response;
    }

    private function sendInlinePdf(string $bytes, string $filename): WebResponse
    {
        $response = Craft::$app->getResponse();
        $response->format = Response::FORMAT_RAW;
        $response->headers->set('Content-Type', 'application/pdf');
        $response->headers->set('Content-Disposition', 'inline; filename="' . $filename . '"');
        $response->headers->set('Cache-Control', 'no-store');
        $response->data = $bytes;
        return $response;
    }

    /**
     * Wrap a PDF body render with a thin debug toolbar so the developer can
     * jump between permits, toggle PDF mode, and turn on auto-reload.
     */
    private function wrapPreview(string $body, int $reload, string $title): string
    {
        $reloadMeta = $reload > 0
            ? '<meta http-equiv="refresh" content="' . $reload . '">'
            : '';

        $url = Craft::$app->getRequest()->getAbsoluteUrl();
        $pdfUrl = preg_replace('#(/bozp/debug/(?:sub)?permit/\d+)(\?.*)?$#', '$1/pdf', $url);
        $reloadOnUrl = $this->urlWithParam($url, 'reload', $reload > 0 ? '0' : '2');

        $toolbar = '<div style="position:sticky;top:0;z-index:9999;background:#4F2170;color:#FEFFFD;'
            . 'padding:8px 16px;font:13px/1.4 system-ui,sans-serif;display:flex;gap:16px;align-items:center;">'
            . '<strong style="font-weight:700;">' . htmlspecialchars($title, ENT_QUOTES) . '</strong>'
            . '<a style="color:#FEFFFD;text-decoration:underline;" href="/bozp/debug">← index</a>'
            . '<a style="color:#FEFFFD;text-decoration:underline;" href="' . htmlspecialchars($pdfUrl, ENT_QUOTES) . '">view as PDF</a>'
            . '<a style="color:#FEFFFD;text-decoration:underline;" href="' . htmlspecialchars($reloadOnUrl, ENT_QUOTES) . '">'
            . ($reload > 0 ? 'auto-reload: ' . $reload . 's (off)' : 'auto-reload: off (turn on 2s)')
            . '</a>'
            . '<span style="margin-left:auto;opacity:.7;">HTML preview — CSS iteration mode</span>'
            . '</div>';

        return '<!doctype html><html><head><meta charset="utf-8">' . $reloadMeta
            . '<title>' . htmlspecialchars($title, ENT_QUOTES) . '</title></head><body style="margin:0;background:#ddd;">'
            . $toolbar
            . '<div style="padding:24px;display:flex;justify-content:center;">'
            . '<div style="width:210mm;min-height:297mm;background:#fff;box-shadow:0 4px 20px rgba(0,0,0,.15);padding:12mm;">'
            . $body
            . '</div></div></body></html>';
    }

    private function urlWithParam(string $url, string $key, string $value): string
    {
        $parts = parse_url($url);
        parse_str($parts['query'] ?? '', $q);
        $q[$key] = $value;
        $base = ($parts['scheme'] ?? 'http') . '://' . ($parts['host'] ?? '') . ($parts['path'] ?? '');
        return $base . '?' . http_build_query($q);
    }

    /**
     * @param PermitRecord[]    $permits
     * @param SubpermitRecord[] $subpermits
     */
    private function indexBody(array $permits, array $subpermits): string
    {
        $rows = '';
        foreach ($permits as $p) {
            $rows .= '<tr>'
                . '<td>#' . (int) $p->id . '</td>'
                . '<td>' . htmlspecialchars((string) $p->permitNumber, ENT_QUOTES) . '</td>'
                . '<td>' . htmlspecialchars((string) $p->status, ENT_QUOTES) . '</td>'
                . '<td>' . htmlspecialchars((string) ($p->contractorCompany ?? ''), ENT_QUOTES) . '</td>'
                . '<td><a href="/bozp/debug/permit/' . (int) $p->id . '">HTML</a></td>'
                . '<td><a href="/bozp/debug/permit/' . (int) $p->id . '/pdf">PDF</a></td>'
                . '</tr>';
        }

        $spRows = '';
        foreach ($subpermits as $sp) {
            $spRows .= '<tr>'
                . '<td>#' . (int) $sp->id . '</td>'
                . '<td>' . htmlspecialchars((string) $sp->type, ENT_QUOTES) . '</td>'
                . '<td>' . htmlspecialchars((string) $sp->status, ENT_QUOTES) . '</td>'
                . '<td>parent #' . (int) $sp->parentPermitId . '</td>'
                . '<td><a href="/bozp/debug/subpermit/' . (int) $sp->id . '">HTML</a></td>'
                . '<td><a href="/bozp/debug/subpermit/' . (int) $sp->id . '/pdf">PDF</a></td>'
                . '</tr>';
        }

        return '<h1>Permits</h1><table>'
            . '<thead><tr><th>ID</th><th>Number</th><th>Status</th><th>Contractor</th><th></th><th></th></tr></thead>'
            . '<tbody>' . ($rows ?: '<tr><td colspan="6"><em>none</em></td></tr>') . '</tbody></table>'
            . '<h1 style="margin-top:32px;">Subpermits</h1><table>'
            . '<thead><tr><th>ID</th><th>Type</th><th>Status</th><th>Parent</th><th></th><th></th></tr></thead>'
            . '<tbody>' . ($spRows ?: '<tr><td colspan="6"><em>none</em></td></tr>') . '</tbody></table>';
    }

    private function renderShell(string $title, string $body): string
    {
        return '<!doctype html><html><head><meta charset="utf-8"><title>' . htmlspecialchars($title, ENT_QUOTES) . '</title>'
            . '<style>'
            . 'body{font:14px/1.5 system-ui,sans-serif;margin:0;background:#f6f4f9;color:#222;}'
            . 'header{background:#4F2170;color:#FEFFFD;padding:16px 24px;}'
            . 'header h1{margin:0;font-size:18px;}'
            . 'main{padding:24px;max-width:1100px;margin:0 auto;}'
            . 'main h1{font-size:16px;color:#4F2170;margin:0 0 12px;}'
            . 'table{width:100%;border-collapse:collapse;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,.05);}'
            . 'th,td{padding:8px 12px;border-bottom:1px solid #eee;text-align:left;font-size:13px;}'
            . 'th{background:#faf8fc;color:#4F2170;font-weight:600;text-transform:uppercase;letter-spacing:.5px;font-size:11px;}'
            . 'a{color:#4F2170;text-decoration:none;font-weight:600;}'
            . 'a:hover{text-decoration:underline;}'
            . '</style></head><body>'
            . '<header><h1>' . htmlspecialchars($title, ENT_QUOTES) . '</h1></header>'
            . '<main>' . $body . '</main></body></html>';
    }
}
