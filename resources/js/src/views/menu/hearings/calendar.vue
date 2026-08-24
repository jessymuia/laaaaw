<template>
    <div class="hearings-calendar-page">
        <teleport to="#breadcrumb">
            <ul class="navbar-nav flex-row">
                <li>
                    <div class="page-header">
                        <nav class="breadcrumb-one" aria-label="breadcrumb">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="javascript:;">Hearings</a></li>
                                <li class="breadcrumb-item active" aria-current="page"><span>Calendar</span></li>
                            </ol>
                        </nav>
                    </div>
                </li>
            </ul>
        </teleport>

        <div class="calendar-header">
            <h2 class="page-title">Hearings Calendar</h2>
            <div class="month-nav">
                <button type="button" class="btn-nav" @click="goToPreviousMonth" aria-label="Previous month">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="15 18 9 12 15 6"></polyline>
                    </svg>
                </button>
                <span class="current-month" data-testid="calendar-month-label">{{ monthLabel }}</span>
                <button type="button" class="btn-nav" @click="goToNextMonth" aria-label="Next month">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="9 18 15 12 9 6"></polyline>
                    </svg>
                </button>
                <button type="button" class="btn-today" @click="goToToday">Today</button>
            </div>
        </div>

        <div v-if="loading" class="calendar-loading">Loading hearings...</div>

        <div v-else class="calendar-grid" data-testid="hearings-calendar-grid">
            <div class="weekday-row">
                <div v-for="day in weekdayLabels" :key="day" class="weekday-cell">{{ day }}</div>
            </div>
            <div class="days-row">
                <div
                    v-for="cell in calendarCells"
                    :key="cell.key"
                    class="day-cell"
                    :class="{ 'other-month': !cell.inCurrentMonth, 'is-today': cell.isToday }"
                >
                    <div class="day-number">{{ cell.dayNumber }}</div>
                    <div class="day-hearings">
                        <a
                            v-for="hearing in cell.hearings"
                            :key="hearing.id"
                            href="javascript:;"
                            class="hearing-chip"
                            :data-testid="`hearing-chip-${hearing.id}`"
                            :title="`${hearing.case} at ${hearing.court}`"
                            @click="goToHearingCase(hearing)"
                        >
                            {{ hearing.case }}
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import axios from '../../../api';
import { parseWireDate } from '@/utils/date';

// FUN-3 / journey 3: no calendar view existed anywhere in the app before
// this (fullcalendar was correctly removed as unused dead weight during
// the UI-1 template purge — there was nothing rendering hearings visually
// at all until now). This is a minimal, real month-grid view sourced
// from the same /api/hearings endpoint the list page already uses.

const hearings = ref([]);
const loading = ref(true);
const viewDate = ref(new Date());

const weekdayLabels = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

const monthLabel = computed(() =>
    viewDate.value.toLocaleDateString(undefined, { month: 'long', year: 'numeric' })
);

const dateKey = (date) => `${date.getFullYear()}-${date.getMonth()}-${date.getDate()}`;

const hearingsByDay = computed(() => {
    const map = {};
    hearings.value.forEach((h) => {
        if (!h.hearing_date) return;
        // UI-7: uses the one shared date utility (parseWireDate) instead
        // of a second hand-rolled d/m/Y parser — this also means a
        // malformed hearing_date is now safely skipped instead of
        // silently producing an "Invalid Date" grouping key, which the
        // old inline parser had no protection against at all.
        const parsed = parseWireDate(h.hearing_date);
        if (!parsed) return;
        const key = dateKey(parsed);
        if (!map[key]) map[key] = [];
        map[key].push(h);
    });
    return map;
});

const calendarCells = computed(() => {
    const year = viewDate.value.getFullYear();
    const month = viewDate.value.getMonth();
    const firstOfMonth = new Date(year, month, 1);
    const startOffset = firstOfMonth.getDay(); // 0 = Sunday
    const daysInMonth = new Date(year, month + 1, 0).getDate();
    const today = new Date();

    const cells = [];

    // Leading days from the previous month, for a visually complete grid.
    const daysInPrevMonth = new Date(year, month, 0).getDate();
    for (let i = startOffset - 1; i >= 0; i--) {
        const dayNumber = daysInPrevMonth - i;
        const date = new Date(year, month - 1, dayNumber);
        cells.push(buildCell(date, false, today));
    }

    for (let day = 1; day <= daysInMonth; day++) {
        const date = new Date(year, month, day);
        cells.push(buildCell(date, true, today));
    }

    // Trailing days from the next month to complete the last week row.
    let nextMonthDay = 1;
    while (cells.length % 7 !== 0) {
        const date = new Date(year, month + 1, nextMonthDay);
        cells.push(buildCell(date, false, today));
        nextMonthDay++;
    }

    return cells;
});

function buildCell(date, inCurrentMonth, today) {
    const key = dateKey(date);
    return {
        key: `${key}-${inCurrentMonth}`,
        dayNumber: date.getDate(),
        inCurrentMonth,
        isToday: dateKey(today) === key,
        hearings: hearingsByDay.value[key] || [],
    };
}

const router = useRouter();

const goToHearingCase = (hearing) => {
    localStorage.setItem('caseId', hearing.case_id);
    router.push('/view-case');
};

const goToPreviousMonth = () => {
    viewDate.value = new Date(viewDate.value.getFullYear(), viewDate.value.getMonth() - 1, 1);
};

const goToNextMonth = () => {
    viewDate.value = new Date(viewDate.value.getFullYear(), viewDate.value.getMonth() + 1, 1);
};

const goToToday = () => {
    viewDate.value = new Date();
};

const fetchHearings = () => {
    loading.value = true;
    axios.get('/api/hearings')
        .then((response) => {
            hearings.value = response.data.data;
        })
        .catch((error) => {
            console.error('Error fetching hearings for calendar:', error);
        })
        .finally(() => {
            loading.value = false;
        });
};

onMounted(() => {
    fetchHearings();
});
</script>

<style lang="scss" scoped>
.hearings-calendar-page {
    padding: 20px;
}

.calendar-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 20px;
}

.page-title {
    font-size: 22px;
    font-weight: 700;
    margin: 0;
}

.month-nav {
    display: flex;
    align-items: center;
    gap: 12px;
}

.current-month {
    font-weight: 600;
    font-size: 15px;
    min-width: 160px;
    text-align: center;
}

.btn-nav {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    border: 1px solid rgba(0, 0, 0, 0.1);
    background: white;
    border-radius: 8px;
    cursor: pointer;

    &:hover {
        background: rgba(2, 132, 199, 0.08);
    }
}

.btn-today {
    padding: 6px 14px;
    border: 1px solid var(--primary-600);
    color: var(--primary-600);
    background: white;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;

    &:hover {
        background: rgba(2, 132, 199, 0.08);
    }
}

.calendar-loading {
    padding: 40px 0;
    text-align: center;
    color: #6b7280;
}

.calendar-grid {
    background: white;
    border-radius: 12px;
    overflow: hidden;
    border: 1px solid rgba(0, 0, 0, 0.08);
}

.weekday-row, .days-row {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
}

.weekday-cell {
    padding: 10px;
    text-align: center;
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    color: #6b7280;
    border-bottom: 1px solid rgba(0, 0, 0, 0.06);
}

.day-cell {
    min-height: 100px;
    padding: 8px;
    border-right: 1px solid rgba(0, 0, 0, 0.04);
    border-bottom: 1px solid rgba(0, 0, 0, 0.04);

    &.other-month {
        background: rgba(0, 0, 0, 0.02);

        .day-number {
            color: #ccc;
        }
    }

    &.is-today {
        background: rgba(2, 132, 199, 0.05);

        .day-number {
            color: var(--primary-600);
            font-weight: 700;
        }
    }
}

.day-number {
    font-size: 12px;
    font-weight: 600;
    margin-bottom: 6px;
}

.day-hearings {
    display: flex;
    flex-direction: column;
    gap: 3px;
}

.hearing-chip {
    display: block;
    font-size: 11px;
    padding: 2px 6px;
    border-radius: 4px;
    background: rgba(2, 132, 199, 0.12);
    color: var(--primary-600);
    text-decoration: none;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;

    &:hover {
        background: rgba(2, 132, 199, 0.25);
    }
}
</style>
