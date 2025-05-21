<script setup>
import { Head } from '@inertiajs/vue3';
import PanelLayout from '@/Layouts/PanelLayout.vue';

defineProps({ invoice: Object });

function goBack() {
  window.history.back();
}
</script>

<template>
  <PanelLayout>
    <Head title="Счёт" />
    <div>
      <a :href="route('invoices-index')" class="mb-4 text-blue-600 hover:underline">
        ← Назад
      </a>
      <h1 class="text-xl font-bold mb-2">Счёт #{{ invoice.id }}</h1>
      <p>Статус: {{ invoice.status }}</p>
      <p>Сумма: {{ invoice.amount }} ₽</p>
    </div>
  </PanelLayout>
</template>
