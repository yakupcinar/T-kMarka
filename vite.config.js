import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    plugins: [
        laravel({
            /*
             | ⚠️ YALNIZCA PANEL derleniyor. Vitrin sunucuda render edilen
             | Blade (4-K1) ve stilini kendi düzeninde taşıyor — derlenmiş
             | bir JS paketine ihtiyacı yok ve olmaması bilinçli: müşteri
             | tarafı betik yüklenmeden de alışveriş yapabilmeli (4B-K1).
             |
             | Laravel'in varsayılan `app.js`/`app.css` girişleri KALDIRILDI:
             | hiçbir sayfa onları çağırmıyordu, derlenmeleri boş çıktı
             | üretiyordu.
             */
            input: ['resources/js/panel.js', 'resources/js/platform.js'],
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
