<div
    class="kw-inbox-manager"
    data-inbox-url="{{ url('/admin/inbox') }}"
    data-push-store-url="{{ url('/admin/push-subscriptions') }}"
    data-webpush-public-key="{{ config('services.webpush.public_key') }}"
>
    <style>
        .kw-inbox-layout {
            --kw-surface: #ffffff;
            --kw-surface-muted: #f8fafc;
            --kw-border: #e2e8f0;
            --kw-text: #0f172a;
            --kw-text-muted: #64748b;
            --kw-bot: #10b981;
            --kw-admin: #7c3aed;
            --kw-customer: #f3f4f6;
            --kw-shadow: 0 20px 40px rgba(15, 23, 42, 0.08);
        }

        .kw-inbox-shell {
            background: linear-gradient(180deg, #f8fafc 0%, #eef2ff 100%);
            border: 1px solid var(--kw-border);
            border-radius: 1.5rem;
            box-shadow: var(--kw-shadow);
            overflow: hidden;
        }

        .kw-inbox-panel {
            background: rgba(255, 255, 255, 0.86);
            backdrop-filter: blur(12px);
        }

        .kw-scroll::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        .kw-scroll::-webkit-scrollbar-thumb {
            background: rgba(100, 116, 139, 0.35);
            border-radius: 999px;
        }
    </style>

    <div class="kw-inbox-shell kw-inbox-layout">
        <div class="grid min-h-[78vh] lg:grid-cols-[22rem_minmax(0,1fr)_20rem]">
            <div class="kw-inbox-panel border-b border-slate-200 lg:border-b-0 lg:border-r {{ $mobileConversationOpen ? 'hidden lg:block' : 'block' }}">
                @include('livewire.inbox.conversation-list-inline')
            </div>

            <div class="kw-inbox-panel border-b border-slate-200 lg:border-b-0 {{ $mobileConversationOpen ? 'block' : 'hidden lg:block' }}">
                <livewire:inbox.chat-panel :conversationId="$selectedConversationId" :key="'chat-panel-' . ($selectedConversationId ?: 'empty')" />
            </div>

            <div class="kw-inbox-panel border-l border-slate-200 hidden lg:block">
                <livewire:inbox.customer-info-panel :conversationId="$selectedConversationId" :key="'customer-panel-' . ($selectedConversationId ?: 'empty')" />
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('livewire:init', () => {
            const container = document.querySelector('.kw-inbox-manager');

            if (!container) {
                return;
            }

            // Initialize Echo if available
            if (typeof window.Echo !== 'undefined') {
                console.log('Echo initialized');
            } else {
                console.warn('Echo not found - real-time features will not work');
            }

            const inboxUrl = container.dataset.inboxUrl || '/admin/inbox';
            const pushStoreUrl = container.dataset.pushStoreUrl || '';
            const vapidPublicKey = container.dataset.webpushPublicKey || '';
            let gestureBound = false;
            let subscriptionAttempted = false;

            const supportsNotifications = () => 'Notification' in window;
            const supportsWebPush = () => (
                'serviceWorker' in navigator &&
                'PushManager' in window &&
                window.isSecureContext
            );

            const urlBase64ToUint8Array = (base64String) => {
                const padding = '='.repeat((4 - base64String.length % 4) % 4);
                const base64 = (base64String + padding)
                    .replace(/-/g, '+')
                    .replace(/_/g, '/');

                const rawData = window.atob(base64);
                return Uint8Array.from([...rawData].map((char) => char.charCodeAt(0)));
            };

            const ensurePushSubscription = async () => {
                if (
                    subscriptionAttempted ||
                    !pushStoreUrl ||
                    !vapidPublicKey ||
                    !supportsNotifications() ||
                    !supportsWebPush() ||
                    Notification.permission !== 'granted'
                ) {
                    return;
                }

                subscriptionAttempted = true;

                try {
                    await navigator.serviceWorker.register('/admin-sw.js', {
                        scope: '/admin/',
                    });
                    const registration = await navigator.serviceWorker.ready;

                    let subscription = await registration.pushManager.getSubscription();

                    if (!subscription) {
                        subscription = await registration.pushManager.subscribe({
                            userVisibleOnly: true,
                            applicationServerKey: urlBase64ToUint8Array(vapidPublicKey),
                        });
                    }

                    await fetch(pushStoreUrl, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        },
                        body: JSON.stringify(subscription.toJSON()),
                    });
                } catch (error) {
                    console.warn('Failed to initialize push subscription.', error);
                }
            };

            const requestNotificationPermission = async () => {
                if (!supportsNotifications()) {
                    return;
                }

                try {
                    if (Notification.permission === 'default') {
                        const permission = await Notification.requestPermission();

                        if (permission === 'granted') {
                            await ensurePushSubscription();
                        }

                        return;
                    }

                    if (Notification.permission === 'granted') {
                        await ensurePushSubscription();
                    }
                } catch (error) {
                    console.warn('Notification permission request failed.', error);
                }
            };

            const bindPermissionGesture = () => {
                if (gestureBound || !supportsNotifications()) {
                    return;
                }

                gestureBound = true;

                document.addEventListener('click', () => {
                    requestNotificationPermission();
                }, { once: true });
            };

            if (supportsNotifications() && Notification.permission === 'granted') {
                ensurePushSubscription();
            } else {
                bindPermissionGesture();
            }

            Livewire.on('inbox-browser-notification', (payload) => {
                const event = Array.isArray(payload) ? payload[0] : payload;

                if (!supportsNotifications()) {
                    return;
                }

                if (document.hidden && Notification.permission === 'granted') {
                    const notification = new Notification(event.title || 'New inbox message', {
                        body: event.body || 'You have a new message.',
                        icon: '/assets/images/favicon.png',
                    });

                    notification.onclick = () => {
                        window.location.href = inboxUrl;
                        window.focus();
                    };
                }

                ensurePushSubscription();
            });
        });
    </script>
</div>
