<script setup>
/*
 | Ürün oluşturma / düzenleme. (4D)
 |
 | ⚠️ Tek bileşen iki iş yapıyor: `urun === null` ise oluşturma, değilse
 | düzenleme. İki ayrı dosya olsaydı alan listesi iki yerde tutulurdu ve
 | biri güncellenmeden kalırdı.
 */
import { computed, ref, watch } from 'vue'
import { useForm, Head, Link, router } from '@inertiajs/vue3'
import PanelDuzeni from '../../Layouts/PanelDuzeni.vue'

const props = defineProps({
  urun: Object,
  kategoriler: Array,
  durumlar: Array,
  eksenler: Array,
  manuelKoleksiyonlar: Array,
})

/*
 | ★ `computed` — DÜZ DEĞİŞKEN DEĞİL. (4.5L)
 |
 | ⚠️ Gerçek kullanımda bulundu: "ürünü oluşturuyorum, 'şimdi varyant
 | ekleyebilirsin' yazısı geliyor ama varyant ve görsel bölümü gelmiyor;
 | sayfa değiştirip ürüne tekrar tıklayınca geliyor."
 |
 | Sebep Inertia'nın bileşen yeniden kullanımı: oluşturma ve düzenleme
 | AYNI bileşen (`Urunler/Form`). Yönlendirme aynı bileşene gidince Vue
 | örneği yeniden KURULMUYOR — `setup()` bir daha koşmuyor. Düz değişken
 | olarak yazılan `yeniMi` `true`'da donuyor ve varyant paneli hiç
 | görünmüyor.
 |
 | ⚠️ Sunucu tarafında hiçbir şey yanlış değildi: yönlendirme doğru,
 | prop'lar doğru geliyordu. Bu yüzden 4.5G'de "ölçtüm, zaten çalışıyor"
 | denmişti — ölçülen şey yönlendirmeydi, EKRAN değil.
 */
const yeniMi = computed(() => !props.urun)

const form = useForm({
  title: props.urun?.title ?? '',
  description: props.urun?.description ?? '',
  brand: props.urun?.brand ?? '',
  model: props.urun?.model ?? '',
  tax_rate: props.urun?.tax_rate ?? '',
  category_uuid: props.urun?.category_uuid ?? '',
})

/*
 | ⚠️ Form alanları da yeniden tohumlanıyor. `yeniMi`'yi `computed`
 | yapmak tek başına yetmez: `useForm` başlangıç değerlerini de setup'ta
 | okuyor, yani başka bir ürüne geçildiğinde kutularda ESKİ ürünün
 | verisi kalırdı — ve kaydedilirdi.
 */
watch(() => props.urun?.uuid, () => {
  form.defaults({
    title: props.urun?.title ?? '',
    description: props.urun?.description ?? '',
    brand: props.urun?.brand ?? '',
    model: props.urun?.model ?? '',
    tax_rate: props.urun?.tax_rate ?? '',
    category_uuid: props.urun?.category_uuid ?? '',
  })
  form.reset()
})

function kaydet() {
  if (yeniMi.value) {
    form.post('/yonetim/urunler')
  } else {
    form.put(`/yonetim/urunler/${props.urun.uuid}`)
  }
}

/* Varyant formu — ayrı, çünkü ürün kaydedilmeden varyant eklenemez. */
/*
 | ★ EKSENLER (4.5L). Ürünün hangi eksenleri kullandığı burada seçiliyor;
 | varyant eklerken her eksen için bir değer isteniyor.
 |
 | ⚠️ Eksen tanımlanamadığı sürece her varyantın `options` alanı boş
 | kalıyordu ve `(product_id, options)` benzersiz kısıtı yüzünden İKİNCİ
 | varyant her zaman patlıyordu — ham 500 ile. Yani bu ekran eksikken
 | ürünlerin tek varyantı olabiliyordu.
 */
/*
 | ⚠️ `useForm` — düz `router.post` DEĞİL. (4.5S)
 |
 | Sınır aşıldığında (en fazla `maksEksen` eksen) sunucu 422 dönüyor ve
 | düz `router.post` ile bu hata EKRANDA HİÇ GÖRÜNMÜYORDU: marka beş
 | ekseni işaretleyip kaydediyor, hiçbir şey olmuyor ve "kaydettim ama
 | seçenekler gelmiyor" diyordu.
 */
const eksenForm = useForm({ option_uuids: [] })

watch(() => props.urun?.uuid, () => {
  eksenForm.option_uuids = (props.urun?.options ?? []).map((e) => e.uuid)
  eksenForm.clearErrors()
}, { immediate: true })

/*
 | ⚠️ EKSEN SEÇİLMEDEN "kaydet" ANLAMSIZ. Sunucu boş listeyi kabul ediyor
 | (eksensiz ürün geçerli bir şey) ama marka düğmeye basıp hiçbir şeyin
 | değişmediğini görüyordu.
 */
const eksenSecildi = computed(() => eksenForm.option_uuids.length > 0)

/*
 | ⚠️ SINIRA GELİNCE kalan kutucuklar kapanıyor (1B-K4: bir üründe en
 | fazla `maksEksen` eksen). Sunucu zaten reddediyor ama marka bunu
 | ancak kaydettikten SONRA öğreniyordu.
 */
const eksenSiniriDoldu = computed(() => eksenForm.option_uuids.length >= (props.maksEksen ?? 3))

function eksenleriKaydet() {
  eksenForm.post(`/yonetim/urunler/${props.urun.uuid}/eksenler`, { preserveScroll: true })
}

/* Ürünün kullandığı eksenler — varyant formundaki seçiciler bunlardan. */
const urunEksenleri = computed(() => props.urun?.options ?? [])

/*
 | ★ VARYANT EKLEME KOŞULU (4.5P).
 |
 | ⚠️ Gerçek kullanımda bulundu: eksen seçilip KAYDEDİLMEDEN "Ekle"ye
 | basılıyordu. Ekranda eksen seçicisi yok (ürünün kayıtlı ekseni yok),
 | boş `options` gidiyor ve ürün eksensiz bir varyant kazanıyordu —
 | sonra eksen ARTIK KİLİTLİ (varyant var) ve marka çıkmaza giriyordu.
 |
 | ⚠️ Ayrıca kayıtlı eksen VARSA her biri için değer seçilmiş olmalı;
 | boş seçim sunucuda "Her varyant ekseni için bir değer seçin" ile
 | dönüyordu ama düğmeyi baştan kapatmak markaya bir tur kazandırıyor.
 */
const eksenBekliyor = computed(
  () => (props.eksenler?.length ?? 0) > 0
    && urunEksenleri.value.length === 0
    && !props.urun?.eksen_kilitli,
)

const varyantEklenebilir = computed(() => {
  if (eksenBekliyor.value) return false

  return urunEksenleri.value.every((e) => (varyant.options[e.slug] ?? '') !== '')
})

/*
 | ★ KOLEKSİYON ÜYELİĞİ ÜRÜN TARAFINDAN (4.5L).
 |
 | ⚠️ Seçici koleksiyon AYRINTISINDA zaten vardı ve çalışıyordu — ama
 | marka onu ürün tarafından arıyordu ve bulamayınca "seçtirmiyor" dedi.
 | Aynı iş iki yerden yapılabiliyor; kural tek yerde (CollectionService).
 */
const uyeUuidleri = computed(() => new Set((props.urun?.koleksiyonlar ?? []).map((k) => k.uuid)))

function koleksiyonDegistir(uuid, ekle) {
  router.post(`/yonetim/urunler/${props.urun.uuid}/koleksiyon`, { collection_uuid: uuid, ekle })
}

const varyant = useForm({
  sku: '',
  price: '',
  stock: 0,
  barcode: '',
  is_active: true,
  options: {},
})

function varyantEkle() {
  /*
   | ⚠️ `options` DOLDURULUYOR: eksen seçicilerinden gelen eksen slug →
   | değer slug eşlemesi. Boş gönderilseydi ürünün tanımlı ekseni varken
   | "'renk' ekseni eksik" hatası alınırdı.
   */
  varyant.post(`/yonetim/urunler/${props.urun.uuid}/varyantlar`, {
    onSuccess: () => {
      varyant.reset()
      varyant.options = {}
    },
  })
}

function varyantSil(uuid) {
  router.delete(`/yonetim/urunler/${props.urun.uuid}/varyantlar/${uuid}`)
}

/* GÖRSELLER (4.5E) */
const gorsel = useForm({ image: null, alt: '' })

function gorselYukle() {
  /*
   | ⚠️ `forceFormData`: dosya gönderiliyor. Olmadan Inertia JSON
   | göndermeye çalışır ve dosya sunucuya HİÇ ULAŞMAZ.
   */
  gorsel.post(`/yonetim/urunler/${props.urun.uuid}/gorseller`, {
    forceFormData: true,
    onSuccess: () => gorsel.reset(),
  })
}

function gorselSil(uuid) {
  router.delete(`/yonetim/urunler/${props.urun.uuid}/gorseller/${uuid}`)
}

function durumDegistir(deger) {
  router.post(`/yonetim/urunler/${props.urun.uuid}/durum`, { status: deger })
}

function urunSil() {
  /*
   | ⚠️ Onay isteniyor. Silme geri alınamayan bir işlem ve tek tıkla
   | erişilebilir olması, yanlışlıkla silmeyi kaçınılmaz kılardı.
   */
  if (confirm('Bu ürün silinsin mi? Bu işlem geri alınamaz.')) {
    router.delete(`/yonetim/urunler/${props.urun.uuid}`)
  }
}
</script>

<template>
  <Head :title="yeniMi ? 'Yeni ürün' : urun.title" />

  <PanelDuzeni>
    <div class="flex items-center gap-3 mb-6">
      <Link href="/yonetim/urunler" class="text-sm text-stone-600 hover:text-orange-600">← Ürünler</Link>
      <h1 class="text-2xl font-bold">{{ yeniMi ? 'Yeni ürün' : urun.title }}</h1>

      <div v-if="!yeniMi" class="ml-auto flex items-center gap-2">
        <select
          class="rounded-lg border border-stone-300 px-3 py-2 text-sm"
          :value="urun.status"
          @change="durumDegistir($event.target.value)"
        >
          <option v-for="d in durumlar" :key="d.deger" :value="d.deger">{{ d.ad }}</option>
        </select>

        <button type="button" class="rounded-lg border border-red-300 text-red-700 px-3 py-2 text-sm" @click="urunSil">
          Sil
        </button>
      </div>
    </div>

    <form class="grid grid-cols-2 gap-6" @submit.prevent="kaydet">
      <div class="col-span-2 md:col-span-1 rounded-xl bg-white border border-stone-200 p-5">
        <h2 class="font-semibold mb-4">Ürün bilgileri</h2>

        <label class="block text-sm mb-3">
          Başlık
          <input v-model="form.title" type="text" required class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2">
          <span v-if="form.errors.title" class="text-red-700">{{ form.errors.title }}</span>
        </label>

        <label class="block text-sm mb-3">
          Açıklama
          <textarea v-model="form.description" rows="4" class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2" />
        </label>

        <div class="grid grid-cols-2 gap-3">
          <label class="block text-sm mb-3">
            Marka
            <input v-model="form.brand" type="text" class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2">
          </label>
          <label class="block text-sm mb-3">
            Model
            <input v-model="form.model" type="text" class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2">
          </label>
        </div>

        <label class="block text-sm mb-3">
          Kategori
          <select v-model="form.category_uuid" class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2">
            <option value="">— seçilmedi —</option>
            <option v-for="k in kategoriler" :key="k.uuid" :value="k.uuid">{{ k.name }}</option>
          </select>
        </label>

        <!-- ⚠️ Boş bırakılırsa mağaza varsayılanı uygulanıyor; "0" yazmakla
             aynı şey DEĞİL. Yer tutucu bunu söylüyor. -->
        <label class="block text-sm mb-4">
          KDV oranı (%)
          <input v-model="form.tax_rate" type="number" step="0.01" min="0" max="100"
                 placeholder="boş = mağaza varsayılanı"
                 class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2">
        </label>

        <button
          type="submit"
          class="rounded-lg bg-orange-600 text-white px-4 py-2 font-semibold disabled:opacity-60"
          :disabled="form.processing"
        >{{ yeniMi ? 'Oluştur' : 'Kaydet' }}</button>
      </div>

      <!--
        KOLEKSİYONLAR (4.5L) — elle seçilen koleksiyonlara üyelik.

        ⚠️ Kurallı koleksiyonlar burada YOK: orada üyelik sorgu anında
        hesaplanıyor (2D) ve elle ekleme "bu ürün neden burada"
        sorusunun iki cevabı olması demekti.
      -->
      <div v-if="!yeniMi && manuelKoleksiyonlar.length" class="col-span-2 rounded-xl bg-white border border-stone-200 p-5">
        <h2 class="font-semibold mb-3">Koleksiyonlar</h2>

        <div class="flex flex-wrap gap-3">
          <label v-for="k in manuelKoleksiyonlar" :key="k.uuid" class="flex items-center gap-2 text-sm">
            <input
              type="checkbox"
              :checked="uyeUuidleri.has(k.uuid)"
              @change="koleksiyonDegistir(k.uuid, $event.target.checked)"
            >
            {{ k.title }}
          </label>
        </div>
      </div>

      <!--
        EKSENLER (4.5L) — ürünün "Renk", "Beden" gibi varyant eksenleri.

        ⚠️ Eksen tanımlanamadığı sürece bir ürünün YALNIZCA TEK varyantı
        olabiliyordu: her varyantın `options` alanı boş kalıyor ve
        `(product_id, options)` benzersiz kısıtı ikinciyi reddediyordu.
      -->
      <div v-if="!yeniMi" class="col-span-2 rounded-xl bg-white border border-stone-200 p-5">
        <h2 class="font-semibold mb-1">Varyant eksenleri</h2>

        <p v-if="eksenler.length === 0" class="text-sm text-stone-600">
          Henüz eksen tanımlı değil.
          <Link href="/yonetim/katalog" class="text-orange-700">Katalog ayarlarından</Link>
          "Renk", "Beden" gibi eksenler ekleyin.
        </p>

        <template v-else>
          <!--
            ⚠️ Varyant varken eksen DEĞİŞTİRİLEMİYOR (1B): değiştirilseydi
            eldeki varyantlar anında geçersizleşir, ürün sayfasında
            seçilemez hâle gelir ve stok orada asılı kalırdı.
          -->
          <p v-if="urun.eksen_kilitli" class="text-sm text-stone-600 mb-2">
            Bu üründe varyant olduğu için eksenler kilitli.
            Değiştirmek için önce varyantları silin.
          </p>
          <p v-else class="text-sm text-stone-600 mb-2">
            Bu ürünün varyantları hangi eksenlere göre ayrışsın? Sıra önemlidir.
            <strong>En fazla {{ maksEksen }} eksen</strong> seçebilirsiniz.
          </p>

          <div class="flex flex-wrap gap-3 mb-3">
            <label v-for="e in eksenler" :key="e.uuid" class="flex items-center gap-2 text-sm">
              <input
                v-model="eksenForm.option_uuids"
                type="checkbox"
                :value="e.uuid"
                :disabled="urun.eksen_kilitli || (eksenSiniriDoldu && !eksenForm.option_uuids.includes(e.uuid))"
              >
              {{ e.name }}
              <span class="text-stone-500">({{ e.values.map((d) => d.value).join(', ') || 'değer yok' }})</span>
            </label>
          </div>

          <!-- ⚠️ Eksen seçilmeden kaydetmek anlamsız: sunucu kabul ediyor
               ama ekranda hiçbir şey değişmiyor ve marka düğmenin bozuk
               olduğunu sanıyordu. -->
          <!-- ⚠️ Sunucu hatası GÖRÜNMELİ: düz `router.post` ile 422 sessizce
               yutuluyordu ve marka "kaydettim ama bir şey olmadı" diyordu. -->
          <p v-for="(mesaj, alan) in eksenForm.errors" :key="alan" class="text-sm text-red-700 mb-2">
            {{ mesaj }}
          </p>

          <button
            v-if="!urun.eksen_kilitli"
            type="button"
            class="rounded-lg border border-stone-300 px-3 py-2 text-sm disabled:opacity-40 disabled:cursor-not-allowed"
            :disabled="!eksenSecildi || eksenForm.processing"
            @click="eksenleriKaydet"
          >Eksenleri kaydet</button>
        </template>
      </div>

      <div v-if="!yeniMi" class="col-span-2 rounded-xl bg-white border border-stone-200 p-5">
        <h2 class="font-semibold mb-3">Görseller</h2>

        <div v-if="urun.images.length" class="flex gap-3 flex-wrap mb-4">
          <div v-for="g in urun.images" :key="g.uuid" class="relative">
            <img :src="g.url" :alt="g.alt ?? ''" class="w-24 h-24 object-cover rounded-lg border border-stone-200">
            <button type="button" class="absolute top-1 right-1 rounded bg-white/90 px-1 text-xs text-red-700" @click="gorselSil(g.uuid)">
              sil
            </button>
          </div>
        </div>

        <!-- ⚠️ Görselsiz ürün vitrinde boş kare çıkıyor; uyarı gizlenmiyor. -->
        <p v-else class="text-sm text-amber-700 mb-4">Görsel yok — ürün vitrinde görselsiz görünür.</p>

        <div class="flex gap-2 items-center">
          <input type="file" accept="image/jpeg,image/png,image/webp" class="text-sm"
                 @input="gorsel.image = $event.target.files[0]">
          <input v-model="gorsel.alt" placeholder="Görsel açıklaması" class="rounded-lg border border-stone-300 px-3 py-2 text-sm">
          <button type="button" class="rounded-lg border border-stone-300 px-3 py-2 text-sm"
                  :disabled="!gorsel.image || gorsel.processing" @click="gorselYukle">
            Yükle
          </button>
        </div>

        <p v-if="gorsel.errors.image" class="text-sm text-red-700 mt-2">{{ gorsel.errors.image }}</p>
      </div>

      <div v-if="!yeniMi" class="col-span-2 md:col-span-1 rounded-xl bg-white border border-stone-200 p-5">
        <h2 class="font-semibold mb-1">Varyantlar</h2>

        <!-- ⚠️ Varyantsız ürün SATILAMAZ. Bunu gizlemek yerine yazıyoruz. -->
        <p v-if="urun.variants.length === 0" class="text-sm text-amber-700 mb-4">
          Varyant yok — bu ürün satılamaz.
        </p>

        <table v-else class="w-full text-sm mb-4">
          <tr v-for="v in urun.variants" :key="v.uuid" class="border-b border-stone-100">
            <td class="py-2">
              <code>{{ v.sku }}</code>
              <!-- ⚠️ Seçenekler GÖRÜNÜYOR: "Kırmızı / M" yazmadan marka
                   hangi satırın hangi varyant olduğunu ayırt edemezdi. -->
              <span v-if="Object.keys(v.options ?? {}).length" class="ml-2 text-stone-600">
                {{ Object.values(v.options).join(' / ') }}
              </span>
            </td>
            <td class="py-2">{{ Number(v.price).toLocaleString('tr-TR', { minimumFractionDigits: 2 }) }} TL</td>
            <td class="py-2">
              {{ v.stock }}
              <!-- ⚠️ BAĞLI stok ayrı gösteriliyor: ödemesi süren siparişlerin
                   rezervesi. Sadece toplam gösterilseydi marka "stok var"
                   sanıp satamadığı ürünü anlamazdı. -->
              <span v-if="v.committed > 0" class="text-stone-500">({{ v.committed }} bağlı)</span>
            </td>
            <td class="py-2 text-right">
              <button type="button" class="text-red-700" @click="varyantSil(v.uuid)">sil</button>
            </td>
          </tr>
        </table>

        <div class="border-t border-stone-200 pt-4">
          <h3 class="text-sm font-semibold mb-2">Varyant ekle</h3>

          <!--
            ⚠️ Her eksen için BİR değer isteniyor. Eksik bırakılırsa
            sunucu "'renk' ekseni eksik" diyor — ekran o hatayı hiç
            doğurmasın diye seçiciler burada.
          -->
          <div v-if="urunEksenleri.length" class="flex flex-wrap gap-2 mb-2">
            <label v-for="e in urunEksenleri" :key="e.uuid" class="text-sm">
              <span class="text-stone-600">{{ e.name }}</span>
              <select v-model="varyant.options[e.slug]" :disabled="eksenBekliyor"
                      class="ml-1 rounded-lg border border-stone-300 px-2 py-2 text-sm disabled:bg-stone-100">
                <option value="">— seçin —</option>
                <option v-for="d in e.values" :key="d.slug" :value="d.slug">{{ d.value }}</option>
              </select>
            </label>
          </div>

          <!--
            ⚠️ EKSEN KAYDEDİLMEDEN ALANLAR DA KAPALI (4.5S).

            Önce yalnızca "Ekle" düğmesi kapatılmıştı; marka SKU, fiyat ve
            stok kutularını yine dolduruyor, sonra düğmenin çalışmadığını
            görüyordu. Doldurduğu veri de eksenler kaydedilince
            sıfırlanıyordu — iki kez emek.
          -->
          <div class="grid grid-cols-3 gap-2 mb-2">
            <input v-model="varyant.sku" placeholder="SKU" :disabled="eksenBekliyor"
                   class="rounded-lg border border-stone-300 px-3 py-2 text-sm disabled:bg-stone-100 disabled:text-stone-400">
            <input v-model="varyant.price" type="number" step="0.01" min="0" placeholder="Fiyat" :disabled="eksenBekliyor"
                   class="rounded-lg border border-stone-300 px-3 py-2 text-sm disabled:bg-stone-100 disabled:text-stone-400">
            <input v-model="varyant.stock" type="number" min="0" placeholder="Stok" :disabled="eksenBekliyor"
                   class="rounded-lg border border-stone-300 px-3 py-2 text-sm disabled:bg-stone-100 disabled:text-stone-400">
          </div>

          <!-- ⚠️ Seçenek hatası GÖSTERİLMELİ: "bu birleşimde zaten varyant
               var" mesajı burada çıkmazsa marka neden eklenmediğini
               göremez — eskiden ham 500 alıyordu. -->
          <!--
            ⚠️ HATA ANAHTARI `options.renk` GİBİ — `options` değil. Yalnızca
            `errors.options` gösterildiği için sunucunun döndürdüğü uyarı
            ekranda HİÇ GÖRÜNMÜYORDU: marka düğmeye basıyor, hiçbir şey
            olmuyordu.
          -->
          <p v-for="(mesaj, alan) in varyant.errors" :key="alan" class="text-sm text-red-700 mb-2">
            {{ mesaj }}
          </p>

          <!--
            ⚠️ EKSEN KAYDEDİLMEDEN VARYANT EKLENEMEZ (4.5P).

            Marka eksenleri işaretleyip KAYDETMEDEN "Ekle"ye basıyordu:
            boş `options` gidiyor, ürün eksensiz bir varyant kazanıyor ve
            eksenler ARTIK KİLİTLENİYOR (varyant var) — marka çıkmaza
            giriyordu.
          -->
          <p v-if="eksenBekliyor" class="text-sm text-amber-700 mb-2">
            Önce eksenleri seçip <strong>“Eksenleri kaydet”</strong>e basın.
            Bu ürün eksensiz kalacaksa eksen seçmeden devam edebilirsiniz —
            ama varyant eklendikten sonra eksen değiştirilemez.
          </p>

          <button
            type="button"
            class="rounded-lg border border-stone-300 px-3 py-2 text-sm disabled:opacity-40 disabled:cursor-not-allowed"
            :disabled="varyant.processing || !varyantEklenebilir"
            @click="varyantEkle"
          >Ekle</button>
        </div>
      </div>

      <!-- ⚠️ Yeni üründe varyant paneli YOK: ürün kaydedilmeden varyant
           eklenemez. Boş bir panel göstermek "neden çalışmıyor" sorusunu
           doğururdu. -->
      <div v-else class="col-span-2 md:col-span-1 rounded-xl bg-stone-50 border border-dashed border-stone-300 p-5 text-sm text-stone-600">
        Varyantları ürünü oluşturduktan sonra ekleyeceksiniz.
      </div>
    </form>
  </PanelDuzeni>
</template>
