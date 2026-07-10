<template>
    <div class="tyre-history-timeline">
        <div v-if="events.length === 0" class="text-center text-muted py-4">No history found.</div>

        <template v-else>
            <div
                v-for="(group, groupIndex) in groupedEvents"
                :key="group.dateKey"
                class="tyre-history-timeline__day"
            >
                <div class="tyre-history-timeline__day-label">
                    <span class="tyre-history-timeline__day-text">{{ group.dateLabel }}</span>
                </div>

                <div class="tyre-history-timeline__track">
                    <article
                        v-for="(event, eventIndex) in group.events"
                        :key="`${event.sort_at}-${event.type}-${groupIndex}-${eventIndex}`"
                        class="tyre-history-timeline__item"
                    >
                        <div
                            class="tyre-history-timeline__marker"
                            :class="markerClass(event.type)"
                            :title="event.type_label || formatHistoryType(event.type)"
                        >
                            <i class="bi" :class="markerIcon(event.type)"></i>
                        </div>

                        <div class="tyre-history-timeline__card">
                            <div class="tyre-history-timeline__card-head">
                                <div class="tyre-history-timeline__meta">
                                    <span
                                        v-if="hasEventTime(event)"
                                        class="tyre-history-timeline__time"
                                    >
                                        {{ formatEventParts(event).time }}
                                    </span>
                                    <span class="badge" :class="typeBadgeClass(event.type)">
                                        {{ event.type_label || formatHistoryType(event.type) }}
                                    </span>
                                </div>
                            </div>
                            <h6 class="tyre-history-timeline__title mb-1">{{ event.title }}</h6>
                            <p v-if="event.detail" class="tyre-history-timeline__detail mb-0">
                                {{ event.detail }}
                            </p>
                        </div>
                    </article>
                </div>
            </div>
        </template>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { formatDateHeading, formatEventParts } from '../../utils/datetime';

const props = defineProps({
    events: { type: Array, default: () => [] },
    typeBadgeClass: { type: Function, required: true },
    formatHistoryType: { type: Function, required: true },
});

const groupedEvents = computed(() => {
    const groups = [];
    const groupMap = new Map();

    for (const event of props.events) {
        const parts = formatEventParts(event);
        const dateKey = event.date ?? parts.date;

        if (!groupMap.has(dateKey)) {
            const group = {
                dateKey,
                dateLabel: formatDateHeading(event.date ?? event.sort_at ?? dateKey),
                events: [],
            };
            groupMap.set(dateKey, group);
            groups.push(group);
        }

        groupMap.get(dateKey).events.push(event);
    }

    return groups;
});

function hasEventTime(event) {
    const { time } = formatEventParts(event);

    return time && time !== '—';
}

function markerClass(type) {
    const map = {
        installed: 'tyre-history-timeline__marker--installed',
        removed: 'tyre-history-timeline__marker--removed',
        rotation: 'tyre-history-timeline__marker--rotation',
        damage: 'tyre-history-timeline__marker--damage',
        replacement: 'tyre-history-timeline__marker--replacement',
        scrapped: 'tyre-history-timeline__marker--scrapped',
        expense: 'tyre-history-timeline__marker--expense',
    };

    return map[type] ?? 'tyre-history-timeline__marker--default';
}

function markerIcon(type) {
    const map = {
        installed: 'bi-plus-circle-fill',
        removed: 'bi-dash-circle-fill',
        rotation: 'bi-arrow-repeat',
        damage: 'bi-tools',
        replacement: 'bi-arrow-left-right',
        scrapped: 'bi-trash-fill',
        expense: 'bi-currency-rupee',
    };

    return map[type] ?? 'bi-circle-fill';
}
</script>

<style scoped>
.tyre-history-timeline {
    --timeline-line: #dbe3ef;
    --timeline-card-border: #e6edf7;
    --timeline-card-bg: #fff;
    padding: 0.25rem 0;
}

.tyre-history-timeline__day + .tyre-history-timeline__day {
    margin-top: 1.25rem;
}

.tyre-history-timeline__day-label {
    margin-bottom: 0.75rem;
}

.tyre-history-timeline__day-text {
    display: inline-block;
    font-size: 0.8rem;
    font-weight: 600;
    letter-spacing: 0.02em;
    text-transform: uppercase;
    color: #64748b;
    background: #f8fafc;
    border: 1px solid var(--timeline-card-border);
    border-radius: 999px;
    padding: 0.2rem 0.75rem;
}

.tyre-history-timeline__track {
    position: relative;
    margin-left: 0.5rem;
    padding-left: 2rem;
}

.tyre-history-timeline__track::before {
    content: '';
    position: absolute;
    top: 0.35rem;
    bottom: 0.35rem;
    left: 0.72rem;
    width: 2px;
    background: var(--timeline-line);
}

.tyre-history-timeline__item {
    position: relative;
    display: flex;
    gap: 0.75rem;
    margin-bottom: 0.85rem;
}

.tyre-history-timeline__item:last-child {
    margin-bottom: 0;
}

.tyre-history-timeline__marker {
    position: absolute;
    left: -2rem;
    top: 0.85rem;
    width: 1.45rem;
    height: 1.45rem;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.72rem;
    color: #fff;
    border: 2px solid #fff;
    box-shadow: 0 0 0 2px var(--timeline-line);
    z-index: 1;
}

.tyre-history-timeline__marker--installed {
    background: #198754;
}

.tyre-history-timeline__marker--removed {
    background: #6c757d;
}

.tyre-history-timeline__marker--rotation {
    background: #ffc107;
    color: #212529;
}

.tyre-history-timeline__marker--damage {
    background: #dc3545;
}

.tyre-history-timeline__marker--replacement {
    background: #0d6efd;
}

.tyre-history-timeline__marker--scrapped {
    background: #212529;
}

.tyre-history-timeline__marker--expense {
    background: #f8f9fa;
    color: #495057;
    box-shadow: 0 0 0 2px var(--timeline-line), inset 0 0 0 1px #ced4da;
}

.tyre-history-timeline__marker--default {
    background: #6c757d;
}

.tyre-history-timeline__card {
    flex: 1;
    border: 1px solid var(--timeline-card-border);
    border-radius: 0.75rem;
    background: var(--timeline-card-bg);
    padding: 0.75rem 0.9rem;
}

.tyre-history-timeline__card-head {
    margin-bottom: 0.35rem;
}

.tyre-history-timeline__meta {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.45rem;
}

.tyre-history-timeline__time {
    font-size: 0.82rem;
    font-weight: 600;
    color: #334155;
    font-variant-numeric: tabular-nums;
}

.tyre-history-timeline__title {
    font-size: 0.95rem;
    font-weight: 600;
    color: #0f172a;
}

.tyre-history-timeline__detail {
    font-size: 0.84rem;
    color: #64748b;
    line-height: 1.45;
}
</style>
