<template>
    <AppModal :show="show" :loading="uploading" as-form @close="handleClose" @submit="handleUpload">
        <template #title>
            <i class="bi bi-cloud-arrow-up me-2"></i>
            {{ title }}
        </template>

        <div class="bulk-upload-content">

            <p class="text-muted small mb-3">
                Upload multiple records using the Excel template.
                Download the template first, fill in the required data,
                and then upload the completed file.
            </p>

            <!-- Download template -->
            <div class="bulk-upload-template-card mb-3">
                <div>
                    <div class="fw-semibold">
                        {{ title }} Template
                    </div>

                    <div class="text-muted small">
                        {{ templateDescription }}
                    </div>
                </div>

                <button type="button" class="btn btn-outline-primary btn-sm"
                    :disabled="uploading || downloadingTemplate" @click="downloadTemplateFile">
                    <i class="bi bi-download me-1"></i>

                    {{
                        downloadingTemplate
                            ? 'Downloading…'
                            : 'Download Template'
                    }}
                </button>
            </div>

            <!-- File selection -->
            <div class="mb-3">
                <label :for="fileInputId" class="form-label fw-semibold">
                    Upload Bulk Data
                    <span class="text-danger">*</span>
                </label>

                <input :id="fileInputId" ref="fileInput" type="file" class="form-control" :accept="accept"
                    :disabled="uploading" @change="handleFileChange" />

                <div class="form-text">
                    Upload the completed Excel file.
                </div>
            </div>

            <!-- Selected file -->
            <div v-if="selectedFile" class="selected-file mb-3">
                <i class="bi bi-file-earmark-excel me-2"></i>

                <span>
                    {{ selectedFile.name }}
                </span>

                <span class="text-muted small ms-auto">
                    {{ formatFileSize(selectedFile.size) }}
                </span>
            </div>

            <!-- File error -->
            <div v-if="fileError" class="alert alert-danger py-2 small">
                {{ fileError }}
            </div>

            <!-- Upload progress -->
            <div v-if="uploading" class="bulk-upload-progress">
                <div class="d-flex justify-content-between mb-1">
                    <span class="small fw-semibold">
                        Uploading...
                    </span>

                    <span class="small text-muted">
                        {{ uploadProgress }}%
                    </span>
                </div>

                <div class="progress" role="progressbar" :aria-valuenow="uploadProgress" aria-valuemin="0"
                    aria-valuemax="100">
                    <div class="progress-bar" :style="{ width: `${uploadProgress}%` }"></div>
                </div>
            </div>

            <!-- Result -->
            <div v-if="uploadResult" class="bulk-upload-result mt-3">
                <div class="alert" :class="uploadResult.failed > 0
                        ? 'alert-warning'
                        : 'alert-success'
                    ">
                    <div class="fw-semibold mb-2">
                        Bulk upload completed
                    </div>

                    <div class="small">
                        <span>
                            Total:
                            <strong>
                                {{ uploadResult.total ?? 0 }}
                            </strong>
                        </span>

                        <span class="text-success ms-3">
                            ✓ {{ uploadResult.successful ?? 0 }} imported
                        </span>

                        <span v-if="uploadResult.failed > 0" class="text-danger ms-3">
                            ✕ {{ uploadResult.failed }} failed
                        </span>
                    </div>
                </div>

                <!-- Row-level errors -->
                <div v-if="uploadResult.errors?.length" class="bulk-upload-errors mt-3">
                    <div class="fw-semibold text-danger mb-2">
                        Upload Errors
                    </div>

                    <div class="table-responsive">
                        <table class="table table-sm table-bordered align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 90px">
                                        Excel Row
                                    </th>

                                    <th>
                                        {{ errorLabel }}
                                    </th>

                                    <th>
                                        Error
                                    </th>
                                </tr>
                            </thead>

                            <tbody>
                                <tr v-for="(error, index) in uploadResult.errors" :key="`${error.row}-${index}`">
                                    <td>
                                        <strong>
                                            Row {{ error.row }}
                                        </strong>
                                    </td>

                                    <td>
                                        {{ error.value ?? '—' }}
                                    </td>

                                    <td>
                                        <div v-for="(
                                            message, messageIndex
                                            ) in error.errors" :key="messageIndex" class="text-danger small">
                                            {{ message }}
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- No errors -->
                <div v-else class="text-success small mt-2">
                    All rows were imported successfully.
                </div>
            </div>
        </div>

        <template #footer>
            <button type="button" class="btn btn-secondary" :disabled="uploading" @click="handleClose">
                Cancel
            </button>

            <AppButton type="submit" label="Upload" :loading="uploading" :disabled="!selectedFile || uploading" />
        </template>
    </AppModal>
</template>

<script setup>
import { computed, ref } from 'vue';
import AppButton from './AppButton.vue';
import AppModal from './AppModal.vue';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },

    title: {
        type: String,
        default: 'Bulk Upload',
    },

    templateName: {
        type: String,
        default: 'bulk_upload_template.xlsx',
    },

    templateDescription: {
        type: String,
        default: 'Use the provided template format for the upload.',
    },

    accept: {
        type: String,
        default: '.xlsx,.xls,.csv',
    },

    errorLabel: {
        type: String,
        default: 'Value',
    },

    downloadTemplate: {
        type: Function,
        required: true,
    },

    uploadFile: {
        type: Function,
        required: true,
    },
});

const emit = defineEmits([
    'close',
    'uploaded',
]);

const uploading = ref(false);
const fileInput = ref(null);
const selectedFile = ref(null);
const fileError = ref('');
const downloadingTemplate = ref(false);
const uploadProgress = ref(0);
const uploadResult = ref(null);

const fileInputId = computed(() => {
    return `${props.title
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')}-bulk-file`;
});

function handleFileChange(event) {
    const file = event.target.files?.[0] ?? null;

    fileError.value = '';
    uploadResult.value = null;
    uploadProgress.value = 0;

    if (!file) {
        selectedFile.value = null;
        return;
    }

    const allowedExtensions = [
        'xlsx',
        'xls',
        'csv',
    ];

    const extension = file.name
        .split('.')
        .pop()
        ?.toLowerCase();

    if (!allowedExtensions.includes(extension)) {
        selectedFile.value = null;

        fileError.value =
            'Please select a valid Excel or CSV file.';

        if (fileInput.value) {
            fileInput.value.value = '';
        }

        return;
    }

    selectedFile.value = file;
}

async function downloadTemplateFile() {
    downloadingTemplate.value = true;
    fileError.value = '';

    try {
        const blob = await props.downloadTemplate();

        const url = window.URL.createObjectURL(blob);

        const link = document.createElement('a');

        link.href = url;
        link.download = props.templateName;

        document.body.appendChild(link);
        link.click();
        link.remove();

        window.URL.revokeObjectURL(url);
    } catch {
        fileError.value =
            'Could not download the template.';
    } finally {
        downloadingTemplate.value = false;
    }
}

async function handleUpload() {
    if (!selectedFile.value) {
        fileError.value =
            'Please select a file to upload.';

        return;
    }

    fileError.value = '';
    uploadProgress.value = 0;
    uploadResult.value = null;
    uploading.value = true;

    try {
        const result = await props.uploadFile(
            selectedFile.value,
            (progressEvent) => {
                if (!progressEvent.total) {
                    return;
                }

                uploadProgress.value = Math.round(
                    (progressEvent.loaded /
                        progressEvent.total) *
                    100,
                );
            },
        );

        uploadProgress.value = 100;
        uploadResult.value = result;

        emit('uploaded', result);
    } catch (error) {
        fileError.value =
            error.response?.data?.message ??
            'Could not upload the file.';
    } finally {
        uploading.value = false;
    }
}

function handleClose() {
    if (
        uploading.value ||
        downloadingTemplate.value
    ) {
        return;
    }

    reset();

    emit('close');
}

function reset() {
    selectedFile.value = null;
    fileError.value = '';
    uploadProgress.value = 0;
    uploadResult.value = null;
    uploading.value = false;

    if (fileInput.value) {
        fileInput.value.value = '';
    }
}

function formatFileSize(bytes) {
    if (!bytes) {
        return '0 KB';
    }

    const kb = bytes / 1024;

    if (kb < 1024) {
        return `${kb.toFixed(1)} KB`;
    }

    return `${(kb / 1024).toFixed(1)} MB`;
}
</script>

<style scoped>
.bulk-upload-template-card {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 0.9rem 1rem;
    border: 1px solid #e2e8f0;
    border-radius: 0.75rem;
    background: #f8fafc;
}

.selected-file {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 0.9rem;
    border: 1px solid #e2e8f0;
    border-radius: 0.65rem;
    background: #f8fafc;
}

.selected-file i {
    font-size: 1.1rem;
}

.bulk-upload-progress .progress {
    height: 8px;
}
</style>