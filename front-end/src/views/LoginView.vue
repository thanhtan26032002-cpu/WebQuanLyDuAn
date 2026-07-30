<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { apiFetch, parseApiError, setAuthSession } from '../services/api'

const router = useRouter()
const form = ref({ name: '', email: '', password: '' })
const isRegistering = ref(false)
const loading = ref(false)
const error = ref('')

async function submit() {
  loading.value = true
  error.value = ''
  try {
    const endpoint = isRegistering.value ? '/api/register' : '/api/login'
    const response = await apiFetch(endpoint, { method: 'POST', body: JSON.stringify(form.value) })
    if (!response.ok) throw new Error(await parseApiError(response))
    const payload = await response.json()
    setAuthSession(payload.token, payload.user)
    await router.replace('/')
    window.location.reload()
  } catch (e) {
    error.value = e.message
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <main class="grid min-h-screen place-items-center bg-slate-950 p-4">
    <form class="w-full max-w-md rounded-3xl bg-white p-8 shadow-2xl" @submit.prevent="submit">
      <p class="text-2xl font-bold text-violet-700">RingNet</p>
      <h1 class="mt-8 text-3xl font-bold">{{ isRegistering ? 'Tạo tài khoản' : 'Đăng nhập' }}</h1>
      <p class="mt-2 text-sm text-slate-500">{{ isRegistering ? 'Tài khoản đầu tiên sẽ là quản trị viên hệ thống.' : 'Tiếp tục vào không gian làm việc của bạn.' }}</p>
      <p v-if="error" role="alert" class="mt-5 rounded-xl bg-rose-50 p-3 text-sm text-rose-700">{{ error }}</p>
      <label v-if="isRegistering" class="mt-6 block text-sm font-semibold">Họ và tên
        <input v-model="form.name" required autocomplete="name" class="mt-1.5 w-full rounded-xl border p-3 focus:border-violet-400 focus:outline-none focus:ring-4 focus:ring-violet-100" />
      </label>
      <label :class="['block text-sm font-semibold', isRegistering ? 'mt-4' : 'mt-6']">Email
        <input v-model="form.email" required type="email" autocomplete="email" class="mt-1.5 w-full rounded-xl border p-3 focus:border-violet-400 focus:outline-none focus:ring-4 focus:ring-violet-100" />
      </label>
      <label class="mt-4 block text-sm font-semibold">Mật khẩu
        <input v-model="form.password" required minlength="6" type="password" autocomplete="current-password" class="mt-1.5 w-full rounded-xl border p-3 focus:border-violet-400 focus:outline-none focus:ring-4 focus:ring-violet-100" />
      </label>
      <button :disabled="loading" class="mt-6 w-full rounded-xl bg-violet-600 p-3 font-semibold text-white disabled:opacity-60">
        {{ loading ? 'Đang xử lý...' : (isRegistering ? 'Tạo tài khoản' : 'Đăng nhập') }}
      </button>
      <button type="button" class="mt-4 w-full text-sm font-semibold text-violet-700 hover:text-violet-900" @click="isRegistering = !isRegistering; error = ''">
        {{ isRegistering ? 'Đã có tài khoản? Đăng nhập' : 'Chưa có tài khoản? Đăng ký' }}
      </button>
    </form>
  </main>
</template>
