<template>
    <span v-if="soft" :class="statusBadgeSoftClass(document)">
        <span>{{ document.status_label ?? '—' }}</span>
        <span v-if="document.status_sublabel" class="compliance-badge__sub">
            {{ document.status_sublabel }}
        </span>
    </span>
    <span v-else :class="['badge document-status-badge', statusBadgeClass(document)]">
        <span class="document-status-badge__label">{{ document.status_label ?? '—' }}</span>
        <span v-if="document.status_sublabel" class="document-status-badge__sub">
            {{ document.status_sublabel }}
        </span>
    </span>
</template>

<script setup>
import { statusBadgeClass, statusBadgeSoftClass } from '../../utils/documentStatus';

defineProps({
    document: { type: Object, required: true },
    soft: { type: Boolean, default: false },
});
</script>

<style scoped>
.document-status-badge {
    display: inline-flex;
    flex-direction: column;
    align-items: flex-start;
    line-height: 1.2;
    padding: 0.35em 0.55em;
    text-align: left;
}

.document-status-badge__label {
    font-weight: 600;
}

.document-status-badge__sub {
    font-size: 0.72em;
    opacity: 0.9;
}
</style>
