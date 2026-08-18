/*
 | Panelin giriş noktası. (4C)
 |
 | ⚠️ SSR YOK (4-K2): `createInertiaApp` yalnızca tarayıcıda çalışıyor.
 | Ayrı bir Node süreci açılsaydı tüm markalar onu paylaşırdı ve modül
 | seviyesindeki durum istekler arasında sızardı — M-2.4'te pgBouncer'ı
 | reddetme gerekçesinin aynısı.
 */
import { createApp, h } from 'vue'
import { createInertiaApp } from '@inertiajs/vue3'
import '../css/panel.css'

createInertiaApp({
    title: (baslik) => (baslik ? `${baslik} · Panel` : 'Panel'),

    /*
     | Sayfalar tembel yükleniyor: `import.meta.glob` her bileşeni ayrı
     | parçaya bölüyor. Hepsi tek dosyada toplansaydı marka paneli her
     | açılışta hiç görmeyeceği ekranları da indirirdi.
     */
    resolve: (ad) => {
        const sayfalar = import.meta.glob('./Panel/Pages/**/*.vue')
        const yol = `./Panel/Pages/${ad}.vue`

        if (!sayfalar[yol]) {
            throw new Error(`Panel sayfası bulunamadı: ${ad}`)
        }

        return sayfalar[yol]()
    },

    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el)
    },

    progress: { color: '#ea580c' },
})
