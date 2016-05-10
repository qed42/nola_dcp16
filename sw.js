var version = '1';

var cacheUrls = [
    '/',
    '/themes/poly/bower_components/webcomponentsjs/webcomponents-lite.js',
    '/themes/poly/dist/elements.vulcanized.html',
    '/core/misc/favicon.ico',
    '/default-logo.png',
    '/default.png',
    '/sessions',
    '/sponsors',
    '/about',
    '/venue',
    '/sessions?field_drupal_version_target_id=4&field_target_audience_level_target_id=All',
    '/sessions?field_drupal_version_target_id=5&field_target_audience_level_target_id=All',
    '/sessions?field_drupal_version_target_id=6&field_target_audience_level_target_id=All',
    '/sessions?field_drupal_version_target_id=7&field_target_audience_level_target_id=All',
    '/sessions?field_drupal_version_target_id=All&field_target_audience_level_target_id=3',
    '/sessions?field_drupal_version_target_id=All&field_target_audience_level_target_id=1',
    '/sessions?field_drupal_version_target_id=All&field_target_audience_level_target_id=2',
    '/sessions?field_drupal_version_target_id=5&field_target_audience_level_target_id=3',
    '/sessions?field_drupal_version_target_id=5&field_target_audience_level_target_id=1',
    '/sessions?field_drupal_version_target_id=5&field_target_audience_level_target_id=2',
    '/sessions?field_drupal_version_target_id=6&field_target_audience_level_target_id=1',
    '/sessions?field_drupal_version_target_id=6&field_target_audience_level_target_id=2',
    '/sessions?field_drupal_version_target_id=6&field_target_audience_level_target_id=3',
    '/sessions?field_drupal_version_target_id=7&field_target_audience_level_target_id=1',
    '/sessions?field_drupal_version_target_id=7&field_target_audience_level_target_id=2',
    '/sessions?field_drupal_version_target_id=7&field_target_audience_level_target_id=3',
    '/sessions?field_drupal_version_target_id=4&field_target_audience_level_target_id=1',
    '/sessions?field_drupal_version_target_id=4&field_target_audience_level_target_id=2',
    '/sessions?field_drupal_version_target_id=4&field_target_audience_level_target_id=3',
    '/sessions?field_drupal_version_target_id=All&field_target_audience_level_target_id=All'
];

var syncType = 'sessionSync';
var IndexDbSyncName = 'sessionStore';

self.addEventListener('install', function(event) {
    event.waitUntil(
        // Static cache bucket.
        // Pre-cache static resources. Since the response of the page would depnend on
        // the user who is logged-in, we use credentials:include.
        caches.open('static-' + version).then(function (cache) {
            cacheUrls.forEach(function(url) {
                return fetch(url, {credentials: 'include'}).then(function(response) {
                    return cache.put(url, response);
                }).catch(function(err) {
                    console.error("Error while fetching cache", err);
                    return err;
                });
            })
        }).then(function () {
            // Dynamic cache.
            // Pre-cache session & sponsor nodes. Cache updated when these nodes are hit.
            // session.json & sponsor.json are rest endpoints exposed in Drupal.
            cacheJsonUrls('/sessions.json', 'dynamic').then(function () {
                cacheJsonUrls('/sponsors.json', 'dynamic').then(function () {
                    // Pre-cache aggregated css & js files since they will be mostly static.
                    // To update this, service worker update needs to be triggered. Any change
                    // done to css or js resources would require a bump in version variable &
                    // clean-up in activate listener.
                    cacheJsonUrls('/css-js-aggregated.json', 'assets').then(function() {
                        self.skipWaiting();
                    })
                }).catch(function (error) {
                    console.error(error);
                });
            })
        })
    )
});

/**
 * Helper function to cache the url response from dynamic
 * rest enf-points exposed by Drupal.
 *
 * @param listUrl
 * @param bucketName
 *
 * @return Promise object
 */
function cacheJsonUrls(listUrl, bucketName) {
    return new Promise(function (resolve, reject) {
        caches.open( bucketName + '-' + version, {credentials: 'include'}).then(function (cache) {
            return fetch(listUrl).then(function (response) {
                return response.json();
            }).then(function (data) {
                data.forEach(function (datum) {
                    switch(bucketName) {
                        case 'dynamic':
                            var url = '/node/' + datum['nid'][0]['value'];
                            break;
                        case 'assets':
                            var url = datum;
                    }
                    cache.add(url);
                });
                resolve(data);
            }).catch(function(err) {
                reject(err);
            })
        });
    });
}

/**
 * Check the network for response first.
 *
 * If ofline, respond back from cache.
 *
 * If online, fetch data from network , cache it & respond back.
 *
 * @param url
 * @param bucket
 */
function networkFirstResponse(bucket, event) {
    event.respondWith(
        caches.open(bucket + '-' + version).then(function(cache) {
            var cacheBucketInstance = cache;
            return fetch(event.request.url, {credentials: 'include'}).then(function (response) {
                // We need to clone the response here since the response object is
                // a readable stream object & can be consumed only once(either to update cache or
                // respond back).
                var responseClone = response.clone();
                cache.put(event.request.url, response);
                return responseClone;
            }).catch(function(err) {
                return cacheBucketInstance.match(event.request.url).then(function(response) {
                    return response;
                });
            });
        })
    );
}

/**
 * Check the cache for response first.
 *
 * If response found in cache, respond back & update cache with fresh data.
 *
 * @param bucket
 * @param event
 */
function offlineFirstCacheResponse(bucket, event) {
    var urlLookup = event.request.url;
    var url = new URL(event.request.url);

    if (bucket == 'assets') {
        urlLookup = url.protocol + '//' + url.hostname + url.pathname;
    }
    event.respondWith(
        caches.open(bucket + '-' + version).then(function(cache) {
            return cache.match(urlLookup).then(function(response) {
                var fetchPromise = fetch(event.request.url, {credentials: 'include'}).then(function(response) {
                    var responseClone = response.clone();
                    cache.put(urlLookup, response);
                    return responseClone;
                }).catch(function(err) {
                    if (bucket == 'images') {
                        return caches.open('static-' + version).then(function(cache) {
                           return cache.match(url.protocol + '//' + url.hostname + '/default.png').then(function(response) {
                               return response;
                           })
                        });
                    }
                    else {
                        return err;
                    }
                });
                return response || fetchPromise;
            });
        })
    );
}

self.addEventListener('fetch', function(event) {
    var url = new URL(event.request.url);
    if (!event.request.url.includes('/admin')) {
        if (((url.pathname == '/sessions') && (!url.search)) || (url.pathname == '/my-schedule')) {
            networkFirstResponse('static', event);
        }
        else if (cacheUrls.indexOf(url.pathname) != -1) {
            offlineFirstCacheResponse('static', event);
        }
        else if (event.request.url.includes('/node/')) {
            offlineFirstCacheResponse('dynamic', event);
        }
        else if ((/\.(css|js)/i).test(event.request.url) && (!(/\.(json)/i).test(event.request.url))) {
            offlineFirstCacheResponse('assets', event);
        }
        else if ((/\.(gif|jpg|jpeg|tiff|png)$/i).test(event.request.url)) {
            // Cache images ones they have been browsed.
            offlineFirstCacheResponse('images', event);
        }
    }

});

self.addEventListener('sync', function(sync_event) {
    openBgSyncDB().then(function (db) {
        return fetchSessions(db, "session");
    }).then(function (sessions) {
        for (var i=0; i<sessions.length; i++) {
            if (sessions[i]) {
                fetch('/schedule/' + sessions[i].sessionId + '/' + sessions[i].action + '/' + sessions[i].uid).then(function(response) {
                    var jsonResponse = response.json();
                    return jsonResponse;
                }).then(function(jsonResponse) {
                    openBgSyncDB().then(function(db) {
                        deleteSession(db, jsonResponse.sessionId);
                    });
                });
            }
        }
        self.registration.showNotification("Your preference for sessions synced successfully!");
    }).catch(function (err) {
        self.registration.showNotification("Sync fired! There was an error.");
        self.registration.showNotification(err.message);
        // postErrorToClients(err);
    });
});

function openBgSyncDB() {
    return new Promise(function (resolve, reject) {
        var open_request = indexedDB.open(IndexDbSyncName, 3);
        open_request.onerror = function (event) {
            reject(new Error("Error opening database."));
        };
        open_request.onsuccess = function (event) {
            resolve(event.target.result);
        };
        open_request.onupgradeneeded = function(event) {
            var db = event.target.result;
            db.createObjectStore("currentUser", {keyPath: "user"});
        };
    });
}

function fetchSessions(db) {
    return new Promise(function (resolve, reject) {
        var store = db.transaction(["sessionQueue"], "readwrite").objectStore("sessionQueue");
        var get_request = store.getAll();

        get_request.onerror = function (event) {
            reject(new Error("Error getting value from database."));
        };

        get_request.onsuccess = function (event) {
            resolve(event.target.result);
        };
    });
}

function deleteSession(db, sessionId) {
    return new Promise(function (resolve, reject) {
        var store = db.transaction(["sessionQueue"], "readwrite").objectStore("sessionQueue");
        var get_request = store.delete(sessionId);

        get_request.onerror = function (event) {
            reject(new Error("Error getting value from database."));
        };

        get_request.onsuccess = function (event) {
            resolve(event.target.result);
        };
    });
}

function postErrorToClients(err) {
    clients.matchAll({ includeUncontrolled: true }).then(function (clientList) {
        clientList.forEeach(function (client) {
            return client.postMessage(err.message);
        });
    });
}
