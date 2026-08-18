<script setup>
/*
 | İade talebi ayrıntısı. (4E)
 |
 | ⚠️ Karar düğmeleri `order.refund` izni yoksa gizleniyor — kolaylık.
 | Gerçek koruma sunucudaki `izin:order.refund`.
 */
import { computed, ref } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import PanelDuzeni from '../../Layouts/PanelDuzeni.vue'

const props = defineProps({ talep: Object })

const izinler = computed(() => usePage().props.auth?.permissions ?? [])
const kararVerebilir = computed(() => izinler.value.includes('order.refund'))

const not = ref('')
const stogaKoy = ref(false)

const y = (yol, veri = {}) => router.post(`/yonetim/iadeler/${props.talep.uuid}/${yol}`, veri)

function para(v) { return Number(v).toLocaleString('tr-TR', { minimumFractionDigits: 2 }) + ' TL' }
const durumAdi = {
  requested: 'Talep edildi', approved: 'Onaylandı', rejected: 'Reddedildi',
  received: 'Teslim alındı', completed: 'Tamamlandı',
}
</script>

<template>
  <Head title="İade talebi" />

  <PanelDuzeni>
    <div class="flex items-center gap-3 mb-6">
      <Link href="/yonetim/iadeler" class="text-sm text-stone-600 hover:text-orange-600">← İadeler</Link>
      <h1 class="text-2xl font-bold">{{ talep.order_number }}</h1>
      <span class="rounded-full bg-stone-200 px-2 py-0.5 text-xs">{{ durumAdi[talep.status] ?? talep.status }}</span>
    </div>

    <div class="grid grid-cols-3 gap-6">
      <div class="col-span-2 rounded-xl bg-white border border-stone-200 p-5">
        <h2 class="font-semibold mb-3">İade edilen ürünler</h2>
        <table class="w-full text-sm mb-4">
          <tr v-for="(s, i) in talep.items" :key="i" class="border-b border-stone-100">
            <td class="py-2">{{ s.title }} <code class="text-stone-500">{{ s.sku }}</code></td>
            <td class="py-2 text-right">{{ s.quantity }} adet</td>
          </tr>
        </table>

        <p class="text-sm"><strong>Tür:</strong> {{ talep.is_withdrawal ? 'Cayma hakkı' : 'Ayıplı ürün' }}</p>
        <p v-if="talep.reason" class="text-sm mt-1"><strong>Müşteri notu:</strong> {{ talep.reason }}</p>
        <p v-if="talep.decision_note" class="text-sm mt-1"><strong>Karar notu:</strong> {{ talep.decision_note }}</p>

        <div v-if="talep.refunds.length" class="mt-4 border-t border-stone-200 pt-3">
          <h3 class="font-semibold text-sm mb-2">Yapılan para iadeleri</h3>
          <div v-for="r in talep.refunds" :key="r.uuid" class="text-sm text-stone-600">
            {{ para(r.amount) }} — {{ r.status }}
          </div>
        </div>
      </div>

      <aside v-if="kararVerebilir" class="rounded-xl bg-white border border-stone-200 p-5 space-y-3">
        <h2 class="font-semibold">İşlemler</h2>

        <template v-if="talep.status === 'requested'">
          <button type="button" class="w-full rounded-lg bg-orange-600 text-white py-2 text-sm font-semibold" @click="y('onayla')">Onayla</button>
          <input v-model="not" placeholder="Ret sebebi" class="w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">
          <button type="button" class="w-full rounded-lg border border-red-300 text-red-700 py-2 text-sm" @click="y('reddet', { note: not })">Reddet</button>
        </template>

        <template v-else-if="talep.status === 'approved'">
          <!-- ⚠️ STOĞA GERİ KOYMA VARSAYILAN KAPALI (2B): iade edilen ürün
               hasarlı olabilir. Karar personelin, otomatik değil. -->
          <label class="flex items-center gap-2 text-sm">
            <input v-model="stogaKoy" type="checkbox"> Stoğa geri koy
          </label>
          <button type="button" class="w-full rounded-lg bg-orange-600 text-white py-2 text-sm font-semibold" @click="y('teslim-al', { restock: stogaKoy })">
            Teslim alındı
          </button>
        </template>

        <template v-else-if="talep.status === 'received'">
          <button type="button" class="w-full rounded-lg bg-orange-600 text-white py-2 text-sm font-semibold" @click="y('para-iadesi')">
            Para iadesini yap
          </button>
        </template>

        <p v-else class="text-sm text-stone-600">Bu talep için yapılacak işlem kalmadı.</p>
      </aside>

      <aside v-else class="rounded-xl bg-stone-50 border border-dashed border-stone-300 p-5 text-sm text-stone-600">
        İade kararı vermek için yetkiniz yok.
      </aside>
    </div>
  </PanelDuzeni>
</template>
