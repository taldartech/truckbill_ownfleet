<template>
    <div class="container mt-4 drivers-page">
        <div class="col-sm-6">
            <h3 class="mb-0">Drivers Management</h3>
        </div>

        <div v-if="flash.success" class="alert alert-success mt-3">{{ flash.success }}</div>
        <div v-if="flash.error" class="alert alert-danger mt-3">{{ flash.error }}</div>

        <div v-if="canManage" class="d-flex justify-content-end gap-2 mb-3">
            <AppButton icon="bi bi-plus" label="Create" @click="openCreate" />
        </div>

        <div class="card mb-4">
            <div class="card-header">
                <h3 class="card-title">Drivers List</h3>
            </div>
            <div class="card-body">
                <div v-if="loading" class="text-center py-4 text-muted">Loading drivers…</div>
                <table v-show="!loading" id="driversTable" class="table table-bordered table-striped w-100">
                    <thead>
                        <tr>
                            <th style="width: 80px">Sr. No</th>
                            <th>Name</th>
                            <th>Mobile</th>
                            <th>License Number</th>
                            <th>Address</th>
                            <th v-if="canManage" style="width: 120px">Actions</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>

        <DriverFormModal
            modal-id="addDriverModal"
            :show="showCreate"
            mode="create"
            :saving="saving"
            :field-errors="fieldErrors"
            :form-error="driverFormError"
            @close="closeCreate"
            @submit="handleCreate"
        />

        <DriverFormModal
            modal-id="editDriverModal"
            :show="showEdit"
            mode="edit"
            :driver="editingDriver"
            :saving="saving"
            :field-errors="fieldErrors"
            :form-error="driverFormError"
            @close="closeEdit"
            @submit="handleUpdate"
        />
    </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import { createDriver, deleteDriver, listAllDrivers, updateDriver } from '../../api/drivers';
import DriverFormModal from '../../components/drivers/DriverFormModal.vue';
import AppButton from '../../components/ui/AppButton.vue';
import { usePermissions } from '../../composables/usePermissions';
import { useApiFormErrors } from '../../composables/useApiFormErrors';
import { initDriversDataTable, reloadDriversDataTable } from '../../composables/useDriversDataTable';

const { fieldErrors, formError: driverFormError, clearErrors, applyApiError } = useApiFormErrors();

const { canInCompany } = usePermissions();
const canManage = computed(() => canInCompany('drivers.manage'));

const drivers = ref([]);
const loading = ref(true);
const saving = ref(false);
const showCreate = ref(false);
const showEdit = ref(false);
const editingDriver = ref(null);
const flash = reactive({ success: '', error: '' });

let dataTable = null;

function showToast(message, type = 'success') {
    if (type === 'success') {
        flash.success = message;
        flash.error = '';
    } else {
        flash.error = message;
        flash.success = '';
    }
}

async function loadDrivers() {
    loading.value = true;
    flash.error = '';

    try {
        drivers.value = await listAllDrivers();
    } catch {
        flash.error = 'Could not load drivers.';
        drivers.value = [];
    } finally {
        loading.value = false;
    }
}

function initTable() {
    const tableElement = document.getElementById('driversTable');

    if (!tableElement) {
        return;
    }

    if (dataTable) {
        reloadDriversDataTable(dataTable, drivers.value, canManage.value);
        return;
    }

    dataTable = initDriversDataTable({
        tableElement,
        drivers: drivers.value,
        canManage: canManage.value,
    });
}

function bindTableActions() {
    const $ = window.jQuery;

    if (!$) {
        return;
    }

    $(document).on('click.driversPage', '.edit-driver-btn', function onEditClick() {
        const $btn = $(this);

        editingDriver.value = {
            id: $btn.attr('data-driver-id'),
            name: $btn.attr('data-driver-name'),
            mobile: $btn.attr('data-driver-mobile') ?? '',
            license_number: $btn.attr('data-driver-license') ?? '',
            address: $btn.attr('data-driver-address') ?? '',
            daily_wage: $btn.attr('data-driver-daily-wage') ?? '0',
            is_active: ($btn.attr('data-driver-is-active') ?? '0') === '1',
        };
        fieldErrors.value = {};
        showEdit.value = true;
    });

    $(document).on('click.driversPage', '.delete-driver-btn', async function onDeleteClick() {
        const $btn = $(this);
        const driverId = $btn.data('driver-id');
        const driverName = $btn.data('driver-name');

        if (!window.confirm(`Are you sure you want to delete driver "${driverName}"?`)) {
            return;
        }

        try {
            await deleteDriver(driverId);
            showToast('Driver deleted successfully.');
            await loadDrivers();
            initTable();
        } catch {
            showToast('Unable to delete driver. Please try again.', 'error');
        }
    });
}

function unbindTableActions() {
    window.jQuery?.(document).off('click.driversPage');
}

function clearFormState() {
    clearErrors();
}

function openCreate() {
    clearFormState();
    showCreate.value = true;
}

function closeCreate() {
    showCreate.value = false;
    clearFormState();
}

function closeEdit() {
    showEdit.value = false;
    editingDriver.value = null;
    clearFormState();
}

function applyValidationError(error) {
    if (applyApiError(error)) {
        return;
    }

    if (error.response?.status === 419) {
        showToast('Your session has expired. Please refresh the page and sign in again.', 'error');
        return;
    }

    if (error.response?.status === 403) {
        showToast('You do not have permission to perform this action.', 'error');
        return;
    }

    showToast('Unable to save driver. Please try again.', 'error');
}

async function handleCreate(payload) {
    saving.value = true;
    clearFormState();

    try {
        await createDriver(payload);
        showToast('Driver has been added successfully.');
        closeCreate();
        await loadDrivers();
        initTable();
    } catch (error) {
        applyValidationError(error);
    } finally {
        saving.value = false;
    }
}

async function handleUpdate(payload) {
    if (!editingDriver.value?.id) {
        return;
    }

    saving.value = true;
    clearFormState();

    try {
        await updateDriver(editingDriver.value.id, payload);
        showToast('Driver has been updated successfully.');
        closeEdit();
        await loadDrivers();
        initTable();
    } catch (error) {
        applyValidationError(error);
    } finally {
        saving.value = false;
    }
}

onMounted(async () => {
    bindTableActions();
    await loadDrivers();
    initTable();
});

onBeforeUnmount(() => {
    unbindTableActions();

    if (dataTable) {
        dataTable.destroy();
        dataTable = null;
    }
});
</script>
