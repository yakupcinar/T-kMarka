<script setup>
/*
 | Ürün oluşturma / düzenleme. (4D)
 |
 | ⚠️ Tek bileşen iki iş yapıyor: `urun === null` ise oluşturma, değilse
 | düzenleme. İki ayrı dosya olsaydı alan listesi iki yerde tutulurdu ve
 | biri güncellenmeden kalırdı.
 */
import { useForm, Head, Link, router } from '@inertiajs/vue3'
import PanelDuzeni from '../../Layouts/PanelDuzeni.vue'

const props = defineProps({
  urun: Object,
  kategoriler: Array,
  durumlar: Array,
})

const yeniMi = props.urun === null

const form = useForm({
  title: props.urun?.title ?? '',
  description: props.urun?.description ?? '',
  brand: props.urun?.brand ?? '',
  model: props.urun?.model ?? '',
  tax_rate: props.urun?.tax_rate ?? '',
  category_uuid: props.urun?.category_uuid ?? '',
})

function kaydet() {
  if (yeniMi) {
    form.post('/yonetim/urunler')
  } else {
    form.put(`/yonetim/urunler/${props.urun.uuid}`)
  }
}

/* Varyant formu — ayrı, çünkü ürün kaydedilmeden varyant eklenemez. */
const varyant = useForm({
  sku: '',
  price: '',
  stock: 0,
  barcode: '',
  is_active: true,
  options: {},
})

function varyantEkle() {
  varyant.post(`/yonetim/urunler/${props.urun.uuid}/varyantlar`, {
    onSuccess: () => varyant.reset(),
  })
}

function varyantSil(uuid) {
  router.delete(`/yonetim/urunler/${props.urun.uuid}/varyantlar/${uuid}`)
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

      <div v-if="!yeniMi" class="col-span-2 md:col-span-1 rounded-xl bg-white border border-stone-200 p-5">
        <h2 class="font-semibold mb-1">Varyantlar</h2>

        <!-- ⚠️ Varyantsız ürün SATILAMAZ. Bunu gizlemek yerine yazıyoruz. -->
        <p v-if="urun.variants.length === 0" class="text-sm text-amber-700 mb-4">
          Varyant yok — bu ürün satılamaz.
        </p>

        <table v-else class="w-full text-sm mb-4">
          <tr v-for="v in urun.variants" :key="v.uuid" class="border-b border-stone-100">
            <td class="py-2"><code>{{ v.sku }}</code></td>
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

          <div class="grid grid-cols-3 gap-2 mb-2">
            <input v-model="varyant.sku" placeholder="SKU" class="rounded-lg border border-stone-300 px-3 py-2 text-sm">
            <input v-model="varyant.price" type="number" step="0.01" min="0" placeholder="Fiyat" class="rounded-lg border border-stone-300 px-3 py-2 text-sm">
            <input v-model="varyant.stock" type="number" min="0" placeholder="Stok" class="rounded-lg border border-stone-300 px-3 py-2 text-sm">
          </div>

          <p v-if="varyant.errors.sku" class="text-sm text-red-700 mb-2">{{ varyant.errors.sku }}</p>
          <p v-if="varyant.errors.price" class="text-sm text-red-700 mb-2">{{ varyant.errors.price }}</p>

          <button
            type="button"
            class="rounded-lg border border-stone-300 px-3 py-2 text-sm"
            :disabled="varyant.processing"
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
