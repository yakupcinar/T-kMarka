<script setup>
import { ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import YonetimDuzeni from '../../Layouts/YonetimDuzeni.vue'

const props = defineProps({ marka: Object, planlar: Array, durumlar: Array })

const yeniDurum = ref(props.marka.status)
const yeniPlan = ref('')

function durumDegistir() {
  router.post(`/yonetim/markalar/${props.marka.id}/durum`, { status: yeniDurum.value })
}
function planAta() {
  if (yeniPlan.value) router.post(`/yonetim/markalar/${props.marka.id}/plan`, { plan_id: yeniPlan.value })
}
function tarih(v) { return v ? new Date(v).toLocaleString('tr-TR') : '—' }
</script>

<template>
  <Head :title="marka.name" />

  <YonetimDuzeni>
    <div class="flex items-center gap-3 mb-6">
      <Link href="/yonetim/markalar" class="text-sm text-slate-600 hover:underline">← Markalar</Link>
      <h1 class="text-2xl font-bold">{{ marka.name }}</h1>
      <span class="rounded-full bg-slate-200 px-2 py-0.5 text-xs">{{ marka.status }}</span>
    </div>

    <div class="grid grid-cols-3 gap-6">
      <div class="col-span-2 space-y-6">
        <div class="rounded-xl bg-white border border-slate-200 p-5 text-sm">
          <h2 class="font-semibold mb-3">Yaşam döngüsü</h2>
          <div class="grid grid-cols-2 gap-y-2">
            <span class="text-slate-600">Deneme bitişi</span><span>{{ tarih(marka.trial_ends_at) }}</span>
            <span class="text-slate-600">Nezaket süresi</span><span>{{ tarih(marka.grace_ends_at) }}</span>
            <span class="text-slate-600">Askıya alındı</span><span>{{ tarih(marka.suspended_at) }}</span>
            <span class="text-slate-600">Kapatıldı</span><span>{{ tarih(marka.closed_at) }}</span>
            <span class="text-slate-600">Abonelik ref</span><span><code>{{ marka.subscription_ref ?? '—' }}</code></span>
          </div>
        </div>

        <div class="rounded-xl bg-white border border-slate-200 p-5 text-sm">
          <h2 class="font-semibold mb-3">Alan adları</h2>
          <div v-for="d in marka.domains" :key="d.domain" class="flex items-center gap-2 py-1">
            <code>{{ d.domain }}</code>
            <!-- ⚠️ Doğrulanmamış alan adı AÇIKÇA işaretleniyor: on-demand
                 TLS yalnızca doğrulanmışa sertifika alıyor (3H). -->
            <span v-if="d.verified" class="text-green-700 text-xs">doğrulandı</span>
            <span v-else class="text-amber-700 text-xs">doğrulanmadı</span>
          </div>
        </div>
      </div>

      <aside class="space-y-6">
        <div class="rounded-xl bg-white border border-slate-200 p-5 space-y-2">
          <h2 class="font-semibold text-sm">Durum değiştir</h2>
          <select v-model="yeniDurum" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <option v-for="d in durumlar" :key="d.deger" :value="d.deger">{{ d.ad }}</option>
          </select>
          <button type="button" class="w-full rounded-lg bg-slate-900 text-white py-2 text-sm" @click="durumDegistir">Uygula</button>
          <!-- ⚠️ Geçerli olmayan geçişler sunucuda reddediliyor (3C):
               "kapatılmış markayı askıya al" gibi bir hamle burada
               seçilebilir ama sunucudan geçmez. -->
          <p class="text-xs text-slate-500">Geçersiz geçişler sunucuda reddedilir.</p>
        </div>

        <div class="rounded-xl bg-white border border-slate-200 p-5 space-y-2">
          <h2 class="font-semibold text-sm">Plan</h2>
          <p class="text-sm text-slate-600">Şu an: {{ marka.plan ?? '—' }}</p>
          <select v-model="yeniPlan" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <option value="">— seçin —</option>
            <option v-for="p in planlar" :key="p.id" :value="p.id">{{ p.name }}</option>
          </select>
          <button type="button" class="w-full rounded-lg border border-slate-300 py-2 text-sm" @click="planAta">Ata</button>
        </div>

        <div class="rounded-xl bg-white border border-slate-200 p-5">
          <h2 class="font-semibold text-sm mb-2">Veriyi dışa aktar</h2>

          <!-- ⚠️ KVKK: veri işleyen, sözleşme bitince veriyi İADE EDİP
               siler. Silme 3G'de vardı, İADE bu blokta geldi. -->
          <p class="text-xs text-slate-600 mb-3">
            Markanın tüm verisi JSON olarak iner. Oturum jetonları dâhil edilmez.
          </p>

          <!-- ⚠️ Inertia `Link` DEĞİL düz `<a>`: Inertia bağlantısı XHR
               yapar ve dosya inmez, sayfa sessizce hiçbir şey yapmaz. -->
          <a :href="`/yonetim/markalar/${marka.id}/disa-aktar`"
             class="block text-center rounded-lg border border-slate-300 py-2 text-sm">
            JSON indir
          </a>
        </div>
      </aside>
    </div>
  </YonetimDuzeni>
</template>
