
self.addEventListener('install', function(event) {
    self.skipWaiting();
});

self.addEventListener('activate', function(event) {
    event.waitUntil(clients.claim());
});

self.addEventListener('push', function(event) {
    let payload = {};
    if (event.data) {
        try {
            payload = event.data.json();
        } catch (err) {
            payload = {
                title: 'Tailor Pro',
                body: event.data.text()
            };
        }
    }

    const title = payload.title || 'Tailor Pro Notification';
    const notificationData = payload.data || {};
    const iconUrl = payload.icon || 'assets/images/tailor_logo_color.png';
    const badgeUrl = payload.badge || 'favicon.ico';

    const options = {
        body: payload.body || 'You have received an order update.',
        icon: iconUrl,
        badge: badgeUrl,
        data: notificationData,
        tag: 'tailor-pro-order-' + (notificationData.order_id || Date.now()),
        renotify: true,
        vibrate: [200, 100, 200]
    };

    event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', function(event) {
    event.notification.close();

    const data = event.notification.data || {};
    const notifId = data.notification_id || null;
    const orderId = data.order_id || null;

    let targetPath = data.url || 'admin/dashboard.php';
    if (!targetPath.startsWith('http://') && !targetPath.startsWith('https://')) {
        if (!targetPath.startsWith('/') && !targetPath.startsWith('admin/')) {
            targetPath = 'admin/' + targetPath;
        }
    }

    const baseUrl = self.registration.scope || self.location.href;
    const targetUrl = new URL(targetPath, baseUrl).href;

    // Send mark-as-read in background if notification_id or order_id is present
    const markReadPromise = (notifId || orderId) ? (function() {
        const markUrl = new URL('admin/ajax/mark_notifications_read.php', baseUrl).href;
        const bodyParams = new URLSearchParams();
        if (notifId) bodyParams.append('id', notifId);
        if (orderId) bodyParams.append('order_id', orderId);

        return fetch(markUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: bodyParams.toString()
        }).catch(function() {});
    })() : Promise.resolve();

    event.waitUntil(
        markReadPromise.then(function() {
            return clients.matchAll({ type: 'window', includeUncontrolled: true });
        }).then(function(windowClients) {
            for (let i = 0; i < windowClients.length; i++) {
                const client = windowClients[i];
                if (client.url === targetUrl && 'focus' in client) {
                    return client.focus();
                }
            }
            for (let i = 0; i < windowClients.length; i++) {
                const client = windowClients[i];
                if (client.url.includes('/admin/') && 'focus' in client) {
                    if ('navigate' in client) {
                        return client.navigate(targetUrl).then(c => c.focus());
                    }
                    return client.focus();
                }
            }
            if (clients.openWindow) {
                return clients.openWindow(targetUrl);
            }
        })
    );
});
