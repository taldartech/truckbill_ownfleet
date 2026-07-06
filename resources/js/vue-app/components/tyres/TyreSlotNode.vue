<template>
    <div
        class="tyre-node-wrapper"
        :class="{ 'tyre-node-wrapper--interactive': interactive && canManage }"
    >
        <component
            :is="installed && !interactive ? RouterLink : 'div'"
            :to="installed && !interactive ? { name: 'tyres.show', params: { id: slotData.tyre.id } } : undefined"
            class="tyre-node"
            :class="nodeClass"
            :title="tooltip"
            role="button"
            :tabindex="interactive && canManage && !installed ? 0 : undefined"
            @click="handleNodeClick"
            @keydown.enter.prevent="handleNodeClick"
        >
            <div class="tyre-node__shape">
                <i v-if="installed" class="bi bi-circle-fill tyre-node__icon"></i>
                <i v-else-if="variant === 'spare'" class="bi bi-life-preserver tyre-node__icon tyre-node__icon--spare"></i>
                <i v-else class="bi bi-plus-lg tyre-node__icon tyre-node__icon--empty"></i>
            </div>

            <div class="tyre-node__label">{{ shortLabel }}</div>

            <template v-if="installed">
                <RouterLink
                    v-if="interactive"
                    :to="{ name: 'tyres.show', params: { id: slotData.tyre.id } }"
                    class="tyre-node__serial tyre-node__serial--link"
                    @click.stop
                >
                    {{ slotData.tyre.serial_number }}
                </RouterLink>
                <div v-else class="tyre-node__serial">{{ slotData.tyre.serial_number }}</div>
                <div v-if="slotData.tyre.stint_km != null" class="tyre-node__km">
                    {{ formatKm(slotData.tyre.stint_km) }}
                </div>
                <div
                    v-if="slotData.tyre.life_used_percent != null"
                    class="tyre-node__life"
                    :class="lifeClass(slotData.tyre.life_used_percent)"
                >
                    {{ slotData.tyre.life_used_percent }}% used
                </div>
            </template>
            <div v-else class="tyre-node__free">{{ interactive && canManage ? 'INSTALL' : 'FREE' }}</div>
        </component>

        <button
            v-if="installed && interactive && canManage"
            type="button"
            class="tyre-node__remove-btn"
            title="Remove tyre from this position"
            @click.stop="emit('remove', slotData)"
        >
            <i class="bi bi-box-arrow-right"></i>
        </button>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { RouterLink } from 'vue-router';
import { formatDate } from '../../utils/datetime';

const props = defineProps({
    slotData: {
        type: Object,
        default: null,
    },
    sideHint: {
        type: String,
        default: '',
    },
    variant: {
        type: String,
        default: 'road',
    },
    interactive: {
        type: Boolean,
        default: false,
    },
    canManage: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['install', 'remove']);

const installed = computed(() => Boolean(props.slotData?.tyre && !props.slotData?.is_empty));

function handleNodeClick(event) {
    if (!props.interactive || !props.canManage || !props.slotData) {
        return;
    }

    if (installed.value) {
        return;
    }

    event.preventDefault();
    emit('install', props.slotData);
}

const shortLabel = computed(() => {
    if (!props.slotData) {
        return props.sideHint || '—';
    }

    if (props.variant === 'spare') {
        return props.slotData.position_label ?? 'Spare';
    }

    const code = props.slotData.position ?? '';
    if (code.startsWith('STEER')) {
        return code.endsWith('_L') || code.includes('LEFT') ? 'Steer L' : 'Steer R';
    }

    const axleMatch = code.match(/AX(\d+)/i);
    const axle = axleMatch ? axleMatch[1] : '';
    const side = code.includes('_L') || code.includes('LEFT') ? 'L' : 'R';
    if (code.includes('_OUT') || code.includes('OUTER')) {
        return `${axle}${side} Out`;
    }
    if (code.includes('_IN') || code.includes('INNER')) {
        return `${axle}${side} In`;
    }
    if (code.startsWith('FRONT')) {
        return side === 'L' ? 'Front L' : 'Front R';
    }
    if (code.includes('REAR')) {
        if (code.includes('OUTER')) {
            return `R${side} Out`;
        }
        if (code.includes('INNER')) {
            return `R${side} In`;
        }
    }

    return props.slotData.position_label ?? props.sideHint ?? code;
});

const tooltip = computed(() => {
    if (!props.slotData) {
        return '';
    }

    if (installed.value) {
        const tyre = props.slotData.tyre;
        return [
            props.slotData.position_label,
            tyre.serial_number,
            tyre.brand,
            tyre.installed_at ? `Installed ${formatDate(tyre.installed_at)}` : null,
        ]
            .filter(Boolean)
            .join(' · ');
    }

    return `${props.slotData.position_label} — No tyre (free position)`;
});

const nodeClass = computed(() => ({
    'tyre-node--installed': installed.value,
    'tyre-node--empty': !installed.value && props.slotData,
    'tyre-node--missing': !props.slotData,
    'tyre-node--spare': props.variant === 'spare',
    'tyre-node--link': installed.value && !props.interactive,
    'tyre-node--actionable': props.interactive && props.canManage && !installed.value && props.slotData,
}));

function formatKm(value) {
    return `${Number(value).toLocaleString('en-IN')} km`;
}

function lifeClass(percent) {
    if (percent >= 85) {
        return 'tyre-node__life--danger';
    }
    if (percent >= 60) {
        return 'tyre-node__life--warn';
    }
    return 'tyre-node__life--ok';
}
</script>

<style scoped>
.tyre-node-wrapper {
    position: relative;
    display: inline-flex;
}

.tyre-node-wrapper--interactive .tyre-node--actionable {
    cursor: pointer;
}

.tyre-node-wrapper--interactive .tyre-node--actionable:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(13, 110, 253, 0.18);
}

.tyre-node__remove-btn {
    position: absolute;
    top: -6px;
    right: -6px;
    width: 22px;
    height: 22px;
    border: none;
    border-radius: 999px;
    background: #fff;
    color: #dc3545;
    box-shadow: 0 1px 4px rgba(0, 0, 0, 0.15);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.7rem;
    padding: 0;
}

.tyre-node__remove-btn:hover {
    background: #dc3545;
    color: #fff;
}

.tyre-node--actionable {
    border-color: #0d6efd;
}

.tyre-node {
    width: 72px;
    min-height: 96px;
    border-radius: 0.65rem;
    padding: 0.35rem 0.25rem;
    text-align: center;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.1rem;
    transition: transform 0.12s ease, box-shadow 0.12s ease;
    text-decoration: none;
    color: inherit;
}

.tyre-node--link:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(25, 135, 84, 0.2);
}

.tyre-node--installed {
    background: #e8f5ee;
    border: 2px solid #198754;
}

.tyre-node--empty {
    background: #f8f9fa;
    border: 2px dashed #ced4da;
    color: #6c757d;
}

.tyre-node--missing {
    background: transparent;
    border: 2px dashed transparent;
    visibility: hidden;
}

.tyre-node--spare {
    width: 88px;
    border-color: #74c0fc;
    background: #e7f5ff;
}

.tyre-node--spare.tyre-node--installed {
    background: #d3f9d8;
    border-color: #198754;
}

.tyre-node__icon--spare {
    color: #1864ab;
    font-size: 1rem;
}

.tyre-node__shape {
    width: 34px;
    height: 34px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #fff;
    margin-bottom: 0.1rem;
}

.tyre-node--installed .tyre-node__shape {
    box-shadow: inset 0 0 0 2px rgba(25, 135, 84, 0.25);
}

.tyre-node__icon {
    font-size: 1.1rem;
    color: #198754;
}

.tyre-node__icon--empty {
    font-size: 0.85rem;
    color: #adb5bd;
}

.tyre-node__label {
    font-size: 0.58rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.02em;
    line-height: 1.2;
    color: #495057;
}

.tyre-node__serial--link {
    text-decoration: none;
}

.tyre-node__serial--link:hover {
    text-decoration: underline;
}

.tyre-node__serial {
    font-size: 0.68rem;
    font-weight: 700;
    color: #198754;
    line-height: 1.1;
    word-break: break-all;
}

.tyre-node__km {
    font-size: 0.58rem;
    color: #495057;
}

.tyre-node__life {
    font-size: 0.55rem;
    font-weight: 600;
    border-radius: 999px;
    padding: 0.05rem 0.35rem;
}

.tyre-node__life--ok {
    background: #d1e7dd;
    color: #0f5132;
}

.tyre-node__life--warn {
    background: #fff3cd;
    color: #664d03;
}

.tyre-node__life--danger {
    background: #f8d7da;
    color: #842029;
}

.tyre-node__free {
    font-size: 0.62rem;
    font-weight: 700;
    letter-spacing: 0.06em;
    color: #adb5bd;
    margin-top: 0.15rem;
}

@media (max-width: 576px) {
    .tyre-node {
        width: 62px;
        min-height: 88px;
    }
}
</style>
