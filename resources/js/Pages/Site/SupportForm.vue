<script setup>
import { ref } from 'vue'
import GuestLayout from '@/Layouts/GuestLayout.vue'
import { Head } from '@inertiajs/vue3'
import axios from 'axios'

// Простая реактивная форма
const form = ref({
  email: '',
  subject: '',
  message: '',
})

const errors = ref({})
const message = ref(null)
const loading = ref(false)

const submit = async () => {
  loading.value = true
  errors.value = {}
  message.value = null

  try {
    const response = await axios.post('/support', form.value)

    // Сохраняем сообщение
    message.value = response.data.message

    // Очищаем форму
    form.value.email = ''
    form.value.subject = ''
    form.value.message = ''
  } catch (error) {
    if (error.response?.status === 422) {
      // Валидационные ошибки
      errors.value = error.response.data.errors
    } else {
      message.value = 'Произошла ошибка при отправке запроса.'
    }
  } finally {
    loading.value = false
  }
}
</script>


<template>
  <GuestLayout>
    <Head title="Обращение в поддержку" />

    <div class="site-index">
      <div class="max-w-xl mx-auto mt-10 bg-white p-6 rounded shadow">
        <h1 class="text-2xl font-semibold mb-4 text-center">Обращение в поддержку</h1>

        <form @submit.prevent="submit" class="space-y-4">
          <div>
            <label for="email" class="block font-medium">Email</label>
            <input
                v-model="form.email"
                type="email"
                id="email"
                class="w-full border rounded px-4 py-2"
                required
            />
            <div v-if="errors.email" class="text-red-500 text-sm">
              {{ errors.email }}
            </div>
          </div>

          <div>
            <label for="subject" class="block font-medium">Тема</label>
            <input
                v-model="form.subject"
                type="text"
                id="subject"
                class="w-full border rounded px-4 py-2"
                required
            />
            <div v-if="errors.subject" class="text-red-500 text-sm">
              {{ errors.subject }}
            </div>
          </div>

          <div>
            <label for="message" class="block font-medium">Сообщение</label>
            <textarea
                v-model="form.message"
                id="message"
                class="w-full border rounded px-4 py-2"
                rows="5"
                required
            ></textarea>
            <div v-if="errors.message" class="text-red-500 text-sm">
              {{ errors.message }}
            </div>
          </div>

          <button
              type="submit"
              :disabled="loading"
              class="bg-blue-500 text-white px-6 py-2 rounded hover:bg-blue-600"
          >
            Отправить
          </button>
        </form>

        <!-- Сообщение после отправки -->
        <div v-if="message" class="mt-4 text-green-600 text-center">
          {{ message }}
        </div>
      </div>
    </div>
  </GuestLayout>
</template>
