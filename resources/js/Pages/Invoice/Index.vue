<script setup>
import { Head, Link } from '@inertiajs/vue3';
import PanelLayout from '@/Layouts/PanelLayout.vue';

defineProps({
  invoices: Object,
  start: Number
});

function formatLabel(label) {
  if (label === 'pagination.previous') return '← Предыдущая';
  if (label === 'pagination.next') return 'Следующая →';
  return label;
}
</script>

<template>
  <PanelLayout>
    <Head title="Счета" />
    <div class="p-4">
      <h1 class="text-2xl font-bold mb-6 text-gray-900 dark:text-white">Список счетов</h1>

      <ul class="mb-6 divide-y divide-gray-200 dark:divide-gray-700 rounded-md overflow-hidden shadow-sm">
        <li
            v-for="(inv, idx) in invoices.data"
            :key="inv.id"
            :class="[
            'p-4 transition-colors',
            idx % 2 === 0
              ? 'bg-gray-50 dark:bg-gray-800'
              : 'bg-white dark:bg-gray-900'
          ]"
        >
          <span class="font-mono text-gray-500 dark:text-gray-400 mr-2">{{ start + idx }}.</span>
          <Link
              :href="route('invoices-show', inv.id)"
              class="text-blue-600 dark:text-blue-400 hover:underline"
          >
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
            'px-3 py-1 border rounded text-sm transition-colors',
            link.active
              ? 'bg-blue-600 text-white dark:bg-blue-500'
              : 'text-gray-700 dark:text-gray-200 border-gray-300 dark:border-gray-600 hover:bg-gray-100 dark:hover:bg-gray-700',
            !link.url && 'pointer-events-none opacity-50'
          ]"
        >
          {{ formatLabel(link.label) }}
        </Link>
      </div>
    </div>
  </PanelLayout>
</template>
