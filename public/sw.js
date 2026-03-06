const CACHE_VERSION = "poof-v10"
const STATIC_CACHE = `static-${CACHE_VERSION}`

const STATIC_ASSETS = [
    "/",
    "/manifest.json",
]

self.addEventListener("install", event => {

    self.skipWaiting()

    event.waitUntil(
        caches.open(STATIC_CACHE).then(cache => {
            return Promise.allSettled(
                STATIC_ASSETS.map(url => cache.add(url))
            )
        })
    )

})

self.addEventListener("activate", event => {

    event.waitUntil(
        caches.keys().then(keys => {
            return Promise.all(
                keys
                    .filter(key => key !== STATIC_CACHE)
                    .map(key => caches.delete(key))
            )
        })
    )

    self.clients.claim()

})

self.addEventListener("fetch", event => {

    if (event.request.method !== "GET") {
        return
    }

    const url = new URL(event.request.url)

    if (url.pathname.startsWith("/api/")) {
        return
    }

    if (url.pathname.startsWith("/build/")) {
        return
    }

    const isSameOrigin = url.origin === self.location.origin
    const isCacheableAsset =
        url.pathname.startsWith("/images/") ||
        url.pathname.startsWith("/assets/images/") ||
        url.pathname.startsWith("/assets/icons/")

    if (isSameOrigin && isCacheableAsset) {

        event.respondWith(
            caches.match(event.request).then(cachedResponse => {

                const networkFetch = fetch(event.request).then(networkResponse => {

                    if (!networkResponse || networkResponse.status !== 200) {
                        return networkResponse
                    }

                    const clone = networkResponse.clone()

                    caches.open(STATIC_CACHE).then(cache => {
                        cache.put(event.request, clone)
                    })

                    return networkResponse

                }).catch(() => cachedResponse)

                return cachedResponse || networkFetch

            })
        )

    }

})
