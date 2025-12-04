const CACHE_NAME = "mao-enumerator-v3";
const FILES_TO_CACHE = [
    "./",
    "./enumerator_login.html",
    "./enumerator_view.php",
    "./manifest.json",
    "./mao-icon.png"
];

// ✅ Install: cache app shell safely
self.addEventListener("install", (event) => {
    console.log("Service Worker installing and caching app shell...");
    event.waitUntil(
        (async () => {
            const cache = await caches.open(CACHE_NAME);
            for (const file of FILES_TO_CACHE) {
                try {
                    const response = await fetch(file);
                    if (response.ok) {
                        await cache.put(file, response);
                        console.log("Cached:", file);
                    } else {
                        console.warn("⚠️ Skipped (not OK):", file);
                    }
                } catch (err) {
                    console.warn("⚠️ Skipped (fetch failed):", file);
                }
            }
        })()
    );
    self.skipWaiting();
});

// ✅ Activate: clean old caches
self.addEventListener("activate", (event) => {
    console.log("Service Worker activating...");
    event.waitUntil(
        caches.keys().then((keys) =>
            Promise.all(keys.map((key) => {
                if (key !== CACHE_NAME) {
                    console.log("Deleting old cache:", key);
                    return caches.delete(key);
                }
            }))
        )
    );
    self.clients.claim();
});

// ✅ Fetch: use cache when offline
self.addEventListener("fetch", (event) => {
    event.respondWith(
        caches.match(event.request).then((response) => {
            return response || fetch(event.request);
        })
    );
});
