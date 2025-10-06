<script setup>
import { ref, watch } from 'vue';
import { Head, usePage, router } from '@inertiajs/vue3';
import PanelLayout from '@/Layouts/PanelLayout.vue';
import Spinner from '@/components/Spinner.vue';

const page = usePage();

const filters = ref({
  ...page.props.filters,
  has_reply: page.props.filters.has_reply ?? '',
});

const loading = ref(false);

// Автообновление блока сообщений при изменении фильтров
watch(
    filters,
    (newFilters) => {
      router.get(
          route('panel-search', { account_id: page.props.account_id }),
          newFilters,
          {
            preserveState: true,
            preserveScroll: true,
            only: ['messages'],
          }
      );
    },
    { deep: true }
);

// Пагинация
function goToPage(url) {
  router.get(url, filters.value, {
    preserveState: true,
    preserveScroll: true,
    only: ['messages'],
    onStart: () => (loading.value = true),
    onFinish: () => (loading.value = false),
  });
}
</script>

<template>
  <PanelLayout>
    <Head title="Панель настроек" />
    <Spinner :show="loading" />

    <div class="p-4 space-y-4">
      <!-- Фильтры -->
      <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-x-4 gap-y-2 mb-4">
        <input
            v-model="filters.search"
            type="text"
            placeholder="Поиск по тексту..."
            class="border border-gray-300 rounded px-3 h-10 w-full focus:outline-none focus:ring focus:border-blue-300"
        />
        <input
            v-model="filters.date_from"
            type="date"
            class="border border-gray-300 rounded px-3 h-10 w-full focus:outline-none focus:ring focus:border-blue-300"
        />
        <input
            v-model="filters.date_to"
            type="date"
            class="border border-gray-300 rounded px-3 h-10 w-full focus:outline-none focus:ring focus:border-blue-300"
        />
        <select
            v-model="filters.has_reply"
            class="border border-gray-300 rounded px-3 h-10 w-full focus:outline-none focus:ring focus:border-blue-300"
        >
          <option value="">Все</option>
          <option value="1">Есть ответ</option>
          <option value="0">Нет ответа</option>
        </select>
      </div>

      <!-- Блок сообщений -->
      <div id="messages">
        <table class="w-full border mt-4 text-sm">
          <tbody>
          <tr v-for="group in page.props.messages.data" :key="group.id" class="border-t bg-gray-50 even:bg-white">
            <td class="p-2">
              <a v-if="group.domain" :href="`https://${group.domain}/leads/detail/${group.lead_id}`" class="text-blue-600 hover:underline" target="_blank">
                {{ group.lead_id }}
              </a>
              <span v-else>–</span>
            </td>
            <td class="p-2 max-w-md">
              <pre class="whitespace-pre-wrap">{{ group.questions }}</pre>
              <div v-if="group.answer_text" class="text-green-600 mt-1">
                → {{ group.answer_text }}
              </div>
            </td>
            <td class="p-2 whitespace-pre-wrap">
              <ul v-if="group.answer_params && group.answer_params.length" class="list-disc list-inside space-y-1">
                <li v-for="(param, i) in group.answer_params" :key="i">{{ param.name }}: {{ param.value }}</li>
              </ul>
              <span v-else>–</span>
            </td>
            <td class="p-2">{{ new Date(group.created_at).toLocaleString() }}</td>
          </tr>
          </tbody>
        </table>

        <!-- Пагинация -->
        <div v-if="page.props.messages.links.length > 3" class="mt-4">
          <div class="flex flex-wrap gap-2">
            <template v-for="(link, i) in page.props.messages.links" :key="i">
              <button
                  v-if="link.url"
                  @click="goToPage(link.url)"
                  :class="[
                  'px-3 py-1 text-sm border rounded',
                  link.active
                    ? 'bg-blue-500 text-white'
                    : 'bg-white text-gray-700 hover:bg-gray-100'
                ]"
              >
                <template v-if="link.label === '&laquo; Previous'">Назад</template>
                <template v-else-if="link.label === 'Next &raquo;'">Вперёд</template>
                <template v-else>{{ link.label }}</template>
              </button>
              <span
                  v-else
                  :class="[
                  'px-3 py-1 text-sm',
                  link.label.includes('...') ? 'text-gray-400' : 'text-gray-500'
                ]"
                  v-html="link.label"
              />
            </template>
          </div>
        </div>
      </div>
    </div>
  </PanelLayout>
</template>
