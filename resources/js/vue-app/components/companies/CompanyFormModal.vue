<template>
    <div
        :id="modalId"
        class="modal fade"
        tabindex="-1"
        :class="{ show: show }"
        :style="show ? { display: 'block' } : {}"
        :aria-hidden="!show"
    >
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form @submit.prevent="$emit('submit', form)">
                    <div class="modal-header">
                        <h5 class="modal-title">Register Company</h5>
                        <button type="button" class="btn-close" aria-label="Close" @click="$emit('close')" />
                    </div>
                    <div class="modal-body">
                        <div v-if="formError" class="alert alert-danger py-2">{{ formError }}</div>
                        <div class="mb-3">
                            <label class="form-label" for="company_name">Company Name *</label>
                            <input
                                id="company_name"
                                v-model="form.name"
                                type="text"
                                class="form-control"
                                :class="{ 'is-invalid': fieldErrors.name }"
                                required
                            />
                            <div v-if="fieldErrors.name" class="invalid-feedback d-block">
                                {{ fieldErrors.name[0] }}
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label" for="gst_number">GST Number</label>
                                <input
                                    id="gst_number"
                                    v-model="form.gst_number"
                                    type="text"
                                    class="form-control"
                                    :class="{ 'is-invalid': fieldErrors.gst_number }"
                                />
                                <div v-if="fieldErrors.gst_number" class="invalid-feedback d-block">
                                    {{ fieldErrors.gst_number[0] }}
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label" for="pan_number">PAN Number</label>
                                <input
                                    id="pan_number"
                                    v-model="form.pan_number"
                                    type="text"
                                    class="form-control"
                                    :class="{ 'is-invalid': fieldErrors.pan_number }"
                                />
                                <div v-if="fieldErrors.pan_number" class="invalid-feedback d-block">
                                    {{ fieldErrors.pan_number[0] }}
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label" for="gst_scheme">GST Scheme</label>
                                <input id="gst_scheme" v-model="form.gst_scheme" type="text" class="form-control" />
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label" for="gst_type">GST Type</label>
                                <input id="gst_type" v-model="form.gst_type" type="text" class="form-control" />
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="address">Address</label>
                            <textarea id="address" v-model="form.address" class="form-control" rows="2" />
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label" for="contact_person">Contact Person</label>
                                <input
                                    id="contact_person"
                                    v-model="form.contact_person"
                                    type="text"
                                    class="form-control"
                                />
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label" for="contact_number">Contact Number</label>
                                <input
                                    id="contact_number"
                                    v-model="form.contact_number"
                                    type="text"
                                    class="form-control"
                                />
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" @click="$emit('close')">Cancel</button>
                        <button type="submit" class="btn btn-brand-primary" :disabled="saving">
                            {{ saving ? 'Saving…' : 'Create' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div v-if="show" class="modal-backdrop fade show" />
</template>

<script setup>
import { reactive, watch } from 'vue';

const props = defineProps({
    modalId: { type: String, default: 'companyFormModal' },
    show: { type: Boolean, default: false },
    saving: { type: Boolean, default: false },
    fieldErrors: { type: Object, default: () => ({}) },
    formError: { type: String, default: '' },
});

defineEmits(['close', 'submit']);

const form = reactive({
    name: '',
    gst_number: '',
    pan_number: '',
    gst_scheme: '',
    gst_type: '',
    address: '',
    contact_person: '',
    contact_number: '',
});

watch(
    () => props.show,
    (visible) => {
        if (!visible) {
            return;
        }

        form.name = '';
        form.gst_number = '';
        form.pan_number = '';
        form.gst_scheme = '';
        form.gst_type = '';
        form.address = '';
        form.contact_person = '';
        form.contact_number = '';
    },
);
</script>
