<template>
    <div v-if="templates.length > 0" class="trts">
        <div class="trts__head">
            <div>
                <span class="trts__title"><i class="bi bi-clock-history me-1"></i>Recent Routes</span>
                <span class="trts__hint">One-click apply a previously used route</span>
            </div>
            <button type="button" class="trts__clear" @click="clearAll">Clear</button>
        </div>
        <div class="trts__list">
            <button
                v-for="template in templates"
                :key="template.id"
                type="button"
                class="trts__card"
                @click="emit('apply', template)"
            >
                <div class="trts__path">{{ template.label }}</div>
                <div class="trts__meta">
                    {{ template.route_legs.length }} stop{{ template.route_legs.length === 1 ? '' : 's' }}
                    <span v-if="template.saved_at"> · {{ formatSavedAt(template.saved_at) }}</span>
                </div>
            </button>
        </div>
    </div>
</template>

<script setup>
import { onMounted, ref, watch } from 'vue';
import {
    clearRecentRouteTemplates,
    listRecentRouteTemplates,
} from '../../utils/tripRouteTemplates';

defineProps({
    stations: { type: Array, default: () => [] },
    customers: { type: Array, default: () => [] },
});

const emit = defineEmits(['apply']);

const templates = ref([]);

function refresh() {
    templates.value = listRecentRouteTemplates();
}

function clearAll() {
    clearRecentRouteTemplates();
    refresh();
}

function formatSavedAt(value) {
    try {
        return new Date(value).toLocaleDateString('en-IN', {
            day: '2-digit',
            month: 'short',
        });
    } catch {
        return '';
    }
}

onMounted(refresh);

watch(
    () => listRecentRouteTemplates().length,
    () => refresh(),
);

defineExpose({ refresh });
</script>

<style scoped>
.trts {
    border: 1px dashed #cbd5e1;
    border-radius: 10px;
    background: #f8fafc;
    padding: 12px 14px;
}
.trts__head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 10px;
}
.trts__title {
    display: block;
    font-size: 0.78rem;
    font-weight: 700;
    color: #334155;
    text-transform: uppercase;
    letter-spacing: 0.03em;
}
.trts__hint {
    display: block;
    margin-top: 2px;
    font-size: 0.75rem;
    color: #64748b;
}
.trts__clear {
    border: none;
    background: transparent;
    color: #64748b;
    font-size: 0.75rem;
    font-weight: 600;
    cursor: pointer;
    padding: 0;
}
.trts__clear:hover {
    color: #dc2626;
}
.trts__list {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}
.trts__card {
    max-width: 100%;
    text-align: left;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    background: #fff;
    padding: 8px 12px;
    cursor: pointer;
    transition: border-color 0.15s, box-shadow 0.15s;
}
.trts__card:hover {
    border-color: #86efac;
    box-shadow: 0 2px 8px rgba(25, 135, 84, 0.12);
}
.trts__path {
    font-size: 0.82rem;
    font-weight: 600;
    color: #1e293b;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 280px;
}
.trts__meta {
    margin-top: 2px;
    font-size: 0.7rem;
    color: #64748b;
}
</style>
