export const DROPDOWN_GAP = 4;
export const VIEWPORT_PADDING = 8;
export const DEFAULT_DROPDOWN_LIST_MAX_HEIGHT = 240;
export const MIN_DROPDOWN_LIST_HEIGHT = 120;
export const ESTIMATED_DROPDOWN_HEIGHT = 280;

/**
 * @param {DOMRect} triggerRect
 * @param {number} dropdownHeight
 * @param {{ gap?: number, viewportPadding?: number, maxListHeight?: number, chromeHeight?: number, zIndex?: string }} [options]
 * @returns {{ opensAbove: boolean, style: Record<string, string>, listMaxHeight: number }}
 */
export function computeFixedDropdownPlacement(triggerRect, dropdownHeight, options = {}) {
    const gap = options.gap ?? DROPDOWN_GAP;
    const padding = options.viewportPadding ?? VIEWPORT_PADDING;
    const maxListHeight = options.maxListHeight ?? DEFAULT_DROPDOWN_LIST_MAX_HEIGHT;
    const chromeHeight = options.chromeHeight ?? 0;
    const estimatedPanelHeight = Math.max(dropdownHeight, chromeHeight + MIN_DROPDOWN_LIST_HEIGHT);

    const spaceBelow = window.innerHeight - triggerRect.bottom - padding;
    const spaceAbove = triggerRect.top - padding;
    const requiredBelow = Math.min(estimatedPanelHeight, chromeHeight + maxListHeight + gap);
    const opensAbove = spaceBelow < requiredBelow && spaceAbove > spaceBelow;

    const availableSpace = (opensAbove ? spaceAbove : spaceBelow) - gap;
    const listMaxHeight = Math.min(
        maxListHeight,
        Math.max(MIN_DROPDOWN_LIST_HEIGHT, availableSpace - chromeHeight),
    );
    const panelHeight = chromeHeight + listMaxHeight;

    const style = {
        position: 'fixed',
        left: `${Math.max(padding, triggerRect.left)}px`,
        width: `${triggerRect.width}px`,
        zIndex: options.zIndex ?? '1060',
    };

    if (opensAbove) {
        style.top = `${Math.max(padding, triggerRect.top - panelHeight - gap)}px`;
    } else {
        style.top = `${triggerRect.bottom + gap}px`;
    }

    const maxLeft = window.innerWidth - triggerRect.width - padding;

    if (maxLeft > padding) {
        style.left = `${Math.min(Math.max(padding, triggerRect.left), maxLeft)}px`;
    }

    return {
        opensAbove,
        style,
        listMaxHeight,
    };
}
