const CACHE_NAME = "poof-v4"

const ASSETS = [
    "/"
]

self.addEventListener("install", event => {

    event.waitUntil(
        caches.open(CACHE_NAME).then(cache => {
            return Promise.allSettled(
                ASSETS.map(url => cache.add(url))
            )
        })
    )

})

self.addEventListener("fetch", event => {

    event.respondWith(
        caches.match(event.request).then(response => {
            return response || fetch(event.request)
        })
    )

})
