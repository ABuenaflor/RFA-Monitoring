import { defineConfig, loadEnv } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd(), '');

    // Read the LAN host from APP_URL (.env) so there's one place to update
    // it — falls back to localhost if APP_URL isn't set to a LAN IP.
    const lanHost = env.APP_URL
        ? new URL(env.APP_URL).hostname
        : 'localhost';

    return {
        plugins: [
            laravel({
                input: [
                    'resources/css/app.css',
                    'resources/js/app.js',
                ],
                refresh: true,
            }),

            tailwindcss(),
        ],

        server: {
            // Bind to all interfaces so other devices on the network can
            // reach the dev server, not just localhost.
            host: '0.0.0.0',

            port: 5173,

            strictPort: true,

            // Browsers need a real, reachable host for the HMR websocket —
            // 0.0.0.0 itself isn't connectable from another device.
            hmr: {
                host: lanHost,
            },
        },
    };
});