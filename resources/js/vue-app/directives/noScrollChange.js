export const noScrollChange = {
    mounted(el) {
        el.addEventListener(
            'wheel',
            (event) => {
                event.preventDefault();
                el.blur();
            },
            { passive: false },
        );
    },
};
