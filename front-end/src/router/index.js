import { createRouter, createWebHistory } from 'vue-router'
import DashboardView from '../views/DashboardView.vue'
import ProjectsView from '../views/ProjectsView.vue'
import ProjectDetailView from '../views/ProjectDetailView.vue'
import TasksView from '../views/TasksView.vue'
import TeamView from '../views/TeamView.vue'
import CalendarView from '../views/CalendarView.vue'
import SettingsView from '../views/SettingsView.vue'
import NotFoundView from '../views/NotFoundView.vue'

const routes = [
  { path: '/', name: 'dashboard', component: DashboardView, meta: { title: 'Tổng quan' } },
  { path: '/projects', name: 'projects', component: ProjectsView, meta: { title: 'Dự án' } },
  { path: '/projects/:id', name: 'project-detail', component: ProjectDetailView, meta: { title: 'Chi tiết dự án' } },
  { path: '/tasks', name: 'tasks', component: TasksView, meta: { title: 'Nhiệm vụ' } },
  { path: '/team', name: 'team', component: TeamView, meta: { title: 'Nhóm' } },
  { path: '/calendar', name: 'calendar', component: CalendarView, meta: { title: 'Lịch' } },
  { path: '/settings', name: 'settings', component: SettingsView, meta: { title: 'Cài đặt' } },
  { path: '/:pathMatch(.*)*', name: 'not-found', component: NotFoundView, meta: { title: 'Không tìm thấy' } },
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
