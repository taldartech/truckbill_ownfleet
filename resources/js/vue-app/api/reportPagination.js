export const REPORT_PER_PAGE_OPTIONS = [10, 25, 50, 100];

export const DEFAULT_REPORT_PER_PAGE = 25;

/**
 * @param {Record<string, unknown>} data
 */
export function parsePaginatedReportResponse(data) {
    return {
        rows: data.data ?? [],
        meta: data.meta ?? {},
        summary: data.summary ?? null,
        grandTotal: data.grand_total ?? null,
        companyName: data.company_name ?? null,
        month: data.month ?? null,
    };
}

/**
 * @param {Record<string, unknown>} params
 * @param {number} page
 * @param {number} perPage
 */
export function withReportPagination(params, page = 1, perPage = DEFAULT_REPORT_PER_PAGE) {
    return {
        ...params,
        page,
        per_page: perPage,
    };
}
