import { createApp } from 'vue';
import { createRouter, createWebHistory } from 'vue-router';
import AppLayout from '@/components/AppLayout.vue';
import ScanPage from '@/pages/ScanPage.vue';
import ScansHistoryPage from '@/pages/ScansHistoryPage.vue';

const router = createRouter({
    history: createWebHistory(),
    routes: [
        { path: '/', name: 'scan', component: ScanPage },
        { path: '/scans', name: 'history', component: ScansHistoryPage },
        { path: '/:pathMatch(.*)*', redirect: '/' },
    ],
});

createApp(AppLayout).use(router).mount('#app');
