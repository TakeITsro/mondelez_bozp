<?php

declare(strict_types=1);

namespace modules\bozp\controllers;

use Craft;
use craft\web\Controller;
use yii\web\Response;

/**
 * Serves PWA shell assets at site-root URLs:
 *   GET /manifest.webmanifest             → actionManifest
 *   GET /sw.js                            → actionServiceWorker (scope: /)
 *   GET /bozp-pwa-icon.svg                → actionIcon
 *   GET /apple-touch-icon.png             → actionAppleTouchIcon (180×180 PNG, GD-generated)
 *   GET /apple-touch-icon-precomposed.png → actionAppleTouchIcon (same image, iOS legacy alias)
 *
 * Assets live as files under `modules/bozp/web/pwa/` and are streamed
 * back with appropriate Content-Type + cache headers. Serving via the
 * controller (rather than dumping files into the document root) keeps
 * the module self-contained — no extra deploy step.
 *
 * iOS Safari note: the SVG manifest icon is ignored for "Add to Home Screen".
 * iOS strictly reads `<link rel="apple-touch-icon">` and requires PNG; that's
 * why we generate one server-side via GD.
 */
class PwaController extends Controller
{
    public array|bool|int $allowAnonymous = true;
    public $enableCsrfValidation = false;

    private const WEB_PATH = __DIR__ . '/../web/pwa';

    public function actionManifest(): Response
    {
        return $this->serveStatic('manifest.webmanifest', 'application/manifest+json; charset=utf-8', 3600);
    }

    public function actionServiceWorker(): Response
    {
        $response = $this->serveStatic('sw.js', 'application/javascript; charset=utf-8', 0);
        // Allow the SW to control the entire origin even though served via Craft.
        $response->getHeaders()->set('Service-Worker-Allowed', '/');
        return $response;
    }

    public function actionIcon(): Response
    {
        return $this->serveStatic('icon.svg', 'image/svg+xml; charset=utf-8', 86400);
    }

    /**
     * iOS home-screen icon. 180×180 PNG generated via PHP GD on first request
     * and cached on disk at modules/bozp/web/pwa/apple-touch-icon.png so all
     * subsequent requests just stream the file.
     *
     * Replace the cached PNG manually any time — it lives next to the SVG.
     */
    public function actionAppleTouchIcon(): Response
    {
        $path = self::WEB_PATH . '/apple-touch-icon.png';
        if (!is_file($path)) {
            $this->generateAppleTouchIcon($path);
        }
        return $this->serveStatic('apple-touch-icon.png', 'image/png', 86400);
    }

    /**
     * Generate a 180×180 PNG home-screen icon: Mondelēz-purple background
     * with white "BOZP" text. Uses PHP GD which Craft already requires for
     * image transforms.
     */
    private function generateAppleTouchIcon(string $destPath): void
    {
        if (!function_exists('imagecreatetruecolor')) {
            throw new \RuntimeException('PHP GD extension is required to generate the apple-touch-icon.');
        }

        $size = 180;
        $img = imagecreatetruecolor($size, $size);

        // Background — Mondelēz purple.
        $bg = imagecolorallocate($img, 0x4F, 0x21, 0x70);
        imagefilledrectangle($img, 0, 0, $size, $size, $bg);

        // Text — white.
        $fg = imagecolorallocate($img, 0xFE, 0xFF, 0xFD);

        // Built-in font 5 is the largest GD bitmap font (~9×15px per char).
        // We render the label "BOZP" centred via simple geometry.
        $font = 5;
        $text = 'BOZP';
        $charWidth  = imagefontwidth($font);
        $charHeight = imagefontheight($font);

        // Scale up via a small in-memory canvas, then resample to 180×180 for crisp output.
        $base = imagecreatetruecolor($charWidth * 8, $charHeight * 4);
        imagefilledrectangle($base, 0, 0, imagesx($base), imagesy($base), $bg);

        $textWidth  = $charWidth * strlen($text);
        $textHeight = $charHeight;
        $bx = (int) ((imagesx($base) - $textWidth) / 2);
        $by = (int) ((imagesy($base) - $textHeight) / 2);
        imagestring($base, $font, $bx, $by, $text, $fg);

        // Resample base → final icon at 180×180.
        imagecopyresampled(
            $img, $base,
            0, 0, 0, 0,
            $size, $size,
            imagesx($base), imagesy($base),
        );
        imagedestroy($base);

        if (!is_dir(dirname($destPath))) {
            mkdir(dirname($destPath), 0755, true);
        }
        imagepng($img, $destPath, 9);
        imagedestroy($img);
    }

    private function serveStatic(string $filename, string $contentType, int $cacheSeconds): Response
    {
        $path = self::WEB_PATH . '/' . $filename;
        if (!is_file($path)) {
            throw new \yii\web\NotFoundHttpException("PWA asset missing: {$filename}");
        }
        $content = file_get_contents($path);
        if ($content === false) {
            throw new \RuntimeException("Failed to read PWA asset: {$filename}");
        }

        $response = Craft::$app->getResponse();
        $response->format = Response::FORMAT_RAW;
        $response->content = $content;

        $headers = $response->getHeaders();
        $headers->set('Content-Type', $contentType);

        if ($cacheSeconds > 0) {
            $headers->set('Cache-Control', "public, max-age={$cacheSeconds}");
        } else {
            // Service worker — never cache. Browsers also re-check on every load.
            $headers->set('Cache-Control', 'public, max-age=0, must-revalidate');
        }
        return $response;
    }
}
