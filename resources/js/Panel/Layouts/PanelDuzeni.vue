<script setup>
/*
 | Panel düzeni — üst bar, menü, bildirim. (4C)
 |
 | ⚠️ MENÜ İZNE GÖRE ÇİZİLİYOR ama bu YETKİ DEĞİL (4C-K4). Kullanıcı
 | adresi elle yazarsa yine sunucudaki `izin:` middleware'i durduruyor.
 | Buradaki filtre yalnızca kullanamayacağı bir bağlantıyı göstermemek için.
 */
import { computed } from 'vue'
import { Link, usePage, router } from '@inertiajs/vue3'

const sayfa = usePage()

const kullanici = computed(() => sayfa.props.auth?.user ?? null)
const izinler = computed(() => sayfa.props.auth?.permissions ?? [])
const marka = computed(() => sayfa.props.marka?.ad ?? 'Panel')
const bildirim = computed(() => sayfa.props.bildirim ?? {})

/* Menü — her maddenin gerektirdiği izin yanında yazılı. */
const menu = [
  { ad: 'Pano', yol: '/yonetim', izin: null },
  { ad: 'Ürünler', yol: '/yonetim/urunler', izin: 'product.write' },
  { ad: 'Siparişler', yol: '/yonetim/siparisler', izin: 'order.view' },
  { ad: 'İadeler', yol: '/yonetim/iadeler', izin: 'order.view' },
  { ad: 'Tema', yol: '/yonetim/tema', izin: 'settings.write' },
]

const gorunenMenu = computed(() =>
  menu.filter((m) => m.izin === null || izinler.value.includes(m.izin)),
)

function cikisYap() {
  router.post('/yonetim/cikis')
}
</script>

<template>
  <div class="min-h-screen bg-stone-100 text-stone-900">
    <header class="bg-white border-b border-stone-200">
      <div class="mx-auto max-w-6xl px-5 py-3 flex items-center gap-6">
        <Link href="/yonetim" class="font-black tracking-tight">
          {{ marka }} <span class="text-orange-600">Panel</span>
        </Link>

        <nav class="flex gap-4 text-sm">
          <Link
            v-for="madde in gorunenMenu"
            :key="madde.yol"
            :href="madde.yol"
            class="hover:text-orange-600"
          >{{ madde.ad }}</Link>
        </nav>

        <div class="ml-auto flex items-center gap-3 text-sm">
          <span v-if="kullanici" class="text-stone-600">{{ kullanici.name }}</span>
          <button
            type="button"
            class="rounded-lg border border-stone-300 px-3 py-1 hover:bg-stone-50"
            @click="cikisYap"
          >Çıkış</button>
        </div>
      </div>
    </header>

    <main class="mx-auto max-w-6xl px-5 py-8">
      <p v-if="bildirim.mesaj" class="mb-4 rounded-lg bg-green-100 border border-green-300 px-4 py-3">
        {{ bildirim.mesaj }}
      </p>
      <p v-if="bildirim.hata" class="mb-4 rounded-lg bg-red-100 border border-red-300 px-4 py-3">
        {{ bildirim.hata }}
      </p>

      <slot />
    </main>
  </div>
</template>
