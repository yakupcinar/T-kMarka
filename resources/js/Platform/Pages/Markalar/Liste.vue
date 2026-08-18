<script setup>
import { ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import YonetimDuzeni from '../../Layouts/YonetimDuzeni.vue'

const props = defineProps({ markalar: Object, arama: String, durum: String, durumlar: Array })

const kelime = ref(props.arama ?? '')

function ara() {
  router.get('/yonetim/markalar', { q: kelime.value || undefined, durum: props.durum || undefined }, { preserveState: true })
}
function suz(d) {
  router.get('/yonetim/markalar', { q: props.arama || undefined, durum: d || undefined }, { preserveState: true })
}
function tarih(v) { return v ? new Date(v).toLocaleDateString('tr-TR') : '—' }

const durumRengi = {
  trial: 'bg-blue-100 text-blue-800', active: 'bg-green-100 text-green-800',
  past_due: 'bg-amber-100 text-amber-800', suspended: 'bg-red-100 text-red-800',
  closed: 'bg-slate-200 text-slate-700', provisioning: 'bg-slate-100 text-slate-600',
}
</script>

<template>
  <Head title="Markalar" />

  <YonetimDuzeni>
    <div class="flex items-center gap-3 mb-6">
      <h1 class="text-2xl font-bold">Markalar</h1>

      <form class="ml-auto flex gap-2" @submit.prevent="ara">
        <input v-model="kelime" type="search" placeholder="Marka ara" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
        <button type="submit" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm">Ara</button>
      </form>

      <select class="rounded-lg border border-slate-300 px-3 py-2 text-sm" :value="durum ?? ''" @change="suz($event.target.value)">
        <option value="">Tüm durumlar</option>
        <option v-for="d in durumlar" :key="d.deger" :value="d.deger">{{ d.ad }}</option>
      </select>
    </div>

    <div v-if="markalar.data.length === 0" class="rounded-xl bg-white border border-slate-200 p-10 text-center text-slate-600">
      Marka bulunamadı.
    </div>

    <table v-else class="w-full bg-white rounded-xl border border-slate-200 overflow-hidden">
      <thead class="bg-slate-50 text-left text-sm text-slate-600">
        <tr><th class="p-3">Marka</th><th class="p-3">Durum</th><th class="p-3">Plan</th><th class="p-3">Deneme bitişi</th><th class="p-3">Açılış</th></tr>
      </thead>
      <tbody>
        <tr v-for="m in markalar.data" :key="m.id" class="border-t border-slate-100">
          <td class="p-3">
            <Link :href="`/yonetim/markalar/${m.id}`" class="font-medium hover:underline">{{ m.name }}</Link>
            <div class="text-xs text-slate-500"><code>{{ m.id }}</code></div>
          </td>
          <td class="p-3"><span class="rounded-full px-2 py-0.5 text-xs" :class="durumRengi[m.status]">{{ m.status }}</span></td>
          <td class="p-3 text-sm">{{ m.plan ?? '—' }}</td>
          <td class="p-3 text-sm">{{ tarih(m.trial_ends_at) }}</td>
          <td class="p-3 text-sm">{{ tarih(m.created_at) }}</td>
        </tr>
      </tbody>
    </table>

    <div v-if="markalar.links.length > 3" class="mt-4 flex gap-1 text-sm">
      <Link v-for="b in markalar.links" :key="b.label" :href="b.url ?? ''"
            class="rounded border border-slate-300 px-3 py-1 bg-white"
            :class="{ 'bg-slate-900 text-white border-slate-900': b.active, 'opacity-40 pointer-events-none': !b.url }"
            v-html="b.label" />
    </div>
  </YonetimDuzeni>
</template>
