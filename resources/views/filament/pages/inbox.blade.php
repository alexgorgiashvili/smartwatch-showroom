<x-filament-panels::page :full-height="true">
    @push('styles')
    <style>
        /* Custom scrollbar for chat */
        .chat-scroll::-webkit-scrollbar {
            width: 6px;
        }
        .chat-scroll::-webkit-scrollbar-track {
            background: #f1f5f9;
        }
        .chat-scroll::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 3px;
        }
        .chat-scroll::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }

    </style>
    @endpush

    @vite(['resources/js/app.js'])

    @push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/flowbite@2.5.1/dist/flowbite.min.js" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/feather-icons/dist/feather.min.js" defer></script>
    <script src="{{ asset('js/inbox-pwa.js') }}" defer></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const inboxDebug = @js((bool) config('app.debug'));
            const debugLog = (...args) => {
                if (inboxDebug) {
                    console.log(...args);
                }
            };
            const debugError = (...args) => {
                if (inboxDebug) {
                    console.error(...args);
                }
            };

            const getInboxBadgeElement = () => {
                const legacyBadge = document.querySelector('#sidebar-inbox-badge');
                if (legacyBadge) {
                    return legacyBadge;
                }

                const inboxLink = document.querySelector('a[href*="/admin/inbox"], a[href*="/inbox"]');
                if (!inboxLink) {
                    return null;
                }

                let badge = inboxLink.querySelector('[data-inbox-badge]');
                if (!badge) {
                    badge = document.createElement('span');
                    badge.setAttribute('data-inbox-badge', '1');
                    badge.setAttribute('data-unread-count', '0');
                    badge.className = 'fi-badge fi-color-danger ms-2 d-none';
                    badge.textContent = '0';
                    inboxLink.appendChild(badge);
                }

                return badge;
            };

            const setInboxBadgeCount = (count) => {
                const badge = getInboxBadgeElement();
                if (!badge) {
                    return;
                }

                const normalized = Number.isFinite(count) ? Math.max(0, count) : 0;
                badge.dataset.unreadCount = String(normalized);
                badge.textContent = String(normalized);

                if (normalized > 0) {
                    badge.classList.remove('d-none');
                } else {
                    badge.classList.add('d-none');
                }
            };

            if (typeof feather !== 'undefined') {
                feather.replace();
            }

            // Setup Echo listeners for real-time updates
            debugLog('Checking Echo availability...');
            debugLog('window.Echo:', typeof window.Echo);
            debugLog('window.Pusher:', typeof window.Pusher);

            if (window.Echo) {
                debugLog('Echo initialized successfully');
                debugLog('Echo config:', {
                    broadcaster: window.Echo.connector?.options?.broadcaster,
                    key: window.Echo.connector?.options?.key,
                    cluster: window.Echo.connector?.options?.cluster
                });

                // Listen to private inbox channel for new messages
                window.Echo.private('inbox')
                    .listen('.MessageReceived', (e) => {
                        debugLog('Message received via Echo:', e);
                        const currentConversationId = e?.conversation?.id;

                        // Dispatch Livewire event to refresh conversations
                        if (typeof Livewire !== 'undefined') {
                            debugLog('Dispatching inbox-message-received to Livewire');
                            Livewire.dispatch('inbox-message-received', {
                                conversationId: currentConversationId,
                                senderType: e?.message?.sender_type ?? null,
                            });
                        }

                        // Update notification badge (only if the same conversation is not currently open)
                        const badge = getInboxBadgeElement();
                        const chatWorkspaceElement = document.querySelector('[wire\\:key^="chat-workspace-"]');
                        const openConversationKey = chatWorkspaceElement?.getAttribute('wire:key') || '';
                        const openConversationId = openConversationKey.replace('chat-workspace-', '');
                        const isSameConversationOpen = !!openConversationId
                            && String(openConversationId) === String(currentConversationId);

                        debugLog('Current conversation ID:', currentConversationId);
                        debugLog('Open conversation ID:', openConversationId);

                        // Show toast notification on mobile if chat is open and message is from customer
                        if (isSameConversationOpen && e?.message?.sender_type !== 'admin' && window.innerWidth < 768) {
                            const customerName = e?.customer?.name || 'Customer';
                            const messagePreview = (e?.message?.content || '').substring(0, 50);

                            if (typeof Livewire !== 'undefined') {
                                Livewire.dispatch('notify', {
                                    title: `New message from ${customerName}`,
                                    message: messagePreview,
                                    type: 'info'
                                });
                            }
                        }

                        if (badge && e?.message?.sender_type !== 'admin' && !isSameConversationOpen) {
                            const current = parseInt(badge.dataset.unreadCount || '0', 10);
                            const next = current + 1;
                            setInboxBadgeCount(next);
                            debugLog('Badge updated:', next);
                        } else {
                            debugLog('Badge not updated - reason:', {
                                noBadge: !badge,
                                isAdmin: e?.message?.sender_type === 'admin',
                                sameConversationOpen: isSameConversationOpen
                            });
                        }
                    })
                    .listen('.ConversationAssigned', (e) => {
                        debugLog('Conversation assigned via Echo:', e);
                        if (typeof Livewire !== 'undefined') {
                            Livewire.dispatch('inbox-conversation-assigned');
                        }
                    });

                debugLog('Echo listeners registered for inbox channel');
            } else {
                debugError('Echo not initialized - real-time updates will not work');
                debugError('Debug info:', {
                    hasBootstrap: typeof window.axios !== 'undefined',
                    hasPusher: typeof window.Pusher !== 'undefined',
                    hasEcho: typeof window.Echo !== 'undefined'
                });
            }

            if (typeof Livewire !== 'undefined') {
                Livewire.on('inbox-unread-count-updated', (payload) => {
                    const event = Array.isArray(payload) ? payload[0] : payload;
                    const totalUnread = Number(event?.totalUnread ?? 0);
                    setInboxBadgeCount(totalUnread);
                });
            }
        });

        document.addEventListener('livewire:navigated', () => {
            if (typeof feather !== 'undefined') {
                feather.replace();
            }
        });

        if (typeof Livewire !== 'undefined') {
            Livewire.hook('morph.updated', ({ el, component }) => {
                if (typeof feather !== 'undefined') {
                    feather.replace();
                }
            });
        }
    </script>
    @endpush

    <div class="kw-inbox-page min-h-[calc(100dvh-4rem)]">
        <livewire:inbox.inbox-shell />
    </div>
</x-filament-panels::page>
