import { usePage } from '@inertiajs/vue3'

/*
 | Tarih biçimlendirme — MAĞAZANIN saat diliminde. (4.5M)
 |
 | ⚠️ Önce `new Date(v).toLocaleString('tr-TR')` yazılıydı, yani
 | PERSONELİN TARAYICI saat dilimi kullanılıyordu. Türkiye'de doğru
 | görünüyordu ama yurt dışından bakan bir personel başka bir saat
 | görürdü — oysa "sipariş saati" mağazaya ait bir olgu.
 |
 | ⚠️ Aynı fonksiyon iki ekranda kopyalanmıştı; biri düzeltilip öteki
 | unutulurdu. Tek yerde.
 |
 | ⚠️ Saat dilimi SUNUCUDAN geliyor (paylaşılan `marka.saat_dilimi`) ve
 | vitrin de AYNI ayarı kullanıyor — iki yüzeyin farklı saat göstermesi
 | zaten bu bloğu doğuran şikâyetti.
 */
export function tarih(deger) {
    if (!deger) return '—'

    const dilim = usePage().props.marka?.saat_dilimi || 'Europe/Istanbul'

    return new Date(deger).toLocaleString('tr-TR', { timeZone: dilim })
}
