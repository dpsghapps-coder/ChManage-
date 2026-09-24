import inertia from '@inertiajs/vite';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import babel from '@rolldown/plugin-babel';
import tailwindcss from '@tailwindcss/vite';
import react, { reactCompilerPreset } from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import { defineConfig, lazyPlugins, loadEnv } from 'vite-plus';

// Set DEV_SERVER_HOST in .env (e.g. 192.168.1.71) to reach `npm run dev` from phones and other PCs on the
// network. Left unset, the dev server stays on this machine only.
const devHost = loadEnv('development', process.cwd(), '').DEV_SERVER_HOST;
const escaped = (host: string) => host.replace(/[.[\]]/g, '\\$&');
const networkServer = devHost
    ? {
          host: '0.0.0.0',
          port: 5173,
          strictPort: true,
          origin: `http://${devHost}:5173`,
          hmr: { host: devHost },
          // Pages served from the LAN address or from this machine may load the dev scripts.
          cors: {
              origin: new RegExp(
                  `^https?://(${[devHost, 'localhost', '127.0.0.1', '[::1]'].map(escaped).join('|')})(:\\d+)?$`,
              ),
          },
      }
    : {};

export default defineConfig({
    plugins: lazyPlugins(() => [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.tsx'],
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
            ],
        }),
        inertia(),
        react(),
        babel({
            presets: [reactCompilerPreset()],
        }),
        tailwindcss(),
        wayfinder({
            formVariants: true,
        }),
    ]),
    server: {
        ...networkServer,
        watch: {
            ignored: [
                '**/.agents/**',
                '**/.claude/**',
                '**/.cursor/**',
                '**/.junie/**',
                '**/vendor/**',
            ],
        },
    },
    lint: {
        ignorePatterns: [
            'vendor/**',
            'node_modules/**',
            'public/**',
            'bootstrap/ssr/**',
            'tailwind.config.js',
            'resources/js/actions/**',
            'resources/js/components/ui/*',
            'resources/js/routes/**',
            'resources/js/wayfinder/**',
        ],
        options: {
            denyWarnings: true,
            typeAware: true,
        },
    },
    fmt: {
        printWidth: 80,
        tabWidth: 4,
        singleQuote: true,
        semi: true,
        singleAttributePerLine: false,
        htmlWhitespaceSensitivity: 'css',
        ignorePatterns: [
            'vendor/**',
            'node_modules/**',
            'public/**',
            '.github/**',
            'composer.json',
            'resources/js/components/ui/*',
            'resources/views/mail/*',
        ],
        sortTailwindcss: {
            functions: ['clsx', 'cn', 'cva'],
            stylesheet: 'resources/css/app.css',
        },
    },
});
