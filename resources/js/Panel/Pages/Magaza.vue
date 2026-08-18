<script setup>
/*
 | Mağaza bilgileri ve yayına alma. (4H)
 |
 | ⚠️ Mağaza KAPALI doğuyor: altı zorunlu bilgi + üç yasal metin
 | tamamlanmadan satış açılamıyor (1A.4). Eksikler TEK SEFERDE
 | gösteriliyor — tek tek bildirilseydi marka altı kez tur atardı.
 */
import { useForm, Head, router } from '@inertiajs/vue3'
import PanelDuzeni from '../Layouts/PanelDuzeni.vue'

const props = defineProps({ ayarlar: Object, zorunlular: Array, eksikler: Array, yayinda: Boolean })

const form = useForm({
  name: props.ayarlar.name ?? '',
  legal_name: props.ayarlar.legal_name ?? '',
  tax_number: props.ayarlar.tax_number ?? '',
  tax_office: props.ayarlar.tax_office ?? '',
  address: props.ayarlar.address ?? '',
  phone: props.ayarlar.phone ?? '',
  contact_email: props.ayarlar.contact_email ?? '',
})

const etiket = {
  name: 'Mağaza adı', legal_name: 'Ticari unvan', tax_number: 'Vergi / TC no',
  tax_office: 'Vergi dairesi', address: 'Adres', phone: 'Telefon', contact_email: 'İletişim e-postası',
}

function kaydet() { form.post('/yonetim/magaza') }
function yayinla() { router.post('/yonetim/magaza/yayinla') }
function kapat() {
  if (confirm('Mağaza satışa kapatılsın mı? Vitrin ziyaretçilere kapalı görünecek.')) {
    router.post('/yonetim/magaza/kapat')
  }
}
</script>

<template>
  <Head title="Mağaza" />

  <PanelDuzeni>
    <div class="flex items-center gap-3 mb-6">
      <h1 class="text-2xl font-bold">Mağaza</h1>
      <span class="rounded-full px-3 py-1 text-xs" :class="yayinda ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-800'">
        {{ yayinda ? 'Satışa açık' : 'Kapalı' }}
      </span>
    </div>

    <div class="grid grid-cols-3 gap-6">
      <form class="col-span-2 rounded-xl bg-white border border-stone-200 p-5" @submit.prevent="kaydet">
        <h2 class="font-semibold mb-4">Bilgiler</h2>

        <label v-for="(ad, anahtar) in etiket" :key="anahtar" class="block text-sm mb-3">
          {{ ad }}
          <!-- ⚠️ Zorunlu alanlar İŞARETLİ: hangisinin sözleşmeye gireceğini
               marka bilmeden dolduramaz. -->
          <span v-if="zorunlular.includes(anahtar)" class="text-red-600">*</span>
          <input v-model="form[anahtar]" type="text" class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2">
          <span v-if="form.errors[anahtar]" class="text-red-700">{{ form.errors[anahtar] }}</span>
        </label>

        <p class="text-xs text-stone-500 mb-4">
          <span class="text-red-600">*</span> işaretli alanlar mesafeli satış sözleşmesine giriyor.
        </p>

        <button type="submit" class="rounded-lg bg-orange-600 text-white px-4 py-2 font-semibold disabled:opacity-60" :disabled="form.processing">
          Kaydet
        </button>
      </form>

      <aside class="rounded-xl bg-white border border-stone-200 p-5">
        <h2 class="font-semibold text-sm mb-3">Yayın durumu</h2>

        <!-- ⚠️ Eksikler TEK SEFERDE listeleniyor, ilk eksikte durulmuyor. -->
        <div v-if="eksikler.length" class="mb-4">
          <p class="text-sm text-amber-800 mb-2">Yayına almak için eksikler:</p>
          <ul class="text-sm text-stone-700 list-disc pl-5">
            <li v-for="e in eksikler" :key="e">{{ etiket[e] ?? e }}</li>
          </ul>
        </div>

        <template v-if="yayinda">
          <p class="text-sm text-stone-600 mb-3">Mağazanız satışa açık.</p>
          <button type="button" class="w-full rounded-lg border border-red-300 text-red-700 py-2 text-sm" @click="kapat">
            Satışa kapat
          </button>
        </template>

        <template v-else>
          <!-- ⚠️ Düğme eksik varken de GÖSTERİLİYOR ama sunucu reddediyor
               ve sebebini yazıyor. Gizlemek, markaya neden açamadığını
               söylemeden yolu kapatmak olurdu. -->
          <button type="button" class="w-full rounded-lg bg-orange-600 text-white py-2 text-sm font-semibold" @click="yayinla">
            Yayına al
          </button>
        </template>
      </aside>
    </div>
  </PanelDuzeni>
</template>
