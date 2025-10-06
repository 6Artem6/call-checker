<script setup>
import { ref } from 'vue'
import { Head, usePage, useForm } from '@inertiajs/vue3'
import PanelLayout from '@/Layouts/PanelLayout.vue'
import Spinner from '@/components/Spinner.vue'

const { pipelines, account_id } = usePage().props
const loading = ref(false)

const form = useForm({
  pipeline_id: 'all'
})

const sync = () => {
  loading.value = true
  form.post(route('panel-sync', { account_id }), {
    onFinish: () => {
      loading.value = false
    }
  })
}
</script>

<template>
  <PanelLayout>
    <Head title="Синхронизация" />
    <Spinner :show="loading" />

    <div class="max-w-lg mx-auto p-6 space-y-6 bg-white rounded-2xl shadow">
      <h1 class="text-xl font-semibold text-gray-800">Синхронизация воронок</h1>

      <div>
        <label for="pipeline" class="block mb-2 text-sm font-medium text-gray-700">
          Выберите воронку
        </label>
        <select
            id="pipeline"
            v-model="form.pipeline_id"
            class="w-full border border-gray-300 rounded-lg p-2 focus:ring-2 focus:ring-blue-400 focus:outline-none"
        >
          <option value="all">Все воронки</option>
          <option v-for="pipeline in pipelines" :key="pipeline.id" :value="pipeline.id">
            {{ pipeline.name }}
          </option>
        </select>
      </div>

      <button
          @click="sync"
          :disabled="loading"
          class="w-full bg-blue-500 hover:bg-blue-600 disabled:opacity-50 text-white px-4 py-2 rounded-lg shadow transition"
      >
        {{ loading ? 'Синхронизация...' : 'Синхронизировать' }}
      </button>
    </div>
  </PanelLayout>
</template>
