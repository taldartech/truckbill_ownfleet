<template>
    <div class="app-lottie-player d-flex justify-content-center" :style="wrapperStyle">
        <div v-if="loadError" class="lottie-fallback text-muted small">
            <i :class="fallbackIcon" style="font-size: 4rem"></i>
        </div>
        <div v-else ref="hostRef" class="lottie-host" :style="hostStyle"></div>
    </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

const LOTTIE_PLAYER_SRC =
    'https://cdn.jsdelivr.net/npm/@lottiefiles/lottie-player@2.0.12/dist/lottie-player.js';

let lottiePlayerScriptPromise = null;

function ensureLottiePlayerScript() {
    if (customElements.get('lottie-player')) {
        return Promise.resolve();
    }

    if (lottiePlayerScriptPromise) {
        return lottiePlayerScriptPromise;
    }

    lottiePlayerScriptPromise = new Promise((resolve, reject) => {
        const existing = document.querySelector(`script[src="${LOTTIE_PLAYER_SRC}"]`);

        if (existing) {
            existing.addEventListener('load', () => resolve(), { once: true });
            existing.addEventListener('error', () => reject(new Error('Lottie player failed')), { once: true });

            return;
        }

        const script = document.createElement('script');
        script.src = LOTTIE_PLAYER_SRC;
        script.async = true;
        script.onload = () => resolve();
        script.onerror = () => reject(new Error('Lottie player failed'));
        document.head.appendChild(script);
    });

    return lottiePlayerScriptPromise;
}

const props = defineProps({
    src: {
        type: String,
        required: true,
    },
    width: {
        type: [Number, String],
        default: 280,
    },
    height: {
        type: [Number, String],
        default: 280,
    },
    loop: {
        type: Boolean,
        default: true,
    },
    autoplay: {
        type: Boolean,
        default: true,
    },
    fallbackIcon: {
        type: String,
        default: 'fas fa-lock text-brand-primary opacity-75',
    },
});

const hostRef = ref(null);
const loadError = ref(false);
let playerEl = null;

const wrapperStyle = computed(() => ({
    minHeight: typeof props.height === 'number' ? `${props.height}px` : props.height,
}));

const hostStyle = computed(() => ({
    width: typeof props.width === 'number' ? `${props.width}px` : props.width,
    height: typeof props.height === 'number' ? `${props.height}px` : props.height,
}));

onMounted(async () => {
    try {
        await ensureLottiePlayerScript();

        if (!hostRef.value) {
            return;
        }

        playerEl = document.createElement('lottie-player');
        playerEl.setAttribute('src', props.src);
        playerEl.setAttribute('background', 'transparent');
        playerEl.setAttribute('speed', '1');
        playerEl.style.width = '100%';
        playerEl.style.height = '100%';

        if (props.loop) {
            playerEl.setAttribute('loop', '');
        }

        if (props.autoplay) {
            playerEl.setAttribute('autoplay', '');
        }

        hostRef.value.appendChild(playerEl);
    } catch {
        loadError.value = true;
    }
});

onBeforeUnmount(() => {
    playerEl?.remove();
    playerEl = null;
});
</script>

<style scoped>
.lottie-host {
    margin: 0 auto;
}

.lottie-fallback {
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 12rem;
}
</style>
