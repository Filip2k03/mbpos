const CACHE_NAME = 'mbpos-v5-shell-2026-09-17-r13';
const APP_SHELL = [
  './offline.html',
  './assets/css/style.css',
  './assets/js/main.js',
  './assets/icons/mbpos.svg',
  './manifest.webmanifest'
];

self.addEventListener('install', (event) => {
  event.waitUntil(caches.open(CACHE_NAME).then((cache) => cache.addAll(APP_SHELL)));
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) => Promise.all(
      keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key))
    ))
  );
  self.clients.claim();
});

self.addEventListener('fetch', (event) => {
  if (event.request.method !== 'GET') return;

  const request = event.request;
  const url = new URL(request.url);
  const isSameOrigin = url.origin === self.location.origin;
  // Chrome can replay history requests with `only-if-cached`. Passing that
  // request back through fetch() from a service worker raises ERR_CACHE_MISS
  // when the authenticated page was correctly never cached. Normalize only
  // the browser cache mode; credentials, headers, and URL remain unchanged.
  const networkRequest = request.cache === 'only-if-cached'
    ? new Request(request, { cache: 'default' })
    : request;

  if (request.mode === 'navigate') {
    event.respondWith(
      fetch(networkRequest)
        .then((response) => response)
        // Authenticated PHP pages are never cached. Offline navigation always
        // receives a neutral shell rather than stale operational records.
        .catch(() => caches.match('./offline.html'))
    );
    return;
  }

  const isStaticAsset = isSameOrigin && (
    url.pathname.includes('/assets/') ||
    url.pathname.endsWith('/bg.jpg') ||
    url.pathname.endsWith('/offline.html') ||
    url.pathname.endsWith('/manifest.webmanifest')
  );

  // API, PHP, and other dynamic GET responses may contain authenticated data.
  // Do not call respondWith: bypass the worker entirely so the browser owns
  // network/cache semantics and no authenticated response enters Cache API.
  if (!isStaticAsset) {
    return;
  }

  event.respondWith(
    caches.match(request).then((cached) => {
      const refresh = fetch(networkRequest).then((response) => {
        if (response.ok) {
          const copy = response.clone();
          caches.open(CACHE_NAME).then((cache) => cache.put(request, copy));
        }
        return response;
      }).catch(() => cached);
      return cached || refresh;
    })
  );
});
