<template>
    <div
        v-if="pagination.total > 0"
        class="app-index-partload-pagination card-footer d-flex align-items-center flex-wrap gap-2 w-100"
    >
        <div class="app-index-partload-pagination__summary text-muted small">
            Showing {{ pagination.from }} to {{ pagination.to }} of {{ pagination.total }}
            <template v-if="itemLabel"> {{ itemLabel }}</template>
        </div>
        <nav
            v-if="pagination.lastPage > 1"
            class="app-index-partload-pagination__nav ms-auto"
            :aria-label="ariaLabel"
        >
            <ul class="pagination pagination-sm app-index-partload-pagination__list mb-0">
                <li
                    v-for="item in paginationLinks"
                    :key="item.key"
                    class="page-item"
                    :class="{
                        active: item.active,
                        disabled: item.disabled,
                        'page-item--ellipsis': item.ellipsis,
                    }"
                >
                    <button
                        v-if="!item.disabled && !item.active"
                        type="button"
                        class="page-link"
                        :aria-label="item.ariaLabel"
                        :disabled="loading"
                        @click="onPageClick(item.page)"
                        v-html="item.label"
                    ></button>
                    <span
                        v-else
                        class="page-link"
                        :aria-current="item.active ? 'page' : undefined"
                        v-html="item.label"
                    ></span>
                </li>
            </ul>
        </nav>
    </div>
</template>

<script setup>
import { toRef } from 'vue';
import { usePartloadPaginationLinks } from '../../composables/usePartloadPaginationLinks';

const props = defineProps({
    pagination: {
        type: Object,
        required: true,
    },
    loading: {
        type: Boolean,
        default: false,
    },
    itemLabel: {
        type: String,
        default: '',
    },
    ariaLabel: {
        type: String,
        default: 'Pagination',
    },
});

const emit = defineEmits(['page-change']);

const paginationLinks = usePartloadPaginationLinks(toRef(props, 'pagination'));

function onPageClick(page) {
    if (page == null || page < 1 || page > props.pagination.lastPage || page === props.pagination.currentPage) {
        return;
    }

    emit('page-change', page);
}
</script>
