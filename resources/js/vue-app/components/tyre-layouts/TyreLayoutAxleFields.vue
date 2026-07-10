<template>
    <div class="tyre-layout-axle-fields">
        <div class="row g-2">
            <div class="col-4">
                <label class="form-label d-block">Front Steering Axles</label>
                <NumberStepper
                    :model-value="modelValue.steer_axles"
                    label="Front steering axles"
                    :min="0"
                    :max="2"
                    @update:model-value="updateSteerAxles"
                />
            </div>
            <div class="col-4">
                <label class="form-label d-block">Rear Axles</label>
                <NumberStepper
                    :model-value="modelValue.rear_axle_count"
                    label="Rear axles"
                    :min="0"
                    :max="8"
                    @update:model-value="updateRearAxleCount"
                />
            </div>
            <div class="col-4">
                <label class="form-label d-block">Spare Tyres</label>
                <NumberStepper
                    :model-value="modelValue.spare_count"
                    label="Spare tyres"
                    :min="0"
                    :max="10"
                    @update:model-value="updateSpareCount"
                />
            </div>
        </div>

        <div v-if="modelValue.rear_axle_count > 0" class="tyre-layout-axle-fields__rear-section">
            <p class="form-label mb-1">Rear axle wheel type</p>
            <div class="tyre-layout-axle-fields__rear-list">
                <div
                    v-for="axle in modelValue.rear_axle_count"
                    :key="axle"
                    class="form-check tyre-layout-axle-fields__rear-item"
                >
                    <input
                        :id="`rearAxleDual${axle}`"
                        class="form-check-input"
                        type="checkbox"
                        :checked="modelValue.rear_axles_dual[axle - 1]"
                        @change="toggleDual(axle - 1, $event.target.checked)"
                    />
                    <label class="form-check-label" :for="`rearAxleDual${axle}`">
                        Axle {{ axle }} —
                        {{ modelValue.rear_axles_dual[axle - 1] ? 'Dual (4)' : 'Single (2)' }}
                    </label>
                </div>
            </div>
            <div class="form-text mb-0">
                Dual = 4 wheels per axle. Uncheck for single-wheel (2 wheels).
            </div>
        </div>
    </div>
</template>

<script setup>
import NumberStepper from '../ui/NumberStepper.vue';
import { resizeRearAxles } from '../../utils/tyreLayoutForm';

const props = defineProps({
    modelValue: { type: Object, required: true },
});

const emit = defineEmits(['update:modelValue']);

function emitConfig(next) {
    emit('update:modelValue', next);
}

function updateSteerAxles(steer_axles) {
    emitConfig({ ...props.modelValue, steer_axles });
}

function updateSpareCount(spare_count) {
    emitConfig({ ...props.modelValue, spare_count });
}

function updateRearAxleCount(rear_axle_count) {
    emitConfig(resizeRearAxles(props.modelValue, rear_axle_count));
}

function toggleDual(index, isDual) {
    const rear_axles_dual = [...props.modelValue.rear_axles_dual];
    rear_axles_dual[index] = isDual;
    emitConfig({ ...props.modelValue, rear_axles_dual });
}
</script>

<style scoped>
.tyre-layout-axle-fields__rear-section {
    margin-top: 0.5rem;
}

.tyre-layout-axle-fields__rear-list {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.35rem 0.75rem;
    padding: 0.5rem 0.65rem;
    background: var(--bs-light, #f8f9fa);
    border-radius: 0.375rem;
    border: 1px solid var(--bs-border-color, #dee2e6);
}

.tyre-layout-axle-fields__rear-item {
    margin-bottom: 0;
}

.tyre-layout-axle-fields__rear-item .form-check-label {
    font-size: 0.8125rem;
}

@media (max-width: 575.98px) {
    .tyre-layout-axle-fields__rear-list {
        grid-template-columns: minmax(0, 1fr);
    }
}
</style>
