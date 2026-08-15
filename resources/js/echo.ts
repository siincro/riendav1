import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

declare global {
    interface Window {
        Pusher: typeof Pusher;
    }
}

window.Pusher = Pusher;

/**
 * Cliente de tiempo real (Laravel Reverb).
 *
 * Se conecta a Reverb usando la misma app y credenciales del backend. Los
 * canales se suscriben por módulo:
 *
 *   category.{id}                        — consola del administrador (privado).
 *   public-category.{torneo}.{categoría} — landing «en vivo» (público).
 */
const echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT,
    wssPort: import.meta.env.VITE_REVERB_PORT,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
    enabledTransports: ['ws', 'wss'],
});

export default echo;
