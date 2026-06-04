<script setup lang="ts">
import { onClickOutside } from '@vueuse/core';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
    modelValue: { from: string; to: string };
    placeholder?: string;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: { from: string; to: string }];
}>();

// ── State ─────────────────────────────────────────────────────────────────────
const open = ref(false);

// Draft selection (not committed until "Toepassen")
const selecting = ref<string | null>(null); // first picked date during selection
const hovered = ref<string | null>(null);
const draft = ref({ from: props.modelValue.from, to: props.modelValue.to });

// Calendar navigation — left month
const today = new Date();
const navYear = ref(today.getFullYear());
const navMonth = ref(today.getMonth()); // 0-indexed

watch(open, (val) => {
    if (val) {
        draft.value = { ...props.modelValue };
        selecting.value = null;
        hovered.value = null;
        // If a from-date is set, navigate to that month
        if (props.modelValue.from) {
            const d = new Date(props.modelValue.from);
            navYear.value = d.getFullYear();
            navMonth.value = d.getMonth();
        }
    }
});

// ── Calendar helpers ──────────────────────────────────────────────────────────
const DAYS = ['ma', 'di', 'wo', 'do', 'vr', 'za', 'zo'];
const MONTHS = ['januari', 'februari', 'maart', 'april', 'mei', 'juni', 'juli', 'augustus', 'september', 'oktober', 'november', 'december'];

function daysInMonth(year: number, month: number) {
    return new Date(year, month + 1, 0).getDate();
}

function firstDayOfMonth(year: number, month: number) {
    // Monday = 0
    return (new Date(year, month, 1).getDay() + 6) % 7;
}

function toISO(year: number, month: number, day: number) {
    return `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
}

interface CalendarDay {
    iso: string;
    day: number;
    currentMonth: boolean;
}

function buildCalendar(year: number, month: number): CalendarDay[] {
    const days: CalendarDay[] = [];
    const firstDay = firstDayOfMonth(year, month);
    const total = daysInMonth(year, month);

    // Leading days from previous month
    const prevMonth = month === 0 ? 11 : month - 1;
    const prevYear = month === 0 ? year - 1 : year;
    const prevTotal = daysInMonth(prevYear, prevMonth);
    for (let i = firstDay - 1; i >= 0; i--) {
        days.push({ iso: toISO(prevYear, prevMonth, prevTotal - i), day: prevTotal - i, currentMonth: false });
    }

    for (let d = 1; d <= total; d++) {
        days.push({ iso: toISO(year, month, d), day: d, currentMonth: true });
    }

    // Trailing days to fill 6 rows
    const nextMonth = month === 11 ? 0 : month + 1;
    const nextYear = month === 11 ? year + 1 : year;
    let trail = 1;
    while (days.length < 42) {
        days.push({ iso: toISO(nextYear, nextMonth, trail++), day: trail - 1, currentMonth: false });
    }

    return days;
}

const rightYear = computed(() => navMonth.value === 11 ? navYear.value + 1 : navYear.value);
const rightMonth = computed(() => navMonth.value === 11 ? 0 : navMonth.value + 1);

const leftDays = computed(() => buildCalendar(navYear.value, navMonth.value));
const rightDays = computed(() => buildCalendar(rightYear.value, rightMonth.value));

function prevMonth() {
    if (navMonth.value === 0) { navMonth.value = 11; navYear.value--; }
    else { navMonth.value--; }
}

function nextMonth() {
    if (navMonth.value === 11) { navMonth.value = 0; navYear.value++; }
    else { navMonth.value++; }
}

// ── Selection logic ───────────────────────────────────────────────────────────
function pickDay(iso: string) {
    if (!selecting.value) {
        // First click — start selection
        selecting.value = iso;
        draft.value = { from: iso, to: '' };
    } else {
        // Second click — complete range
        const [a, b] = [selecting.value, iso].sort();
        draft.value = { from: a, to: b };
        selecting.value = null;
        hovered.value = null;
    }
}

function hoverDay(iso: string) {
    if (selecting.value) hovered.value = iso;
}

function rangeFrom(d: CalendarDay): string {
    return draft.value.from && d.iso === draft.value.from ? draft.value.from : '';
}

function rangeTo(d: CalendarDay): string {
    const to = selecting.value && hovered.value
        ? [selecting.value, hovered.value].sort()[1]
        : draft.value.to;
    return to && d.iso === to ? to : '';
}

function inRange(iso: string): boolean {
    const from = draft.value.from;
    const to = selecting.value && hovered.value
        ? [selecting.value, hovered.value].sort()[1]
        : draft.value.to;
    if (!from || !to) return false;
    return iso > from && iso < to;
}

function isSelected(iso: string): boolean {
    const to = selecting.value && hovered.value
        ? [selecting.value, hovered.value].sort()[1]
        : draft.value.to;
    return iso === draft.value.from || iso === to;
}

// ── Commit / cancel ───────────────────────────────────────────────────────────
function apply() {
    emit('update:modelValue', { ...draft.value });
    open.value = false;
}

function cancel() {
    open.value = false;
}

function clear() {
    draft.value = { from: '', to: '' };
    emit('update:modelValue', { from: '', to: '' });
    open.value = false;
}

// ── Click outside ─────────────────────────────────────────────────────────────
const dropdownRef = ref<HTMLElement | null>(null);
onClickOutside(dropdownRef, cancel);

// ── Display label ─────────────────────────────────────────────────────────────
const label = computed(() => {
    const { from, to } = props.modelValue;
    if (!from && !to) return null;
    const fmt = (s: string) => {
        const [y, m, d] = s.split('-');
        return `${parseInt(d)} ${MONTHS[parseInt(m) - 1].slice(0, 3)} ${y}`;
    };
    if (from && to) return `${fmt(from)} – ${fmt(to)}`;
    return `Vanaf ${fmt(from)}`;
});
</script>

<template>
    <div class="relative">
        <!-- Trigger -->
        <button
            type="button"
            class="inline-flex h-9 items-center gap-2 rounded-lg border border-sidebar-border/70 px-3 text-sm transition-colors hover:bg-sidebar-accent dark:border-sidebar-border"
            :class="modelValue.from ? 'text-foreground' : 'text-muted-foreground'"
            @click="open = !open"
        >
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" class="h-3.5 w-3.5 shrink-0 opacity-60">
                <path d="M5.75 7.5a.75.75 0 1 0 0 1.5.75.75 0 0 0 0-1.5ZM5 10.25a.75.75 0 1 1 1.5 0 .75.75 0 0 1-1.5 0Zm5.75-2.75a.75.75 0 1 0 0 1.5.75.75 0 0 0 0-1.5Zm-.75 2.75a.75.75 0 1 1 1.5 0 .75.75 0 0 1-1.5 0ZM8 7.5a.75.75 0 1 0 0 1.5.75.75 0 0 0 0-1.5ZM7.25 10.25a.75.75 0 1 1 1.5 0 .75.75 0 0 1-1.5 0Z" />
                <path fill-rule="evenodd" d="M4.75 1a.75.75 0 0 1 .75.75V3h5V1.75a.75.75 0 0 1 1.5 0V3h.25A2.75 2.75 0 0 1 15 5.75v7.5A2.75 2.75 0 0 1 12.25 16H3.75A2.75 2.75 0 0 1 1 13.25v-7.5A2.75 2.75 0 0 1 3.75 3H4V1.75A.75.75 0 0 1 4.75 1Zm-1 3.5c-.69 0-1.25.56-1.25 1.25V6.5h11V5.75c0-.69-.56-1.25-1.25-1.25H3.75Zm-1.25 4V13.25c0 .69.56 1.25 1.25 1.25h8.5c.69 0 1.25-.56 1.25-1.25V8.5h-11Z" clip-rule="evenodd" />
            </svg>
            <span>{{ label ?? (placeholder ?? 'Periode kiezen') }}</span>
            <button
                v-if="modelValue.from"
                type="button"
                class="text-muted-foreground hover:text-foreground -mr-1 ml-1 transition-colors"
                aria-label="Wissen"
                @click.stop="clear"
            >
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" class="h-3.5 w-3.5">
                    <path d="M5.28 4.22a.75.75 0 0 0-1.06 1.06L6.94 8l-2.72 2.72a.75.75 0 1 0 1.06 1.06L8 9.06l2.72 2.72a.75.75 0 1 0 1.06-1.06L9.06 8l2.72-2.72a.75.75 0 0 0-1.06-1.06L8 6.94 5.28 4.22Z" />
                </svg>
            </button>
        </button>

        <!-- Dropdown -->
        <Transition
            enter-active-class="transition duration-150 ease-out"
            enter-from-class="opacity-0 scale-95 -translate-y-1"
            enter-to-class="opacity-100 scale-100 translate-y-0"
            leave-active-class="transition duration-100 ease-in"
            leave-from-class="opacity-100 scale-100 translate-y-0"
            leave-to-class="opacity-0 scale-95 -translate-y-1"
        >
            <div
                v-if="open"
                ref="dropdownRef"
                class="absolute left-0 top-full z-50 mt-1 select-none rounded-xl border border-sidebar-border bg-background shadow-xl dark:border-sidebar-border"
                style="min-width: 560px"
            >
                <!-- Calendars -->
                <div class="flex gap-6 p-4">
                    <!-- Left month -->
                    <div class="flex-1">
                        <div class="mb-3 flex items-center justify-between">
                            <button type="button" class="text-muted-foreground hover:text-foreground rounded p-1 transition-colors" @click="prevMonth">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" class="h-4 w-4">
                                    <path fill-rule="evenodd" d="M9.78 4.22a.75.75 0 0 1 0 1.06L7.06 8l2.72 2.72a.75.75 0 1 1-1.06 1.06L5.47 8.53a.75.75 0 0 1 0-1.06l3.25-3.25a.75.75 0 0 1 1.06 0Z" clip-rule="evenodd" />
                                </svg>
                            </button>
                            <span class="text-sm font-medium capitalize">{{ MONTHS[navMonth] }} {{ navYear }}</span>
                            <div class="w-6" />
                        </div>
                        <div class="grid grid-cols-7 gap-y-1">
                            <div v-for="d in DAYS" :key="d" class="text-muted-foreground py-1 text-center text-xs font-medium">{{ d }}</div>
                            <template v-for="day in leftDays" :key="day.iso">
                                <div class="relative flex items-stretch">
                                    <!-- Range background -->
                                    <div
                                        class="absolute inset-y-0 left-0 right-0"
                                        :class="{
                                            'bg-blue-100 dark:bg-blue-900/30': inRange(day.iso),
                                            'bg-blue-100 dark:bg-blue-900/30 rounded-r-full': day.iso === draft.from && !inRange(day.iso) && (draft.to || (selecting && hovered && [selecting, hovered].sort()[1] > day.iso)),
                                            'bg-blue-100 dark:bg-blue-900/30 rounded-l-full': isSelected(day.iso) && day.iso !== draft.from,
                                        }"
                                    />
                                    <button
                                        type="button"
                                        class="relative z-10 mx-auto flex h-8 w-8 items-center justify-center rounded-full text-sm transition-colors"
                                        :class="[
                                            !day.currentMonth ? 'text-muted-foreground/40' : '',
                                            isSelected(day.iso)
                                                ? 'bg-blue-600 text-white font-semibold'
                                                : day.currentMonth
                                                    ? 'hover:bg-sidebar-accent'
                                                    : 'cursor-default',
                                        ]"
                                        :disabled="!day.currentMonth"
                                        @click="day.currentMonth && pickDay(day.iso)"
                                        @mouseenter="hoverDay(day.iso)"
                                    >
                                        {{ day.day }}
                                    </button>
                                </div>
                            </template>
                        </div>
                    </div>

                    <!-- Divider -->
                    <div class="border-l border-sidebar-border/70 dark:border-sidebar-border" />

                    <!-- Right month -->
                    <div class="flex-1">
                        <div class="mb-3 flex items-center justify-between">
                            <div class="w-6" />
                            <span class="text-sm font-medium capitalize">{{ MONTHS[rightMonth] }} {{ rightYear }}</span>
                            <button type="button" class="text-muted-foreground hover:text-foreground rounded p-1 transition-colors" @click="nextMonth">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" class="h-4 w-4">
                                    <path fill-rule="evenodd" d="M6.22 4.22a.75.75 0 0 1 1.06 0l3.25 3.25a.75.75 0 0 1 0 1.06L7.28 11.78a.75.75 0 0 1-1.06-1.06L9.94 8 6.22 5.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" />
                                </svg>
                            </button>
                        </div>
                        <div class="grid grid-cols-7 gap-y-1">
                            <div v-for="d in DAYS" :key="d" class="text-muted-foreground py-1 text-center text-xs font-medium">{{ d }}</div>
                            <template v-for="day in rightDays" :key="day.iso">
                                <div class="relative flex items-stretch">
                                    <div
                                        class="absolute inset-y-0 left-0 right-0"
                                        :class="{
                                            'bg-blue-100 dark:bg-blue-900/30': inRange(day.iso),
                                            'bg-blue-100 dark:bg-blue-900/30 rounded-r-full': day.iso === draft.from && !inRange(day.iso) && (draft.to || (selecting && hovered && [selecting, hovered].sort()[1] > day.iso)),
                                            'bg-blue-100 dark:bg-blue-900/30 rounded-l-full': isSelected(day.iso) && day.iso !== draft.from,
                                        }"
                                    />
                                    <button
                                        type="button"
                                        class="relative z-10 mx-auto flex h-8 w-8 items-center justify-center rounded-full text-sm transition-colors"
                                        :class="[
                                            !day.currentMonth ? 'text-muted-foreground/40' : '',
                                            isSelected(day.iso)
                                                ? 'bg-blue-600 text-white font-semibold'
                                                : day.currentMonth
                                                    ? 'hover:bg-sidebar-accent'
                                                    : 'cursor-default',
                                        ]"
                                        :disabled="!day.currentMonth"
                                        @click="day.currentMonth && pickDay(day.iso)"
                                        @mouseenter="hoverDay(day.iso)"
                                    >
                                        {{ day.day }}
                                    </button>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="flex items-center justify-between border-t border-sidebar-border/70 px-4 py-3 dark:border-sidebar-border">
                    <span class="text-muted-foreground text-sm">
                        <template v-if="selecting">Kies een einddatum</template>
                        <template v-else-if="draft.from && draft.to">{{ draft.from }} – {{ draft.to }}</template>
                        <template v-else-if="draft.from">Vanaf {{ draft.from }}</template>
                        <template v-else>Kies een startdatum</template>
                    </span>
                    <div class="flex gap-2">
                        <button
                            type="button"
                            class="rounded-lg border border-sidebar-border/70 px-3 py-1.5 text-sm transition-colors hover:bg-sidebar-accent dark:border-sidebar-border"
                            @click="cancel"
                        >
                            Annuleren
                        </button>
                        <button
                            type="button"
                            class="rounded-lg bg-blue-600 px-3 py-1.5 text-sm font-medium text-white transition-opacity hover:opacity-90 disabled:opacity-40"
                            :disabled="!draft.from"
                            @click="apply"
                        >
                            Toepassen
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </div>
</template>
