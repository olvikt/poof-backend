const CACHE_VERSION = "poof-v6"
const STATIC_CACHE = `static-${CACHE_VERSION}`

const STATIC_ASSETS = [
    "/",
    "/manifest.json",
]

self.addEventListener("install", event => {

    self.skipWaiting()

    event.waitUntil(
        caches.open(STATIC_CACHE).then(cache => {
            return cache.addAll(STATIC_ASSETS)
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

    const url = new URL(event.request.url)

    // never cache Vite build assets
    if (url.pathname.startsWith("/build/")) {
        return
    }

    // cache images
    if (event.request.destination === "image") {

        event.respondWith(
            caches.match(event.request).then(cached => {

                if (cached) return cached

                return fetch(event.request).then(response => {

                    const clone = response.clone()

                    caches.open(STATIC_CACHE).then(cache => {
                        cache.put(event.request, clone)
                    })

                    return response

                })

            })
        )

    }

})
