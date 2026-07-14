/**
 * EVENTIFY student PWA — cache shell + ticket API for offline passes.
 */
const CACHE_VERSION = 'eventify-student-v12';
const STATIC_CACHE = CACHE_VERSION + '-static';
const TICKETS_CACHE = CACHE_VERSION + '-tickets';

function swBasePath() {
    var p = self.location.pathname || '';
    var marker = '/sw-student.js';
    var idx = p.indexOf(marker);
    if (idx >= 0) {
        return p.slice(0, idx);
    }
    return '';
}

const SW_BASE = swBasePath();

const STATIC_ASSETS = [
    SW_BASE + '/manifest-student.php',
    SW_BASE + '/assets/pwa/icon-192.png',
    SW_BASE + '/assets/pwa/icon-512.png',
    SW_BASE + '/assets/css/pwa_student.css',
    SW_BASE + '/assets/css/event_tickets.css',
    SW_BASE + '/my_tickets.php',
    SW_BASE + '/offline.html'
];

self.addEventListener('message', function (event) {
    if (event && event.data && event.data.type === 'SKIP_WAITING') {
        self.skipWaiting();
    }
});

self.addEventListener('install', function (event) {
    event.waitUntil(
        caches.open(STATIC_CACHE).then(function (cache) {
            return cache.addAll(STATIC_ASSETS).catch(function () {
                /* partial cache ok on dev */
            });
        }).then(function () {
            return self.skipWaiting();
        })
    );
});

self.addEventListener('activate', function (event) {
    event.waitUntil(
        caches.keys().then(function (keys) {
            return Promise.all(
                keys.filter(function (k) {
                    return k.startsWith('eventify-student-') && k !== STATIC_CACHE && k !== TICKETS_CACHE;
                }).map(function (k) {
                    return caches.delete(k);
                })
            );
        }).then(function () {
            return caches.open(STATIC_CACHE).then(function (cache) {
                return cache.keys().then(function (requests) {
                    return Promise.all(requests.filter(function (req) {
                        return req.url.indexOf('eventify_pwa.js') !== -1;
                    }).map(function (req) {
                        return cache.delete(req);
                    }));
                });
            });
        }).then(function () {
            return self.clients.claim();
        })
    );
});

function isTicketsApi(url) {
    return url.pathname.indexOf('/backend/auth/student_tickets_api.php') !== -1;
}

function isTicketPassPage(url) {
    return url.pathname.indexOf('/ticket_pass.php') !== -1;
}

function isStudentTicketsPage(url) {
    if (url.pathname.indexOf(SW_BASE + '/my_tickets.php') !== -1) {
        return true;
    }
    return url.pathname.indexOf('/backend/auth/dashboard_student.php') !== -1
        && url.searchParams.get('panel') === 'tickets';
}

function isAppAsset(url) {
    return url.pathname.indexOf(SW_BASE + '/assets/') === 0;
}

function isNetworkFirstAsset(url) {
    return url.pathname.indexOf('/eventify_pwa.js') !== -1
        || url.pathname.indexOf('/sw-student.js') !== -1;
}

self.addEventListener('fetch', function (event) {
    var req = event.request;
    if (req.method !== 'GET') {
        return;
    }
    var url = new URL(req.url);

    if (isTicketsApi(url)) {
        event.respondWith(
            fetch(req).then(function (res) {
                if (res.ok) {
                    var clone = res.clone();
                    caches.open(TICKETS_CACHE).then(function (cache) {
                        cache.put(req, clone);
                    });
                }
                return res;
            }).catch(function () {
                return caches.match(req).then(function (cached) {
                    return cached || new Response(
                        JSON.stringify({ ok: false, offline: true, tickets: [] }),
                        { status: 200, headers: { 'Content-Type': 'application/json' } }
                    );
                });
            })
        );
        return;
    }

    if (req.mode === 'navigate' && isTicketPassPage(url)) {
        event.respondWith(
            fetch(req).then(function (res) {
                if (res.ok) {
                    var clone = res.clone();
                    caches.open(STATIC_CACHE).then(function (cache) {
                        cache.put(req, clone);
                    });
                }
                return res;
            }).catch(function () {
                return caches.match(req).then(function (cached) {
                    return cached || caches.match(SW_BASE + '/offline.html');
                });
            })
        );
        return;
    }

    if (url.hostname === 'api.qrserver.com') {
        event.respondWith(
            caches.match(req).then(function (cached) {
                return cached || fetch(req).then(function (res) {
                    if (res.ok) {
                        var clone = res.clone();
                        caches.open(STATIC_CACHE).then(function (cache) {
                            cache.put(req, clone);
                        });
                    }
                    return res;
                });
            })
        );
        return;
    }

    if (req.mode === 'navigate' && isStudentTicketsPage(url)) {
        event.respondWith(
            fetch(req).then(function (res) {
                if (res.ok) {
                    var clone = res.clone();
                    caches.open(STATIC_CACHE).then(function (cache) {
                        cache.put(req, clone);
                    });
                }
                return res;
            }).catch(function () {
                return caches.match(req).then(function (cached) {
                    return cached || caches.match(SW_BASE + '/offline.html');
                });
            })
        );
        return;
    }

    if (isAppAsset(url)) {
        if (isNetworkFirstAsset(url)) {
            event.respondWith(
                fetch(req).then(function (res) {
                    if (res.ok) {
                        var clone = res.clone();
                        caches.open(STATIC_CACHE).then(function (cache) {
                            cache.put(req, clone);
                        });
                    }
                    return res;
                }).catch(function () {
                    return caches.match(req);
                })
            );
            return;
        }
        event.respondWith(
            caches.match(req).then(function (cached) {
                return cached || fetch(req).then(function (res) {
                    if (res.ok) {
                        var clone = res.clone();
                        caches.open(STATIC_CACHE).then(function (cache) {
                            cache.put(req, clone);
                        });
                    }
                    return res;
                });
            })
        );
    }
});

self.addEventListener('push', function (event) {
    var payload = { title: 'EVENTIFY', body: 'You have a new update.', url: SW_BASE + '/backend/auth/dashboard_student.php' };
    if (event.data) {
        try {
            var parsed = event.data.json();
            if (parsed && typeof parsed === 'object') {
                payload = parsed;
            }
        } catch (e) {
            payload.body = event.data.text();
        }
    }
    var title = payload.title || 'EVENTIFY';
    var options = {
        body: payload.body || '',
        icon: SW_BASE + '/assets/pwa/icon-192.png',
        badge: SW_BASE + '/assets/pwa/icon-192.png',
        data: {
            url: payload.url || (SW_BASE + '/backend/auth/dashboard_student.php'),
            event_id: payload.event_id || null,
            type: payload.type || ''
        },
        tag: payload.type ? ('eventify-' + payload.type) : 'eventify-alert',
        renotify: true
    };
    event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', function (event) {
    event.notification.close();
    var targetUrl = (event.notification && event.notification.data && event.notification.data.url)
        ? event.notification.data.url
        : (SW_BASE + '/backend/auth/dashboard_student.php');
    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (clientList) {
            for (var i = 0; i < clientList.length; i++) {
                var client = clientList[i];
                if (client.url && client.url.indexOf('dashboard_student.php') !== -1 && 'focus' in client) {
                    return client.focus();
                }
            }
            if (clients.openWindow) {
                return clients.openWindow(targetUrl);
            }
        })
    );
});
