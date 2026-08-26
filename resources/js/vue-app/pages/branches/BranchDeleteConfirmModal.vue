<template>
    <AppModal
        :show="show"
        title="Delete Branch"
        size="lg"
        @close="handleClose"
    >
        <div class="branch-delete-confirm">
            <p class="mb-3">
                Are you sure you want to delete
                <strong>{{ branch?.branch_name }} <small>({{branch?.company_name}})</small></strong>?

                
            </p>

            <div class="alert alert-warning mb-3">
                <template v-if="hasResources">
                    <div>
                        This branch has
                        <strong>{{ resourceSummary }}</strong>.
                    </div>
                </template>

                <template v-else>
                    <div>
                        This branch has no trucks or drivers.
                    </div>
                </template>

                <div
                    v-if="(impact?.users_count ?? 0) > 0"
                    class="mt-2"
                >
                    <strong>{{ impact.users_count }}</strong>
                    assigned user(s) will be unassigned and cannot log in
                    until reassigned.
                </div>

                <div class="mt-2">
                    Operational data stays in the system but will not show
                    under any active branch.
                </div>
            </div>

            <div class="mb-3">
                <label
                    for="branchDeleteReason"
                    class="form-label fw-semibold"
                >
                    Reason for deletion
                </label>

                <textarea
                    id="branchDeleteReason"
                    v-model="reason"
                    class="form-control"
                    rows="3"
                    placeholder="Enter reason for deleting this branch"
                    :disabled="loading"
                />
            </div>
        </div>

        <template #footer>
            <AppButton
                variant="secondary"
                label="Cancel"
                :disabled="loading"
                @click="handleClose"
            />

            <AppButton
                variant="danger"
                label="Delete Branch"
                :loading="loading"
                :disabled="!reason.trim() || loading"
                @click="handleConfirm"
            />
        </template>
    </AppModal>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import AppButton from '../../components/ui/AppButton.vue';
import AppModal from '../../components/ui/AppModal.vue';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },

    branch: {
        type: Object,
        default: null,
    },

    impact: {
        type: Object,
        default: null,
    },

    loading: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits([
    'close',
    'confirm',
]);

const reason = ref('');

const hasResources = computed(() => {
    return (
        (props.impact?.trucks_count ?? 0) > 0 ||
        (props.impact?.drivers_count ?? 0) > 0
    );
});

const resourceSummary = computed(() => {
    const resources = [];

    if ((props.impact?.trucks_count ?? 0) > 0) {
        resources.push(
            `${props.impact.trucks_count} truck(s)`,
        );
    }

    if ((props.impact?.drivers_count ?? 0) > 0) {
        resources.push(
            `${props.impact.drivers_count} driver(s)`,
        );
    }

    return resources.join(' and ');
});

watch(
    () => props.show,
    (isOpen) => {
        if (isOpen) {
            reason.value = '';
        }
    },
);

function handleClose() {
    if (props.loading) {
        return;
    }

    reason.value = '';
    emit('close');
}

function handleConfirm() {
    const trimmedReason = reason.value.trim();

    if (!trimmedReason || props.loading) {
        return;
    }

    emit('confirm', trimmedReason);
}
</script>