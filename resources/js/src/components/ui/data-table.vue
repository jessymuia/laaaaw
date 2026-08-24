<template>
    <div class="app-data-table">
        <!-- Search (server-driven, UI-5/journey 8) -->
        <div v-if="searchableColumns.length" class="table-search-row">
            <input
                type="text"
                class="table-search-input"
                data-testid="table-search-input"
                placeholder="Search..."
                v-model="searchTerm"
                @input="onSearchInput"
            />
        </div>

        <!-- Loading (UI-3) -->
        <SkeletonLoader v-if="loading && rows.length === 0" :rows="4" />

        <!-- Error (UI-3) -->
        <div v-else-if="error" class="table-error">
            <p>Couldn't load this data.</p>
            <button type="button" class="btn btn-primary" @click="fetchPage(currentPage)">Retry</button>
        </div>

        <!-- Empty (UI-3) -->
        <EmptyState
            v-else-if="rows.length === 0"
            :title="emptyTitle"
            :description="emptyDescription"
            :action-label="emptyActionLabel"
            @action="$emit('empty-action')"
        />

        <template v-else>
            <div class="table-wrapper">
                <table class="app-table">
                    <thead>
                        <tr>
                            <th
                                v-for="col in columns"
                                :key="col.key"
                                :class="{ sortable: sortableColumns.includes(col.key) }"
                                :data-testid="`column-header-${col.key}`"
                                :tabindex="sortableColumns.includes(col.key) ? 0 : undefined"
                                :role="sortableColumns.includes(col.key) ? 'button' : undefined"
                                :aria-sort="sortColumn === col.key ? (sortDirection === 'asc' ? 'ascending' : 'descending') : (sortableColumns.includes(col.key) ? 'none' : undefined)"
                                @click="sortableColumns.includes(col.key) && toggleSort(col.key)"
                                @keydown.enter="sortableColumns.includes(col.key) && toggleSort(col.key)"
                                @keydown.space.prevent="sortableColumns.includes(col.key) && toggleSort(col.key)"
                            >
                                {{ col.label }}
                                <span v-if="sortableColumns.includes(col.key)" class="sort-indicator">
                                    {{ sortColumn === col.key ? (sortDirection === 'asc' ? '▲' : '▼') : '⇅' }}
                                </span>
                            </th>
                            <th v-if="$slots.actions" class="actions-col">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in rows" :key="row[rowKey]">
                            <td v-for="col in columns" :key="col.key" :data-label="col.label">
                                <slot :name="`cell-${col.key}`" :row="row">
                                    {{ row[col.key] }}
                                </slot>
                            </td>
                            <td v-if="$slots.actions" class="actions-col" data-label="Actions">
                                <slot name="actions" :row="row"></slot>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Server-driven pagination (UI-5) -->
            <div v-if="lastPage > 1" class="table-pagination">
                <span class="pagination-summary" data-testid="pagination-summary">
                    Showing {{ from }}–{{ to }} of {{ total }}
                </span>
                <div class="pagination-controls">
                    <button type="button" data-testid="pagination-prev" :disabled="currentPage <= 1" @click="fetchPage(currentPage - 1)">Prev</button>
                    <span class="pagination-page" data-testid="pagination-page-label">Page {{ currentPage }} of {{ lastPage }}</span>
                    <button type="button" data-testid="pagination-next" :disabled="currentPage >= lastPage" @click="fetchPage(currentPage + 1)">Next</button>
                </div>
            </div>
        </template>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import axios from '@/api';
import SkeletonLoader from './skeleton-loader.vue';
import EmptyState from './empty-state.vue';

/**
 * UI-4/UI-5: the one shared, server-driven table component. Replaces the
 * v-tables-3 pattern (load every row into the browser, paginate
 * client-side — see FUN-1) with real server-side pagination, sorting,
 * and searching, using the `?page=`/`?per_page=`/`?sort=`/`?direction=`/
 * `?search=` params the backend's paginatedOrFull() helper understands
 * (see app/Http/Controllers/Controller.php) — every one of these is a
 * real request to the server, not a client-side operation over an
 * already-fully-loaded dataset.
 *
 * Per-column custom rendering: pass a `#cell-<columnKey>` slot, e.g.
 *   <template #cell-status="{ row }"><Badge :status="row.status" /></template>
 * Row actions: pass an `#actions` slot, e.g.
 *   <template #actions="{ row }"><button @click="edit(row)">Edit</button></template>
 *
 * sortableColumns / searchableColumns must match the whitelist the
 * backend controller declared in its own paginatedOrFull() call, or the
 * server will simply ignore the param (see Controller.php's own
 * whitelisting comment for why).
 *
 * UI-5: table state (page/sort/direction/search) is mirrored to the
 * URL's query string via router.replace() (not push — every keystroke
 * or page click shouldn't add a back-button stop), so a filtered/sorted
 * view is a real, shareable/bookmarkable/refreshable link, not just
 * in-memory component state that resets on reload. On mount, any of
 * those params already present in the URL (e.g. from a shared link)
 * seed the table's initial state instead of always starting at page 1.
 * Only one DataTable is ever rendered per route in this app, so these
 * query keys aren't namespaced per-instance — add a `queryPrefix` prop
 * before ever putting two DataTables on the same page.
 */
const props = defineProps({
    fetchUrl: { type: String, required: true },
    columns: { type: Array, required: true }, // [{ key, label }]
    rowKey: { type: String, default: 'id' },
    perPage: { type: Number, default: 10 },
    emptyTitle: { type: String, default: 'Nothing here yet' },
    emptyDescription: { type: String, default: '' },
    emptyActionLabel: { type: String, default: '' },
    sortableColumns: { type: Array, default: () => [] },
    searchableColumns: { type: Array, default: () => [] },
});

defineEmits(['empty-action']);

const route = useRoute();
const router = useRouter();

const rows = ref([]);
const loading = ref(true);
const error = ref(false);
const currentPage = ref(1);
const lastPage = ref(1);
const total = ref(0);
const from = ref(0);
const to = ref(0);
const sortColumn = ref(null);
const sortDirection = ref('asc');
const searchTerm = ref('');

let searchDebounceTimer = null;

const isSortColumnAllowed = (column) => props.sortableColumns.includes(column);

const syncUrl = (page) => {
    const query = { ...route.query };

    if (page && page > 1) {
        query.page = String(page);
    } else {
        delete query.page;
    }

    if (sortColumn.value && isSortColumnAllowed(sortColumn.value)) {
        query.sort = sortColumn.value;
        query.direction = sortDirection.value;
    } else {
        delete query.sort;
        delete query.direction;
    }

    if (searchTerm.value) {
        query.search = searchTerm.value;
    } else {
        delete query.search;
    }

    router.replace({ query });
};

const fetchPage = (page = 1, options = {}) => {
    loading.value = true;
    error.value = false;

    const params = { page, per_page: props.perPage };
    if (sortColumn.value) {
        params.sort = sortColumn.value;
        params.direction = sortDirection.value;
    }
    if (searchTerm.value) {
        params.search = searchTerm.value;
    }

    axios.get(props.fetchUrl, { params })
        .then((response) => {
            const paginator = response.data.data;
            rows.value = paginator.data ?? [];
            currentPage.value = paginator.current_page ?? 1;
            lastPage.value = paginator.last_page ?? 1;
            total.value = paginator.total ?? rows.value.length;
            from.value = paginator.from ?? 0;
            to.value = paginator.to ?? rows.value.length;

            if (!options.skipUrlSync) {
                syncUrl(currentPage.value);
            }
        })
        .catch(() => {
            error.value = true;
        })
        .finally(() => {
            loading.value = false;
        });
};

const toggleSort = (column) => {
    if (sortColumn.value === column) {
        sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortColumn.value = column;
        sortDirection.value = 'asc';
    }
    fetchPage(1); // a new sort always starts back at page 1
};

const onSearchInput = () => {
    clearTimeout(searchDebounceTimer);
    searchDebounceTimer = setTimeout(() => fetchPage(1), 300);
};

// UI-4/UI-5: refresh() takes an optional target page — most callers
// just want "reload whatever page I'm looking at" (after an edit or
// delete, where the affected row is presumably still on this page), but
// a freshly *created* record always sorts to page 1 under the
// id-DESC ordering every controller uses, regardless of which page the
// table happened to be on when the create happened. Without an explicit
// page-1 jump, creating a record while on page 2+ silently refreshed
// the wrong page and the new record was invisible until the user
// manually paged back — looked exactly like the create had silently
// failed even though it succeeded.
defineExpose({ refresh: (page) => fetchPage(page ?? currentPage.value, { skipUrlSync: true }) });

onMounted(() => {
    // UI-5: seed initial state from a shared/bookmarked URL, but only
    // honor sort/search values that are actually in this table's own
    // whitelist — a stale or hand-edited query string shouldn't be able
    // to request a sort column the backend would silently reject anyway
    // (see Controller.php's whitelist comment), or silently desync the
    // header's sort-indicator arrow from what's actually being sent.
    const initialPage = Number.parseInt(route.query.page, 10);
    const initialSort = typeof route.query.sort === 'string' ? route.query.sort : null;
    const initialDirection = route.query.direction === 'desc' ? 'desc' : 'asc';
    const initialSearch = typeof route.query.search === 'string' ? route.query.search : '';

    if (initialSort && props.sortableColumns.includes(initialSort)) {
        sortColumn.value = initialSort;
        sortDirection.value = initialDirection;
    }
    if (initialSearch && props.searchableColumns.length) {
        searchTerm.value = initialSearch;
    }

    fetchPage(Number.isInteger(initialPage) && initialPage > 0 ? initialPage : 1);
});
</script>

<style lang="scss" scoped>
.table-search-row {
    margin-bottom: 12px;
}

.table-search-input {
    width: 100%;
    max-width: 320px;
    padding: 8px 12px;
    border: 1px solid var(--border-color);
    border-radius: 8px;
    font-size: 13px;

    &:focus {
        outline: none;
        border-color: var(--primary-600);
    }
}

.table-error {
    text-align: center;
    padding: 40px 20px;
    color: var(--text-tertiary);
}

.table-wrapper {
    overflow-x: auto;
}

.app-table {
    width: 100%;
    border-collapse: collapse;

    th, td {
        padding: 12px 16px;
        text-align: left;
        border-bottom: 1px solid var(--border-light);
        font-size: 13px;
    }

    th {
        font-weight: 600;
        color: var(--text-secondary);
        text-transform: uppercase;
        font-size: 11px;
        letter-spacing: 0.03em;

        &.sortable {
            cursor: pointer;
            user-select: none;

            &:hover {
                color: var(--primary-600);
            }

            &:focus-visible {
                outline: 2px solid var(--primary-600);
                outline-offset: -2px;
            }
        }
    }

    tbody tr:hover {
        background: rgba(2, 132, 199, 0.03);
    }
}

.sort-indicator {
    font-size: 10px;
    margin-left: 4px;
}

.actions-col {
    text-align: center;
    white-space: nowrap;
}

.table-pagination {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px;
    flex-wrap: wrap;
    gap: 12px;
}

.pagination-summary {
    font-size: 12px;
    color: var(--text-tertiary);
}

.pagination-controls {
    display: flex;
    align-items: center;
    gap: 12px;

    button {
        padding: 6px 14px;
        border: 1px solid var(--border-color);
        border-radius: 6px;
        background: var(--surface);
        font-size: 13px;
        cursor: pointer;

        &:disabled {
            opacity: 0.4;
            cursor: not-allowed;
        }

        &:not(:disabled):hover {
            background: rgba(2, 132, 199, 0.06);
        }
    }
}

.pagination-page {
    font-size: 13px;
    color: var(--text-secondary);
}

/*
 * UI-9: below 768px, the table stops being a table at all — each row
 * becomes its own card with label:value pairs, rather than a table
 * forced to horizontally scroll (which works, but is a poor mobile
 * experience for anything wider than 3-4 columns). Labels come from
 * the `data-label` attribute set on each <td> in the template above,
 * driven by the same `columns` prop that renders the desktop header,
 * so there's a single source of truth for column labels either way.
 */
@media (max-width: 768px) {
    .table-wrapper {
        overflow-x: visible;
    }

    .app-table {
        thead {
            // Not display:none — a fully hidden thead is invisible to
            // some screen readers too. Visually hidden, but still in
            // the accessibility tree.
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            border: 0;
        }

        tbody, tr, td {
            display: block;
            width: 100%;
        }

        tr {
            margin-bottom: 12px;
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: 4px 0;
            background: var(--card-bg);
        }

        td {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            text-align: right;
            border-bottom: 1px solid var(--border-light);

            &:last-child {
                border-bottom: none;
            }

            &::before {
                content: attr(data-label);
                font-weight: 600;
                font-size: 11px;
                text-transform: uppercase;
                letter-spacing: 0.03em;
                color: var(--text-tertiary);
                text-align: left;
                flex-shrink: 0;
            }
        }

        .actions-col {
            justify-content: flex-end;

            &::before {
                display: none;
            }
        }
    }

    .table-pagination {
        flex-direction: column;
        align-items: stretch;
        text-align: center;
    }

    .pagination-controls {
        justify-content: center;
    }
}
</style>

