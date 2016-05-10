/**
 * Created by piyuesh23 on 4/23/16.
 */
if (navigator.serviceWorker.controller) {
    var url = navigator.serviceWorker.controller.scriptURL;
    console.log('serviceWorker.controller', url);
    console.log(url, 'onload');
} else {
    navigator.serviceWorker.register('/sw.js', {
        scope: './'
    }).then(function(registration) {
        console.log('Refresh to allow ServiceWorker to control this client', 'onload');
        console.log(registration.scope, 'register');
    });
}

navigator.serviceWorker.addEventListener('controllerchange', function() {
    var scriptURL = navigator.serviceWorker.controller.scriptURL;
    console.log('serviceWorker.onControllerchange', scriptURL);
    console.log(scriptURL, 'oncontrollerchange');
});

var elements = document.querySelectorAll('a[id^="flag-add_to_my_schedule-id-"]');
var syncType = 'sessionSync';
var IndexDbSyncName = 'sessionStore';

for(var i=0; i < elements.length; i++) {
    // Clone the flag links & create a button element
    // to be used when user is offline.
    var clone = elements[i].cloneNode(true);
    var button = document.createElement('button');
    button.className = 'offline-flag';
    button.setAttribute('nid', clone.id.replace('flag-add_to_my_schedule-id-', ''));
    button.innerHTML = elements[i].text;
    button.style.display = 'none';

    // Insert the offline button into the DOM.
    elements[i].parentNode.insertBefore(button, elements[i].nextSibling);

    // Attach  click event listener to the button to save user's preferences
    // into indexedDB.
    button.addEventListener('click', function (event) {
        var element = this;
        var buttonLabel = element.innerHTML;
        event.preventDefault();
        new Promise(function (resolve, reject) {
            // Ask Chrome to grant permission to send notifications.
            Notification.requestPermission(function (result) {
                if (result !== 'granted') return reject(Error("Denied notification permission"));
                resolve();
            })
        }).then(function () {
            return navigator.serviceWorker.ready;
        }).then(function (reg) {
            return reg.sync.register(syncType);
        }).then(function () {
            console.log('Sync registered');
        }).then(function(){
            console.log('element pre-call to indexedDb', element);
            openDB().then(function(db) {
                updateDB(db, element, buttonLabel).then(function(sessionId) {
                    switch(buttonLabel) {
                        case 'Remove from schedule':
                            element.innerHTML = 'Add to schedule';
                            break;
                        case 'Add to schedule':
                            element.innerHTML = 'Remove from schedule';
                            break;
                    }
                });
            })
        }).catch(function (err) {
            console.log('It broke');
            console.log(err.message);
        });
    });
}

function updateOnlineStatus(event) {
    var online = navigator.onLine;
    var addToSchedules = document.querySelectorAll('a[id^="flag-add_to_my_schedule-id-"]');
    var offlineButtons = document.querySelectorAll('button[class^="offline-flag"]');
    var networkElement = document.getElementById("network-status");
    var footerMenuBlock = document.getElementById('block-footer');
    var filterButton = document.getElementById('edit-submit-sessions');

    if (online) {
        if (typeof drupalSettings !== 'undefined') {
            window.localStorage.setItem('currentUser', drupalSettings.user.uid);
        }
    }

    if (networkElement) {
        networkElement.innerHTML = online ? 'Online' : 'Offline';
        networkElement.className = online ? 'online' : 'offline';
    }

    // document.body.className = online ? 'online' : 'offline';

    if (!online) {
        for (var i=0; i < addToSchedules.length; i++) {
            addToSchedules[i].style.display = 'none';
        }

        for (var i=0; i < offlineButtons.length; i++) {
            offlineButtons[i].style.display = 'block';
        }

        if (filterButton) {
            filterButton.style.display= 'none';
            var drupalVersion = document.getElementById('edit-field-drupal-version-target-id');
            var experience = document.getElementById('edit-field-target-audience-level-target-id');

            drupalVersion.addEventListener('change', function() {
                redirectToFilterPage();
            });

            experience.addEventListener('change', function() {
                redirectToFilterPage();
            });
        }

        if (footerMenuBlock) {
            footerMenuBlock.style.display = 'none';
        }
    }
    else {
        for (var i=0; i < addToSchedules.length; i++) {
            addToSchedules[i].style.display = 'block';
        }

        for (var i=0; i < offlineButtons.length; i++) {
            offlineButtons[i].style.display = 'none';
        }
        if (filterButton) {
            filterButton.style.display = 'block';
        }

        if (footerMenuBlock) {
            footerMenuBlock.style.display = 'block';
        }
    }
}

function redirectToFilterPage() {
    var drupalVerison = document.getElementById('edit-field-drupal-version-target-id').value;
    var experienceLevel = document.getElementById('edit-field-target-audience-level-target-id').value;
    window.location.href = '/sessions?field_drupal_version_target_id=' + drupalVerison + '&field_target_audience_level_target_id=' + experienceLevel;
};

window.addEventListener('online', updateOnlineStatus);
window.addEventListener('offline', updateOnlineStatus);

updateOnlineStatus();

function displayErrorFromWorker(message) {
    log("Error: " + message);
}

window.addEventListener('message', displayErrorFromWorker);

function openDB() {
    return new Promise(function (resolve, reject) {
        console.log('indexdbname', IndexDbSyncName);
        var open_request = indexedDB.open(IndexDbSyncName, 3);
        open_request.onerror = function (event) {
            reject(new Error("Error opening database."));
        };
        open_request.onsuccess = function (event) {
            resolve(event.target.result);
        };
        open_request.onupgradeneeded = function(event) {
            var db = event.target.result;
            db.createObjectStore("sessionQueue", {keyPath: "sessionId"});
        }
    });
}

function updateDB(db, element, buttonLabel) {
    return new Promise(function (resolve, reject) {
        var store = db.transaction(["sessionQueue"], "readwrite").objectStore("sessionQueue");
        var data = {};
        data.sessionId = element.getAttribute('nid');
        data.action = buttonLabel == 'Remove from schedule' ? 'remove' : 'add';
        data.uid = window.localStorage.getItem('currentUser');
        var add_request = store.add(data);

        add_request.onerror = function(event) {
            reject(new Error("Error saving value to Database."));
        };

        add_request.onsuccess = function(event) {
            resolve(data.sessionId);
        };
    });
}