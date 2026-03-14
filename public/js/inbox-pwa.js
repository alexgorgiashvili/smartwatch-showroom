/**
 * Inbox PWA Enhancement
 * Handles browser notifications, service worker registration, and PWA features
 */

class InboxPWA {
    constructor() {
        this.notificationPermission = Notification.permission;
        this.serviceWorkerRegistration = null;
        this.init();
    }

    async init() {
        if ('serviceWorker' in navigator) {
            try {
                this.serviceWorkerRegistration = await navigator.serviceWorker.register('/admin-sw.js');
                console.log('Service Worker registered successfully');
            } catch (error) {
                console.error('Service Worker registration failed:', error);
            }
        }

        this.setupNotificationListeners();
    }

    setupNotificationListeners() {
        // Listen for browser notification events from Livewire
        window.addEventListener('inbox-browser-notification', (event) => {
            const { title, body, conversationId } = event.detail;
            this.showBrowserNotification(title, body, conversationId);
        });

        // Listen for Livewire dispatched events
        Livewire.on('inbox-browser-notification', (data) => {
            this.showBrowserNotification(data.title, data.body, data.conversationId);
        });
    }

    async requestNotificationPermission() {
        if (!('Notification' in window)) {
            console.warn('This browser does not support notifications');
            return false;
        }

        if (this.notificationPermission === 'granted') {
            return true;
        }

        if (this.notificationPermission !== 'denied') {
            const permission = await Notification.requestPermission();
            this.notificationPermission = permission;
            return permission === 'granted';
        }

        return false;
    }

    async showBrowserNotification(title, body, conversationId) {
        const hasPermission = await this.requestNotificationPermission();

        if (!hasPermission) {
            console.warn('Notification permission not granted');
            return;
        }

        // Check if the page is focused
        if (document.hasFocus()) {
            console.log('Page is focused, skipping notification');
            return;
        }

        const options = {
            body: body,
            icon: '/images/notification-icon.png',
            badge: '/images/notification-badge.png',
            tag: `conversation-${conversationId}`,
            renotify: true,
            requireInteraction: false,
            data: {
                conversationId: conversationId,
                url: `/admin/inbox?conversation=${conversationId}`,
            },
            actions: [
                {
                    action: 'open',
                    title: 'Open',
                },
                {
                    action: 'close',
                    title: 'Dismiss',
                },
            ],
        };

        if (this.serviceWorkerRegistration) {
            this.serviceWorkerRegistration.showNotification(title, options);
        } else {
            const notification = new Notification(title, options);
            
            notification.onclick = (event) => {
                event.preventDefault();
                window.focus();
                window.location.href = options.data.url;
                notification.close();
            };
        }
    }

    async subscribeToPushNotifications() {
        if (!this.serviceWorkerRegistration) {
            console.error('Service Worker not registered');
            return null;
        }

        try {
            const subscription = await this.serviceWorkerRegistration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: this.urlBase64ToUint8Array(window.vapidPublicKey || ''),
            });

            return subscription;
        } catch (error) {
            console.error('Failed to subscribe to push notifications:', error);
            return null;
        }
    }

    urlBase64ToUint8Array(base64String) {
        const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
        const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
        const rawData = window.atob(base64);
        const outputArray = new Uint8Array(rawData.length);

        for (let i = 0; i < rawData.length; ++i) {
            outputArray[i] = rawData.charCodeAt(i);
        }

        return outputArray;
    }

    playNotificationSound() {
        const audio = new Audio('/sounds/notification.mp3');
        audio.volume = 0.5;
        audio.play().catch(err => console.log('Could not play notification sound:', err));
    }

    triggerHapticFeedback() {
        if ('vibrate' in navigator) {
            navigator.vibrate([100, 50, 100]);
        }
    }
}

// Initialize PWA when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        window.inboxPWA = new InboxPWA();
    });
} else {
    window.inboxPWA = new InboxPWA();
}
