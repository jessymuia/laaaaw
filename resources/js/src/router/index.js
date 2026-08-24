import { createRouter, createWebHistory } from 'vue-router';

import Home from '../views/dashboard.vue';
import store from '../store';

// UI-1: template purge. This router previously carried ~83 routes for
// demo apps (chat, mailbox, scrumboard, notes, todo, a duplicate demo
// invoice module), component/element/form/table style-guide pages, and
// three alternate dashboards (index.vue, index1.0.vue, index2.vue) — none
// of them linked from the actual sidebar/header nav (verified against
// components/layout/sidebar.vue and header.vue). Only the firm's real
// modules and the two routes actually linked from the header dropdown
// (profile, account settings) remain below.

const routes = [
    // dashboard
    { path: '/', name: 'Home', component: Home },

    // users
    {
        path: '/users',
        name: 'users',
        component: () => import(/* webpackChunkName: "users" */ '../views/menu/users/index.vue'),
    },

    // hearing types
    {
        path: '/hearingtypes',
        name: 'hearing-types',
        component: () => import(/* webpackChunkName: "hearings" */ '../views/menu/hearings/types.vue'),
    },

    // expense categories
    {
        path: '/expense-categories',
        name: 'expense-categories',
        component: () => import(/* webpackChunkName: "expenses" */ '../views/menu/expenses/categories.vue'),
    },

    // courts / court types
    {
        path: '/courts',
        name: 'courts',
        component: () => import(/* webpackChunkName: "courts" */ '../views/menu/court/index.vue'),
    },
    {
        path: '/courttypes',
        name: 'court-types',
        component: () => import(/* webpackChunkName: "courts" */ '../views/menu/court/types.vue'),
    },

    // expenses
    {
        path: '/expenses',
        name: 'expenses',
        component: () => import(/* webpackChunkName: "expenses" */ '../views/menu/expenses/index.vue'),
    },

    // hearings
    {
        path: '/hearings',
        name: 'hearings',
        component: () => import(/* webpackChunkName: "hearings" */ '../views/menu/hearings/index.vue'),
    },
    {
        path: '/hearings/calendar',
        name: 'hearings-calendar',
        component: () => import(/* webpackChunkName: "hearings" */ '../views/menu/hearings/calendar.vue'),
        meta: {
            authRequired: true,
        },
    },

    // tasks
    {
        path: '/tasks',
        name: 'tasks',
        component: () => import(/* webpackChunkName: "tasks" */ '../views/menu/tasks/index.vue'),
    },

    // clients
    {
        path: '/clients',
        name: 'clients',
        component: () => import(/* webpackChunkName: "clients" */ '../views/menu/clients/list-clients.vue'),
        meta: {
            authRequired: true,
        },
    },
    {
        path: '/clients/:id',
        name: 'client-details',
        component: () => import(/* webpackChunkName: "clients" */ '../views/menu/clients/view-client.vue'),
        meta: {
            authRequired: true,
        },
    },

    // cases
    {
        path: '/cases',
        name: 'cases',
        component: () => import(/* webpackChunkName: "cases" */ '../views/menu/cases/list-cases.vue'),
        meta: {
            authRequired: true,
        },
    },
    {
        path: '/view-case',
        name: 'case-details',
        component: () => import(/* webpackChunkName: "cases" */ '../views/menu/cases/view-case.vue'),
        meta: {
            authRequired: true,
        },
    },
    {
        path: '/cases/create',
        name: 'create-case',
        component: () => import(/* webpackChunkName: "cases" */ '../views/menu/cases/create-case.vue'),
        meta: {
            authRequired: true,
        },
    },

    // invoices
    {
        path: '/invoices',
        name: 'invoices',
        component: () => import(/* webpackChunkName: "invoices" */ '../views/menu/invoices/index.vue'),
        meta: {
            authRequired: true,
        },
    },
    {
        path: '/view-invoice',
        name: 'invoice-details',
        component: () => import(/* webpackChunkName: "invoices" */ '../views/menu/invoices/items/index.vue'),
        meta: {
            authRequired: true,
        },
    },
    {
        path: '/invoice-preview',
        name: 'invoice-preview-d',
        component: () => import(/* webpackChunkName: "invoices" */ '../views/menu/invoices/preview.vue'),
        meta: {
            authRequired: true,
        },
    },

    // roles
    {
        path: '/roles',
        name: 'roles',
        component: () => import(/* webpackChunkName: "roles" */ '../views/menu/roles/index.vue'),
        meta: {
            authRequired: true,
        },
    },

    // account settings (linked from the header dropdown) — the dead
    // '/users/profile' route (static demo content: "Skills", "Bio",
    // "GitHub Contributor" — leftover admin-template bloat unrelated to
    // this app, with no real data behind it at all) has been removed;
    // account_setting.vue now covers real profile + password settings.
    {
        path: '/users/account-setting',
        name: 'account-setting',
        component: () => import(/* webpackChunkName: "profile" */ '../views/users/account_setting.vue'),
        meta: {
            authRequired: true,
        },
    },

    // auth
    {
        path: '/login',
        name: 'login',
        component: () => import(/* webpackChunkName: "auth-login" */ '../views/auth/login.vue'),
        meta: { layout: 'auth' },
    },
    {
        path: '/auth/reset/:token',
        name: 'reset-password-token',
        component: () => import(/* webpackChunkName: "auth-reset" */ '../views/auth/resetPassword.vue'),
        meta: { layout: 'auth' },
    },
    {
        path: '/auth/pass-recovery',
        name: 'pass-recovery',
        component: () => import(/* webpackChunkName: "auth-pass-recovery" */ '../views/auth/pass_recovery.vue'),
        meta: { layout: 'auth' },
    },

    // error pages (UI-2: dedicated 403 alongside the existing 404/500/503)
    {
        path: '/pages/error403',
        name: 'error403',
        component: () => import(/* webpackChunkName: "pages-error403" */ '../views/pages/error403.vue'),
        meta: { layout: 'auth' },
    },
    {
        path: '/pages/error404',
        name: 'error404',
        component: () => import(/* webpackChunkName: "pages-error404" */ '../views/pages/error404.vue'),
        meta: { layout: 'auth' },
    },
    {
        path: '/pages/error500',
        name: 'error500',
        component: () => import(/* webpackChunkName: "pages-error500" */ '../views/pages/error500.vue'),
        meta: { layout: 'auth' },
    },
    {
        path: '/pages/error503',
        name: 'error503',
        component: () => import(/* webpackChunkName: "pages-error503" */ '../views/pages/error503.vue'),
        meta: { layout: 'auth' },
    },

    // catch-all: any unmatched path lands on the 404 page rather than a
    // blank screen.
    {
        path: '/:pathMatch(.*)*',
        name: 'not-found',
        component: () => import(/* webpackChunkName: "pages-error404" */ '../views/pages/error404.vue'),
        meta: { layout: 'auth' },
    },
];

const router = new createRouter({
    history: createWebHistory(),
    linkExactActiveClass: 'active',
    routes,
    scrollBehavior(to, from, savedPosition) {
        if (savedPosition) {
            return savedPosition;
        } else {
            return { left: 0, top: 0 };
        }
    },
});

router.beforeEach((to, from, next) => {
    if (to.meta && to.meta.layout && to.meta.layout == 'auth') {
        store.commit('setLayout', 'auth');
    } else {
        store.commit('setLayout', 'app');
    }
    const authRequired = to.matched.some((route) => route.meta.authRequired);
    if (!authRequired) return next();

    // Client-side check only: a UX shortcut to avoid an unnecessary round
    // trip when we already know there's no session. It is not a security
    // boundary — every actual permission/authorization check happens
    // server-side (see SEC-3/SEC-8), and this flag holds no session
    // secret, just a login/display marker, since the real session lives
    // in an httpOnly cookie.
    if (localStorage.getItem('user')) {
        next();
    } else {
        next({ name: 'login', query: { redirectFrom: to.fullPath } });
    }
});

export default router;
