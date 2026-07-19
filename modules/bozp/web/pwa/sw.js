/* Mondelēz BOZP — service worker
 *
 * Scope: site root (registered via Service-Worker-Allowed: / header).
 *
 * Strategy:
 *   - Precache: manifest + icon.
 *   - Static assets (css/js/fonts/img): cache-first.
 *   - Contractor portal (/contractor/*): network-only (no cache — never serve
 *     stale signature state).
 *   - Other GET requests: network-first with cache fallback (offline page).
 *   - POST / non-GET: pass through, never cached.
 *
 * Bump CACHE_VERSION whenever cache shape changes — old caches purge on activate.
 */

const CACHE_VERSION = 'bozp-v2';
const STATIC_CACHE  = CACHE_VERSION + '-static';
const RUNTIME_CACHE = CACHE_VERSION + '-runtime';

const PRECACHE_URLS = [
    '/manifest.webmanifest',
    '/bozp-pwa-icon.svg',
];

self.addEventListener('install', function (event) {
    event.waitUntil(
        caches.open(STATIC_CACHE)
            .then(function (cache) { return cache.addAll(PRECACHE_URLS); })
            .then(function () { return self.skipWaiting(); })
    );
});

self.addEventListener('activate', function (event) {
    event.waitUntil(
        caches.keys().then(function (keys) {
            return Promise.all(
                keys
                    .filter(function (k) { return k.indexOf(CACHE_VERSION) !== 0; })
                    .map(function (k) { return caches.delete(k); })
            );
        }).then(function () { return self.clients.claim(); })
    );
});

self.addEventListener('fetch', function (event) {
    const req = event.request;

    // Only handle same-origin GETs.
    if (req.method !== 'GET') return;
    const url = new URL(req.url);
    if (url.origin !== self.location.origin) return;

    // Contractor portal — never cache. Always network.
    if (url.pathname.indexOf('/contractor/') === 0) return;

    // Static assets — cache-first.
    if (/\.(?:css|js|woff2?|ttf|svg|png|jpe?g|webp|ico|gif)$/i.test(url.pathname)) {
        event.respondWith(cacheFirst(req));
        return;
    }

    // Everything else — network-first with cache fallback.
    event.respondWith(networkFirst(req));
});

async function cacheFirst(req) {
    const cache = await caches.open(STATIC_CACHE);
    const hit = await cache.match(req);
    if (hit) return hit;
    try {
        const fresh = await fetch(req);
        if (fresh.ok) cache.put(req, fresh.clone());
        return fresh;
    } catch (e) {
        return new Response('', { status: 504, statusText: 'Offline' });
    }
}

async function networkFirst(req) {
    const cache = await caches.open(RUNTIME_CACHE);
    try {
        const fresh = await fetch(req);
        if (fresh.ok && fresh.type === 'basic') cache.put(req, fresh.clone());
        return fresh;
    } catch (e) {
        const hit = await cache.match(req);
        if (hit) return hit;
        return new Response(
            '<!doctype html><meta charset="utf-8"><title>Offline</title>' +
            '<style>body{font-family:sans-serif;padding:2em;text-align:center;color:#4F2170;}' +
            'h1{font-size:1.5em;margin-bottom:0.5em;}p{color:#444}</style>' +
            '<h1>Ste offline</h1><p>Skontrolujte pripojenie a obnovte stránku.</p>',
            { status: 503, headers: { 'Content-Type': 'text/html; charset=utf-8' } }
        );
    }
}
