<script setup>
import { Head, usePage, useForm } from '@inertiajs/vue3';
import PanelLayout from '@/Layouts/PanelLayout.vue';
import Spinner from "@/components/Spinner.vue";
import { ref } from 'vue';
import axios from 'axios';

// Получаем данные из Inertia
const page = usePage();
const setting = ref(page.props.setting || {});
// Доступные модели для выбора
const availableModels = ref(page.props.available_models || []);
const uploadedFiles = ref(page.props.uploaded_files || []);
const loading = ref(false);

// Создаем объект формы с начальными значениями
const form = useForm({
  _token: page.props.csrf_token,
  _method: "POST",
  prompt: setting.value.prompt || '',
  temperature: setting.value.temperature || 0.5,
  model: setting.value.model || 'gpt-4', // Выбор модели
  files: [] // Хранение загруженных файлов
});

// Обработчик загрузки файлов
const handleFileUpload = (event) => {
  form.files = Array.from(event.target.files);
};

// Обработчик удаления файла
const deleteFile = async (name) => {
  if (!confirm("Вы уверены, что хотите удалить этот файл?")) return;

  try {
    loading.value = true;
    await axios.delete(route('panel-file-delete', {'name': name}));
    uploadedFiles.value = uploadedFiles.value.filter(file => file.stored_name !== name);
  } catch (error) {
    console.error("Ошибка при удалении файла", error);
  } finally {
    loading.value = false;
  }
};

const submitForm = async () => {
  const formData = new FormData();
  formData.append("_method", form._method);
  formData.append("_token", form._token);
  formData.append("prompt", form.prompt);
  formData.append("temperature", form.temperature);
  formData.append("model", form.model);
  form.files.forEach((file) => {
    formData.append("files[]", file);
  });

  try {
    loading.value = true;
    const response = await axios.post(route('panel-save', {'account_id': setting.value.account_id}), formData);
    alert(response.data.message);

    // Обновляем список загруженных файлов
    if (response.data.files) {
      response.data.files.forEach(file => {
        uploadedFiles.value.push(file);
      });
    }

    // Очищаем файлы в форме
    form.files = [];
  } catch (error) {
    console.error(error);
    alert("Ошибка при сохранении");
  } finally {
    loading.value = false;
  }
};
</script>

<template>
  <PanelLayout>
    <Head title="Настройка плагина" />

    <Spinner :show="loading" />

    <div class="p-6 bg-gray-800 rounded-lg">
      <h2 class="text-white text-xl mb-4">Настройка плагина</h2>

      <input v-model="form._method" type="hidden" name="_method" value="POST">
      <input v-model="form._token" type="hidden" name="_token" :value="page.props.csrf_token">

      <label class="text-white">Промпт:</label>
      <textarea v-model="form.prompt" required class="w-full p-2 rounded mb-4"></textarea>

      <label class="text-white">Температура:</label>
      <input v-model="form.temperature" required type="number" min="0" max="1" step="0.01" class="w-full p-2 rounded mb-4" />

      <label class="text-white">Модель:</label>
      <select v-model="form.model" required class="w-full p-2 rounded mb-4">
        <option v-for="model in availableModels" :key="model" :value="model">{{ model }}</option>
      </select>

      <label class="text-white">Файлы:</label>
      <input type="file" multiple @change="handleFileUpload" class="w-full p-2 rounded mb-4" />

      <h3 class="text-white mt-6">Загруженные файлы:</h3>
      <div v-if="uploadedFiles.length > 0" class="space-y-2">
        <div v-for="file in uploadedFiles" :key="file.file_id" class="flex justify-between items-center bg-gray-700 p-3 rounded-lg">
          <a :href="route('panel-file-download', {'name': file.stored_name})" class="text-blue-400" download>
            {{ file.original_name }}
          </a>
          <button @click="deleteFile(file.stored_name)" class="bg-red-500 text-white px-3 py-1 rounded">
            Удалить
          </button>
        </div>
      </div>
      <p v-else class="text-gray-400">Файлы отсутствуют</p>

      <button @click="submitForm" class="bg-blue-500 w-full text-white p-2 rounded mt-4">
        Сохранить
      </button>
    </div>
  </PanelLayout>
</template>
