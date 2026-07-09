function parseMin(el) {
    const raw = el.getAttribute('min');

    if (raw === null || raw === '') {
        return null;
    }

    const min = Number(raw);

    return Number.isFinite(min) ? min : null;
}

function shouldBlockNegative(min) {
    return min !== null && min >= 0;
}

function clampNegative(el) {
    if (el.value === '') {
        return;
    }

    const value = el.valueAsNumber;

    if (Number.isFinite(value) && value < 0) {
        el.value = '';
        el.dispatchEvent(new Event('input', { bubbles: true }));
    }
}

export const noScrollChange = {
    mounted(el) {
        const onWheel = (event) => {
            event.preventDefault();
            el.blur();
        };

        el.addEventListener('wheel', onWheel, { passive: false });

        const min = parseMin(el);

        if (!shouldBlockNegative(min)) {
            el._noScrollChangeCleanup = () => {
                el.removeEventListener('wheel', onWheel);
            };

            return;
        }

        const floor = min ?? 0;

        const onKeydown = (event) => {
            if (event.key === '-' || event.key === 'Minus' || event.key === 'e' || event.key === 'E') {
                event.preventDefault();

                return;
            }

            if (event.key === 'ArrowDown') {
                const current = el.value === '' ? floor : Number(el.value);

                if (Number.isFinite(current) && current <= floor) {
                    event.preventDefault();
                }
            }
        };

        const onInput = () => {
            clampNegative(el);
        };

        const onPaste = (event) => {
            const text = event.clipboardData?.getData('text') ?? '';
            const parsed = Number(text.trim());

            if (text.includes('-') || (Number.isFinite(parsed) && parsed < 0)) {
                event.preventDefault();
            }
        };

        el.addEventListener('keydown', onKeydown);
        el.addEventListener('input', onInput);
        el.addEventListener('paste', onPaste);

        el._noScrollChangeCleanup = () => {
            el.removeEventListener('wheel', onWheel);
            el.removeEventListener('keydown', onKeydown);
            el.removeEventListener('input', onInput);
            el.removeEventListener('paste', onPaste);
        };
    },
    unmounted(el) {
        el._noScrollChangeCleanup?.();
    },
};
