@extends('layouts.app')

@push('styles')
<!-- DataTables CSS -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/searchpanes/2.2.0/css/searchPanes.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/select/1.7.0/css/select.bootstrap5.min.css">
@endpush

@section('content')
<div class="container mt-4">
    <div class="col-sm-6"><h3 class="mb-0">Drivers Management</h3></div>
    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="d-flex justify-content-end gap-2 mb-3">
        <button type="button" class="btn btn-brand-primary" data-bs-toggle="modal" data-bs-backdrop="false"  data-bs-target="#addDriverModal">
            <i class="bi bi-plus"></i> Create
        </button>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <h3 class="card-title">Drivers List</h3>
        </div>
        <div class="card-body">
            <table id="driversTable" class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th style="width: 80px;">Sr. No</th>
                        <th>Name</th>
                        <th>Mobile</th>
                        <th>License Number</th>
                        <th>Address</th>

                        <th style="width: 120px;">Actions</th>

                    </tr>
                </thead>
            </table>
        </div>
    </div>
</div>

<!-- Add Driver Modal -->
<div class="modal fade" id="addDriverModal" tabindex="-1" aria-labelledby="addDriverModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-gradient-primary">
                <h5 class="modal-title" id="addDriverModalLabel">
                    <i class="bi bi-person-plus"></i> Add New Driver
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="addDriverForm" method="POST" action="{{ route('drivers.store') }}">
                @csrf
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="modal_name" class="form-label">Name *</label>
                            <input type="text" class="form-control" id="modal_name" name="name" required>
                            <div class="invalid-feedback">Please enter a name.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="modal_mobile" class="form-label">Mobile *</label>
                            <input type="text" class="form-control" id="modal_mobile" name="mobile" pattern="[0-9]{10}" maxlength="10" placeholder="10-digit mobile number" required>
                            <div class="invalid-feedback">Please enter a valid 10-digit mobile number.</div>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="modal_license_number" class="form-label">License Number</label>
                            <input type="text" class="form-control" id="modal_license_number" name="license_number" placeholder="e.g., DL-1420110012345">
                            <div class="invalid-feedback"></div>
                            <small class="form-text text-muted">Optional field</small>
                        </div>
                        <div class="col-md-6">
                            <label for="modal_address" class="form-label">Address</label>
                            <input type="text" class="form-control" id="modal_address" name="address" placeholder="Enter address">
                            <small class="form-text text-muted">Optional field</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-check-circle"></i> Add Driver
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Driver Modal -->
<div class="modal fade" id="editDriverModal" tabindex="-1" aria-labelledby="editDriverModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-gradient-primary">
                <h5 class="modal-title" id="editDriverModalLabel">
                    <i class="bi bi-pencil-square"></i> Edit Driver
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="editDriverForm" method="POST">
                @csrf
                @method('PUT')
                <input type="hidden" id="edit_driver_id" name="driver_id">
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="edit_name" class="form-label">Name *</label>
                            <input type="text" class="form-control" id="edit_name" name="name" required>
                            <div class="invalid-feedback">Please enter a name.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_mobile" class="form-label">Mobile *</label>
                            <input type="text" class="form-control" id="edit_mobile" name="mobile" pattern="[0-9]{10}" maxlength="10" placeholder="10-digit mobile number" required>
                            <div class="invalid-feedback">Please enter a valid 10-digit mobile number.</div>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="edit_license_number" class="form-label">License Number</label>
                            <input type="text" class="form-control" id="edit_license_number" name="license_number" placeholder="e.g., DL-1420110012345">
                            <div class="invalid-feedback"></div>
                            <small class="form-text text-muted">Optional field</small>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_address" class="form-label">Address</label>
                            <input type="text" class="form-control" id="edit_address" name="address" placeholder="Enter address">
                            <small class="form-text text-muted">Optional field</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-check-circle"></i> Update Driver
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<!-- DataTables JS -->
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>
<script src="https://cdn.datatables.net/searchpanes/2.2.0/js/dataTables.searchPanes.min.js"></script>
<script src="https://cdn.datatables.net/searchpanes/2.2.0/js/searchPanes.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/select/1.7.0/js/dataTables.select.min.js"></script>

<script>
$(document).ready(function() {
    var table = $('#driversTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '{{ route("drivers.data") }}',
            type: 'GET'
        },
        columns: [
            { data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false },
            { data: 'name', name: 'name', orderable: true, searchable: true },
            { data: 'mobile', name: 'mobile', orderable: true, searchable: true },
            { data: 'license_number', name: 'license_number', orderable: true, searchable: true },
            { data: 'address', name: 'address', orderable: true, searchable: true },

            { data: 'actions', name: 'actions', orderable: false, searchable: false }

        ],
        order: [[1, 'asc']],
        pageLength: 10,
        lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
        stateLoadParams: function(settings, data) {
            data.order = [[1, 'asc']];
        },
        dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>' +
             '<"row"<"col-sm-12 col-md-12"B>>' +
             '<"row"<"col-sm-12"tr>>' +
             '<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
        buttons: [
            {
                extend: 'excel',
                className: 'btn btn-secondary btn-sm',
                text: '<i class="bi bi-file-earmark-excel"></i> Excel'
            }
        ],
        language: {
            search: "Search:",
            lengthMenu: "Show _MENU_ entries",
            info: "Showing _START_ to _END_ of _TOTAL_ entries",
            infoEmpty: "Showing 0 to 0 of 0 entries",
            infoFiltered: "(filtered from _MAX_ total entries)",
            zeroRecords: "No matching records found",
            emptyTable: "No data available in table",
            paginate: {
                first: "First",
                last: "Last",
                next: "Next",
                previous: "Previous"
            }
        },
        responsive: true,
        autoWidth: false,
        stateSave: true,
        searchDelay: 500,
        initComplete: function() {
            // Add individual column search
            this.api().columns([1, 2,3,4]).every(function() {
                var column = this;
                var $header = $(column.header());
                var title = $header.text().trim();

                // Wrap existing text in a div to keep it at top
                $header.html('<div style="margin-bottom: 5px;">' + title + '</div>');
                let debounceTimer;

                // Create input element
                var input = $('<input type="text" class="form-control form-control-sm" placeholder="Search ' + title + '" />')
                    .appendTo($header)
                    .on('keyup', function() {
                        clearTimeout(debounceTimer);

                        const value = this.value;

                        debounceTimer = setTimeout(() => {
                            if (column.search() !== value) {
                                column.search(value).draw();
                            }
                        }, 500);
                    });
            });
        }
    });

    // Individual column searching
    $('#driversTable thead tr:eq(1) th').each(function(i) {
        if (i === 0 || i === {{ auth()->check() && auth()->user()->canActAsAdmin() ? '5' : '4' }}) {
            return; // Skip ID and Actions columns
        }

        $('input', this).on('keyup change', function() {
            if (table.column(i).search() !== this.value) {
                table.column(i).search(this.value).draw();
            }
        });
    });

    // Handle form submissions with AJAX to prevent page reload
    $(document).on('submit', 'form[action*="drivers"][action*="destroy"]', function(e) {
        e.preventDefault();

        if (confirm('Are you sure you want to delete this driver?')) {
            var form = $(this);
            var url = form.attr('action');

            $.ajax({
                url: url,
                type: 'POST',
                data: form.serialize(),
                success: function(response) {
                    // Show success message
                    $('body').prepend(`
                        <div class="alert alert-success alert-dismissible fade show position-fixed" style="top: 20px; right: 20px; z-index: 9999; min-width: 300px;">
                            <i class="bi bi-check-circle"></i>
                            <strong>Success!</strong> Driver deleted successfully.
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    `);

                    // Auto-hide alert after 5 seconds
                    setTimeout(function() {
                        $('.alert-success').fadeOut();
                    }, 5000);

                    // Reload DataTable
                    table.ajax.reload(null, false);
                },
                error: function(xhr) {
                    // Show error message
                    $('body').prepend(`
                        <div class="alert alert-danger alert-dismissible fade show position-fixed" style="top: 20px; right: 20px; z-index: 9999; min-width: 300px;">
                            <i class="bi bi-exclamation-triangle"></i>
                            <strong>Error!</strong> Unable to delete driver. Please try again.
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    `);

                    setTimeout(function() {
                        $('.alert-danger').fadeOut();
                    }, 5000);
                }
            });
        }
    });

    // Handle Add Driver form submission
    $('#addDriverForm').on('submit', function(e) {
        e.preventDefault();

        // Clear previous validation states
        $(this).find('.form-control').removeClass('is-invalid is-valid');
        $(this).find('.invalid-feedback').hide();

        // Show loading state
        const submitBtn = $(this).find('button[type="submit"]');
        const originalText = submitBtn.html();
        submitBtn.html('<i class="bi bi-hourglass-split"></i> Adding...').prop('disabled', true);

        $.ajax({
            url: $(this).attr('action'),
            type: 'POST',
            data: $(this).serialize(),
            success: function(response) {
                // Show success message
                $('body').prepend(`
                    <div class="alert alert-success alert-dismissible fade show position-fixed" style="top: 20px; right: 20px; z-index: 9999; min-width: 300px;">
                        <i class="bi bi-check-circle"></i>
                        <strong>Success!</strong> Driver has been added successfully.
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                `);

                // Auto-hide alert after 5 seconds
                setTimeout(function() {
                    $('.alert-success').fadeOut();
                }, 5000);

                // Reset form and close modal
                $('#addDriverForm')[0].reset();
                $('#addDriverForm .form-control').removeClass('is-invalid is-valid');
                $('#addDriverModal').modal('hide');

                // Reload DataTable
                table.ajax.reload(null, false);
            },
            error: function(xhr) {
                if (xhr.status === 422) {
                    // Handle validation errors
                    const errors = xhr.responseJSON.errors;
                    Object.keys(errors).forEach(function(field) {
                        const input = $(`#modal_${field}`);
                        input.addClass('is-invalid');
                         // Find correct feedback div
                        const feedback = input.closest('.col-md-6').find('.invalid-feedback');

                        if (feedback.length) {
                            feedback.text(errors[field][0]).show();
                        }
                    });
                } else {
                    // Show general error message
                    $('body').prepend(`
                        <div class="alert alert-danger alert-dismissible fade show position-fixed" style="top: 20px; right: 20px; z-index: 9999; min-width: 300px;">
                            <i class="bi bi-exclamation-triangle"></i>
                            <strong>Error!</strong> Unable to add driver. Please try again.
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    `);

                    setTimeout(function() {
                        $('.alert-danger').fadeOut();
                    }, 5000);
                }
            },
            complete: function() {
                // Restore button state
                submitBtn.html(originalText).prop('disabled', false);
            }
        });
    });

    // Reset Add Driver form when modal is hidden
    $('#addDriverModal').on('hidden.bs.modal', function() {
        $('#addDriverForm')[0].reset();
        $('#addDriverForm .form-control').removeClass('is-invalid is-valid');
        $('#addDriverForm .invalid-feedback').hide();
    });

    // Handle Edit Driver button click
    $(document).on('click', '.edit-driver-btn', function() {
        const driverId = $(this).data('driver-id');
        const driverName = $(this).data('driver-name');
        const driverMobile = $(this).data('driver-mobile');
        const driverLicense = $(this).data('driver-license');
        const driverAddress = $(this).data('driver-address');

        // Populate the edit form
        $('#edit_driver_id').val(driverId);
        $('#edit_name').val(driverName);
        $('#edit_mobile').val(driverMobile);
        $('#edit_license_number').val(driverLicense);
        $('#edit_address').val(driverAddress);

        // Set the form action URL
        $('#editDriverForm').attr('action', `/drivers/${driverId}`);

        // Clear any previous validation states
        $('#editDriverForm .form-control').removeClass('is-invalid is-valid');
        $('#editDriverForm .invalid-feedback').hide();

        // Show the modal
        $('#editDriverModal').modal('show');
    });

    // Handle Edit Driver form submission
    $('#editDriverForm').on('submit', function(e) {
        e.preventDefault();

        // Clear previous validation states
        $(this).find('.form-control').removeClass('is-invalid is-valid');
        $(this).find('.invalid-feedback').hide();

        // Show loading state
        const submitBtn = $(this).find('button[type="submit"]');
        const originalText = submitBtn.html();
        submitBtn.html('<i class="bi bi-hourglass-split"></i> Updating...').prop('disabled', true);

        $.ajax({
            url: $(this).attr('action'),
            type: 'PUT',
            data: $(this).serialize(),
            success: function(response) {
                // Show success message
                $('body').prepend(`
                    <div class="alert alert-success alert-dismissible fade show position-fixed" style="top: 20px; right: 20px; z-index: 9999; min-width: 300px;">
                        <i class="bi bi-check-circle"></i>
                        <strong>Success!</strong> Driver has been updated successfully.
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                `);

                // Auto-hide alert after 5 seconds
                setTimeout(function() {
                    $('.alert-success').fadeOut();
                }, 5000);

                // Close modal
                $('#editDriverModal').modal('hide');

                // Reload DataTable
                table.ajax.reload(null, false);
            },
            error: function(xhr) {
                if (xhr.status === 422) {
                    // Handle validation errors
                    const errors = xhr.responseJSON.errors;
                    Object.keys(errors).forEach(function(field) {
                        const input = $(`#edit_${field}`);
                        input.addClass('is-invalid');
                         // Find correct feedback div
                        const feedback = input.closest('.col-md-6').find('.invalid-feedback');

                        if (feedback.length) {
                            feedback.text(errors[field][0]).show();
                        }
                    });
                } else {
                    // Show general error message
                    $('body').prepend(`
                        <div class="alert alert-danger alert-dismissible fade show position-fixed" style="top: 20px; right: 20px; z-index: 9999; min-width: 300px;">
                            <i class="bi bi-exclamation-triangle"></i>
                            <strong>Error!</strong> Unable to update driver. Please try again.
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    `);

                    setTimeout(function() {
                        $('.alert-danger').fadeOut();
                    }, 5000);
                }
            },
            complete: function() {
                // Restore button state
                submitBtn.html(originalText).prop('disabled', false);
            }
        });
    });

    // Reset Edit Driver form when modal is hidden
    $('#editDriverModal').on('hidden.bs.modal', function() {
        $('#editDriverForm')[0].reset();
        $('#editDriverForm .form-control').removeClass('is-invalid is-valid');
        $('#editDriverForm .invalid-feedback').hide();
    });

    $('#addDriverModal, #editDriverModal').on('shown.bs.modal', function () {
    $('.modal-backdrop').remove();
});

});
</script>
@endpush
