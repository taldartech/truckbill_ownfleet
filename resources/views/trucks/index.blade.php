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
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h3 class="mb-0">Truck Management</h3>
        @if(auth()->check() && in_array(auth()->user()->userRole->name, ['admin', 'moderator', 'super_admin']))
            <a href="{{ route('trucks.create') }}" class="btn btn-brand-primary">
                <i class="bi bi-plus-circle"></i> Add Truck
            </a>
        @endif
    </div>
    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="card mb-4">
        <div class="card-header">
            <h3 class="card-title">Trucks List</h3>
        </div>
        <div class="card-body">
            <table id="trucksTable" class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th style="width: 80px;">Sr. No</th>
                        <th>Truck Number</th>
                        <th>Truck Type</th>
                        <th>Registration Date</th>
                        <th>Truck Owner</th>
                        <th>Driver</th>
                        @if(auth()->check() && auth()->user()->canActAsAdmin())
                        <th style="width: 120px;">Actions</th>
                        @endif
                    </tr>
                </thead>
            </table>
        </div>
    </div>
</div>

<!-- Truck Details Modal -->
<div class="modal fade" id="truckDetailsModal" tabindex="-1" aria-labelledby="truckDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="truckDetailsModalLabel">
                    <i class="bi bi-truck"></i> Truck Details
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="truckDetailsContent">
                <div class="text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">Loading truck details...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                @if (auth()->user()?->canActAsAdmin())
                    <button type="button" class="btn btn-edit" id="editTruckBtn" style="display: none;">
                        <i class="bi bi-pencil"></i> Edit Truck
                    </button>
                @endif
            </div>
        </div>
    </div>
</div>

<!-- Assign Driver Modal -->
<div class="modal fade" id="assignDriverModal" tabindex="-1" aria-labelledby="assignDriverModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="assignDriverModalLabel">
                    <i class="bi bi-person-plus"></i> Assign Driver to Truck
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="assign_truck_id" name="truck_id">

                <!-- Truck Information Display -->
                <div class="card mb-3">
                    <div class="card-header bg-light">
                        <h6 class="mb-0"><i class="bi bi-truck"></i> Truck Information</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <p class="mb-2"><strong>Truck Number:</strong> <span id="display_truck_number" class="badge bg-primary"></span></p>
                                <p class="mb-2"><strong>Truck Type:</strong> <span id="display_truck_type"></span></p>
                            </div>
                            <div class="col-md-6">
                                <p class="mb-2"><strong>Truck Owner:</strong> <span id="display_transporter_name"></span></p>
                                <p class="mb-2"><strong>Current Driver:</strong> <span id="display_current_driver" class="text-muted">Not assigned</span></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Driver Selection Form -->
                <form id="assignDriverForm">
                    @csrf
                    @method('PUT')
                    <div class="row mb-3">
                        <div class="col-md-8">
                            <label for="assign_driver_id" class="form-label">Select Driver</label>
                            <select class="form-select" id="assign_driver_id" name="driver_id">
                                <option value="">No Driver (Remove current assignment)</option>
                            </select>
                            <div class="invalid-feedback">Please select a driver.</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">&nbsp;</label>
                            <div>
                                <button type="button" class="btn btn-primary" id="showAddDriverForm">
                                    <i class="bi bi-plus-circle"></i> Add New Driver
                                </button>
                            </div>
                        </div>
                    </div>
                </form>

                <!-- Add New Driver Form (Initially Hidden) -->
                <div id="addDriverForm" style="display: none;">
                    <div class="card border-primary">
                        <div class="card-header bg-primary text-white">
                            <h6 class="mb-0"><i class="bi bi-person-plus"></i> Add New Driver</h6>
                        </div>
                        <div class="card-body">
                            <form id="newDriverForm">
                                @csrf
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="new_driver_name" class="form-label">Name *</label>
                                        <input type="text" class="form-control" id="new_driver_name" name="name" required>
                                        <div class="invalid-feedback">Please enter a name.</div>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="new_driver_mobile" class="form-label">Mobile *</label>
                                        <input type="text" class="form-control" id="new_driver_mobile" name="mobile" required pattern="[0-9]{10}" maxlength="10">
                                        <div class="invalid-feedback">Please enter a valid 10-digit mobile number.</div>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="new_driver_license" class="form-label">License Number</label>
                                        <input type="text" class="form-control" id="new_driver_license" name="license_number">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="new_driver_address" class="form-label">Address</label>
                                        <input type="text" class="form-control" id="new_driver_address" name="address">
                                    </div>
                                </div>
                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-check-circle"></i> Add Driver
                                    </button>
                                    <button type="button" class="btn btn-secondary" id="cancelAddDriver">
                                        <i class="bi bi-x-circle"></i> Cancel
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i>
                    <strong>Note:</strong> You can add a new driver using the form above. The new driver will be automatically selected after creation.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" id="assignDriverBtn">
                    <i class="bi bi-check-circle"></i> Assign Driver
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Assign Owner Modal -->
<div class="modal fade" id="assignOwnerModal" tabindex="-1" aria-labelledby="assignOwnerModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="assignOwnerModalLabel">Assign Truck Owner</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="assignOwnerForm" action="{{ route('trucks.assign-owner') }}" method="POST">
                @csrf
                <input type="hidden" name="truck_id" id="assignTruckId">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="transporter_id" class="form-label">Select Truck Owner</label>
                        <select class="form-select select2" id="transporter_id" name="transporter_id" required>
                            <option value="">-- Select Owner --</option>
                            @foreach($owners as $owner)
                            <option value="{{ $owner->id }}">{{ $owner->name }} ({{ $owner->pan_number ?? 'No PAN' }})</option>
                        @endforeach
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Assign Owner</button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('styles')
<!-- Select2 CSS -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<style>
    .select2-container--default .select2-selection--single {
        height: 38px;
        padding: 5px;
        border: 1px solid #ced4da;
    }
    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 36px;
    }
</style>
@endpush

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
    $('#trucksTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '{{ route("trucks.index") }}',
            type: 'GET'
        },
        columns: [
            {
                data: 'DT_RowIndex',
                name: 'DT_RowIndex',
                width: '80px',
                className: 'text-center',
                orderable: false,
                render: function(data, type, row, meta) {
                    return meta.row + 1;
                }
            },
            {
                data: 'truck_number',
                name: 'truck_number',
                render: function(data, type, row) {
                    return '<a href="#" class="truck-number-link text-primary fw-bold" data-id="' + row.id + '">' + data + '</a>';
                }
            },
            {
                data: 'truck_type',
                name: 'truck_type',
                width: '120px'
            },
            {
                data: 'registration_date',
                name: 'registration_date',
                width: '130px'
            },
            {
                data: 'transporter',
                name: 'transporter'
            },
            {
                data: 'driver',
                name: 'driver'
            },
            @if(auth()->check() && auth()->user()->canActAsAdmin())
            {
                data: 'actions',
                name: 'actions',
                orderable: false,
                searchable: false,
                width: '120px',
                className: 'text-center'
            }
            @endif
        ],
        order: [[0, 'desc']],
        pageLength: 10,
        lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
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
            this.api().columns([1, 2, 3, 4, 5]).every(function() {
                var column = this;
                var $header = $(column.header());
                var title = $header.text().trim();

                // Wrap existing text in a div to keep it at top
                $header.html('<div style="margin-bottom: 5px;">' + title + '</div>');

                // Create input element
                var input = $('<input type="text" class="form-control form-control-sm" placeholder="Search ' + title + '" />')
                    .appendTo($header)
                    .on('keyup change clear', function() {
                        if (column.search() !== this.value) {
                            column.search(this.value).draw();
                        }
                    });
            });
        }
    });

    // Handle delete confirmation
    $(document).on('submit', 'form[action*="destroy"]', function(e) {
        if (!confirm('Are you sure you want to delete this truck?')) {
            e.preventDefault();
            return false;
        }
    });

    // Handle truck number click to show details
    $(document).on('click', '.truck-number-link', function(e) {
        e.preventDefault();
        var truckId = $(this).data('id');

        // Show modal with loading state
        $('#truckDetailsModal').modal('show');
        $('#truckDetailsContent').html(`
            <div class="text-center">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-2">Loading truck details...</p>
            </div>
        `);
        $('#editTruckBtn').hide();

        // Fetch truck details
        $.ajax({
            url: '/trucks/' + truckId,
            type: 'GET',
            success: function(data) {
                var detailsHtml = `
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card h-100">
                                <div class="card-header bg-light">
                                    <h6 class="mb-0"><i class="bi bi-truck"></i> Truck Information</h6>
                                </div>
                                <div class="card-body">
                                    <p class="mb-2"><strong>Truck Number:</strong> <span class="badge bg-primary">${data.truck_number}</span></p>
                                    <p class="mb-2"><strong>Truck Type:</strong> ${data.truck_type}</p>
                                    <p class="mb-2"><strong>Registration Date:</strong> ${data.registration_date || 'Not specified'}</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card h-100">
                                <div class="card-header bg-light">
                                    <h6 class="mb-0"><i class="bi bi-person-badge"></i> Truck Owner Information</h6>
                                </div>
                                <div class="card-body">
                                    ${data.transporter ? `
                                    <p class="mb-2"><strong>Name:</strong> ${data.transporter.name}</p>
                                    <p class="mb-2"><strong>Short Name:</strong> ${data.transporter.short_name || 'N/A'}</p>
                                    <p class="mb-2"><strong>Mobile:</strong> ${data.transporter.mobile_number}</p>
                                    <p class="mb-2"><strong>GST Number:</strong> ${data.transporter.gst_number || 'N/A'}</p>
                                    <p class="mb-2"><strong>PAN Number:</strong> ${data.transporter.pan_number || 'N/A'}</p>
                                    ` : '<p class="text-muted">No truck owner assigned</p>'}
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row mt-3">
                        <div class="col-md-6">
                            <div class="card h-100">
                                <div class="card-header bg-light">
                                    <h6 class="mb-0"><i class="bi bi-person-circle"></i> Driver Information</h6>
                                </div>
                                <div class="card-body">
                                    ${data.driver ? `
                                        <p class="mb-2"><strong>Name:</strong> ${data.driver.name}</p>
                                        <p class="mb-2"><strong>Mobile:</strong> ${data.driver.mobile}</p>
                                        <p class="mb-2"><strong>License Number:</strong> ${data.driver.license_number || 'N/A'}</p>
                                    ` : '<p class="text-muted">No driver assigned</p>'}
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card h-100">
                                <div class="card-header bg-light">
                                    <h6 class="mb-0"><i class="bi bi-geo-alt"></i> Address Information</h6>
                                </div>
                                <div class="card-body">
                                    <p class="mb-2"><strong>Address:</strong> ${data.transporter ? data.transporter.address : 'Not specified'}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row mt-3">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header bg-light">
                                    <h6 class="mb-0"><i class="bi bi-clock-history"></i> Record Information</h6>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <p class="mb-1"><strong>Created:</strong> ${data.created_at}</p>
                                        </div>
                                        <div class="col-md-6">
                                            <p class="mb-1"><strong>Last Updated:</strong> ${data.updated_at}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                `;

                $('#truckDetailsContent').html(detailsHtml);

                // Show edit button and set up click handler
                $('#editTruckBtn').show().off('click').on('click', function() {
                    window.location.href = '/trucks/' + data.id + '/edit';
                });
            },
            error: function(xhr) {
                $('#truckDetailsContent').html(`
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle"></i>
                        <strong>Error!</strong> Unable to load truck details. Please try again.
                    </div>
                `);
            }
        });
    });

    // Handle assign driver button click
    $(document).on('click', '.assign-driver-btn', function(e) {
        e.preventDefault();

        var truckId = $(this).data('truck-id');
        var truckNumber = $(this).data('truck-number');
        var truckType = $(this).data('truck-type');
        var transporterName = $(this).data('transporter-name');
        var currentDriverId = $(this).data('current-driver-id');
        var currentDriverName = $(this).data('current-driver-name');

        // Populate truck information
        $('#assign_truck_id').val(truckId);
        $('#display_truck_number').text(truckNumber);
        $('#display_truck_type').text(truckType);
        $('#display_transporter_name').text(transporterName);
        $('#display_current_driver').text(currentDriverName || 'Not assigned');

        // Load drivers and set current selection
        loadDriversForAssignment(currentDriverId);

        // Show modal
        $('#assignDriverModal').modal('show');
    });

    // Function to load drivers for assignment
    function loadDriversForAssignment(currentDriverId) {
        $.ajax({
            url: '/api/drivers',
            type: 'GET',
            success: function(drivers) {
                var driverSelect = $('#assign_driver_id');
                driverSelect.empty();
                driverSelect.append('<option value="">No Driver (Remove current assignment)</option>');

                drivers.forEach(function(driver) {
                    var selected = (driver.id == currentDriverId) ? 'selected' : '';
                    driverSelect.append('<option value="' + driver.id + '" ' + selected + '>' + driver.name + ' - ' + driver.mobile + '</option>');
                });
            },
            error: function() {
                console.error('Failed to load drivers');
            }
        });
    }

    // Show/Hide Add Driver Form
    $('#showAddDriverForm').on('click', function() {
        $('#addDriverForm').slideDown();
        $(this).hide();
    });

    $('#cancelAddDriver').on('click', function() {
        $('#addDriverForm').slideUp();
        $('#showAddDriverForm').show();
        $('#newDriverForm')[0].reset();
        $('#newDriverForm').removeClass('was-validated');
    });

    // Handle new driver form submission
    $('#newDriverForm').on('submit', function(e) {
        e.preventDefault();

        // Validate form
        if (!this.checkValidity()) {
            e.stopPropagation();
            $(this).addClass('was-validated');
            return;
        }

        var formData = {
            _token: $('meta[name="csrf-token"]').attr('content'),
            name: $('#new_driver_name').val(),
            mobile: $('#new_driver_mobile').val(),
            license_number: $('#new_driver_license').val(),
            address: $('#new_driver_address').val()
        };

        $.ajax({
            url: '{{ route("drivers.store") }}',
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    // Show success message
                    $('body').prepend(`
                        <div class="alert alert-success alert-dismissible fade show position-fixed" style="top: 20px; right: 20px; z-index: 9999; min-width: 300px;">
                            <i class="bi bi-check-circle"></i>
                            <strong>Success!</strong> Driver added successfully!
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    `);

                    // Reset form and hide it
                    $('#newDriverForm')[0].reset();
                    $('#newDriverForm').removeClass('was-validated');
                    $('#addDriverForm').slideUp();
                    $('#showAddDriverForm').show();

                    // Reload drivers and auto-select the new one
                    loadDriversForAssignment(response.driver.id);

                    // Auto-hide success message
                    setTimeout(function() {
                        $('.alert-success').fadeOut();
                    }, 5000);
                }
            },
            error: function(xhr) {
                var errors = xhr.responseJSON?.errors || {};
                var errorMessage = 'Unable to add driver. Please check the form and try again.';

                // Clear previous error states
                $('.is-invalid').removeClass('is-invalid');

                // Show field-specific errors
                Object.keys(errors).forEach(function(field) {
                    var input = $('#new_driver_' + field);
                    if (input.length) {
                        input.addClass('is-invalid');
                        input.siblings('.invalid-feedback').text(errors[field][0]);
                    }
                });

                // Show general error message
                if (xhr.responseJSON?.message) {
                    errorMessage = xhr.responseJSON.message;
                }

                $('body').prepend(`
                    <div class="alert alert-danger alert-dismissible fade show position-fixed" style="top: 20px; right: 20px; z-index: 9999; min-width: 300px;">
                        <i class="bi bi-exclamation-triangle"></i>
                        <strong>Error!</strong> ${errorMessage}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                `);

                setTimeout(function() {
                    $('.alert-danger').fadeOut();
                }, 5000);
            }
        });
    });

    // Handle assign driver button click
    $('#assignDriverBtn').on('click', function(e) {
        e.preventDefault();

        var truckId = $('#assign_truck_id').val();
        var driverId = $('#assign_driver_id').val();

        $.ajax({
            url: '/trucks/' + truckId + '/assign-driver',
            type: 'PUT',
            data: {
                _token: $('meta[name="csrf-token"]').attr('content'),
                driver_id: driverId
            },
            success: function(response) {
                if (response.success) {
                    // Show success message
                    $('body').prepend(`
                        <div class="alert alert-success alert-dismissible fade show position-fixed" style="top: 20px; right: 20px; z-index: 9999; min-width: 300px;">
                            <i class="bi bi-check-circle"></i>
                            <strong>Success!</strong> ${response.message}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    `);

                    // Hide modal
                    $('#assignDriverModal').modal('hide');

                    // Refresh DataTable
                    $('#trucksTable').DataTable().ajax.reload();

                    // Auto-hide success message
                    setTimeout(function() {
                        $('.alert-success').fadeOut();
                    }, 5000);
                }
            },
            error: function(xhr) {
                var errorMessage = 'Unable to assign driver. Please try again.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMessage = xhr.responseJSON.message;
                }

                // Show error message
                $('body').prepend(`
                    <div class="alert alert-danger alert-dismissible fade show position-fixed" style="top: 20px; right: 20px; z-index: 9999; min-width: 300px;">
                        <i class="bi bi-exclamation-triangle"></i>
                        <strong>Error!</strong> ${errorMessage}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                `);

                setTimeout(function() {
                    $('.alert-danger').fadeOut();
                }, 5000);
            }
        });
    });

    // Initialize Select2
    $('.select2').select2({
        placeholder: 'Search for a truck owner...',
        allowClear: true,
        width: '100%',
        dropdownParent: $('#assignOwnerModal')
    });

    // Handle assign owner button click
    $(document).on('click', '.assign-owner-btn', function() {
        var truckId = $(this).data('truck-id');
        var truckNumber = $(this).data('truck-number');

        $('#assignTruckId').val(truckId);
        $('#assignOwnerModalLabel').text('Assign Owner for Truck: ' + truckNumber);
        $('#assignOwnerModal').modal('show');
    });

    // Handle form submission
    $('#assignOwnerForm').on('submit', function(e) {
        e.preventDefault();

        $.ajax({
            url: $(this).attr('action'),
            type: 'POST',
            data: $(this).serialize(),
            success: function(response) {
                if (response.success) {
                    $('#assignOwnerModal').modal('hide');
                    // Show success message
                    alert('Truck owner assigned successfully!');
                    // Reload the page to reflect changes
                    location.reload();
                } else {
                    alert('Error: ' + (response.message || 'Failed to assign owner'));
                }
            },
            error: function(xhr) {
                let errorMessage = 'An error occurred while assigning the owner.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMessage = xhr.responseJSON.message;
                }
                alert(errorMessage);
            }
        });
    });
});
</script>
@endpush

@push('scripts')
<!-- Select2 JS -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
@endpush

@endsection
