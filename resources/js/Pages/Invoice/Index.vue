<script setup>
import { Head, Link } from '@inertiajs/vue3';
import PanelLayout from '@/Layouts/PanelLayout.vue';

defineProps({
  invoices: Object,
  start: Number
});
function formatLabel(label) {
  if (label === 'pagination.previous') return 'Предыдущая';
  if (label === 'pagination.next') return 'Следующая';
  return label;
}
</script>

<template>
  <PanelLayout>
    <Head title="Счета" />
    <div>
      <h1 class="text-2xl font-bold mb-4">Список счетов</h1>
      <ul class="mb-6 divide-y divide-gray-200">
        <li
            v-for="(inv, idx) in invoices.data"
            :key="inv.id"
            :class="idx % 2 === 0 ? 'bg-gray-50' : 'bg-white'"
            class="p-3"
        >
          <span class="font-mono text-gray-500 mr-2">{{ start + idx }}.</span>
          <Link :href="route('invoices-show', inv.id)" class="text-blue-600 hover:underline">
            Счёт #{{ inv.id }} — {{ inv.amount }} ₽
          </Link>
        </li>
      </ul>

      <!-- Пагинация -->
      <div class="flex flex-wrap gap-2">
        <Link
            v-for="(link, index) in invoices.links"
            :key="index"
            :href="link.url"
            :class="[
              'px-3 py-1 border rounded text-sm',
              link.active ? 'bg-blue-500 text-white' : 'text-gray-700 hover:bg-gray-100',
              !link.url ? 'pointer-events-none opacity-50' : ''
            ]"
        >
          {{ formatLabel(link.label) }}
        </Link>
      </div>
    </div>
  </PanelLayout>
</template>
