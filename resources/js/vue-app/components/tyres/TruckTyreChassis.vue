<template>
    <div class="truck-chassis">
        <div class="chassis-legend d-flex flex-wrap gap-3 mb-3 small">
            <span class="legend-item">
                <span class="legend-dot legend-dot--installed"></span> Tyre installed
            </span>
            <span class="legend-item">
                <span class="legend-dot legend-dot--empty"></span> Position free
            </span>
            <span class="legend-item">
                <span class="legend-dot legend-dot--spare"></span> Spare slot
            </span>
            <span class="text-muted">Front ↑ · Rear ↓</span>
        </div>

        <div class="chassis-frame">
            <div class="chassis-direction">
                <span class="direction-badge">FRONT</span>
            </div>

            <div class="chassis-body">
                <div v-for="row in layout.rows" :key="row.key" class="axle-row">
                    <div class="axle-label">{{ row.label }}</div>

                    <div class="axle-tyres">
                        <div class="tyre-side tyre-side--left">
                            <template v-if="row.type === 'dual'">
                                <TyreSlotNode :slot-data="row.left.outer" side-hint="L-Out" />
                                <TyreSlotNode :slot-data="row.left.inner" side-hint="L-In" />
                            </template>
                            <template v-else>
                                <TyreSlotNode :slot-data="row.left" side-hint="L" />
                            </template>
                        </div>

                        <div class="chassis-rail" aria-hidden="true">
                            <div class="chassis-rail__line"></div>
                        </div>

                        <div class="tyre-side tyre-side--right">
                            <template v-if="row.type === 'dual'">
                                <TyreSlotNode :slot-data="row.right.inner" side-hint="R-In" />
                                <TyreSlotNode :slot-data="row.right.outer" side-hint="R-Out" />
                            </template>
                            <template v-else>
                                <TyreSlotNode :slot-data="row.right" side-hint="R" />
                            </template>
                        </div>
                    </div>
                </div>
            </div>

            <div class="chassis-direction chassis-direction--rear">
                <span class="direction-badge direction-badge--muted">REAR</span>
            </div>
        </div>

        <div v-if="layout.spares.length" class="spare-section mt-4">
            <div class="spare-panel">
                <div class="spare-heading">
                    <i class="bi bi-life-preserver me-1"></i> Spare Tyres
                </div>
                <div class="spare-grid">
                    <TyreSlotNode
                        v-for="slot in layout.spares"
                        :key="slot.position"
                        :slot-data="slot"
                        variant="spare"
                    />
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { buildTruckChassisLayout } from '../../utils/truckTyreLayout';
import TyreSlotNode from './TyreSlotNode.vue';

const props = defineProps({
    positions: {
        type: Array,
        default: () => [],
    },
});

const layout = computed(() => buildTruckChassisLayout(props.positions));
</script>

<style scoped>
.truck-chassis {
    --tyre-installed: #198754;
    --tyre-installed-bg: #e8f5ee;
    --tyre-empty: #adb5bd;
    --tyre-empty-bg: #f8f9fa;
    --chassis-rail: #495057;
}

.legend-item {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
}

.legend-dot {
    width: 14px;
    height: 14px;
    border-radius: 4px;
    display: inline-block;
}

.legend-dot--installed {
    background: var(--tyre-installed-bg);
    border: 2px solid var(--tyre-installed);
}

.legend-dot--empty {
    background: var(--tyre-empty-bg);
    border: 2px dashed var(--tyre-empty);
}

.legend-dot--spare {
    background: #e7f5ff;
    border: 2px dashed #339af0;
}

.chassis-frame {
    background: linear-gradient(180deg, #f1f3f5 0%, #e9ecef 100%);
    border: 1px solid #dee2e6;
    border-radius: 1rem;
    padding: 1rem 0.75rem;
    max-width: 720px;
    margin: 0 auto;
}

.chassis-direction {
    text-align: center;
    margin-bottom: 0.5rem;
}

.chassis-direction--rear {
    margin-top: 0.5rem;
    margin-bottom: 0;
}

.direction-badge {
    display: inline-block;
    font-size: 0.65rem;
    font-weight: 700;
    letter-spacing: 0.08em;
    color: #0d6efd;
    background: #fff;
    border: 1px solid #b6d4fe;
    border-radius: 999px;
    padding: 0.15rem 0.65rem;
}

.direction-badge--muted {
    color: #6c757d;
    border-color: #ced4da;
}

.chassis-body {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.axle-row {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
}

.axle-label {
    text-align: center;
    font-size: 0.7rem;
    font-weight: 600;
    color: #6c757d;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

.axle-tyres {
    display: grid;
    grid-template-columns: 1fr auto 1fr;
    align-items: center;
    gap: 0.5rem;
}

.tyre-side {
    display: flex;
    gap: 0.35rem;
}

.tyre-side--left {
    justify-content: flex-end;
}

.tyre-side--right {
    justify-content: flex-start;
}

.chassis-rail {
    width: 28px;
    display: flex;
    justify-content: center;
    align-self: stretch;
}

.chassis-rail__line {
    width: 10px;
    height: 100%;
    min-height: 56px;
    background: var(--chassis-rail);
    border-radius: 4px;
    box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.15);
}

.spare-section {
    max-width: 720px;
    margin-left: auto;
    margin-right: auto;
}

.spare-panel {
    background: linear-gradient(180deg, #e7f5ff 0%, #f8f9fa 100%);
    border: 1px dashed #74c0fc;
    border-radius: 0.75rem;
    padding: 0.85rem;
}

.spare-heading {
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: #1864ab;
    margin-bottom: 0.65rem;
    text-align: center;
}

.spare-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    justify-content: center;
}

@media (max-width: 576px) {
    .chassis-frame {
        padding: 0.75rem 0.35rem;
    }

    .axle-tyres {
        gap: 0.25rem;
    }

    .chassis-rail {
        width: 18px;
    }

    .chassis-rail__line {
        width: 8px;
    }
}
</style>
