const CACHE_NAME = 'documentarchive-lite-static-v1';
const STATIC_ASSETS = [
  '/lite-icon.svg',
  '/lite-offline.html',
  '/manifest-lite.json'
];

self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => cache.addAll(STATIC_ASSETS))
      .then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys()
      .then(keys => Promise.all(
        keys
          .filter(key => key !== CACHE_NAME)
          .map(key => caches.delete(key))
      ))
      .then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', event => {
  const request = event.request;
  if (request.method !== 'GET') return;

  const url = new URL(request.url);
  if (url.origin !== self.location.origin) return;

  if (request.mode === 'navigate') {
    event.respondWith(
      fetch(request).catch(() =>
        caches.match('/lite-offline.html')
      )
    );
    return;
  }

  const isStatic =
    url.pathname === '/lite-icon.svg' ||
    url.pathname === '/manifest-lite.json' ||
    url.pathname === '/lite-offline.html' ||
    url.pathname.startsWith('/css/') ||
    url.pathname.startsWith('/js/');

  if (!isStatic) return;

  event.respondWith(
    fetch(request)
      .then(response => {
        if (response.ok) {
          const clone = response.clone();
          caches.open(CACHE_NAME).then(cache => cache.put(request, clone));
        }
        return response;
      })
      .catch(() => caches.match(request))
  );
});
