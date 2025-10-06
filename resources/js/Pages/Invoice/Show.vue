<script setup>
import { Head, Link } from '@inertiajs/vue3';
import PanelLayout from '@/Layouts/PanelLayout.vue';

defineProps({ invoice: Object });
</script>

<template>
  <PanelLayout>
    <Head title="Счёт" />
    <div class="p-4">
      <Link
          :href="route('invoices-index')"
          class="mb-4 inline-block text-blue-600 dark:text-blue-400 hover:underline"
      >
        ← Назад
      </Link>

      <h1 class="text-xl font-bold mb-2 text-gray-900 dark:text-white">Счёт #{{ invoice.id }}</h1>
      <p class="text-gray-700 dark:text-gray-300">Статус: {{ invoice.status }}</p>
      <p class="text-gray-700 dark:text-gray-300">Сумма: {{ invoice.amount }} ₽</p>
    </div>
  </PanelLayout>
</template>
