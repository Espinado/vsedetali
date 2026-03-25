import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

function stripEnvQuotes(value) {
    if (value === undefined || value === null) {
        return '';
    }

    return String(value).replace(/^["']|["']$/g, '').trim();
}

export function createEcho() {
    const key = import.meta.env.VITE_REVERB_APP_KEY;

    if (!key) {
        return null;
    }

    window.Pusher = Pusher;

    const host =
        stripEnvQuotes(import.meta.env.VITE_REVERB_HOST) || window.location.hostname;
    const rawPort = stripEnvQuotes(import.meta.env.VITE_REVERB_PORT);
    const portNum = rawPort === '' ? NaN : Number(rawPort);
    const port =
        Number.isFinite(portNum) && portNum > 0 ? portNum : 8080;
    const scheme = stripEnvQuotes(import.meta.env.VITE_REVERB_SCHEME) || 'http';

    // Reverb по умолчанию слушает :8080 без TLS. Любой wss://…:8080 в браузере падает.
    const forceTLS =
        port === 8080 ? false : scheme === 'https';

    const csrf =
        typeof document !== 'undefined'
            ? document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? ''
            : '';

    const origin =
        typeof window !== 'undefined' && window.location?.origin
            ? window.location.origin
            : '';

    const authHeaders = {
        ...(csrf ? { 'X-CSRF-TOKEN': csrf } : {}),
        'X-Requested-With': 'XMLHttpRequest',
        Accept: 'application/json',
    };

    const authUrl = origin ? `${origin}/broadcasting/auth` : '/broadcasting/auth';

    return new Echo({
        broadcaster: 'reverb',
        key,
        authEndpoint: authUrl,
        wsHost: host,
        wsPort: port,
        wssPort: port,
        forceTLS,
        csrfToken: csrf || undefined,
        auth: {
            headers: authHeaders,
        },
        // Явно отправляем cookie сессии (Filament / web). Встроенный ajax в pusher-js не задаёт withCredentials.
        authorizer: (channel) => ({
            authorize: (socketId, callback) => {
                const body = new URLSearchParams();
                body.set('socket_id', socketId);
                body.set('channel_name', channel.name);

                fetch(authUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        ...authHeaders,
                    },
                    credentials: 'include',
                    body: body.toString(),
                })
                    .then(async (response) => {
                        const text = await response.text();
                        if (!response.ok) {
                            throw new Error(text || `HTTP ${response.status}`);
                        }
                        try {
                            return JSON.parse(text);
                        } catch {
                            throw new Error('Invalid JSON from /broadcasting/auth');
                        }
                    })
                    .then((data) => callback(null, data))
                    .catch((err) => callback(err, null));
            },
        }),
        // без wss при forceTLS:false — меньше лишних попыток
        enabledTransports: port === 8080 ? ['ws'] : ['ws', 'wss'],
    });
}
