<script setup>
import { ref, reactive, onMounted, nextTick, watch } from 'vue'
import axios from 'axios'
import { Head, usePage } from '@inertiajs/vue3'
import PanelLayout from '@/Layouts/PanelLayout.vue'
import Spinner from '@/components/Spinner.vue'

const page = usePage()
const accountId = page.props.account_id

const input = ref('')
const messages = reactive([]) // здесь будут [{ text, from, replies: [] }]
const loading = ref(false)
const chatContainer = ref(null)

const pipelineStatuses = ref({})
const selectedPipelineId = ref(page.props.active_pipeline_id ?? null)
const pipelineSettings = ref({
  setting_id: 0,
  prompt: '',
  temperature: 0.5,
  model: 'gpt-3.5-turbo',
})

function scrollToBottom() {
  nextTick(() => {
    if (chatContainer.value) {
      chatContainer.value.scrollTop = chatContainer.value.scrollHeight
    }
  })
}

onMounted(async () => {
  try {
    const res = await axios.get(route('panel-pipeline-status-all', { account_id: accountId }))
    pipelineStatuses.value = res.data
  } catch (e) {
    console.error('Ошибка загрузки pipeline-ов', e)
  }

  await loadChatMessages()

  if (selectedPipelineId.value) {
    await loadPipelineSettings(selectedPipelineId.value)
  }
})

async function loadChatMessages() {
  loading.value = true
  try {
    const res = await axios.get(route('playground-account-messages', { account_id: accountId }))
    messages.splice(0, messages.length, ...res.data) // реактивное обновление
  } catch (e) {
    console.error('Ошибка загрузки сообщений', e)
  } finally {
    loading.value = false
    scrollToBottom()
  }
}

watch(selectedPipelineId, async (newId) => {
  if (newId) {
    await loadPipelineSettings(newId)
  }
})

async function loadPipelineSettings(pipelineId) {
  loading.value = true
  try {
    const res = await axios.get(route('panel-setting', { account_id: accountId }), {
      params: { pipeline_status_id: pipelineId }
    })
    const setting = res.data.setting ?? {}
    pipelineSettings.value.setting_id = setting.setting_id ?? 0
    pipelineSettings.value.prompt = setting.prompt ?? ''
    pipelineSettings.value.temperature = setting.temperature ?? 0.5
    pipelineSettings.value.model = setting.model ?? 'gpt-3.5-turbo'
  } catch (e) {
    console.error('Ошибка загрузки настроек pipeline', e)
  } finally {
    loading.value = false
  }
}

async function sendMessage() {
  const text = input.value.trim()
  if (!text || !selectedPipelineId.value) return

  const userMessage = {
    from: 'user',
    text,
    status: 'pending',
    has_reply: true,
    replies: [],
  }

  messages.push(userMessage)
  scrollToBottom()
  input.value = ''
  loading.value = true

  try {
    const res = await axios.get(route('playground-send-message', { account_id: accountId }), {
      params: {
        text,
        setting_id: pipelineSettings.value.setting_id,
      },
    })

    const messageId = res.data?.id
    if (messageId) {
      const replyText = await pollResponse(messageId)
      if (replyText) {
        userMessage.replies.push({
          from: 'assistant',
          text: replyText,
        })
      }
    } else {
      throw new Error('ID не получен')
    }
  } catch (e) {
    console.error('Ошибка при отправке сообщения', e)
    userMessage.replies.push({ from: 'assistant', text: 'Произошла ошибка при отправке сообщения.' })
  } finally {
    loading.value = false
    scrollToBottom()
  }
}

async function pollResponse(messageId) {
  try {
    const res = await axios.get(route('playground-check-message-status', { message_id: messageId }))
    const { status, result } = res.data

    if (status === 'pending') {
      await new Promise(resolve => setTimeout(resolve, 1000))
      return pollResponse(messageId)
    }

    if (status === 'completed' && result) {
      return result
    } else {
      return 'Ошибка при получении ответа.'
    }
  } catch (e) {
    console.error('Ошибка при опросе статуса', e)
    return 'Ошибка при получении ответа от сервера.'
  }
}
</script>

<template>
  <PanelLayout>
    <Head title="Панель тестовых настроек" />
    <Spinner :show="loading" />

    <div class="max-w-xl mx-auto p-4 space-y-4">
      <div>
        <label class="block mb-1 font-semibold">Воронка / Этап:</label>
        <select v-model="selectedPipelineId"
                class="w-full border rounded px-3 py-2 focus:outline-none">
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
      </div>

      <div class="border rounded-lg p-4 mb-4 h-96 overflow-y-auto" ref="chatContainer">
        <template v-for="(msg, index) in messages" :key="index">
          <div class="mb-2 text-right">
            <div class="inline-block bg-blue-500 text-white rounded-lg px-3 py-1">
              {{ msg.text }}
            </div>
          </div>

          <div v-for="(reply, rIndex) in msg.replies" :key="rIndex" class="mb-2 text-left">
            <div class="inline-block bg-gray-200 text-gray-800 rounded-lg px-3 py-1">
              {{ reply.text }}
            </div>
          </div>
        </template>

        <div v-if="loading" class="text-left animate-pulse text-gray-500">
          GPT ассистент печатает...
        </div>
      </div>

      <form @submit.prevent="sendMessage" class="flex">
        <input
            v-model="input"
            type="text"
            placeholder="Введите сообщение..."
            class="flex-1 border rounded-l-lg px-3 py-2 focus:outline-none"
        />
        <button
            type="submit"
            :disabled="loading || !input.trim()"
            class="bg-blue-500 text-white px-4 rounded-r-lg hover:bg-blue-600 disabled:opacity-50">
          Отправить
        </button>
      </form>
    </div>
  </PanelLayout>
</template>
