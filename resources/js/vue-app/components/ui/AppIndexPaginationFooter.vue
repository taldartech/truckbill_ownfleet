<template>
    <div
        v-if="total > 0"
        class="card-footer d-flex flex-wrap justify-content-between align-items-center gap-2 app-index-pagination"
    >
        <small class="text-muted">
            Showing {{ from }} to {{ to }} of {{ total }}<template v-if="itemLabel"> {{ itemLabel }}</template>
        </small>
        <div class="d-flex align-items-center gap-2">
            <label class="small text-muted mb-0" :for="perPageInputId">Per page</label>
            <select
                :id="perPageInputId"
                class="form-select form-select-sm app-index-pagination__per-page"
                :value="perPage"
                :disabled="loading"
                @change="onPerPageChange"
            >
                <option v-for="size in resolvedPerPageOptions" :key="size" :value="size">
                    {{ size }}
                </option>
            </select>
            <div v-if="lastPage > 1" class="btn-group btn-group-sm">
                <button
                    type="button"
                    class="btn btn-outline-secondary"
                    :disabled="loading || currentPage <= 1"
                    @click="$emit('page-change', currentPage - 1)"
                >
                    Previous
                </button>
                <button type="button" class="btn btn-outline-secondary" disabled>
                    Page {{ currentPage }} / {{ lastPage }}
                </button>
                <button
                    type="button"
                    class="btn btn-outline-secondary"
                    :disabled="loading || currentPage >= lastPage"
                    @click="$emit('page-change', currentPage + 1)"
                >
                    Next
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    pageId: {
        type: String,
        required: true,
    },
    loading: {
        type: Boolean,
        default: false,
    },
    currentPage: {
        type: Number,
        required: true,
    },
    lastPage: {
        type: Number,
        required: true,
    },
    perPage: {
        type: Number,
        required: true,
    },
    total: {
        type: Number,
        required: true,
    },
    from: {
        type: Number,
        required: true,
    },
    to: {
        type: Number,
        required: true,
    },
    itemLabel: {
        type: String,
        default: '',
    },
    perPageOptions: {
        type: Array,
        default: () => [10, 25, 50, 100],
    },
});

const emit = defineEmits(['page-change', 'update:perPage']);

const perPageInputId = computed(() => `${props.pageId}_per_page`);

const resolvedPerPageOptions = computed(() => {
    const sizes = new Set(props.perPageOptions.map((size) => Number(size)));

    if (props.perPage > 0) {
        sizes.add(Number(props.perPage));
    }

    return [...sizes].sort((left, right) => left - right);
});

function onPerPageChange(event) {
    emit('update:perPage', Number(event.target.value));
}
</script>
