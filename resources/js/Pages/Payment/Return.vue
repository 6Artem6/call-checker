<script setup>
import { Head } from '@inertiajs/vue3';
import PanelLayout from '@/Layouts/PanelLayout.vue';

defineProps({ status: String });
</script>

<template>
  <PanelLayout>
    <Head title="Создание оплаты" />
    <div class="bg-white">
      <h1 class="text-2xl font-bold mb-4">Статус оплаты</h1>
      <p class="text-lg">
        {{ status === 'succeeded' ? 'Платёж прошёл успешно!' : 'Платёж отменён или не удался.' }}
      </p>
    </div>
  </PanelLayout>
</template>
