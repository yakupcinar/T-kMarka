<script setup>
/*
 | Tema ayarları. (4G) — 4-K5'in arayüzü.
 |
 | ⚠️ SERBEST METİN ALANI YOK (özel CSS/HTML). Blade PHP'dir ve kum havuzu
 | yoktur; markanın yazdığı şablonu render etmek şema bazlı kiracılıkta
 | BÜTÜN markaların verisini riske atardı. Marka SEÇER, yazmaz.
 */
import { useForm, Head, router } from '@inertiajs/vue3'
import PanelDuzeni from '../Layouts/PanelDuzeni.vue'

const props = defineProps({ tema: Object, secenekler: Object, varsayilan_renk: String })

const form = useForm({
  renk: props.tema.renk,
  yazi_tipi: props.tema.yazi_tipi,
  duzen: props.tema.duzen,
})

const yaziTipiAdi = { sistem: 'Sistem yazı tipi', serif: 'Serif', mono: 'Tek aralıklı' }
const duzenAdi = { sade: 'Sade', vitrinli: 'Vitrinli (karşılama bölümü)' }

function kaydet() { form.post('/yonetim/tema') }

const logoFormu = useForm({ logo: null })

function logoYukle(olay) {
  const dosya = olay.target.files?.[0]
  if (!dosya) return
  logoFormu.logo = dosya
  logoFormu.post('/yonetim/tema/logo', { forceFormData: true, onSuccess: () => logoFormu.reset() })
}

function logoKaldir() { router.delete('/yonetim/tema/logo') }
</script>

<template>
  <Head title="Tema" />

  <PanelDuzeni>
    <h1 class="text-2xl font-bold mb-6">Tema</h1>

    <div class="grid grid-cols-3 gap-6">
      <form class="col-span-2 rounded-xl bg-white border border-stone-200 p-5" @submit.prevent="kaydet">
        <h2 class="font-semibold mb-4">Görünüm</h2>

        <label class="block text-sm mb-4">
          Marka rengi
          <div class="mt-1 flex items-center gap-3">
            <input v-model="form.renk" type="color" class="h-10 w-16 rounded border border-stone-300">
            <input v-model="form.renk" type="text" class="rounded-lg border border-stone-300 px-3 py-2 w-32">
            <button type="button" class="text-sm text-stone-600 underline" @click="form.renk = varsayilan_renk">
              varsayılana dön
            </button>
          </div>
          <span v-if="form.errors.renk" class="text-sm text-red-700">{{ form.errors.renk }}</span>
        </label>

        <!-- ⚠️ Seçenekler SUNUCUDAN geliyor: arayüze sabit yazılsaydı listeye
             eklenen bir seçenek panelde görünmez, kaldırılan bir seçenek
             ise panelde durur ve kaydedince sessizce varsayılana düşerdi. -->
        <label class="block text-sm mb-4">
          Yazı tipi
          <select v-model="form.yazi_tipi" class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2">
            <option v-for="y in secenekler.yazi_tipleri" :key="y" :value="y">{{ yaziTipiAdi[y] ?? y }}</option>
          </select>
        </label>

        <label class="block text-sm mb-5">
          Vitrin düzeni
          <select v-model="form.duzen" class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2">
            <option v-for="d in secenekler.duzenler" :key="d" :value="d">{{ duzenAdi[d] ?? d }}</option>
          </select>
        </label>

        <button type="submit" class="rounded-lg bg-orange-600 text-white px-4 py-2 font-semibold disabled:opacity-60" :disabled="form.processing">
          Kaydet
        </button>
      </form>

      <aside class="space-y-6">
        <div class="rounded-xl bg-white border border-stone-200 p-5">
          <h2 class="font-semibold text-sm mb-3">Logo</h2>

          <div v-if="tema.logo" class="mb-3">
            <img :src="tema.logo" alt="Logo" class="max-h-16">
          </div>
          <!-- ⚠️ Logo boşken YER TUTUCU GÖRSEL konmuyor (4A): marka onu
               değiştirmeyi unutur ve mağazasını başkasının logosuyla
               açardı. Boşken vitrin mağaza adını yazıyor. -->
          <p v-else class="text-sm text-stone-600 mb-3">Logo yok — vitrinde mağaza adı yazıyor.</p>

          <input type="file" accept="image/jpeg,image/png,image/webp" class="text-sm" @change="logoYukle">
          <p class="text-xs text-stone-500 mt-2">JPEG, PNG veya WebP · en fazla 2 MB</p>
          <!-- ⚠️ SVG kabul edilmiyor: XML belgesidir ve betik taşıyabilir. -->

          <button v-if="tema.logo" type="button" class="mt-3 text-sm text-red-700" @click="logoKaldir">Logoyu kaldır</button>
        </div>

        <div class="rounded-xl bg-white border border-stone-200 p-5">
          <h2 class="font-semibold text-sm mb-2">Önizleme</h2>
          <p class="text-xs text-stone-600 mb-3">Değişiklikler kaydedildikten sonra vitrinde görünür.</p>
          <!-- ⚠️ Inertia `Link` DEĞİL düz `<a>` + yeni sekme: vitrin ayrı
               bir uygulama (Blade), Inertia gezinmesi onu yükleyemez. -->
          <a href="/" target="_blank" rel="noopener" class="block text-center rounded-lg border border-stone-300 py-2 text-sm">
            Vitrini aç
          </a>
        </div>
      </aside>
    </div>
  </PanelDuzeni>
</template>
