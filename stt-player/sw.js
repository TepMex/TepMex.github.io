const CACHE_NAME = 'stt-audioplayer-v1';
const RUNTIME_CACHE = 'runtime-cache-v1';

// Files to cache on install (using relative paths)
const PRECACHE_URLS = [
  './',
  './index.html',
  './vite.svg'
];

// Install event - cache static assets
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then((cache) => cache.addAll(PRECACHE_URLS))
      .then(() => self.skipWaiting())
  );
});

// Activate event - clean up old caches
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((cacheNames) => {
      return Promise.all(
        cacheNames
          .filter((cacheName) => {
            return cacheName !== CACHE_NAME && cacheName !== RUNTIME_CACHE;
          })
          .map((cacheName) => caches.delete(cacheName))
      );
    }).then(() => self.clients.claim())
  );
});

// Fetch event - serve from cache, fallback to network
self.addEventListener('fetch', (event) => {
  const { request } = event;
  const url = new URL(request.url);

  // Skip cross-origin requests
  if (url.origin !== location.origin) {
    return;
  }

  // Cache strategy: Cache First for static assets, Network First for API/data
  event.respondWith(
    caches.match(request).then((cachedResponse) => {
      if (cachedResponse) {
        return cachedResponse;
      }

      return fetch(request)
        .then((response) => {
          // Don't cache non-successful responses
          if (!response || response.status !== 200 || response.type !== 'basic') {
            return response;
          }

          // Clone the response
          const responseToCache = response.clone();

          // Cache models, WASM, and other static assets
          if (
            url.pathname.includes('/models/') ||
            url.pathname.includes('/wasm-fuzzy/') ||
            url.pathname.endsWith('.js') ||
            url.pathname.endsWith('.css') ||
            url.pathname.endsWith('.wasm') ||
            url.pathname.endsWith('.onnx') ||
            url.pathname.endsWith('.json') ||
            url.pathname.endsWith('.txt')
          ) {
            caches.open(RUNTIME_CACHE).then((cache) => {
              cache.put(request, responseToCache);
            });
          }

          return response;
        })
        .catch(() => {
          // If network fails and we're looking for the page, return a cached version
          if (request.mode === 'navigate') {
            return caches.match('./index.html');
          }
        });
    })
  );
});

