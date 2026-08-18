/*
 | Kontrol düzleminin giriş noktası. (4F)
 |
 | ⚠️ Marka panelinden AYRI PAKET: `resolve` yalnızca `Platform/Pages`
 | altına bakıyor. Tek paket olsaydı marka personelinin tarayıcısına
 | kontrol düzleminin ekran kodu da inerdi.
 */
import { createApp, h } from 'vue'
import { createInertiaApp } from '@inertiajs/vue3'
import '../css/panel.css'

createInertiaApp({
    title: (baslik) => (baslik ? `${baslik} · TıkMarka` : 'TıkMarka Yönetim'),

    resolve: (ad) => {
        const sayfalar = import.meta.glob('./Platform/Pages/**/*.vue')
        const yol = `./Platform/Pages/${ad}.vue`

        if (!sayfalar[yol]) {
            throw new Error(`Yönetim sayfası bulunamadı: ${ad}`)
        }

        return sayfalar[yol]()
    },

    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el)
    },

    progress: { color: '#0f172a' },
})
