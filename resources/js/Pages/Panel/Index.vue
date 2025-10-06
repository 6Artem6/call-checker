<script setup>
import { ref, watch, onMounted } from 'vue';
import { Head, usePage, useForm } from '@inertiajs/vue3';
import PanelLayout from '@/Layouts/PanelLayout.vue';
import Spinner from '@/components/Spinner.vue';
import axios from 'axios';

const props = usePage().props;

// реактивные пропсы
const pipelineStatuses = ref({});
const settings  = ref(props.settings);
const accountId = props.account_id;
const availableModels = ref(props.available_models);
const uploadedFiles = ref(props.uploaded_files);

const loading = ref(false);

// дни недели
const days = [
  { value: 1, short: 'Пн' },
  { value: 2, short: 'Вт' },
  { value: 3, short: 'Ср' },
  { value: 4, short: 'Чт' },
  { value: 5, short: 'Пт' },
  { value: 6, short: 'Сб' },
  { value: 7, short: 'Вс' }
];

// инициализация формы из props.setting
const form = useForm({
  _method: 'POST',
  _token: props.csrf_token,
  pipeline_status_id: props.active_pipeline_id,
  setting_id: props.setting.setting_id ?? null,
  prompt: props.setting.prompt,
  model: props.setting.model,
  completion_condition: props.setting.completion_condition ?? '',
  delay: props.setting.delay ?? 0,
  is_active: !!props.setting.is_active,
  files: [],
  schedules: props.setting.schedules ?? []
});

// при смене pipeline подгружаем данные через API
watch(() => form.pipeline_status_id, async (newId) => {
  if (!newId) return;
  loading.value = true;
  try {
    const res = await axios.get(route('panel-setting', { account_id: accountId }), {
      params: {
        pipeline_status_id: newId,
      },
    });

    const data = res.data;
    form.setting_id           = data.setting?.setting_id ?? null;
    form.prompt               = data.setting?.prompt ?? '';
    form.model                = data.setting?.model ?? 'gpt-4.1';
    form.completion_condition = data.setting?.completion_condition ?? '';
    form.delay                = data.setting?.delay ?? 0;
    form.is_active            = !!data.setting?.is_active;
    uploadedFiles.value       = data.uploaded_files ?? [];
    form.schedules            = data.setting?.schedules ?? [];
  } catch (e) {
    console.error(e);
  } finally {
    loading.value = false;
  }
});

onMounted(async () => {
  try {
    const res = await axios.get(route('panel-pipeline-status-all', { account_id: accountId }));
    pipelineStatuses.value = res.data;
  } catch (e) {
    console.error('Ошибка загрузки статусов воронок', e);
  }
});

// загрузка файлов
function handleFileUpload(e) {
  form.files = Array.from(e.target.files);
}

// удаление файла
async function deleteFile(name) {
  if (!confirm('Удалить файл?')) return;
  loading.value = true;
  try {
    await axios.delete(route('panel-file-delete', { name }));
    uploadedFiles.value = uploadedFiles.value.filter(f => f.stored_name !== name);
  } finally {
    loading.value = false;
  }
}

// сохранение формы
async function submitForm() {
  const fd = new FormData();
  Object.entries(form).forEach(([k, v]) => {
    if (k === 'files') {
      v.forEach(file => fd.append('files[]', file));
    } else if (k === 'schedules') {
      v.forEach((sch, idx) => {
        sch.days.forEach(day => {
          fd.append(`schedules[${idx}][weekday][]`, day);
        });
        fd.append(`schedules[${idx}][time_from]`, sch.time_from);
        fd.append(`schedules[${idx}][time_to]`, sch.time_to);
      });
    } else {
      fd.append(k, v);
    }
  });

  loading.value = true;
  try {
    const res = await axios.post(route('panel-save', { account_id: accountId }), fd);
    alert(res.data.message);
    if (res.data.files) uploadedFiles.value.push(...res.data.files);
    form.files = [];
  } catch (e) {
    console.error(e);
    alert('Ошибка при сохранении');
  } finally {
    loading.value = false;
  }
}
</script>

<template>
  <PanelLayout>
    <Head title="Панель настроек" />
    <Spinner :show="loading" />

    <div class="p-6 bg-gray-800 rounded-lg space-y-6">
      <h2 class="text-white text-xl">Настройки бота</h2>

      <!-- Select с optgroup -->
      <label class="text-white">Воронка / Этап:</label>
      <select
          v-model="form.pipeline_status_id"
          name="pipeline_status_id"
          class="w-full p-2 rounded mb-4"
      >
        <option value="" disabled>Выберите этап</option>
        <optgroup
            v-for="(statuses, pipelineName) in pipelineStatuses"
            :key="pipelineName"
            :label="pipelineName"
        >
          <option
              v-for="status in statuses"
              :key="status.status_id"
              :value="status.status_id"
          >
            {{ status.name }}
          </option>
        </optgroup>
      </select>

      <!-- Прочие поля -->
      <label class="text-white">Промпт:</label>
      <textarea v-model="form.prompt" class="w-full p-2 rounded mb-4"></textarea>

      <label class="text-white">Модель:</label>
      <select v-model="form.model" class="w-full p-2 rounded mb-4">
        <option v-for="m in availableModels" :key="m" :value="m">{{ m }}</option>
      </select>

      <label class="text-white">Условие завершения:</label>
      <textarea
          v-model="form.completion_condition"
          class="w-full p-2 rounded mb-4"
          placeholder="Например: диалог завершается, если пользователь сказал 'Спасибо' или 'Пока'"
      ></textarea>

      <label class="text-white">Время задержки ответа:</label>
      <input
          v-model="form.delay"
          min="0"
          type="number"
          class="w-full p-2 rounded mb-4"
          placeholder="Необходимо для ответа на несколько вопросов сразу"
      />

      <label class="text-white">Статус бота на воронке:</label>
      <select v-model="form.is_active" class="w-full p-2 rounded mb-4">
        <option :value="false">Неактивен</option>
        <option :value="true">Активен</option>
      </select>

      <!-- Расписание -->
      <div class="mt-6">
        <h3 class="text-white text-lg mb-2">Расписание работы бота</h3>

        <div
            v-for="(s, i) in form.schedules"
            :key="i"
            class="flex items-center space-x-4 bg-gray-700 p-3 rounded mb-3"
        >
          <!-- чекбоксы дней недели -->
          <div class="flex space-x-2">
            <label v-for="d in days" :key="d.value" class="flex items-center space-x-1 text-white">
              <input type="checkbox" v-model="s.days" :value="d.value" />
              <span>{{ d.short }}</span>
            </label>
          </div>

          <!-- время -->
          <div class="flex items-center space-x-2">
            <input type="time" v-model="s.time_from" class="p-1 rounded" />
            <span class="text-white">–</span>
            <input type="time" v-model="s.time_to" class="p-1 rounded" />
          </div>

          <!-- удалить -->
          <button
              @click="form.schedules.splice(i, 1)"
              type="button"
              class="bg-red-500 text-white px-2 py-1 rounded"
          >
            ✕
          </button>
        </div>

        <!-- добавить -->
        <button
            @click="form.schedules.push({ days: [], time_from: '09:00', time_to: '18:00' })"
            type="button"
            class="bg-green-500 text-white px-3 py-1 rounded"
        >
          Добавить интервал
        </button>
      </div>

      <!-- Файлы -->
      <div>
        <label class="text-white">Файлы:</label>
        <input
            type="file" multiple
            @change="handleFileUpload"
            class="w-full p-2 rounded mb-4"
        />

        <h3 class="text-white mt-2">Загруженные файлы:</h3>
        <div v-if="uploadedFiles.length" class="space-y-2">
          <div
              v-for="f in uploadedFiles"
              :key="f.file_id"
              class="flex justify-between items-center bg-gray-700 p-3 rounded-lg"
          >
            <a
                :href="route('panel-file-download', { name: f.stored_name })"
                class="text-blue-400"
                download
            >
              {{ f.original_name }}
            </a>
            <button
                @click="deleteFile(f.stored_name)"
                class="bg-red-500 text-white px-3 py-1 rounded"
            >
              Удалить
            </button>
          </div>
        </div>
        <p v-else class="text-gray-400">Файлов нет</p>
      </div>

      <button
          @click="submitForm"
          class="bg-blue-500 text-white w-full p-2 rounded mt-4"
      >
        Сохранить
      </button>
    </div>
  </PanelLayout>
</template>
