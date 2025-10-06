<script setup>
import { Head, Link } from '@inertiajs/vue3'
import PanelLayout from '@/Layouts/PanelLayout.vue'

function formatDate(dateStr) {
  return new Date(dateStr).toLocaleString("ru-RU", {
    day: "2-digit",
    month: "2-digit",
    year: "numeric",
    hour: "2-digit",
    minute: "2-digit"
  });
}

function formatMoney(value) {
  return Number(value).toFixed(2);
}

function typeLabel(type) {
  const map = {
    usage: "Списание",
    payment: "Пополнение",
    refund: "Возврат",
    subscription: "Подписка"
  };
  return map[type] ?? type;
}

defineProps({
  balance: Object,
  transactions: Object,
  tokenUsage: Object
})
</script>

<template>
  <PanelLayout>
    <Head title="Баланс" />

    <div class="bg-white shadow rounded p-4 mb-4">
      <p><b>Текущий баланс:</b> {{ balance.amount_rub }} ₽</p>
      <p><b>Порог уведомления:</b> {{ balance.low_balance_threshold }} ₽</p>
    </div>

    <div class="bg-white shadow rounded p-4 mb-4">
      <h2 class="text-xl font-semibold">Статистика по токенам</h2>
      <p><b>Потрачено токенов:</b> {{ tokenUsage.tokens }}</p>
      <p><b>Списано в $:</b> {{ tokenUsage.usd }}</p>
    </div>

    <div class="bg-white shadow rounded p-4">
      <h2 class="text-xl font-semibold mb-2">Журнал операций</h2>
      <table class="w-full border text-sm">
        <thead class="bg-gray-100">
        <tr>
          <th class="p-2 text-left">Дата</th>
          <th class="p-2 text-left">Тип</th>
          <th class="p-2 text-right">USD</th>
          <th class="p-2 text-right">Курс</th>
          <th class="p-2 text-right">RUB</th>
        </tr>
        </thead>
        <tbody>
        <tr
            v-for="t in transactions.data"
            :key="t.id"
            class="border-t hover:bg-gray-50"
        >
          <td class="p-2 whitespace-nowrap">
            {{ formatDate(t.created_at) }}
          </td>
          <td class="p-2">
            {{ typeLabel(t.type) }}
          </td>
          <td class="p-2 text-right">
            {{ formatMoney(t.usd_cost) }}
          </td>
          <td class="p-2 text-right">
            {{ formatMoney(t.fx_used) }}
          </td>
          <td class="p-2 text-right">
            {{ formatMoney(t.usd_cost * t.fx_used * t.margin_used) }}
          </td>
        </tr>
        </tbody>
      </table>

      <!-- пагинация -->
      <div class="flex justify-center mt-4 space-x-2">
        <template v-for="link in transactions.links" :key="link.url ?? link.label">
          <span
              v-if="!link.url"
              class="px-3 py-1 text-gray-400"
              v-html="link.label"
          />
          <Link
              v-else
              class="px-3 py-1 border rounded"
              :class="{ 'bg-gray-200 font-semibold': link.active }"
              :href="link.url"
              v-html="link.label"
          />
        </template>
      </div>
    </div>
  </PanelLayout>
</template>
