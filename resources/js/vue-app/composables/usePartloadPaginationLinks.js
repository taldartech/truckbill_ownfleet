import { computed, unref } from 'vue';

const MAX_PAGES_WITHOUT_ELLIPSIS = 5;

/**
 * @param {number} current
 * @param {number} last
 * @returns {Array<number | 'ellipsis'>}
 */
export function buildVisiblePages(current, last) {
    if (last <= MAX_PAGES_WITHOUT_ELLIPSIS) {
        return Array.from({ length: last }, (_, index) => index + 1);
    }

    const pages = new Set([1, last, current]);

    if (current > 1) {
        pages.add(current - 1);
    }

    if (current < last) {
        pages.add(current + 1);
    }

    if (current <= 3) {
        pages.add(2);
        pages.add(3);
    }

    if (current >= last - 2) {
        pages.add(last - 1);
        pages.add(last - 2);
    }

    const sorted = [...pages].sort((left, right) => left - right);
    const result = [];

    for (let index = 0; index < sorted.length; index++) {
        const page = sorted[index];

        if (index > 0 && page - sorted[index - 1] > 1) {
            result.push('ellipsis');
        }

        result.push(page);
    }

    return result;
}

/**
 * @param {import('vue').MaybeRefOrGetter<{ currentPage: number, lastPage: number, total?: number, perPage?: number }>} pagination
 */
export function usePartloadPaginationLinks(pagination) {
    return computed(() => {
        const state = unref(pagination);
        const current = Number(state.currentPage) || 1;
        let last = Number(state.lastPage) || 0;

        if (last < 1 && state.total > 0 && state.perPage > 0) {
            last = Math.max(1, Math.ceil(Number(state.total) / Number(state.perPage)));
        }

        if (last < 1) {
            last = 1;
        }

        const items = [
            {
                key: 'prev',
                label: '&laquo; Previous',
                page: current - 1,
                disabled: current <= 1,
                active: false,
                ellipsis: false,
                ariaLabel: 'Previous page',
            },
        ];

        for (const entry of buildVisiblePages(current, last)) {
            if (entry === 'ellipsis') {
                items.push({
                    key: `ellipsis-${items.length}`,
                    label: '&hellip;',
                    page: null,
                    disabled: true,
                    active: false,
                    ellipsis: true,
                    ariaLabel: 'More pages',
                });
                continue;
            }

            items.push({
                key: `page-${entry}`,
                label: String(entry),
                page: entry,
                disabled: false,
                active: entry === current,
                ellipsis: false,
                ariaLabel: `Page ${entry}`,
            });
        }

        items.push({
            key: 'next',
            label: 'Next &raquo;',
            page: current + 1,
            disabled: current >= last,
            active: false,
            ellipsis: false,
            ariaLabel: 'Next page',
        });

        return items;
    });
}
