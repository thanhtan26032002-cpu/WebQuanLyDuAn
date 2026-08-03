<script setup>
import { ref, reactive, onMounted, onUnmounted, watch, computed, nextTick } from 'vue'
import { X, Settings, Check, Trash2, AlertTriangle, Building2 } from '@lucide/vue'
import { useRouter } from 'vue-router'
import { useProjectWorkspace } from '../../composables/useProjectWorkspace'

const router = useRouter()
const { projects, members, customers, projectSettingsModalOpen, editingProjectId, updateProject, deleteProject, addCustomer, validateCustomerDraft } = useProjectWorkspace()

const form = reactive({
  name: '',
  description: '',
  color: 'indigo',
  status: 'planning',
  customerId: '',
  managerId: '',
  startDate: '',
  dueDate: '',
  delayReason: '',
  recoveryPlan: '',
  extensionReason: '',
  progress: 0,
})

const isDeleting = ref(false)
const showNewCustomer = ref(false)
const savingCustomer = ref(false)
const customerForm = reactive({ name: '', company: '', email: '', phone: '' })
const errors = ref({})
const modalBody = ref(null)
const errorMessages = computed(() => [...new Set(Object.values(errors.value).filter(Boolean))])

function showErrors(nextErrors) {
  errors.value = nextErrors
  nextTick(() => modalBody.value?.scrollTo({ top: 0, behavior: 'smooth' }))
}

const project = computed(() => projects.value.find(p => p.id === editingProjectId.value))
const isDeadlineExtension = computed(() => {
  const currentDueDate = project.value?.dueDate?.split('T')[0]
  return Boolean(currentDueDate && form.dueDate && form.dueDate > currentDueDate)
})
const showDelayGovernance = computed(() =>
  project.value?.deadlineState === 'overdue'
  || Boolean(form.delayReason)
  || Boolean(form.recoveryPlan),
)

watch(projectSettingsModalOpen, (isOpen) => {
  if (isOpen && project.value) {
    form.name = project.value.name
    form.description = project.value.description || ''
    form.color = project.value.color
    form.status = project.value.status
    form.customerId = project.value.customerId || ''
    form.managerId = project.value.managerId || ''
    form.startDate = project.value.startDate ? project.value.startDate.split('T')[0] : ''
    form.dueDate = project.value.dueDate ? project.value.dueDate.split('T')[0] : ''
    form.delayReason = project.value.delayReason || ''
    form.recoveryPlan = project.value.recoveryPlan || ''
    form.extensionReason = ''
    form.progress = project.value.progress
    isDeleting.value = false
    showNewCustomer.value = false
    Object.assign(customerForm, { name: '', company: '', email: '', phone: '' })
    errors.value = {}
  } else if (!isOpen) {
    editingProjectId.value = null
  }
})

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

async function submit() {
  errors.value = {}
  if (!form.name.trim()) {
    showErrors({ name: 'Vui lòng nhập tên dự án.' })
    return
  }
  const result = await updateProject(editingProjectId.value, { ...form, name: form.name.trim() })
  if (result?.success) {
    projectSettingsModalOpen.value = false
  } else {
    showErrors(result?.errors || { _general: 'Không thể cập nhật dự án.' })
  }
}

async function confirmDelete() {
  const pId = editingProjectId.value
  const deleted = await deleteProject(pId)
  if (deleted) {
    projectSettingsModalOpen.value = false
    router.push('/projects')
  }
}

// Close on escape
const onKeydown = (e) => {
  if (e.key === 'Escape') projectSettingsModalOpen.value = false
}
onMounted(() => document.addEventListener('keydown', onKeydown))
onUnmounted(() => document.removeEventListener('keydown', onKeydown))
</script>

<template>
  <Teleport to="body">
    <div v-if="projectSettingsModalOpen" class="fixed inset-0 z-[100] flex items-center justify-center p-4 sm:p-6 animate-in fade-in duration-200">
      <!-- Backdrop -->
      <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" @click="projectSettingsModalOpen = false"></div>
      
      <!-- Modal Content -->
      <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-xl flex flex-col max-h-full overflow-hidden animate-in zoom-in-95 duration-200">
        
        <!-- Header -->
        <header class="flex items-start justify-between p-6 border-b border-slate-100 bg-slate-50/50">
          <div class="flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-violet-100 text-violet-600 flex items-center justify-center shrink-0">
              <Settings class="w-6 h-6" />
            </div>
            <div>
              <h2 class="text-xl font-bold text-slate-900 mb-1">Cài đặt Dự án</h2>
              <p class="text-sm text-slate-500">Chỉnh sửa thông tin cơ bản hoặc xóa dự án.</p>
            </div>
          </div>
          <button type="button" @click="projectSettingsModalOpen = false" class="text-slate-400 hover:text-slate-700 p-2 rounded-xl hover:bg-slate-100 transition-colors">
            <X class="w-5 h-5" />
          </button>
        </header>

        <!-- Body -->
        <div ref="modalBody" class="p-6 overflow-y-auto space-y-8 custom-scrollbar flex-1">
          <div v-if="errorMessages.length" role="alert" class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            <p class="font-semibold">Không thể lưu thay đổi. Vui lòng kiểm tra:</p>
            <ul class="mt-1 list-disc space-y-0.5 pl-5">
              <li v-for="message in errorMessages" :key="message">{{ message }}</li>
            </ul>
          </div>
          <!-- Basic Info -->
          <form id="project-settings-form" @submit.prevent="submit" class="space-y-5">
            <div class="space-y-2">
              <label class="block text-sm font-semibold text-slate-700">Tên dự án *</label>
              <input v-model="form.name" placeholder="Ví dụ: Thiết kế lại website" @input="errors.name = ''" :class="['w-full bg-slate-50 border rounded-xl px-4 py-2.5 text-slate-900 focus:bg-white focus:ring-4 transition-all outline-none', errors.name ? 'border-red-300 focus:border-red-300 focus:ring-red-500/10' : 'border-slate-200 focus:border-violet-300 focus:ring-violet-500/10']" />
              <p v-if="errors.name" class="text-xs font-medium text-red-500 mt-1">{{ errors.name }}</p>
            </div>

            <div class="space-y-2">
              <label class="block text-sm font-semibold text-slate-700">Mô tả</label>
              <textarea v-model="form.description" rows="3" placeholder="Mô tả ngắn về dự án..." class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-slate-900 focus:bg-white focus:border-violet-300 focus:ring-4 focus:ring-violet-500/10 transition-all outline-none resize-none"></textarea>
            </div>

            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
              <div class="space-y-3 rounded-2xl border border-violet-100 bg-violet-50/50 p-4 sm:col-span-2">
                <div class="flex items-center justify-between gap-3">
                  <label class="flex items-center gap-2 text-sm font-semibold text-slate-700"><Building2 class="h-4 w-4 text-violet-600" />Khách hàng của dự án</label>
                  <button type="button" @click="showNewCustomer = !showNewCustomer" class="text-xs font-bold text-violet-700 hover:text-violet-900">{{ showNewCustomer ? 'Chọn khách hàng có sẵn' : '+ Tạo khách hàng mới' }}</button>
                </div>
                <select v-if="!showNewCustomer" v-model="form.customerId" class="w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm outline-none focus:border-violet-300">
                  <option value="">— Chưa gắn khách hàng —</option>
                  <option v-for="customer in customers" :key="customer.id" :value="customer.id">{{ customer.name }}{{ customer.company ? ` · ${customer.company}` : '' }}</option>
                </select>
                <div v-else class="space-y-3">
                  <p class="text-xs text-slate-500">Khách hàng mới sẽ được lưu vào hệ thống và tự động chọn thay cho khách hàng hiện tại.</p>
                  <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div><input v-model="customerForm.name" @input="errors.customer_name = ''" placeholder="Tên người liên hệ *" aria-label="Tên khách hàng" :class="['w-full rounded-xl border bg-white px-3 py-2 text-sm outline-none', errors.customer_name ? 'border-red-300 focus:border-red-400' : 'border-slate-200 focus:border-violet-300']" /><p v-if="errors.customer_name" class="mt-1 text-[11px] font-medium text-red-500">{{ errors.customer_name }}</p></div>
                    <input v-model="customerForm.company" placeholder="Công ty / tổ chức" class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm outline-none focus:border-violet-300" />
                    <div><input v-model="customerForm.email" @input="errors.email = ''" type="email" inputmode="email" autocomplete="email" placeholder="Email" aria-label="Email khách hàng" :class="['w-full rounded-xl border bg-white px-3 py-2 text-sm outline-none', errors.email ? 'border-red-300 focus:border-red-400' : 'border-slate-200 focus:border-violet-300']" /><p v-if="errors.email" class="mt-1 text-[11px] font-medium text-red-500">{{ errors.email }}</p></div>
                    <div><input v-model="customerForm.phone" @input="errors.phone = ''" inputmode="tel" autocomplete="tel" placeholder="Số điện thoại" aria-label="Số điện thoại khách hàng" :class="['w-full rounded-xl border bg-white px-3 py-2 text-sm outline-none', errors.phone ? 'border-red-300 focus:border-red-400' : 'border-slate-200 focus:border-violet-300']" /><p v-if="errors.phone" class="mt-1 text-[11px] font-medium text-red-500">{{ errors.phone }}</p></div>
                  </div>
                  <button type="button" @click="createCustomer" :disabled="savingCustomer" class="rounded-lg bg-violet-600 px-3 py-2 text-xs font-bold text-white shadow-sm disabled:opacity-50">{{ savingCustomer ? 'Đang lưu...' : 'Lưu và chọn khách hàng mới' }}</button>
                </div>
              </div>
              <div class="space-y-2">
                <label class="block text-sm font-semibold text-slate-700">Quản lý dự án</label>
                <select v-model="form.managerId" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-sm outline-none focus:border-violet-300">
                  <option value="">— Chưa phân công —</option>
                  <option v-for="member in members" :key="member.id" :value="member.id">{{ member.name }}</option>
                </select>
              </div>
              <div class="space-y-2">
                <label class="block text-sm font-semibold text-slate-700">Ngày bắt đầu</label>
                <input v-model="form.startDate" type="date" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-sm outline-none focus:border-violet-300" />
              </div>
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

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
              <div class="space-y-2">
                <label class="block text-sm font-semibold text-slate-700">Trạng thái</label>
                <select v-model="form.status" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-slate-900 focus:bg-white focus:border-violet-300 focus:ring-4 focus:ring-violet-500/10 transition-all outline-none appearance-none cursor-pointer">
                  <option value="planning">Lập kế hoạch</option>
                  <option value="active">Đang triển khai</option>
                  <option value="on_hold">Tạm dừng</option>
                  <option value="completed">Hoàn thành</option>
                </select>
              </div>
              <div class="space-y-2">
                <label class="block text-sm font-semibold text-slate-700">Hạn chót</label>
                <input v-model="form.dueDate" type="date" @input="errors.due_date = ''" :class="['w-full bg-slate-50 border rounded-xl px-4 py-2.5 text-slate-900 focus:bg-white focus:ring-4 transition-all outline-none cursor-pointer', errors.due_date ? 'border-red-300 focus:border-red-300 focus:ring-red-500/10' : 'border-slate-200 focus:border-violet-300 focus:ring-violet-500/10']" />
                <p v-if="errors.due_date" class="text-xs font-medium text-red-500">{{ errors.due_date }}</p>
              </div>
            </div>

            <div v-if="isDeadlineExtension" class="space-y-2 rounded-2xl border border-amber-200 bg-amber-50 p-4">
              <label class="block text-sm font-bold text-amber-900">Lý do gia hạn *</label>
              <textarea v-model="form.extensionReason" rows="2" placeholder="Giải thích vì sao phải thay đổi hạn chót..." class="w-full rounded-xl border border-amber-200 bg-white px-4 py-2.5 text-sm outline-none focus:border-amber-400"></textarea>
              <p class="text-xs text-amber-700">Thay đổi này sẽ được lưu vĩnh viễn trong lịch sử gia hạn.</p>
            </div>

            <div v-if="showDelayGovernance" class="space-y-4 rounded-2xl border border-rose-200 bg-rose-50/70 p-4">
              <div>
                <p class="text-sm font-bold text-rose-900">
                  Dự án {{ project?.overdueDays ? `đã quá hạn ${project.overdueDays} ngày` : 'có rủi ro chậm tiến độ' }}
                </p>
                <p class="mt-1 text-xs text-rose-700">Cập nhật nguyên nhân và kế hoạch đưa dự án trở lại đúng hướng.</p>
              </div>
              <div class="space-y-2">
                <label class="block text-sm font-semibold text-slate-700">Lý do chậm *</label>
                <textarea v-model="form.delayReason" rows="3" placeholder="Nguyên nhân khiến dự án không đạt hạn chót..." class="w-full rounded-xl border border-rose-200 bg-white px-4 py-2.5 text-sm outline-none focus:border-rose-400"></textarea>
              </div>
              <div class="space-y-2">
                <label class="block text-sm font-semibold text-slate-700">Kế hoạch khắc phục *</label>
                <textarea v-model="form.recoveryPlan" rows="3" placeholder="Hành động, người chịu trách nhiệm và thời gian dự kiến..." class="w-full rounded-xl border border-rose-200 bg-white px-4 py-2.5 text-sm outline-none focus:border-rose-400"></textarea>
              </div>
            </div>

            <div class="space-y-3">
              <label class="flex justify-between text-sm font-semibold text-slate-700">
                <span>Tiến độ</span>
                <span class="text-violet-600">{{ form.progress }}%</span>
              </label>
              <input v-model="form.progress" type="range" min="0" max="100" step="5" class="w-full accent-violet-600 h-2 bg-slate-200 rounded-lg appearance-none cursor-pointer" />
            </div>
          </form>

          <hr class="border-slate-100" />

          <!-- Danger Zone -->
          <div>
            <h3 class="text-sm font-bold text-rose-600 flex items-center gap-2 mb-3">
              <AlertTriangle class="w-4 h-4" /> Vùng nguy hiểm
            </h3>
            <div class="p-4 bg-rose-50 border border-rose-100 rounded-xl flex items-center justify-between gap-4">
              <div>
                <strong class="block text-sm text-rose-900">Xóa dự án này</strong>
                <span class="text-xs text-rose-700">Dự án sẽ biến mất khỏi giao diện hoạt động nhưng dữ liệu vẫn được giữ lại. Bạn có thể khôi phục trong 30 ngày.</span>
              </div>
              <button 
                v-if="!isDeleting" 
                @click="isDeleting = true" 
                class="px-4 py-2 bg-white border border-rose-200 text-rose-600 text-sm font-medium rounded-lg hover:bg-rose-50 transition-colors shrink-0"
              >
                Xóa dự án
              </button>
              <div v-else class="flex gap-2 shrink-0">
                <button @click="isDeleting = false" class="px-3 py-2 bg-white text-slate-600 text-sm font-medium rounded-lg hover:bg-slate-50">Hủy</button>
                <button @click="confirmDelete" class="px-3 py-2 bg-rose-600 text-white text-sm font-medium rounded-lg shadow-sm hover:bg-rose-700">Xác nhận xóa</button>
              </div>
            </div>
          </div>
        </div>

        <!-- Footer -->
        <footer class="p-5 border-t border-slate-100 flex justify-end gap-3 bg-slate-50/50">
          <button type="button" @click="projectSettingsModalOpen = false" class="px-5 py-2.5 bg-white border border-slate-200 rounded-xl text-sm font-medium text-slate-700 hover:bg-slate-50 transition-colors">
            Đóng
          </button>
          <button form="project-settings-form" type="submit" class="flex items-center gap-2 px-5 py-2.5 bg-gradient-to-r from-violet-500 to-indigo-600 text-white rounded-xl text-sm font-medium shadow-md shadow-violet-500/25 hover:shadow-premium transition-all">
            <Check class="w-4 h-4" /> Lưu thay đổi
          </button>
        </footer>
      </div>
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
