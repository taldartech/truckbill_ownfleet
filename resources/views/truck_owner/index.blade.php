@extends('layouts.app')

@push('styles')
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/searchpanes/2.2.0/css/searchPanes.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/select/1.7.0/css/select.bootstrap5.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
    <style>
        .add-truck-modal-driver-row .flex-grow-1 {
            min-width: 0;
        }

        .add-truck-modal-driver-row .select2-container {
            width: 100% !important;
        }

        .add-truck-modal-driver-row .select2-container .select2-selection--single {
            min-height: 38px;
            display: flex;
            align-items: center;
        }
    </style>
@endpush

@section('content')
    <div class="container mt-4">
        <div class="col-sm-6">
            <h3 class="mb-0">Truck Owner Management</h3>
        </div>
        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <div class="d-flex justify-content-end gap-2 mb-3">
            <a href="{{ route('truck_owners.create') }}" class="btn btn-brand-primary">
                <i class="bi bi-plus"></i> Create
            </a>
            <a href="{{ route('reports.transporter-master-export', ['type' => 'owner']) }}" class="btn btn-warning">
                <i class="bi bi-file-earmark-spreadsheet"></i> Export Truck Owners
            </a>
        </div>

        <div class="card mb-4">
            <div class="card-header">
                <h3 class="card-title">Truck Owners List</h3>
            </div>
            <div class="card-body">
                <table id="truckOwnersTable" class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Short Name</th>
                            <th>PAN Number</th>
                            <th>Location</th>
                            <th>Contact Number</th>
                            <th>Declaration Given</th>
                            <th>Book Balance</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>

    <!-- Truck Owner Details Modal -->
    <div class="modal fade" id="truckOwnerDetailsModal" tabindex="-1" aria-labelledby="truckOwnerDetailsModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-gradient-primary">
                    <h5 class="modal-title" id="truckOwnerDetailsModalLabel">
                        <i class="bi bi-truck"></i> Truck Owner Details
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Close"></button>
                </div>
                <div class="modal-body" id="truckOwnerDetailsContent">
                    <div class="text-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2">Loading truck owner details...</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    @if (Auth::check() && Auth::user()->canActAsAdmin())
                        <button type="button" class="btn btn-primary" id="editTruckOwnerBtn" style="display: none;">
                            <i class="bi bi-pencil"></i> Edit Truck Owner
                        </button>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Advance Payment Modal -->
    <div class="modal fade" id="advancePaymentModal" tabindex="-1" aria-labelledby="advancePaymentModalLabel"
        aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-gradient-primary">
                    <h5 class="modal-title" id="advancePaymentModalLabel">
                        <i class="bi bi-currency-rupee"></i> Add Advance Payment
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Close"></button>
                </div>
                <form id="advancePaymentForm" method="POST" action="{{ route('advance-payments.store') }}">
                    @csrf
                    <div class="modal-body">
                        <input type="hidden" id="transporter_id" name="transporter_id">

                        <div class="mb-3">
                            <label for="transporter_name_display" class="form-label">Truck Owner Name</label>
                            <input type="text" class="form-control" id="transporter_name_display" readonly>
                        </div>

                        <div class="mb-3">
                            <label for="advance_amount" class="form-label">Book Balance *</label>
                            <div class="input-group">
                                <span class="input-group-text"></span>
                                <input type="number" step="0.01" class="form-control" id="advance_amount"
                                    name="advance_amount" required placeholder="Use + to add, - to deduct">
                            </div>
                            <small class="text-muted">Enter positive amount to add balance, negative amount to deduct balance.</small>
                            <div class="invalid-feedback"></div>
                        </div>

                        <div class="mb-3">
                            <label for="payment_date" class="form-label">Payment Date *</label>
                            <input type="date" class="form-control" id="payment_date" name="payment_date" required>
                            <div class="invalid-feedback"></div>
                        </div>

                        <div class="mb-3">
                            <label for="remark" class="form-label">Remark</label>
                            <textarea class="form-control" id="remark" name="remark" rows="3"
                                placeholder="Enter any remarks or notes (optional)"></textarea>
                            <div class="invalid-feedback"></div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-brand-primary">
                            <i class="bi bi-check-lg"></i> Add Payment
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Truck Modal -->
    <div class="modal fade" id="addTruckModal" tabindex="-1" aria-labelledby="addTruckModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-gradient-primary">
                    <h5 class="modal-title" id="addTruckModalLabel">
                        <i class="bi bi-truck"></i> Add New Truck
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Close"></button>
                </div>
                <form id="addTruckForm" method="POST" action="{{ route('trucks.store') }}">
                    @csrf
                    <div class="modal-body">
                        <input type="hidden" id="modal_transporter_id" name="transporter_id">

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="modal_truck_owner" class="form-label">Truck Owner *</label>
                                <input type="text" class="form-control" id="modal_truck_owner" disabled>
                            </div>
                            <div class="col-md-6">
                                <label for="modal_truck_number" class="form-label">Truck Number *</label>
                                <input type="text" class="form-control" placeholder="e.g., MH12AB1234 or GJ01CD5678"
                                    id="modal_truck_number" name="truck_number" required>
                                <div class="invalid-feedback">Please enter a valid truck number (6-15 characters).</div>
                                <small class="form-text text-muted">Format: State code + digits + letters + digits (e.g.,
                                    MH12AB1234)</small>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="modal_truck_type" class="form-label">Truck Type *</label>
                                <select class="form-select" id="modal_truck_type" name="truck_type" required>
                                    <option value="">Select</option>
                                    @foreach($truckTypes ?? [] as $type)
                                        <option value="{{ $type->name }}">{{ $type->name }}</option>
                                    @endforeach
                                </select>
                                <div class="invalid-feedback">Please select a truck type.</div>
                            </div>
                            <div class="col-md-6">
                                <label for="modal_driver_id" class="form-label">Driver</label>
                                <div class="d-flex gap-2 align-items-stretch add-truck-modal-driver-row">
                                    <div class="flex-grow-1">
                                        <select class="form-select" id="modal_driver_id" name="driver_id">
                                            <option value="">No Driver</option>
                                        </select>
                                    </div>
                                    <button type="button" class="btn btn-primary flex-shrink-0" id="showAddDriverFormModal">
                                        <i class="bi bi-plus-circle"></i> Add New
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="modal_registration_date" class="form-label">Registration Date</label>
                                <input type="date" class="form-control" id="modal_registration_date"
                                    name="registration_date">
                                <small class="form-text text-muted">Optional field</small>
                            </div>
                        </div>

                        <!-- Add New Driver Form (Initially Hidden) -->
                        <div id="addDriverFormModal" style="display: none;">
                            <hr class="my-4">
                            <div class="card border-primary">
                                <div class="card-header bg-gradient-primary">
                                    <h6 class="mb-0"><i class="bi bi-person-plus"></i> Add New Driver</h6>
                                </div>
                                <div class="card-body">
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label for="new_driver_name_modal" class="form-label">Name *</label>
                                            <input type="text" class="form-control" id="new_driver_name_modal"
                                                name="new_driver_name">
                                            <div class="invalid-feedback">Please enter a name.</div>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="new_driver_mobile_modal" class="form-label">Mobile *</label>
                                            <input type="text" class="form-control" id="new_driver_mobile_modal"
                                                name="new_driver_mobile" pattern="[0-9]{10}" maxlength="10">
                                            <div class="invalid-feedback">Please enter a valid 10-digit mobile number.
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label for="new_driver_license_modal" class="form-label">License
                                                Number</label>
                                            <input type="text" class="form-control" id="new_driver_license_modal"
                                                name="new_driver_license">
                                        </div>
                                        <div class="col-md-6">
                                            <label for="new_driver_address_modal" class="form-label">Address</label>
                                            <input type="text" class="form-control" id="new_driver_address_modal"
                                                name="new_driver_address">
                                        </div>
                                    </div>
                                    <div class="d-flex gap-2">
                                        <button type="button" class="btn btn-primary" id="addDriverBtnModal">
                                            <i class="bi bi-check-circle"></i> Add Driver
                                        </button>
                                        <button type="button" class="btn btn-secondary" id="cancelAddDriverModal">
                                            <i class="bi bi-x-circle"></i> Cancel
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div class="alert alert-info mt-3">
                                <i class="bi bi-info-circle"></i>
                                <strong>Note:</strong> The new driver will be automatically selected after creation.
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-brand-primary">
                            <i class="bi bi-truck"></i> Add Truck
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
            function destroyAddTruckModalDriverSelect2() {
                const $el = $('#modal_driver_id');
                if ($el.length && $el.hasClass('select2-hidden-accessible')) {
                    $el.select2('destroy');
                }
            }

            function addTruckModalDriverAjaxConfig(url) {
                return {
                    url: url,
                    dataType: 'json',
                    delay: 700,
                    data: function (params) {
                        return {
                            q: params.term || '',
                            limit: 20
                        };
                    },
                    processResults: function (data) {
                        return {
                            results: data.results || [],
                            pagination: { more: false }
                        };
                    },
                    transport: function (params, success, failure) {
                        const $element = $(params.element);
                        const previousRequest = $element.data('select2ActiveRequest');
                        if (previousRequest && typeof previousRequest.abort === 'function') {
                            previousRequest.abort();
                        }
                        const request = $.ajax(params);
                        $element.data('select2ActiveRequest', request);
                        request.then(success);
                        request.fail(failure);
                        request.always(function () {
                            $element.removeData('select2ActiveRequest');
                        });
                        return request;
                    },
                    cache: true
                };
            }

            function initAddTruckModalDriverSelect2(selectedDriver) {
                if (!window.jQuery || typeof window.jQuery.fn.select2 === 'undefined') {
                    return;
                }
                const $el = $('#modal_driver_id');
                destroyAddTruckModalDriverSelect2();
                $el.empty();
                $el.append('<option value="">No Driver</option>');
                if (selectedDriver && selectedDriver.id) {
                    const text = selectedDriver.text || String(selectedDriver.id);
                    $el.append(new Option(text, String(selectedDriver.id), true, true));
                }
                $el.select2({
                    theme: 'bootstrap-5',
                    dropdownParent: $('#addTruckModal'),
                    width: '100%',
                    allowClear: true,
                    minimumInputLength: 1,
                    placeholder: 'Search driver (type first letter)...',
                    language: {
                        inputTooShort: function () {
                            return 'Please type at least 1 character to search';
                        },
                        searching: function () {
                            return 'Searching...';
                        }
                    },
                    ajax: addTruckModalDriverAjaxConfig('/api/drivers/search')
                });
            }

            $('#truckOwnersTable').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: '{{ route('truck_owners.index') }}',
                    type: 'GET',
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                },
                columns: [{
                        data: 'name',
                        name: 'name',
                        render: function(data, type, row) {
                            return data; // Allow HTML rendering for clickable links
                        }
                    },
                    {
                        data: 'short_name',
                        name: 'short_name',
                        render: function(data, type, row) {
                            return data; // Allow HTML rendering for clickable links
                        }
                    },
                    {
                        data: 'pan_number',
                        name: 'pan_number',
                    },

                    {
                        data: 'location',
                        name: 'location',
                    },
                    {
                        data: 'contact_number',
                        name: 'contact_number',
                    },
                    {
                        data: 'declaration_given',
                        name: 'declaration_given',
                        className: 'text-center'
                    },

                    {
                        data: 'advance_amount',
                        name: 'advance_amount',
                        className: 'text-end'
                    },
                    {
                        data: 'actions',
                        name: 'actions',
                        orderable: false,
                        searchable: false,
                        className: 'text-center'
                    }
                ],
                order: [
                    [0, 'asc']
                ],
                pageLength: 10,
                lengthMenu: [
                    [10, 25, 50, 100],
                    [10, 25, 50, 100]
                ],
                dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>' +
                    '<"row"<"col-sm-12 col-md-12"B>>' +
                    '<"row"<"col-sm-12"tr>>' +
                    '<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
                buttons: [{
                    extend: 'excel',
                    className: 'btn btn-secondary btn-sm',
                    text: '<i class="bi bi-file-earmark-excel"></i> Excel'
                }],
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
                    this.api().columns([0, 1, 2, 3, 4]).every(function() {
                        var column = this;
                        var $header = $(column.header());
                        var title = $header.text().trim();

                        // Wrap existing text in a div to keep it at top
                        $header.html('<div style="margin-bottom: 5px;">' + title + '</div>');

                        // Create input element
                        var input = $(
                                '<input type="text" class="form-control form-control-sm" placeholder="Search ' +
                                title + '" />')
                            .appendTo($header)
                            .on('keyup change clear', function() {
                                if (column.search() !== this.value) {
                                    column.search(this.value).draw();
                                }
                            });
                    });
                }
            });

            // Handle delete confirmation with AJAX
            $(document).on('submit', 'form[action*="destroy"]', function(e) {
                e.preventDefault();

                if (!confirm(
                        'Are you sure you want to delete this truck owner? This action can be undone.')) {
                    return false;
                }

                var form = $(this);
                var url = form.attr('action');
                var token = form.find('input[name="_token"]').val();

                $.ajax({
                    url: url,
                    type: 'POST',
                    data: {
                        '_token': token,
                        '_method': 'DELETE'
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

                            // Auto-hide alert after 5 seconds
                            setTimeout(function() {
                                $('.alert-success').fadeOut();
                            }, 5000);

                            // Reload DataTable to show updated data
                            $('#truckOwnersTable').DataTable().ajax.reload(null, false);
                        }
                    },
                    error: function(xhr) {
                        // Show error message
                        $('body').prepend(`
                    <div class="alert alert-danger alert-dismissible fade show position-fixed" style="top: 20px; right: 20px; z-index: 9999; min-width: 300px;">
                        <i class="bi bi-exclamation-triangle"></i>
                        <strong>Error!</strong> Unable to delete truck owner. Please try again.
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                `);

                        setTimeout(function() {
                            $('.alert-danger').fadeOut();
                        }, 5000);
                    }
                });
            });

            // Handle truck owner name click to show details
            $(document).on('click', '.truck-owner-name-link', function(e) {
                e.preventDefault();
                var truckOwnerId = $(this).data('id');

                // Show modal with loading state
                $('#truckOwnerDetailsModal').modal('show');
                $('#truckOwnerDetailsContent').html(`
            <div class="text-center">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-2">Loading truck owner details...</p>
            </div>
        `);
                $('#editTruckOwnerBtn').hide();

                // Fetch truck owner details
                $.ajax({
                    url: '/truck_owners/' + truckOwnerId,
                    type: 'GET',
                    success: function(data) {
                        var detailsHtml = `
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card h-100">
                                <div class="card-header bg-light">
                                    <h6 class="mb-0"><i class="bi bi-person-circle"></i> Basic Information</h6>
                                </div>
                                <div class="card-body">
                                    <table class="table table-borderless table-sm">
                                        <tr>
                                            <td class="fw-bold">Name:</td>
                                            <td>${data.name}</td>
                                        </tr>
                                        <tr>
                                            <td class="fw-bold">Short Name:</td>
                                            <td>${data.short_name || 'Not provided'}</td>
                                        </tr>
                                        <tr>
                                            <td class="fw-bold">Contact Person:</td>
                                            <td>${data.contact_person_name || 'Not provided'}</td>
                                        </tr>
                                        <tr>
                                            <td class="fw-bold">Mobile Number:</td>
                                            <td>${data.mobile_number}</td>
                                        </tr>
                                        <tr>
                                            <td class="fw-bold">Type:</td>
                                            <td><span class="badge bg-brand-primary">${data.is_owner}</span></td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card h-100">
                                <div class="card-header bg-light">
                                    <h6 class="mb-0"><i class="bi bi-file-text"></i> Business Information</h6>
                                </div>
                                <div class="card-body">
                                    <table class="table table-borderless table-sm">
                                        <tr>
                                            <td class="fw-bold">GST Number:</td>
                                            <td>${data.gst_number}</td>
                                        </tr>
                                        <tr>
                                            <td class="fw-bold">PAN Number:</td>
                                            <td>${data.pan_number}</td>
                                        </tr>
                                        <tr>
                                            <td class="fw-bold">PAN Active:</td>
                                            <td><span class="badge ${data.pan_active === 'Yes' ? 'bg-brand-primary' : 'bg-warning'}">${data.pan_active}</span></td>
                                        </tr>
                                        <tr>
                                            <td class="fw-bold">Declaration Given:</td>
                                            <td><span class="badge ${data.declaration_given === 'Yes' ? 'bg-brand-primary' : 'bg-warning'}">${data.declaration_given}</span></td>
                                        </tr>
                                        <tr>
                                            <td class="fw-bold">TDS Percentage:</td>
                                            <td><span class="badge bg-warning text-dark">${data.tds_percentage}%</span></td>
                                        </tr>
                                        <tr>
                                            <td class="fw-bold">Total Trucks:</td>
                                            <td class="fw-bold text-info">${data.total_trucks}</td>
                                        </tr>
                                        <tr>
                                            <td class="fw-bold">Book Balance:</td>
                                            <td class="fw-bold text-success">${data.advance_amount}</td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row mt-3">
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header bg-light">
                                    <h6 class="mb-0"><i class="bi bi-geo-alt"></i> Location Information</h6>
                                </div>
                                <div class="card-body">
                                    <table class="table table-borderless table-sm">
                                        <tr>
                                            <td class="fw-bold">Address:</td>
                                            <td>${data.address}</td>
                                        </tr>
                                        <tr>
                                            <td class="fw-bold">City:</td>
                                            <td>${data.city}</td>
                                        </tr>
                                        <tr>
                                            <td class="fw-bold">State:</td>
                                            <td>${data.state}</td>
                                        </tr>
                                        <tr>
                                            <td class="fw-bold">Country:</td>
                                            <td>${data.country}</td>
                                        </tr>
                                        <tr>
                                            <td class="fw-bold">Company:</td>
                                            <td>${data.company}</td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header bg-light">
                                    <h6 class="mb-0"><i class="bi bi-truck"></i> Registered Trucks</h6>
                                </div>
                                <div class="card-body" style="max-height: 300px; overflow-y: auto;">
                                    ${data.trucks && data.trucks.length > 0 ?
                                        `<div class="table-responsive">
                                                                <table class="table table-sm table-striped">
                                                                    <thead>
                                                                        <tr>
                                                                            <th>Truck No.</th>
                                                                            <th>Type</th>
                                                                            <th>Driver</th>
                                                                            <th>Mobile</th>

                                                                        </tr>
                                                                    </thead>
                                                                    <tbody>
                                                                        ${data.trucks.map(truck => `
                                                        <tr>
                                                            <td class="fw-bold">${truck.truck_number}</td>
                                                            <td>${truck.truck_type}</td>
                                                            <td>${truck.driver_name}</td>
                                                            <td>${truck.driver_mobile}</td>

                                                        </tr>
                                                    `).join('')}
                                                                    </tbody>
                                                                </table>
                                                            </div>`
                                        : '<p class="text-muted text-center">No trucks registered</p>'
                                    }
                                </div>
                            </div>
                        </div>
                    </div>
                    ${data.advance_payments && data.advance_payments.length > 0 ? `
                                            <div class="row mt-3">
                                                <div class="col-12">
                                                    <div class="card">
                                                        <div class="card-header bg-light">
                                                            <h6 class="mb-0"><i class="bi bi-currency-rupee"></i> Recent Advance Payments</h6>
                                                        </div>
                                                        <div class="card-body" style="max-height: 250px; overflow-y: auto;">
                                                            <div class="table-responsive">
                                                                <table class="table table-sm table-striped">
                                                                    <thead>
                                                                        <tr>
                                                                            <th>Amount</th>
                                                                            <th>Payment Date</th>
                                                                            <th>Remark</th>
                                                                            <th>Added On</th>
                                                                        </tr>
                                                                    </thead>
                                                                    <tbody>
                                                                        ${data.advance_payments.map(payment => {
                                                        const parsedAmount = parseFloat(String(payment.amount).replace(/[^0-9.-]/g, ''));
                                                        const amountClass = !isNaN(parsedAmount) && parsedAmount < 0 ? 'text-danger' : 'text-success';
                                                        return `
                                                        <tr>
                                                            <td class="fw-bold ${amountClass}">${payment.amount}</td>
                                                            <td>${payment.payment_date}</td>
                                                            <td>${payment.remark}</td>
                                                            <td class="text-muted">${payment.created_at}</td>
                                                        </tr>
                                                    `;
                                                    }).join('')}
                                                                    </tbody>
                                                                </table>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        ` : ''}
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

                        $('#truckOwnerDetailsContent').html(detailsHtml);

                        // Show edit button and set up click handler
                        $('#editTruckOwnerBtn').show().off('click').on('click', function() {
                            window.location.href = '/truck_owners/' + data.id + '/edit';
                        });
                    },
                    error: function(xhr) {
                        $('#truckOwnerDetailsContent').html(`
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle"></i>
                        <strong>Error:</strong> Unable to load truck owner details. Please try again.
                    </div>
                `);
                    }
                });
            });

            // Handle advance payment button click
            $(document).on('click', '.advance-payment-btn', function(e) {
                e.preventDefault();
                var transporterId = $(this).data('transporter-id');
                var transporterName = $(this).data('transporter-name');

                // Reset form and populate data
                $('#advancePaymentForm')[0].reset();
                $('#transporter_id').val(transporterId);
                $('#transporter_name_display').val(transporterName);

                // Set today's date as default
                var today = @json(now()->timezone(config('app.timezone'))->format('Y-m-d'));
                $('#payment_date').val(today);

                // Clear any previous validation states
                $('#advancePaymentForm .form-control').removeClass('is-invalid is-valid');
                $('#advancePaymentForm .invalid-feedback').text('');

                // Show modal
                $('#advancePaymentModal').modal('show');
            });

            // Handle advance payment form submission
            $('#advancePaymentForm').on('submit', function(e) {
                e.preventDefault();
                //  confirm before submitting
                let advanceAmount = $('#advance_amount').val();
                if (!confirm('Are you sure you want to submit a book balance entry of ' + advanceAmount +
                        '?')) {
                    return false;
                }

                var form = $(this);
                var submitBtn = form.find('button[type="submit"]');
                var originalBtnText = submitBtn.html();

                // Show loading state
                submitBtn.prop('disabled', true).html(
                    '<i class="bi bi-hourglass-split"></i> Processing...');

                // Clear previous validation states
                form.find('.form-control').removeClass('is-invalid is-valid');
                form.find('.invalid-feedback').text('');

                $.ajax({
                    url: form.attr('action'),
                    type: 'POST',
                    data: form.serialize(),
                    success: function(response) {
                        // Show success message
                        $('#advancePaymentModal').modal('hide');

                        // Show success alert
                        $('body').prepend(`
                    <div class="alert alert-success alert-dismissible fade show position-fixed" style="top: 20px; right: 20px; z-index: 9999; min-width: 300px;">
                        <i class="bi bi-check-circle"></i>
                        <strong>Success!</strong> Book balance entry of ${$('#advance_amount').val()} has been saved successfully.
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                `);

                        // Auto-hide alert after 5 seconds
                        setTimeout(function() {
                            $('.alert-success').fadeOut();
                        }, 5000);

                        // Reload DataTable to show updated data
                        $('#truckOwnersTable').DataTable().ajax.reload(null, false);
                    },
                    error: function(xhr) {
                        if (xhr.status === 422) {
                            // Validation errors
                            var errors = xhr.responseJSON.errors;
                            $.each(errors, function(field, messages) {
                                var input = $('#' + field);
                                input.addClass('is-invalid');
                                input.siblings('.invalid-feedback').text(messages[0]);
                            });
                        } else {
                            // General error
                            $('body').prepend(`
                        <div class="alert alert-danger alert-dismissible fade show position-fixed" style="top: 20px; right: 20px; z-index: 9999; min-width: 300px;">
                            <i class="bi bi-exclamation-triangle"></i>
                            <strong>Error!</strong> Unable to process advance payment. Please try again.
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    `);

                            setTimeout(function() {
                                $('.alert-danger').fadeOut();
                            }, 5000);
                        }
                    },
                    complete: function() {
                        // Reset button state
                        submitBtn.prop('disabled', false).html(originalBtnText);
                    }
                });
            });

            // Reset form when modal is hidden
            $('#advancePaymentModal').on('hidden.bs.modal', function() {
                $('#advancePaymentForm')[0].reset();
                $('#advancePaymentForm .form-control').removeClass('is-invalid is-valid');
                $('#advancePaymentForm .invalid-feedback').text('');
            });

            // Handle Add Truck button click
            $(document).on('click', '.add-truck-btn', function() {
                const transporterId = $(this).data('transporter-id');
                const transporterName = $(this).data('transporter-name');

                // Set truck owner details in modal
                $('#modal_transporter_id').val(transporterId);
                $('#modal_truck_owner').val(transporterName);

                initAddTruckModalDriverSelect2(null);

                // Show the modal
                $('#addTruckModal').modal('show');
            });

            // Handle Add Truck form submission
            $('#addTruckForm').on('submit', function(e) {
                e.preventDefault();

                // Clear previous validation states
                $(this).find('.form-control, .form-select').removeClass('is-invalid is-valid');
                $(this).find('.invalid-feedback').hide();

                $.ajax({
                    url: $(this).attr('action'),
                    type: 'POST',
                    data: $(this).serialize(),
                    success: function(response) {
                        // Show success message
                        $('body').prepend(`
                    <div class="alert alert-success alert-dismissible fade show position-fixed" style="top: 20px; right: 20px; z-index: 9999; min-width: 300px;">
                        <i class="bi bi-check-circle"></i>
                        <strong>Success!</strong> Truck has been added successfully.
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                `);

                        // Auto-hide alert after 5 seconds
                        setTimeout(function() {
                            $('.alert-success').fadeOut();
                        }, 5000);

                        // Reset form and close modal
                        $('#addTruckForm')[0].reset();
                        $('#addTruckModal').modal('hide');

                        // Reload DataTable
                        $('#truckOwnersTable').DataTable().ajax.reload(null, false);
                    },
                    error: function(xhr) {
                        if (xhr.status === 422) {
                            // Handle validation errors
                            const errors = xhr.responseJSON.errors;
                            Object.keys(errors).forEach(function(field) {
                                const input = $(`#modal_${field}`);
                                input.addClass('is-invalid');
                                input.siblings('.invalid-feedback').text(errors[field][
                                    0
                                ]).show();
                            });
                        } else {
                            // Show general error message
                            $('body').prepend(`
                        <div class="alert alert-danger alert-dismissible fade show position-fixed" style="top: 20px; right: 20px; z-index: 9999; min-width: 300px;">
                            <i class="bi bi-exclamation-triangle"></i>
                            <strong>Error!</strong> Unable to add truck. Please try again.
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    `);

                            setTimeout(function() {
                                $('.alert-danger').fadeOut();
                            }, 5000);
                        }
                    }
                });
            });

            // Reset Add Truck form when modal is hidden
            $('#addTruckModal').on('hidden.bs.modal', function() {
                destroyAddTruckModalDriverSelect2();
                $('#modal_driver_id').empty().append('<option value="">No Driver</option>');
                $('#addTruckForm')[0].reset();
                $('#addTruckForm .form-control, #addTruckForm .form-select').removeClass(
                    'is-invalid is-valid');
                $('#addTruckForm .invalid-feedback').hide();
                $('#addDriverFormModal').hide();
                $('#showAddDriverFormModal').show();
            });

            // Show/Hide Add Driver Form in Add Truck Modal
            $('#showAddDriverFormModal').on('click', function() {
                $('#addDriverFormModal').slideDown();
                $(this).hide();
            });

            $('#cancelAddDriverModal').on('click', function() {
                $('#addDriverFormModal').slideUp();
                $('#showAddDriverFormModal').show();
                // Reset driver form fields
                $('#new_driver_name_modal, #new_driver_mobile_modal, #new_driver_license_modal, #new_driver_address_modal')
                    .val('');
                $('#addDriverFormModal .form-control').removeClass('is-invalid is-valid');
            });

            // Handle new driver addition in add truck modal
            $('#addDriverBtnModal').on('click', function(e) {
                e.preventDefault();

                // Get form data
                const driverData = {
                    name: $('#new_driver_name_modal').val().trim(),
                    mobile: $('#new_driver_mobile_modal').val().trim(),
                    license_number: $('#new_driver_license_modal').val().trim(),
                    address: $('#new_driver_address_modal').val().trim(),
                    _token: $('meta[name="csrf-token"]').attr('content')
                };

                // Basic validation
                let isValid = true;
                if (!driverData.name) {
                    $('#new_driver_name_modal').addClass('is-invalid');
                    isValid = false;
                } else {
                    $('#new_driver_name_modal').removeClass('is-invalid').addClass('is-valid');
                }

                if (!driverData.mobile || !/^[0-9]{10}$/.test(driverData.mobile)) {
                    $('#new_driver_mobile_modal').addClass('is-invalid');
                    isValid = false;
                } else {
                    $('#new_driver_mobile_modal').removeClass('is-invalid').addClass('is-valid');
                }

                if (!isValid) {
                    return;
                }

                // Show loading state
                const originalText = $(this).html();
                $(this).html('<i class="bi bi-hourglass-split"></i> Adding...').prop('disabled', true);

                $.ajax({
                    url: '{{ route('drivers.store') }}',
                    type: 'POST',
                    data: driverData,
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

                            // Reset and hide form
                            $('#new_driver_name_modal, #new_driver_mobile_modal, #new_driver_license_modal, #new_driver_address_modal')
                                .val('');
                            $('#addDriverFormModal .form-control').removeClass(
                                'is-invalid is-valid');
                            $('#addDriverFormModal').slideUp();
                            $('#showAddDriverFormModal').show();

                            const d = response.driver;
                            const label = (d.name || '') + (d.mobile ? ' (' + d.mobile + ')' : '');
                            const opt = new Option(label, String(d.id), true, true);
                            $('#modal_driver_id').append(opt).trigger('change');

                            // Auto-hide success message
                            setTimeout(function() {
                                $('.alert-success').fadeOut();
                            }, 5000);
                        }
                    },
                    error: function(xhr) {
                        let errorMessage = 'Unable to add driver. ';
                        if (xhr.responseJSON && xhr.responseJSON.errors) {
                            const errors = xhr.responseJSON.errors;
                            errorMessage += Object.values(errors).flat().join(' ');
                        } else if (xhr.responseJSON && xhr.responseJSON.message) {
                            errorMessage += xhr.responseJSON.message;
                        } else {
                            errorMessage += 'Please try again.';
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
                    },
                    complete: function() {
                        // Restore button state
                        $('#addDriverBtnModal').html(originalText).prop('disabled', false);
                    }
                });
            });

            // Handle Delete Truck Owner button click
            $(document).on('click', '.delete-truck-owner-btn', function() {
                const truckOwnerId = $(this).data('truck-owner-id');
                const truckOwnerName = $(this).data('truck-owner-name');

                if (confirm(
                        `Are you sure you want to delete truck owner "${truckOwnerName}"? This action cannot be undone.`
                    )) {
                    $.ajax({
                        url: '/truck_owners/' + truckOwnerId,
                        type: 'POST',
                        data: {
                            '_token': $('meta[name="csrf-token"]').attr('content'),
                            '_method': 'DELETE'
                        },
                        success: function(response) {
                            // Show success message
                            $('body').prepend(`
                        <div class="alert alert-success alert-dismissible fade show position-fixed" style="top: 20px; right: 20px; z-index: 9999; min-width: 300px;">
                            <i class="bi bi-check-circle"></i>
                            <strong>Success!</strong> Truck owner "${truckOwnerName}" has been deleted successfully.
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    `);

                            // Auto-hide alert after 5 seconds
                            setTimeout(function() {
                                $('.alert-success').fadeOut();
                            }, 5000);

                            // Reload DataTable
                            $('#truckOwnersTable').DataTable().ajax.reload(null, false);
                        },
                        error: function(xhr) {
                            // Show error message
                            $('body').prepend(`
                        <div class="alert alert-danger alert-dismissible fade show position-fixed" style="top: 20px; right: 20px; z-index: 9999; min-width: 300px;">
                            <i class="bi bi-exclamation-triangle"></i>
                            <strong>Error!</strong> Unable to delete truck owner "${truckOwnerName}". Please try again.
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
        });

        // Function to view truck details
        function viewTruckDetails(truckId) {
            // You can implement a truck details modal here or redirect to truck details page
            window.open('/trucks/' + truckId, '_blank');
        }

        // Function to delete truck
        function deleteTruck(truckId, truckNumber) {
            if (confirm('Are you sure you want to delete truck "' + truckNumber + '"? This action cannot be undone.')) {
                $.ajax({
                    url: '/trucks/' + truckId,
                    type: 'POST',
                    data: {
                        '_token': $('meta[name="csrf-token"]').attr('content'),
                        '_method': 'DELETE'
                    },
                    success: function(response) {
                        // Show success message
                        $('body').prepend(`
                    <div class="alert alert-success alert-dismissible fade show position-fixed" style="top: 20px; right: 20px; z-index: 9999; min-width: 300px;">
                        <i class="bi bi-check-circle"></i>
                        <strong>Success!</strong> Truck "${truckNumber}" has been deleted successfully.
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                `);

                        // Auto-hide alert after 5 seconds
                        setTimeout(function() {
                            $('.alert-success').fadeOut();
                        }, 5000);

                        // Close the truck owner details modal and reload the main table
                        $('#truckOwnerDetailsModal').modal('hide');
                        $('#truckOwnersTable').DataTable().ajax.reload(null, false);
                    },
                    error: function(xhr) {
                        // Show error message
                        $('body').prepend(`
                    <div class="alert alert-danger alert-dismissible fade show position-fixed" style="top: 20px; right: 20px; z-index: 9999; min-width: 300px;">
                        <i class="bi bi-exclamation-triangle"></i>
                        <strong>Error!</strong> Unable to delete truck "${truckNumber}". Please try again.
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                `);

                        setTimeout(function() {
                            $('.alert-danger').fadeOut();
                        }, 5000);
                    }
                });
            }
        }
    </script>
@endpush
