<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { BadgeCheck, BriefcaseBusiness, Building2, LogOut, Phone, Sparkles } from '@lucide/vue'
import { apiFetch, clearAuthSession, getStoredUser, parseApiError, updateStoredUser } from '../services/api'

const router = useRouter()
const currentUser = getStoredUser()
const form = ref({
  phone: currentUser?.phone || '',
  department: currentUser?.department || '',
  job_title: currentUser?.job_title || '',
  bio: currentUser?.bio || '',
  color: currentUser?.color || 'violet',
})
const colors = [
  { id: 'violet', className: 'bg-violet-500' },
  { id: 'blue', className: 'bg-blue-500' },
  { id: 'emerald', className: 'bg-emerald-500' },
  { id: 'amber', className: 'bg-amber-500' },
  { id: 'rose', className: 'bg-rose-500' },
]
const loading = ref(false)
const error = ref('')

async function submit() {
  loading.value = true
  error.value = ''
  try {
    const response = await apiFetch('/api/profile/complete', {
      method: 'POST',
      body: JSON.stringify(form.value),
    })
    if (!response.ok) throw new Error(await parseApiError(response, 'Không thể hoàn tất hồ sơ.'))
    const payload = await response.json()
    updateStoredUser(payload.user)
    await router.replace('/')
    window.location.reload()
  } catch (exception) {
    error.value = exception.message
  } finally {
    loading.value = false
  }
}

function logout() {
  clearAuthSession()
  router.replace('/login')
}
</script>

<template>
  <main class="login-canvas min-h-screen px-4 py-8 sm:px-6">
    <div class="mx-auto grid min-h-[calc(100vh-4rem)] max-w-5xl overflow-hidden rounded-[2rem] bg-white shadow-2xl lg:grid-cols-[0.9fr_1.1fr]">
      <section class="relative hidden overflow-hidden bg-gradient-to-br from-violet-700 via-indigo-700 to-blue-700 p-10 text-white lg:flex lg:flex-col lg:justify-between">
        <div class="absolute -right-24 -top-24 h-72 w-72 rounded-full bg-white/10"></div>
        <div class="absolute -bottom-28 -left-20 h-80 w-80 rounded-full bg-blue-300/10"></div>
        <div class="relative">
          <p class="text-2xl font-bold">RingNet</p>
          <div class="mt-16 inline-flex rounded-2xl bg-white/15 p-3 backdrop-blur"><Sparkles class="h-7 w-7" /></div>
          <h1 class="mt-6 text-4xl font-bold leading-tight">Hoàn thiện hồ sơ để bắt đầu cộng tác.</h1>
          <p class="mt-4 leading-7 text-indigo-100">Thông tin này tạo nên thẻ thành viên của bạn và giúp quản lý phân công đúng người, đúng chuyên môn.</p>
        </div>
        <div class="relative flex items-center gap-3 rounded-2xl border border-white/15 bg-white/10 p-4 text-sm backdrop-blur">
          <BadgeCheck class="h-6 w-6 shrink-0 text-emerald-300" />
          Bạn luôn có thể cập nhật hồ sơ cá nhân trong phần Cài đặt.
        </div>
      </section>

      <section class="flex flex-col justify-center p-6 sm:p-10 lg:p-12">
        <div class="flex items-start justify-between gap-4">
          <div>
            <p class="text-sm font-bold uppercase tracking-[0.18em] text-violet-600">Bước cuối cùng</p>
            <h2 class="mt-2 text-3xl font-bold text-slate-900">Thông tin thành viên</h2>
            <p class="mt-2 text-sm text-slate-500">Xin chào {{ currentUser?.name }}. Hãy bổ sung các trường bắt buộc bên dưới.</p>
          </div>
          <button type="button" title="Đăng xuất" class="rounded-xl border border-slate-200 p-2.5 text-slate-500 transition hover:bg-slate-50 hover:text-slate-800" @click="logout"><LogOut class="h-5 w-5" /></button>
        </div>

        <p v-if="error" role="alert" class="mt-5 rounded-xl border border-rose-100 bg-rose-50 p-3 text-sm text-rose-700">{{ error }}</p>

        <form class="mt-7 space-y-5" @submit.prevent="submit">
          <label class="block text-sm font-semibold text-slate-700">Số điện thoại <span class="text-rose-500">*</span>
            <span class="relative mt-1.5 block"><Phone class="absolute left-3.5 top-3.5 h-4 w-4 text-slate-400" /><input v-model.trim="form.phone" required pattern="\+?[0-9]{9,15}" inputmode="tel" placeholder="0901234567" class="w-full rounded-xl border border-slate-200 bg-slate-50 py-3 pl-10 pr-4 outline-none transition focus:border-violet-400 focus:bg-white focus:ring-4 focus:ring-violet-100" /></span>
          </label>
          <div class="grid gap-5 sm:grid-cols-2">
            <label class="block text-sm font-semibold text-slate-700">Chức danh <span class="text-rose-500">*</span>
              <span class="relative mt-1.5 block"><BriefcaseBusiness class="absolute left-3.5 top-3.5 h-4 w-4 text-slate-400" /><input v-model.trim="form.job_title" required maxlength="100" placeholder="Frontend Developer" class="w-full rounded-xl border border-slate-200 bg-slate-50 py-3 pl-10 pr-4 outline-none transition focus:border-violet-400 focus:bg-white focus:ring-4 focus:ring-violet-100" /></span>
            </label>
            <label class="block text-sm font-semibold text-slate-700">Phòng ban <span class="text-rose-500">*</span>
              <span class="relative mt-1.5 block"><Building2 class="absolute left-3.5 top-3.5 h-4 w-4 text-slate-400" /><input v-model.trim="form.department" required maxlength="100" placeholder="Phát triển sản phẩm" class="w-full rounded-xl border border-slate-200 bg-slate-50 py-3 pl-10 pr-4 outline-none transition focus:border-violet-400 focus:bg-white focus:ring-4 focus:ring-violet-100" /></span>
            </label>
          </div>
          <label class="block text-sm font-semibold text-slate-700">Giới thiệu ngắn
            <textarea v-model.trim="form.bio" maxlength="1000" rows="3" placeholder="Kinh nghiệm, thế mạnh hoặc lĩnh vực bạn đang phụ trách..." class="mt-1.5 w-full resize-none rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 outline-none transition focus:border-violet-400 focus:bg-white focus:ring-4 focus:ring-violet-100"></textarea>
          </label>
          <fieldset>
            <legend class="text-sm font-semibold text-slate-700">Màu đại diện</legend>
            <div class="mt-2 flex gap-3">
              <button v-for="color in colors" :key="color.id" type="button" :aria-label="`Chọn màu ${color.id}`" :class="[color.className, form.color === color.id ? 'ring-4 ring-violet-200 ring-offset-2' : 'opacity-75 hover:opacity-100']" class="h-9 w-9 rounded-full transition" @click="form.color = color.id"></button>
            </div>
          </fieldset>
          <button :disabled="loading" class="w-full rounded-xl bg-gradient-to-r from-violet-600 to-indigo-600 px-5 py-3.5 font-bold text-white shadow-lg shadow-violet-500/20 transition hover:-translate-y-0.5 hover:shadow-xl disabled:translate-y-0 disabled:opacity-60">
            {{ loading ? 'Đang hoàn tất...' : 'Hoàn tất và vào hệ thống' }}
          </button>
        </form>
      </section>
    </div>
  </main>
</template>
