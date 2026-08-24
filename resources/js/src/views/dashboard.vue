<template>
    <div class="firm-dashboard">
        <teleport to="#breadcrumb">
            <ul class="navbar-nav flex-row">
                <li>
                    <div class="page-header">
                        <nav class="breadcrumb-one" aria-label="breadcrumb">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="javascript:;">Dashboard</a></li>
                                <li class="breadcrumb-item active" aria-current="page"><span>Overview</span></li>
                            </ol>
                        </nav>
                    </div>
                </li>
            </ul>
        </teleport>

        <!-- Loading state (UI-3) -->
        <div v-if="loading" class="dashboard-loading">
            <div class="skeleton-row" v-for="n in 4" :key="n"></div>
        </div>

        <!-- Error state (UI-3) -->
        <div v-else-if="loadError" class="dashboard-error">
            <p>Couldn't load your dashboard.</p>
            <button type="button" class="btn btn-primary" @click="fetchDashboard">Retry</button>
        </div>

        <template v-else>
            <!-- Top-line stats -->
            <div class="stat-cards">
                <div class="stat-card">
                    <div class="stat-value">{{ data.stats.total_clients }}</div>
                    <div class="stat-label">Clients</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">{{ data.stats.open_cases }}</div>
                    <div class="stat-label">Open Cases</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">{{ formatMoney(data.unbilled_total) }}</div>
                    <div class="stat-label">Unbilled Work</div>
                </div>
                <div class="stat-card" :class="{ warn: data.outstanding_total > 0 }">
                    <div class="stat-value">{{ formatMoney(data.outstanding_total) }}</div>
                    <div class="stat-label">Outstanding Invoices</div>
                </div>
            </div>

            <!-- Firm-wide export (FUN-4, admin-only) -->
            <div v-if="isAdmin" class="admin-export-bar">
                <div class="admin-export-text">
                    <strong>Firm-wide data export</strong>
                    <span>Download every module's data as a single zip of CSV files — clients, cases, hearings, invoices, payments, expenses, time entries, and tasks.</span>
                </div>
                <button type="button" class="btn-firm-export" @click="exportFirmData" :disabled="exportingFirm">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                        <polyline points="7 10 12 15 17 10"></polyline>
                        <line x1="12" y1="15" x2="12" y2="3"></line>
                    </svg>
                    {{ exportingFirm ? 'Preparing export...' : 'Export All Firm Data' }}
                </button>
            </div>

            <div class="dashboard-grid">
                <!-- Upcoming hearings -->
                <div class="dashboard-panel">
                    <div class="panel-header">
                        <h3>Upcoming Hearings <span class="muted">(next 7 days)</span></h3>
                    </div>
                    <div v-if="!data.upcoming_hearings.length" class="empty-state">
                        No hearings scheduled in the next 7 days.
                    </div>
                    <ul v-else class="panel-list">
                        <li v-for="h in data.upcoming_hearings" :key="h.id">
                            <div class="list-main">
                                <span class="list-title">{{ h.case }}</span>
                                <span class="list-sub">{{ h.court }}</span>
                            </div>
                            <span class="list-badge" :class="h.days_away <= 1 ? 'urgent' : 'default'">{{ h.date }}</span>
                        </li>
                    </ul>
                </div>

                <!-- Tasks due -->
                <div class="dashboard-panel">
                    <div class="panel-header">
                        <h3>Tasks Due</h3>
                    </div>
                    <div v-if="!data.tasks_due.length" class="empty-state">
                        No open tasks. Nice work.
                    </div>
                    <ul v-else class="panel-list">
                        <li v-for="t in data.tasks_due" :key="t.id">
                            <div class="list-main">
                                <span class="list-title">{{ t.title }}</span>
                                <span class="list-sub">{{ t.assignee }}</span>
                            </div>
                            <span class="list-badge" :class="t.overdue ? 'urgent' : 'default'">{{ t.due_date }}</span>
                        </li>
                    </ul>
                </div>

                <!-- Unbilled work by case -->
                <div class="dashboard-panel">
                    <div class="panel-header">
                        <h3>Unbilled Work by Case</h3>
                    </div>
                    <div v-if="!data.unbilled_by_case.length" class="empty-state">
                        Everything billable has been billed.
                    </div>
                    <ul v-else class="panel-list">
                        <li v-for="u in data.unbilled_by_case" :key="u.case_id">
                            <div class="list-main">
                                <span class="list-title">{{ u.case }}</span>
                                <span class="list-sub">{{ u.hours }} hrs</span>
                            </div>
                            <span class="list-badge default">{{ formatMoney(u.amount) }}</span>
                        </li>
                    </ul>
                </div>

                <!-- Outstanding invoices -->
                <div class="dashboard-panel">
                    <div class="panel-header">
                        <h3>Outstanding Invoices</h3>
                    </div>
                    <div v-if="!data.outstanding_invoices.length" class="empty-state">
                        No outstanding balances.
                    </div>
                    <ul v-else class="panel-list">
                        <li v-for="inv in data.outstanding_invoices" :key="inv.id">
                            <div class="list-main">
                                <span class="list-title">{{ inv.invoice_number }} — {{ inv.client }}</span>
                                <span class="list-sub">Due {{ inv.due_date }}</span>
                            </div>
                            <span class="list-badge" :class="inv.overdue ? 'urgent' : 'default'">{{ formatMoney(inv.outstanding) }}</span>
                        </li>
                    </ul>
                </div>

                <!-- Recent case activity -->
                <div class="dashboard-panel wide">
                    <div class="panel-header">
                        <h3>Recent Case Activity</h3>
                    </div>
                    <div v-if="!data.recent_cases.length" class="empty-state">
                        No case activity yet.
                    </div>
                    <ul v-else class="panel-list">
                        <li v-for="c in data.recent_cases" :key="c.id">
                            <div class="list-main">
                                <span class="list-title">{{ c.case_number }} — {{ c.client }}</span>
                                <span class="list-sub">{{ c.status }}</span>
                            </div>
                            <span class="list-badge default">{{ c.updated_at }}</span>
                        </li>
                    </ul>
                </div>
            </div>
        </template>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import axios from '../api';
import { useMeta } from '@/composables/use-meta';
import { useToast } from '@/composables/use-toast';
import { useFileDownload } from '@/composables/use-file-download';

useMeta({ title: 'Dashboard' });

const { showMessage } = useToast();
const { downloadFile } = useFileDownload();

// Client-side check only, for showing/hiding the export button — the
// real enforcement is server-side via the EXPORT_FIRM_DATA permission
// (see ExportController::fullFirmExport). Mirrors the same pattern used
// for other role-based UI throughout the app: a UX convenience, not a
// security boundary.
const isAdmin = ref(false);
try {
    const storedUser = JSON.parse(localStorage.getItem('user') || 'null');
    isAdmin.value = Array.isArray(storedUser?.roles) && storedUser.roles.includes('admin');
} catch (e) {
    isAdmin.value = false;
}

const exportingFirm = ref(false);

const exportFirmData = () => {
    exportingFirm.value = true;
    downloadFile('/api/export/firm-data', `firm-export-${new Date().toISOString().slice(0, 10)}.zip`)
        .finally(() => { exportingFirm.value = false; });
};

const loading = ref(true);
const loadError = ref(false);
const data = ref({
    stats: { total_clients: 0, total_cases: 0, open_cases: 0 },
    upcoming_hearings: [],
    tasks_due: [],
    unbilled_total: 0,
    unbilled_by_case: [],
    outstanding_invoices: [],
    outstanding_total: 0,
    recent_cases: [],
});

const formatMoney = (value) => {
    const n = Number(value) || 0;
    return n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

const fetchDashboard = () => {
    loading.value = true;
    loadError.value = false;

    axios.get('/api/dashboard')
        .then((response) => {
            data.value = response.data.data;
        })
        .catch((error) => {
            loadError.value = true;
            showMessage('Could not load dashboard data.', 'error');
            console.error(error);
        })
        .finally(() => {
            loading.value = false;
        });
};

onMounted(() => {
    fetchDashboard();
});
</script>

<style lang="scss" scoped>
.firm-dashboard {
    padding: 20px;
}

// Loading skeleton (UI-3)
.dashboard-loading {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.skeleton-row {
    height: 100px;
    border-radius: 12px;
    background: linear-gradient(90deg, #eee 25%, #f5f5f5 37%, #eee 63%);
    background-size: 400% 100%;
    animation: skeleton-loading 1.4s ease infinite;
}

@keyframes skeleton-loading {
    0% { background-position: 100% 50%; }
    100% { background-position: 0 50%; }
}

.dashboard-error {
    text-align: center;
    padding: 60px 20px;
    color: #6b7280;
}

.stat-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
    margin-bottom: 24px;
}

.admin-export-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
    flex-wrap: wrap;
    background: rgba(2, 132, 199, 0.06);
    border: 1px solid rgba(2, 132, 199, 0.15);
    border-radius: 12px;
    padding: 16px 20px;
    margin-bottom: 24px;
}

.admin-export-text {
    display: flex;
    flex-direction: column;
    gap: 2px;

    strong {
        font-size: 14px;
    }

    span {
        font-size: 12px;
        color: #6b7280;
    }
}

.btn-firm-export {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 20px;
    background: var(--primary-600);
    color: white;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    font-size: 13px;
    cursor: pointer;
    white-space: nowrap;

    &:hover:not(:disabled) {
        background: var(--primary-800);
    }

    &:disabled {
        opacity: 0.6;
        cursor: not-allowed;
    }
}

.stat-card {
    background: var(--background, #fff);
    border: 1px solid rgba(0, 0, 0, 0.08);
    border-radius: 12px;
    padding: 20px;

    &.warn .stat-value {
        color: #e7515a;
    }
}

.stat-value {
    font-size: 28px;
    font-weight: 700;
}

.stat-label {
    font-size: 13px;
    color: #6b7280;
    margin-top: 4px;
}

.dashboard-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
    gap: 16px;
}

.dashboard-panel {
    background: var(--background, #fff);
    border: 1px solid rgba(0, 0, 0, 0.08);
    border-radius: 12px;
    padding: 16px 20px;

    &.wide {
        grid-column: 1 / -1;
    }
}

.panel-header h3 {
    font-size: 15px;
    font-weight: 600;
    margin: 0 0 12px;

    .muted {
        font-weight: 400;
        color: #6b7280;
        font-size: 12px;
    }
}

.empty-state {
    padding: 20px 0;
    text-align: center;
    color: #6b7280;
    font-size: 13px;
}

.panel-list {
    list-style: none;
    margin: 0;
    padding: 0;

    li {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 10px 0;
        border-bottom: 1px solid rgba(0, 0, 0, 0.06);

        &:last-child {
            border-bottom: none;
        }
    }
}

.list-main {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.list-title {
    font-size: 13px;
    font-weight: 500;
}

.list-sub {
    font-size: 12px;
    color: #6b7280;
}

.list-badge {
    font-size: 12px;
    font-weight: 600;
    padding: 4px 10px;
    border-radius: 999px;
    white-space: nowrap;

    &.default {
        background: rgba(2, 132, 199, 0.1);
        color: var(--primary-600);
    }

    &.urgent {
        background: rgba(231, 81, 90, 0.1);
        color: #e7515a;
    }
}
</style>
