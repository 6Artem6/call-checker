<script setup>
import axios from 'axios';
import { Head, useForm } from '@inertiajs/vue3';
import PanelLayout from '@/Layouts/PanelLayout.vue';
import Spinner from "@/components/Spinner.vue";

const form = useForm({
  domain: '',
  amount: 100.0,
});

const submit = async () => {
  try {
    const res = await axios.post(route('payment.store'), {
      amount: form.amount,
    });

    window.open(res.data.redirect_url, '_blank');
  } catch (e) {
    alert('Ошибка при создании платежа');
  }
};
</script>

<template>
  <PanelLayout>
    <Head title="Создание оплаты" />

    <Spinner :show="loading" />

    <div class="p-4 bg-white shadow rounded">
      <h2 class="text-xl mb-4 text-center">Создать платёж</h2>
      <input v-model.number="form.amount" type="number" min="0.5" placeholder="Сумма" class="w-full mb-2 p-2 border rounded" />
      <button @click="submit" class="bg-blue-500 text-white px-4 py-2 w-full rounded">Оплатить</button>
    </div>
  </PanelLayout>
</template>
