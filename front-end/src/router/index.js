import { createRouter, createWebHistory } from 'vue-router'
import { getStoredUser, hasAuthSession } from '../services/api'
const routes = [
  { path: '/login', name: 'login', component: () => import('../views/LoginView.vue'), meta: { title: 'Đăng nhập', public: true } },
  { path: '/complete-profile', name: 'complete-profile', component: () => import('../views/CompleteProfileView.vue'), meta: { title: 'Hoàn tất hồ sơ', onboarding: true } },
  { path: '/my-work', name: 'my-work', component: () => import('../views/MyWorkView.vue'), meta: { title: 'Công việc của tôi' } },
  { path: '/', name: 'dashboard', component: () => import('../views/DashboardView.vue'), meta: { title: 'Tổng quan' } },
  { path: '/projects', name: 'projects', component: () => import('../views/ProjectsView.vue'), meta: { title: 'Dự án' } },
  { path: '/projects/:id', name: 'project-detail', component: () => import('../views/ProjectDetailView.vue'), meta: { title: 'Chi tiết dự án' } },
  { path: '/tasks', name: 'tasks', component: () => import('../views/TasksView.vue'), meta: { title: 'Nhiệm vụ' } },
  { path: '/team', name: 'team', component: () => import('../views/TeamView.vue'), meta: { title: 'Nhóm' } },
  { path: '/calendar', name: 'calendar', component: () => import('../views/CalendarView.vue'), meta: { title: 'Lịch' } },
  { path: '/reports', name: 'reports', component: () => import('../views/ReportsView.vue'), meta: { title: 'Báo cáo', roles: ['admin', 'project_manager', 'manager'] } },
  { path: '/trash', name: 'trash', component: () => import('../views/TrashView.vue'), meta: { title: 'Thùng rác' } },
  { path: '/settings', name: 'settings', component: () => import('../views/SettingsView.vue'), meta: { title: 'Cài đặt' } },
  { path: '/:pathMatch(.*)*', name: 'not-found', component: () => import('../views/NotFoundView.vue'), meta: { title: 'Không tìm thấy' } },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
  scrollBehavior: () => ({ top: 0 }),
})

router.beforeEach((to) => {
  if (!to.meta.public && !hasAuthSession()) return { name: 'login', query: { redirect: to.fullPath } }
  if (!hasAuthSession()) return true

  const user = getStoredUser()
  const requiresProfileCompletion = !user?.profile_completed_at
  if (requiresProfileCompletion && to.name !== 'complete-profile') return { name: 'complete-profile' }
  if (!requiresProfileCompletion && ['login', 'complete-profile'].includes(to.name)) return { name: 'dashboard' }
  if (to.meta.roles && !to.meta.roles.includes(user?.role)) return { name: 'dashboard' }
  return true
})

router.afterEach((to) => {
  document.title = `${to.meta.title} — RingNet`
})

export default router
