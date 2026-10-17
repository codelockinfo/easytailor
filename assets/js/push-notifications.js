(function() {
    'use strict';
    function urlBase64ToUint8Array(base64String) {
        const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
        const base64 = (base64String + padding)
            .replace(/\-/g, '+')
            .replace(/_/g, '/');

        const rawData = window.atob(base64);
        const outputArray = new Uint8Array(rawData.length);

        for (let i = 0; i < rawData.length; ++i) {
            outputArray[i] = rawData.charCodeAt(i);
        }
        return outputArray;
    }
    const isInsideAdmin = window.location.pathname.includes('/admin/');
    const ajaxBase = isInsideAdmin ? 'ajax/' : 'admin/ajax/';
    const swPath = isInsideAdmin ? '../sw.js' : 'sw.js';

    let isSubscribed = false;
    let swRegistration = null;
    const isPushSupported = ('serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window);
    async function initPushNotifications() {
        const enableBtn = document.getElementById('enablePushBtn');
        const pushStatusBadge = document.getElementById('pushStatusBadge');

        if (!isPushSupported) {
            if (enableBtn) {
                enableBtn.style.display = 'none';
            }
            if (pushStatusBadge) {
                pushStatusBadge.innerHTML = '<span class="badge bg-secondary">Push not supported</span>';
            }
            return;
        }

        try {
            swRegistration = await navigator.serviceWorker.register(swPath, { scope: isInsideAdmin ? '../' : './' });
            const subscription = await swRegistration.pushManager.getSubscription();
            isSubscribed = !(subscription === null);

            updatePushUI(isSubscribed);
            if (isSubscribed && Notification.permission === 'granted') {
                await syncSubscriptionWithServer(subscription);
            }
        } catch (error) {
            console.error('[WebPush] Service Worker registration failed:', error);
            updatePushUI(false);
        }

        if (enableBtn) {
            enableBtn.addEventListener('click', handleEnableNotificationsClick);
        }
    }
    function updatePushUI(subscribed) {
        const enableBtn = document.getElementById('enablePushBtn');
        const pushStatusBadge = document.getElementById('pushStatusBadge');

        if (enableBtn) {
            if (Notification.permission === 'denied') {
                enableBtn.innerHTML = '<i class="fas fa-bell-slash me-1"></i> Blocked in Browser';
                enableBtn.classList.remove('btn-primary', 'btn-outline-primary', 'btn-success');
                enableBtn.classList.add('btn-outline-danger');
                enableBtn.title = 'Notifications are blocked. Please enable them in your browser site settings.';
                enableBtn.disabled = true;
            } else if (subscribed && Notification.permission === 'granted') {
                enableBtn.innerHTML = '<i class="fas fa-bell-slash me-1"></i> Disable Push';
                enableBtn.classList.remove('btn-primary', 'btn-outline-primary', 'btn-outline-danger');
                enableBtn.classList.add('btn-outline-secondary');
                enableBtn.title = 'Browser push notifications are active. Click to disable.';
                enableBtn.disabled = false;
            } else {
                enableBtn.innerHTML = '<i class="fas fa-bell me-1"></i> Enable Notifications';
                enableBtn.classList.remove('btn-success', 'btn-outline-secondary', 'btn-outline-danger');
                enableBtn.classList.add('btn-primary');
                enableBtn.title = 'Click to receive order alerts on your browser';
                enableBtn.disabled = false;
            }
        }

        if (pushStatusBadge) {
            if (Notification.permission === 'denied') {
                pushStatusBadge.innerHTML = '<span class="badge bg-danger">Blocked</span>';
            } else if (subscribed && Notification.permission === 'granted') {
                pushStatusBadge.innerHTML = '<span class="badge bg-success"><i class="fas fa-check-circle me-1"></i>Enabled</span>';
            } else {
                pushStatusBadge.innerHTML = '<span class="badge bg-warning text-dark">Disabled</span>';
            }
        }
    }
    async function handleEnableNotificationsClick(e) {
        e.preventDefault();
        const enableBtn = document.getElementById('enablePushBtn');
        if (!enableBtn || enableBtn.disabled) return;

        enableBtn.disabled = true;
        const originalHtml = enableBtn.innerHTML;
        enableBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Processing...';

        try {
            if (isSubscribed) {
                await unsubscribeFromPush();
            } else {
                await subscribeToPush();
            }
        } catch (err) {
            console.error('[WebPush] Toggle failed:', err);
            alert('Push notification setup error: ' + (err.message || err));
        } finally {
            enableBtn.disabled = false;
            updatePushUI(isSubscribed);
        }
    }
    async function subscribeToPush() {
        if (!isPushSupported) {
            throw new Error('Push notifications are not supported in this browser.');
        }

        const permission = await Notification.requestPermission();
        if (permission !== 'granted') {
            if (permission === 'denied') {
                throw new Error('Notification permission was blocked in browser settings. Please allow notifications for this site.');
            }
            return;
        }
        const keyRes = await fetch(ajaxBase + 'get_vapid_public_key.php');
        const keyData = await keyRes.json();
        if (!keyData.success || !keyData.publicKey) {
            throw new Error(keyData.error || 'Failed to retrieve VAPID public key from server');
        }

        const applicationServerKey = urlBase64ToUint8Array(keyData.publicKey);
        const subscription = await swRegistration.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: applicationServerKey
        });
        await syncSubscriptionWithServer(subscription);
        isSubscribed = true;

        showNotificationToast('Notifications Enabled', 'You will now receive instant order updates on this device!');
    }
    async function unsubscribeFromPush() {
        const subscription = await swRegistration.pushManager.getSubscription();
        if (subscription) {
            try {
                await fetch(ajaxBase + 'delete_push_subscription.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ endpoint: subscription.endpoint })
                });
            } catch (err) {
                console.warn('[WebPush] Server deletion warning:', err);
            }

            await subscription.unsubscribe();
        }
        isSubscribed = false;
        showNotificationToast('Notifications Disabled', 'Browser push notifications have been turned off.');
    }
    async function syncSubscriptionWithServer(subscription) {
        const subJson = subscription.toJSON();
        const res = await fetch(ajaxBase + 'save_push_subscription.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                endpoint: subscription.endpoint,
                keys: {
                    p256dh: subJson.keys ? subJson.keys.p256dh : '',
                    auth: subJson.keys ? subJson.keys.auth : ''
                }
            })
        });

        const data = await res.json();
        if (!data.success) {
            throw new Error(data.error || 'Server rejected subscription');
        }
    }
    function showNotificationToast(title, body) {
        const toastContainer = document.getElementById('notificationToastContainer');
        if (!toastContainer) return;

        const toastId = 'toast-' + Date.now();
        const toastHtml = `
            <div id="${toastId}" class="toast align-items-center text-bg-dark border-0 shadow-lg mb-2" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="4000">
                <div class="d-flex">
                    <div class="toast-body d-flex align-items-center gap-2">
                        <i class="fas fa-bell text-warning"></i>
                        <div>
                            <strong>${escapeHtml(title)}</strong><br>
                            <span style="font-size: 0.85rem;">${escapeHtml(body)}</span>
                        </div>
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            </div>
        `;
        toastContainer.insertAdjacentHTML('beforeend', toastHtml);
        const toastEl = document.getElementById(toastId);
        if (window.bootstrap && bootstrap.Toast) {
            const toast = new bootstrap.Toast(toastEl);
            toast.show();
        }
    }

    function escapeHtml(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }
    let lastUnreadCount = 0;
    async function fetchInAppNotifications() {
        try {
            const res = await fetch(ajaxBase + 'get_notifications.php');
            if (!res.ok) return;
            const data = await res.json();
            if (!data.success) return;

            updateBellUI(data.unread_count, data.notifications);
            if (data.unread_count > lastUnreadCount && lastUnreadCount !== 0) {
                const bellIcon = document.querySelector('.notif-bell-btn i');
                if (bellIcon) {
                    bellIcon.classList.add('fa-shake');
                    setTimeout(() => bellIcon.classList.remove('fa-shake'), 1200);
                }
            }
            lastUnreadCount = data.unread_count;
        } catch (e) {
        }
    }
    function updateBellUI(unreadCount, items) {
        const badge = document.querySelector('.notif-badge');
        const headerBadge = document.getElementById('notifHeaderCountBadge');
        const notifListContainer = document.getElementById('notifDropdownItems');

        if (badge) {
            if (unreadCount > 0) {
                badge.textContent = unreadCount > 99 ? '99+' : unreadCount;
                badge.style.display = 'inline-block';
            } else {
                badge.textContent = '0';
                badge.style.display = 'none';
            }
        }

        if (headerBadge) {
            if (unreadCount > 0) {
                headerBadge.textContent = unreadCount + ' new';
                headerBadge.style.display = 'inline-block';
            } else {
                headerBadge.style.display = 'none';
            }
        }

        if (!notifListContainer) return;

        if (!items || items.length === 0) {
            notifListContainer.innerHTML = `
                <li class="text-center py-4 text-muted" style="font-size: 0.85rem;">
                    <i class="fas fa-check-circle text-success mb-2 d-block" style="font-size: 1.5rem;"></i>
                    All caught up! No notifications.
                </li>
            `;
            return;
        }

        let html = '';
        items.forEach(item => {
            const isUnread = parseInt(item.is_read) === 0;
            const bgClass = isUnread ? 'bg-light-subtle fw-medium' : '';
            const unreadDot = isUnread ? '<span class="rounded-circle bg-primary d-inline-block ms-auto" style="width: 7px; height: 7px;"></span>' : '';
            const link = item.link || 'orders.php';

            html += `
                <li class="notif-item-row" data-id="${item.id}">
                    <a class="dropdown-item d-flex align-items-start gap-2 p-2 rounded ${bgClass}" href="${link}" style="white-space: normal; transition: background 0.15s ease;" onclick="window.markNotifRead(${item.id}, event, '${link}')">
                        <i class="${escapeHtml(item.icon)} mt-1" style="font-size: 1rem;"></i>
                        <div class="flex-grow-1" style="min-width: 0;">
                            <div class="d-flex align-items-center justify-content-between">
                                <div class="fw-semibold text-dark text-truncate" style="font-size: 0.82rem;">${escapeHtml(item.title)}</div>
                                ${unreadDot}
                            </div>
                            <div class="text-muted text-break" style="font-size: 0.74rem; line-height: 1.35;">${escapeHtml(item.message)}</div>
                            <div class="text-secondary mt-1" style="font-size: 0.68rem;"><i class="far fa-clock me-1"></i>${escapeHtml(item.time_ago || 'Recent')}</div>
                        </div>
                    </a>
                </li>
            `;
        });

        notifListContainer.innerHTML = html;
    }
    window.markNotifRead = async function(notifId, event, url) {
        if (event) {
            event.preventDefault();
            event.stopPropagation();
        }

        try {
            const notifRow = document.querySelector(`.notif-item-row[data-id="${notifId}"]`);
            if (notifRow) {
                const unreadDot = notifRow.querySelector('.rounded-circle.bg-primary');
                if (unreadDot) unreadDot.remove();
                const itemLink = notifRow.querySelector('.dropdown-item');
                if (itemLink) {
                    itemLink.classList.remove('bg-light-subtle', 'fw-medium');
                }
            }

            const badge = document.querySelector('.notif-badge');
            const headerBadge = document.getElementById('notifHeaderCountBadge');
            let count = parseInt(badge ? badge.textContent : '0') || 0;
            if (count > 0) {
                count = Math.max(0, count - 1);
                if (badge) {
                    badge.textContent = count > 0 ? (count > 99 ? '99+' : count) : '0';
                    badge.style.display = count > 0 ? 'inline-block' : 'none';
                }
                if (headerBadge) {
                    headerBadge.textContent = count + ' new';
                    headerBadge.style.display = count > 0 ? 'inline-block' : 'none';
                }
            }
        } catch (uiErr) {
            console.warn('UI update error:', uiErr);
        }

        let targetUrl = url || 'orders.php';
        if (!targetUrl.startsWith('http://') && !targetUrl.startsWith('https://') && !targetUrl.startsWith('/')) {
            if (!isInsideAdmin && !targetUrl.startsWith('admin/')) {
                targetUrl = 'admin/' + targetUrl;
            }
        }
        try {
            const formData = new FormData();
            formData.append('id', notifId);

            const sendPromise = fetch(ajaxBase + 'mark_notifications_read.php', {
                method: 'POST',
                body: formData,
                keepalive: true
            });

            await Promise.race([
                sendPromise,
                new Promise(resolve => setTimeout(resolve, 250))
            ]);
        } catch (e) {
            console.warn('markNotifRead request warning:', e);
        }

        window.location.href = targetUrl;
    };
    window.markAllNotificationsRead = async function(e) {
        if (e) e.preventDefault();
        try {
            const formData = new FormData();
            formData.append('all', '1');
            const res = await fetch(ajaxBase + 'mark_notifications_read.php', {
                method: 'POST',
                body: formData
            });
            const data = await res.json();
            if (data.success) {
                fetchInAppNotifications();
            }
        } catch (err) {
            console.error('Failed to mark all as read:', err);
        }
    };
    document.addEventListener('DOMContentLoaded', function() {
        initPushNotifications();
        fetchInAppNotifications();
        setInterval(fetchInAppNotifications, 25000);
    });

})();
