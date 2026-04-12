<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

/**
 * Минимальный SW: критерии установки PWA (в т.ч. обработчик fetch).
 * Кэш по хосту — три приложения не пересекаются.
 */
class PwaServiceWorkerController extends Controller
{
    public function __invoke(): Response
    {
        $body = <<<'JS'
const CACHE = 'pwa-precache-' + self.location.host + '-v1';

self.addEventListener('install', (event) => {
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) =>
            Promise.all(
                keys
                    .filter((k) => k.startsWith('pwa-precache-') && k !== CACHE)
                    .map((k) => caches.delete(k)),
            ),
        ).then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    event.respondWith(fetch(event.request));
});

JS;

        return response($body, 200, [
            'Content-Type' => 'application/javascript; charset=utf-8',
            'Cache-Control' => 'public, max-age=0',
            'Service-Worker-Allowed' => '/',
        ]);
    }
}
