import { reactive } from 'vue';

/**
 * @param {number} initialPerPage
 * @returns {{ pagination: import('vue').UnwrapNestedRefs<{ currentPage: number, perPage: number, lastPage: number, total: number, from: number, to: number }>, syncFromMeta: (meta: Record<string, unknown>|null|undefined, listLength?: number) => void, reset: () => void }}
 */
export function useListPagination(initialPerPage = 25) {
    const pagination = reactive({
        currentPage: 1,
        perPage: initialPerPage,
        lastPage: 1,
        total: 0,
        from: 0,
        to: 0,
    });

    function syncFromMeta(meta, listLength = 0) {
        const source = meta ?? {};

        pagination.currentPage =
            Number(source.current_page ?? source.currentPage ?? pagination.currentPage) || 1;
        pagination.lastPage = Number(source.last_page ?? source.lastPage ?? 1) || 1;
        pagination.perPage = Number(source.per_page ?? source.perPage ?? pagination.perPage) || pagination.perPage;
        pagination.total = Number(source.total ?? listLength) || 0;
        pagination.from = Number(source.from ?? (listLength > 0 ? 1 : 0)) || 0;
        pagination.to = Number(source.to ?? listLength) || 0;
    }

    function reset() {
        pagination.currentPage = 1;
        pagination.lastPage = 1;
        pagination.total = 0;
        pagination.from = 0;
        pagination.to = 0;
    }

    return { pagination, syncFromMeta, reset };
}
