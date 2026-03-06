const CACHE_NAME = 'poof-v5'

self.addEventListener('install', (event) => {
  self.skipWaiting()
  event.waitUntil(Promise.resolve())
})

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(
        keys
          .filter((key) => key !== CACHE_NAME)
          .map((key) => caches.delete(key)),
      ),
    ),
  )

  self.clients.claim()
})

self.addEventListener('fetch', (event) => {
  if (event.request.method !== 'GET') {
    return
  }

  const url = new URL(event.request.url)

  if (url.pathname.startsWith('/build/') || url.pathname.startsWith('/api/')) {
    return
  }

  const isSameOrigin = url.origin === self.location.origin
  const isImageOrIcon =
    event.request.destination === 'image' &&
    (url.pathname.startsWith('/images/') || url.pathname.startsWith('/icons/'))

  if (!isSameOrigin || !isImageOrIcon) {
    return
  }

  event.respondWith(
    caches.match(event.request).then((cached) => {
      if (cached) {
        return cached
      }

      return fetch(event.request).then((response) => {
        if (!response || response.status !== 200) {
          return response
        }

        const responseClone = response.clone()
        caches.open(CACHE_NAME).then((cache) => cache.put(event.request, responseClone))

        return response
      })
    }),
  )
})
