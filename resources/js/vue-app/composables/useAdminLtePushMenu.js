import { onBeforeUnmount, onMounted } from 'vue';

const SIDEBAR_TOGGLE_SELECTOR = '[data-lte-toggle="sidebar"]';

/**
 * AdminLTE binds sidebar toggles on DOMContentLoaded. In the Vue SPA the shell
 * mounts later, so we wire push-menu after the layout is in the DOM.
 */
export function useAdminLtePushMenu() {
    /** @type {(() => void) | null} */
    let resizeHandler = null;
    /** @type {Array<{ btn: Element, handler: (event: Event) => void }>} */
    const toggleBindings = [];

    function initPushMenu() {
        const PushMenu = window.adminlte?.PushMenu;

        if (!PushMenu) {
            return;
        }

        const sidebar = document.querySelector('.app-sidebar');

        if (!sidebar) {
            return;
        }

        const pushMenu = new PushMenu(sidebar);
        pushMenu.init();

        if (resizeHandler) {
            window.removeEventListener('resize', resizeHandler);
        }

        resizeHandler = () => {
            pushMenu.init();
        };
        window.addEventListener('resize', resizeHandler);

        const wrapper = document.querySelector('.app-wrapper');

        if (wrapper && !wrapper.querySelector('.sidebar-overlay')) {
            const overlay = document.createElement('div');
            overlay.className = 'sidebar-overlay';
            wrapper.append(overlay);

            const collapseSidebar = (event) => {
                event.preventDefault();
                new PushMenu(overlay).collapse();
            };

            overlay.addEventListener('touchstart', collapseSidebar, { passive: true });
            overlay.addEventListener('click', collapseSidebar);
        }

        toggleBindings.forEach(({ btn, handler }) => {
            btn.removeEventListener('click', handler);
        });
        toggleBindings.length = 0;

        document.querySelectorAll(SIDEBAR_TOGGLE_SELECTOR).forEach((btn) => {
            const handler = (event) => {
                event.preventDefault();

                let button = event.currentTarget;

                if (button?.dataset.lteToggle !== 'sidebar') {
                    button = button?.closest(SIDEBAR_TOGGLE_SELECTOR);
                }

                if (button) {
                    new PushMenu(button).toggle();
                }
            };

            btn.addEventListener('click', handler);
            toggleBindings.push({ btn, handler });
        });
    }

    onMounted(() => {
        initPushMenu();
    });

    onBeforeUnmount(() => {
        if (resizeHandler) {
            window.removeEventListener('resize', resizeHandler);
            resizeHandler = null;
        }

        toggleBindings.forEach(({ btn, handler }) => {
            btn.removeEventListener('click', handler);
        });
        toggleBindings.length = 0;
    });
}
