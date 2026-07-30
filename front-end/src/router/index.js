import { createRouter, createWebHistory } from 'vue-router'
const routes = [
  { path: '/', name: 'dashboard', component: () => import('../views/DashboardView.vue'), meta: { title: 'Tổng quan' } },
  { path: '/projects', name: 'projects', component: () => import('../views/ProjectsView.vue'), meta: { title: 'Dự án' } },
  { path: '/projects/:id', name: 'project-detail', component: () => import('../views/ProjectDetailView.vue'), meta: { title: 'Chi tiết dự án' } },
  { path: '/tasks', name: 'tasks', component: () => import('../views/TasksView.vue'), meta: { title: 'Nhiệm vụ' } },
  { path: '/team', name: 'team', component: () => import('../views/TeamView.vue'), meta: { title: 'Nhóm' } },
  { path: '/calendar', name: 'calendar', component: () => import('../views/CalendarView.vue'), meta: { title: 'Lịch' } },
  { path: '/trash', name: 'trash', component: () => import('../views/TrashView.vue'), meta: { title: 'Thùng rác' } },
  { path: '/settings', name: 'settings', component: () => import('../views/SettingsView.vue'), meta: { title: 'Cài đặt' } },
  { path: '/:pathMatch(.*)*', name: 'not-found', component: () => import('../views/NotFoundView.vue'), meta: { title: 'Không tìm thấy' } },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
  scrollBehavior: () => ({ top: 0 }),
})

router.afterEach((to) => {
  document.title = `${to.meta.title} — RingNet`
})

export default router
