'use strict';

// Service worker minimal : aucune donnée de la liste n'est mise en cache.
// Il affiche seulement une page « Pas de connexion » quand le réseau est indisponible.
const CACHE = 'wishlist-offline-v1';
const OFFLINE_URL = '/offline.html';

self.addEventListener('install', (event) => {
  event.waitUntil(caches.open(CACHE).then((cache) => cache.addAll([OFFLINE_URL, '/assets/css/app.css'])));
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) => Promise.all(keys.filter((key) => key !== CACHE).map((key) => caches.delete(key)))),
  );
  self.clients.claim();
});

self.addEventListener('fetch', (event) => {
  if (event.request.mode !== 'navigate') {
    return;
  }
  event.respondWith(fetch(event.request).catch(() => caches.match(OFFLINE_URL)));
});
