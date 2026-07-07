export const SELECT_SEARCH_THRESHOLD = 8;

export function shouldShowSelectSearch(optionCount) {
    return optionCount > SELECT_SEARCH_THRESHOLD;
}
