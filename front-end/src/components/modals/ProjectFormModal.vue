<script setup>
import { computed, nextTick, reactive, ref, onMounted, onUnmounted } from 'vue'
import { X, FolderKanban, Plus, Check, Paperclip, UploadCloud, Building2 } from '@lucide/vue'
import { useProjectWorkspace } from '../../composables/useProjectWorkspace'

const { members, customers, projectModalOpen, addProject, addCustomer, validateCustomerDraft, formatBytes } = useProjectWorkspace()

const selectedFiles = ref([])

const form = reactive({
  name: '',
  description: '',
  color: 'indigo',
  status: 'planning',
  customerId: '',
  managerId: '',
  startDate: new Date().toISOString().split('T')[0],
  dueDate: '',
  progress: 0,
  memberIds: [],
})

const showNewCustomer = ref(false)
const savingCustomer = ref(false)
const customerForm = reactive({ name: '', company: '', email: '', phone: '' })

async function createCustomer() {
  const customerErrors = validateCustomerDraft(customerForm)
  if (Object.keys(customerErrors).length) {
    errors.value = { ...errors.value, ...customerErrors }
    return
  }
  errors.value = { ...errors.value, customer_name: '', email: '', phone: '' }
  savingCustomer.value = true
  const result = await addCustomer({ ...customerForm, name: customerForm.name.trim() })
  savingCustomer.value = false
  if (result?.success) {
    form.customerId = result.customer.id
    Object.assign(customerForm, { name: '', company: '', email: '', phone: '' })
    showNewCustomer.value = false
  } else {
    showErrors(result?.errors || { _general: 'Không thể tạo khách hàng.' })
  }
}

const colors = [
  { id: 'indigo', bg: 'bg-indigo-500', ring: 'ring-indigo-500' },
  { id: 'emerald', bg: 'bg-emerald-500', ring: 'ring-emerald-500' },
  { id: 'amber', bg: 'bg-amber-500', ring: 'ring-amber-500' },
  { id: 'rose', bg: 'bg-rose-500', ring: 'ring-rose-500' },
  { id: 'sky', bg: 'bg-sky-500', ring: 'ring-sky-500' },
  { id: 'violet', bg: 'bg-violet-500', ring: 'ring-violet-500' },
  { id: 'orange', bg: 'bg-orange-500', ring: 'ring-orange-500' }
]

const errors = ref({})
const modalBody = ref(null)
const errorMessages = computed(() => [...new Set(Object.values(errors.value).filter(Boolean))])

function showErrors(nextErrors) {
  errors.value = nextErrors
  nextTick(() => modalBody.value?.scrollTo({ top: 0, behavior: 'smooth' }))
}

function toggleMember(memberId) {
  const index = form.memberIds.indexOf(memberId)
  if (index >= 0) form.memberIds.splice(index, 1)
  else form.memberIds.push(memberId)
}

async function submit() {
  errors.value = {}
  if (!form.name.trim()) {
    showErrors({ name: 'Vui lòng nhập tên dự án.' })
    return
  }
  if (!form.customerId) {
    showErrors({ customer_code: 'Vui lòng chọn hoặc tạo khách hàng cho dự án.' })
    return
  }
  
  const res = await addProject({ ...form, name: form.name.trim(), memberIds: [...form.memberIds], files: [...selectedFiles.value] })
  if (res && res.success === false && res.errors) {
    showErrors(res.errors)
  } else if (res && res.success) {
    // modal is closed in useProjectWorkspace
    form.name = ''
    form.description = ''
    form.customerId = ''
    form.managerId = ''
    form.startDate = new Date().toISOString().split('T')[0]
    form.dueDate = ''
    form.progress = 0
    form.memberIds = []
    selectedFiles.value = []
  }
}

const onFileSelect = (e) => {
  if (e.target.files && e.target.files.length > 0) {
    selectedFiles.value.push(...Array.from(e.target.files))
  }
}

const removeSelectedFile = (idx) => {
  selectedFiles.value.splice(idx, 1)
}

// Close on escape
const onKeydown = (e) => {
  if (e.key === 'Escape') projectModalOpen.value = false
}
onMounted(() => document.addEventListener('keydown', onKeydown))
onUnmounted(() => document.removeEventListener('keydown', onKeydown))
</script>

<template>
  <Teleport to="body">
    <div class="fixed inset-0 z-[100] flex items-center justify-center p-4 sm:p-6 animate-in fade-in duration-200">
      <!-- Backdrop -->
      <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" @click="projectModalOpen = false"></div>
      
      <!-- Modal Content -->
      <form @submit.prevent="submit" class="relative bg-white rounded-2xl shadow-2xl w-full max-w-xl flex flex-col max-h-full overflow-hidden animate-in zoom-in-95 duration-200">
        
        <!-- Header -->
        <header class="flex items-start justify-between p-6 border-b border-slate-100 bg-slate-50/50">
          <div class="flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-violet-100 text-violet-600 flex items-center justify-center shrink-0">
              <FolderKanban class="w-6 h-6" />
            </div>
            <div>
              <h2 class="text-xl font-bold text-slate-900 mb-1">Tạo dự án mới</h2>
              <p class="text-sm text-slate-500">Thiết lập mục tiêu, thành viên và tiến độ ban đầu.</p>
            </div>
          </div>
          <button type="button" @click="projectModalOpen = false" class="text-slate-400 hover:text-slate-700 p-2 rounded-xl hover:bg-slate-100 transition-colors">
            <X class="w-5 h-5" />
          </button>
        </header>

        <!-- Body -->
        <div ref="modalBody" class="p-6 overflow-y-auto space-y-5 custom-scrollbar flex-1">
          <div v-if="errorMessages.length" role="alert" class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            <p class="font-semibold">Không thể tạo dự án. Vui lòng kiểm tra:</p>
            <ul class="mt-1 list-disc space-y-0.5 pl-5">
              <li v-for="message in errorMessages" :key="message">{{ message }}</li>
            </ul>
          </div>
          <div class="space-y-2">
            <label class="block text-sm font-semibold text-slate-700">Tên dự án *</label>
            <input v-model="form.name" autofocus placeholder="Ví dụ: Thiết kế lại website" @input="errors.name = ''" :class="['w-full bg-slate-50 border rounded-xl px-4 py-2.5 text-slate-900 focus:bg-white focus:ring-4 transition-all outline-none', errors.name ? 'border-red-300 focus:border-red-300 focus:ring-red-500/10' : 'border-slate-200 focus:border-violet-300 focus:ring-violet-500/10']" />
            <p v-if="errors.name" class="text-xs font-medium text-red-500">{{ errors.name }}</p>
          </div>

          <div class="rounded-2xl border border-violet-100 bg-violet-50/50 p-4">
            <div class="mb-3 flex items-center justify-between gap-3">
              <label class="flex items-center gap-2 text-sm font-semibold text-slate-700"><Building2 class="h-4 w-4 text-violet-600" /> Khách hàng *</label>
              <button type="button" @click="showNewCustomer = !showNewCustomer" class="text-xs font-bold text-violet-700 hover:text-violet-900">{{ showNewCustomer ? 'Chọn khách hàng có sẵn' : '+ Tạo khách hàng mới' }}</button>
            </div>
            <select v-if="!showNewCustomer" v-model="form.customerId" @change="errors.customer_code = ''" :class="['w-full rounded-xl border bg-white px-3 py-2.5 text-sm outline-none focus:ring-4', errors.customer_code ? 'border-red-300 focus:ring-red-100' : 'border-slate-200 focus:border-violet-300 focus:ring-violet-100']">
              <option value="">— Chọn khách hàng —</option>
              <option v-for="customer in customers" :key="customer.id" :value="customer.id">{{ customer.name }}{{ customer.company ? ` · ${customer.company}` : '' }}</option>
            </select>
            <div v-else class="space-y-3">
              <div class="grid grid-cols-2 gap-3">
                <div><input v-model="customerForm.name" @input="errors.customer_name = ''" placeholder="Tên người liên hệ *" aria-label="Tên khách hàng" :class="['w-full rounded-xl border bg-white px-3 py-2 text-sm outline-none', errors.customer_name ? 'border-red-300 focus:border-red-400' : 'border-slate-200 focus:border-violet-300']" /><p v-if="errors.customer_name" class="mt-1 text-[11px] font-medium text-red-500">{{ errors.customer_name }}</p></div>
                <input v-model="customerForm.company" placeholder="Công ty / tổ chức" class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm outline-none focus:border-violet-300" />
                <div><input v-model="customerForm.email" @input="errors.email = ''" type="email" inputmode="email" autocomplete="email" placeholder="Email" aria-label="Email khách hàng" :class="['w-full rounded-xl border bg-white px-3 py-2 text-sm outline-none', errors.email ? 'border-red-300 focus:border-red-400' : 'border-slate-200 focus:border-violet-300']" /><p v-if="errors.email" class="mt-1 text-[11px] font-medium text-red-500">{{ errors.email }}</p></div>
                <div><input v-model="customerForm.phone" @input="errors.phone = ''" inputmode="tel" autocomplete="tel" placeholder="Số điện thoại" aria-label="Số điện thoại khách hàng" :class="['w-full rounded-xl border bg-white px-3 py-2 text-sm outline-none', errors.phone ? 'border-red-300 focus:border-red-400' : 'border-slate-200 focus:border-violet-300']" /><p v-if="errors.phone" class="mt-1 text-[11px] font-medium text-red-500">{{ errors.phone }}</p></div>
              </div>
              <button type="button" @click="createCustomer" :disabled="savingCustomer" class="rounded-lg bg-violet-600 px-3 py-2 text-xs font-bold text-white disabled:opacity-50">{{ savingCustomer ? 'Đang lưu...' : 'Lưu và chọn khách hàng' }}</button>
            </div>
            <p v-if="errors.customer_code" class="mt-2 text-xs font-medium text-red-500">{{ errors.customer_code }}</p>
          </div>

          <div class="space-y-2">
            <label class="block text-sm font-semibold text-slate-700">Mô tả</label>
            <textarea v-model="form.description" rows="3" placeholder="Mô tả ngắn về dự án..." class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-slate-900 focus:bg-white focus:border-violet-300 focus:ring-4 focus:ring-violet-500/10 transition-all outline-none resize-none"></textarea>
            <p v-if="errors.description" class="text-xs font-medium text-red-500">{{ errors.description }}</p>
          </div>

          <div class="space-y-3">
            <label class="block text-sm font-semibold text-slate-700">Màu sắc</label>
            <div class="flex flex-wrap gap-3">
              <label 
                v-for="color in colors" 
                :key="color.id"
                class="relative cursor-pointer"
              >
                <input v-model="form.color" type="radio" :value="color.id" class="sr-only" />
                <div 
                  :class="[
                    'w-8 h-8 rounded-full flex items-center justify-center transition-all',
                    color.bg,
                    form.color === color.id ? `ring-4 ring-offset-2 ${color.ring}` : 'hover:scale-110 shadow-sm'
                  ]"
                >
                  <Check v-if="form.color === color.id" class="w-4 h-4 text-white" />
                </div>
              </label>
            </div>
          </div>

          <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
            <div class="space-y-2 sm:col-span-1">
              <label class="block text-sm font-semibold text-slate-700">Quản lý dự án</label>
              <select v-model="form.managerId" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm outline-none focus:border-violet-300">
                <option value="">— Chưa phân công —</option>
                <option v-for="member in members" :key="member.id" :value="member.id">{{ member.name }}</option>
              </select>
            </div>
            <div class="space-y-2">
              <label class="block text-sm font-semibold text-slate-700">Ngày bắt đầu</label>
              <input v-model="form.startDate" type="date" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm outline-none focus:border-violet-300" />
            </div>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
            <div class="space-y-2">
              <label class="block text-sm font-semibold text-slate-700">Trạng thái</label>
              <select v-model="form.status" :class="['w-full bg-slate-50 border rounded-xl px-4 py-2.5 text-slate-900 focus:bg-white focus:ring-4 transition-all outline-none appearance-none cursor-pointer', errors.status ? 'border-red-300 focus:border-red-300 focus:ring-red-500/10' : 'border-slate-200 focus:border-violet-300 focus:ring-violet-500/10']">
                <option value="planning">Lập kế hoạch</option>
                <option value="active">Đang triển khai</option>
                <option value="on_hold">Tạm dừng</option>
                <option value="completed">Hoàn thành</option>
              </select>
              <p v-if="errors.status" class="text-xs font-medium text-red-500">{{ errors.status }}</p>
            </div>
            <div class="space-y-2">
              <label class="block text-sm font-semibold text-slate-700">Hạn chót</label>
              <input v-model="form.dueDate" type="date" @input="errors.due_date = ''" :class="['w-full bg-slate-50 border rounded-xl px-4 py-2.5 text-slate-900 focus:bg-white focus:ring-4 transition-all outline-none cursor-pointer', errors.due_date ? 'border-red-300 focus:border-red-300 focus:ring-red-500/10' : 'border-slate-200 focus:border-violet-300 focus:ring-violet-500/10']" />
              <p v-if="errors.due_date" class="text-xs font-medium text-red-500">{{ errors.due_date }}</p>
            </div>
          </div>

          <div class="space-y-3">
            <label class="flex justify-between text-sm font-semibold text-slate-700">
              <span>Tiến độ</span>
              <span class="text-violet-600">{{ form.progress }}%</span>
            </label>
            <input v-model="form.progress" type="range" min="0" max="100" step="5" class="w-full accent-violet-600 h-2 bg-slate-200 rounded-lg appearance-none cursor-pointer" />
          </div>

          <div class="space-y-3">
            <label class="block text-sm font-semibold text-slate-700">Thành viên</label>
            <div class="flex flex-wrap gap-2">
              <button 
                v-for="member in members" 
                :key="member.id" 
                type="button" 
                @click="toggleMember(member.id)"
                :class="[
                  'flex items-center gap-2 px-3 py-1.5 rounded-xl border transition-all text-sm font-medium',
                  form.memberIds.includes(member.id) 
                    ? 'border-violet-200 bg-violet-50 text-violet-700' 
                    : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50'
                ]"
              >
                <span :class="['w-5 h-5 rounded-full flex items-center justify-center text-[10px] font-bold text-white', `bg-${member.color}-500`]">
                  {{ member.initials }}
                </span>
                {{ member.name }}
              </button>
            </div>
          </div>

          <div class="space-y-3">
            <label class="block text-sm font-semibold text-slate-700">Tệp đính kèm (Tuỳ chọn)</label>
            <div class="border border-dashed border-slate-300 rounded-xl p-4 bg-slate-50 flex flex-col items-center justify-center text-center">
              <input type="file" id="new-project-upload" multiple class="hidden" @change="onFileSelect" />
              <label for="new-project-upload" class="flex items-center gap-2 text-xs font-semibold text-violet-600 hover:text-violet-700 bg-violet-100/60 hover:bg-violet-100 px-3 py-1.5 rounded-lg cursor-pointer transition-colors">
                <Paperclip class="w-3.5 h-3.5" /> Thêm file đính kèm
              </label>
            </div>
            <div v-if="selectedFiles.length > 0" class="space-y-1.5 max-h-28 overflow-y-auto custom-scrollbar pr-1">
              <div v-for="(f, idx) in selectedFiles" :key="idx" class="bg-white border border-slate-200 rounded-lg p-2 flex items-center justify-between text-xs shadow-sm">
                <span class="truncate font-medium text-slate-700 max-w-[280px]">{{ f.name }}</span>
                <div class="flex items-center gap-2 shrink-0">
                  <span class="text-slate-400">{{ formatBytes(f.size) }}</span>
                  <button type="button" @click.stop="removeSelectedFile(idx)" class="text-slate-400 hover:text-rose-500 p-0.5 rounded hover:bg-rose-50">
                    <X class="w-3.5 h-3.5" />
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Footer -->
        <footer class="p-5 border-t border-slate-100 flex justify-end gap-3 bg-slate-50/50">
          <button type="button" @click="projectModalOpen = false" class="px-5 py-2.5 bg-white border border-slate-200 rounded-xl text-sm font-medium text-slate-700 hover:bg-slate-50 transition-colors">
            Hủy
          </button>
          <button type="submit" class="flex items-center gap-2 px-5 py-2.5 bg-gradient-to-r from-violet-500 to-indigo-600 text-white rounded-xl text-sm font-medium shadow-md shadow-violet-500/25 hover:shadow-premium transition-all">
            <Plus class="w-4 h-4" /> Tạo dự án
          </button>
        </footer>
      </form>
    </div>
  </Teleport>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
  width: 6px;
}
.custom-scrollbar::-webkit-scrollbar-track {
  background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
  background-color: #cbd5e1;
  border-radius: 20px;
}
</style>
