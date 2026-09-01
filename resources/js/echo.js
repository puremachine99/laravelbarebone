import Echo from 'laravel-echo';

import Pusher from 'pusher-js';
window.Pusher = Pusher;

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
    wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
    enabledTransports: ['ws', 'wss'],
});

/**
 * Global "who's online" presence, shared by every authenticated page via a single Alpine
 * store. Joined once here (not per-component) so navigating between pages doesn't churn the
 * presence channel membership. `online` maps userId -> their chosen status (online/away/busy);
 * a user with no entry is offline (disconnected). Consumers read it via `$store.presence.statusOf(userId)`.
 */
document.addEventListener('alpine:init', () => {
    Alpine.store('presence', {
        online: new Map(),
        statusOf(userId) {
            return this.online.get(Number(userId)) ?? 'offline';
        },
    });

    const isAuthenticated = document.querySelector('meta[name="authenticated"]')?.content === '1';

    if (!isAuthenticated) {
        return;
    }

    window.Echo.join('online')
        .here((users) => {
            Alpine.store('presence').online = new Map(users.map((user) => [user.id, user.status]));
        })
        .joining((user) => {
            const store = Alpine.store('presence');
            store.online = new Map(store.online).set(user.id, user.status);
        })
        .leaving((user) => {
            const store = Alpine.store('presence');
            const online = new Map(store.online);
            online.delete(user.id);
            store.online = online;
        })
        .listen('.UserStatusChanged', (event) => {
            const store = Alpine.store('presence');
            store.online = new Map(store.online).set(event.id, event.status);
        });
});
