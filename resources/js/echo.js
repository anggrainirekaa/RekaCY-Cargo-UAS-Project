import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

// Runtime config is injected by the server layout (window.RK_REALTIME), so no Vite env rebuild is needed.
const cfg = window.RK_REALTIME;

if (cfg && cfg.enabled) {
    try {
        window.Echo = new Echo({
            broadcaster: 'reverb',
            key: cfg.key,
            wsHost: cfg.host,
            wsPort: cfg.port,
            wssPort: cfg.port,
            forceTLS: cfg.scheme === 'https',
            enabledTransports: ['ws', 'wss'],
        });
    } catch (e) {
        console.warn('Realtime disabled:', e);
    }
}
