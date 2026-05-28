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
        <div class="col-sm-6">
            <h3 class="mb-0">Transporter Management</h3>
        </div>
        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <div class="d-flex justify-content-end gap-2 mb-3">
            <a href="{{ route('transporters.create') }}" class="btn btn-brand-primary">
                <i class="bi bi-plus"></i> Create
            </a>
            <a href="{{ route('reports.transporter-master-export', ['type' => 'transporter']) }}" class="btn btn-warning">
                <i class="bi bi-file-earmark-spreadsheet"></i> Export Transporters
            </a>
        </div>

        <div class="card mb-4">
            <div class="card-header">
                <h3 class="card-title">Transporters List</h3>
            </div>
            <div class="card-body">
                <table id="transportersTable" class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Short Name</th>
                            <th>Beneficiary ID</th>
                            <th>PAN Number</th>
                            <th>Contact Person</th>
                            <th>Contact Number</th>
                            <th>Book Balance</th>
                            {{-- <th>Company</th> --}}
                            @if (auth()->check() && auth()->user()->canActAsAdmin())
                                <th style="width: 120px;">Actions</th>
                            @endif
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>

    <!-- Transporter Details Modal -->
    <div class="modal fade" id="transporterDetailsModal" tabindex="-1" aria-labelledby="transporterDetailsModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-brand-primary text-white">
                    <h5 class="modal-title" id="transporterDetailsModalLabel">
                        <i class="bi bi-truck"></i> Transporter Details
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Close"></button>
                </div>
                <div class="modal-body" id="transporterDetailsContent">
                    <div class="text-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2">Loading transporter details...</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    @if (auth()->check() && Auth::user()->canActAsAdmin())
                        <button type="button" class="btn btn-edit" id="editTransporterBtn" style="display: none;">
                            <i class="bi bi-pencil"></i> Edit Transporter
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
                <div class="modal-header bg-brand-primary text-white">
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
                            <label for="transporter_name_display" class="form-label">Transporter Name</label>
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
            $('#transportersTable').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: '{{ route('transporters.index') }}',
                    type: 'GET'
                },
                columns: [{
                        data: 'name',
                        name: 'name'
                    },

                    {
                        data: 'short_name',
                        name: 'short_name',
                    },
                    {
                        data: 'beneficiary_id',
                        name: 'beneficiary_id',
                    },
                    {
                        data: 'pan_number',
                        name: 'pan_number',
                    },
                    {
                        data: 'contact_person',
                        name: 'contact_person',
                    },
                    {
                        data: 'contact_number',
                        name: 'contact_number',
                    },
                    {
                        data: 'advance_amount',
                        name: 'advance_amount',
                        className: 'text-end'
                    },
                    @if (auth()->check() && auth()->user()->canActAsAdmin())
                        {
                            data: 'actions',
                            name: 'actions',
                            orderable: false,
                            searchable: false,
                            className: 'text-center'
                        }
                    @endif
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
                stateLoadParams: function(settings, data) {
                    data.order = [
                        [0, 'asc']
                    ];
                },
                searchDelay: 500,
                initComplete: function() {
                    // Add individual column search
                    this.api().columns([0, 1, 2, 3, 4, 5, 6]).every(function() {
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

            // Handle delete confirmation
            $(document).on('submit', 'form[action*="destroy"]', function(e) {
                if (!confirm('Are you sure you want to delete this transporter?')) {
                    e.preventDefault();
                    return false;
                }
            });

            // Handle transporter name click to show details
            $(document).on('click', '.transporter-name-link', function(e) {
                e.preventDefault();
                var transporterId = $(this).data('id');

                // Show modal with loading state
                $('#transporterDetailsModal').modal('show');
                $('#transporterDetailsContent').html(`
            <div class="text-center">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-2">Loading transporter details...</p>
            </div>
        `);
                $('#editTransporterBtn').hide();

                // Fetch transporter details
                $.ajax({
                    url: '/transporters/' + transporterId,
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
                                            <td>${data.contact_person_name}</td>
                                        </tr>
                                        <tr>
                                            <td class="fw-bold">Mobile Number:</td>
                                            <td>${data.mobile_number}</td>
                                        </tr>
                                        <tr>
                                            <td class="fw-bold">Type:</td>
                                            <td><span class="badge ${data.is_owner === 'Truck Owner' ? 'bg-brand-primary' : 'bg-info'}">${data.is_owner}</span></td>
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
                                            <td class="fw-bold">Book Balance:</td>
                                            <td class="fw-bold text-brand-primary">${data.advance_amount}</td>
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
                                    <h6 class="mb-0"><i class="bi bi-geo-alt"></i> Location Details</h6>
                                </div>
                                <div class="card-body">
                                    <table class="table table-borderless table-sm">
                                        <tr>
                                            <td class="fw-bold">Country:</td>
                                            <td>${data.country}</td>
                                        </tr>
                                        <tr>
                                            <td class="fw-bold">State:</td>
                                            <td>${data.state}</td>
                                        </tr>
                                        <tr>
                                            <td class="fw-bold">City:</td>
                                            <td>${data.city}</td>
                                        </tr>
                                        <tr>
                                            <td class="fw-bold">Address:</td>
                                            <td>${data.address}</td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header bg-light">
                                    <h6 class="mb-0"><i class="bi bi-info-circle"></i> Additional Information</h6>
                                </div>
                                <div class="card-body">
                                    <table class="table table-borderless table-sm">

                                        <tr>
                                            <td class="fw-bold">Created:</td>
                                            <td>${data.created_at}</td>
                                        </tr>
                                        <tr>
                                            <td class="fw-bold">Last Updated:</td>
                                            <td>${data.updated_at}</td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                `;

                        // Add advance payments section if there are any payments
                        if (data.advance_payments && data.advance_payments.length > 0) {
                            detailsHtml += `
                        <div class="row mt-3">
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-header bg-light">
                                        <h6 class="mb-0"><i class="bi bi-currency-rupee"></i> Recent Advance Payments (Last 10)</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="table-responsive">
                                            <table class="table table-sm table-hover">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th style="width: 15%;">Amount</th>
                                                        <th style="width: 20%;">Payment Date</th>
                                                        <th style="width: 35%;">Remark</th>
                                                        <th style="width: 30%;">Added On</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                    `;

                            data.advance_payments.forEach(function(payment) {
                                const parsedAmount = parseFloat(String(payment.amount).replace(/[^0-9.-]/g, ''));
                                const amountClass = !isNaN(parsedAmount) && parsedAmount < 0 ? 'text-danger' : 'text-brand-primary';
                                detailsHtml += `
                            <tr>
                                <td class="fw-bold ${amountClass}">${payment.amount}</td>
                                <td>${payment.payment_date}</td>
                                <td class="text-muted">${payment.remark}</td>
                                <td class="text-muted small">${payment.created_at}</td>
                            </tr>
                        `;
                            });

                            detailsHtml += `
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                        } else {
                            detailsHtml += `
                        <div class="row mt-3">
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-header bg-light">
                                        <h6 class="mb-0"><i class="bi bi-currency-rupee"></i> Recent Advance Payments</h6>
                                    </div>
                                    <div class="card-body text-center text-muted">
                                        <i class="bi bi-inbox display-6"></i>
                                        <p class="mt-2 mb-0">No advance payments found for this transporter.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                        }

                        $('#transporterDetailsContent').html(detailsHtml);
                        $('#editTransporterBtn').show().off('click').on('click', function() {
                            window.location.href = '/transporters/' + data.id + '/edit';
                        });
                    },
                    error: function(xhr, status, error) {
                        $('#transporterDetailsContent').html(`
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle"></i>
                        <strong>Error:</strong> Unable to load transporter details. Please try again.
                    </div>
                `);
                        $('#editTransporterBtn').hide();
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
                var today = new Date().toISOString().split('T')[0];
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
                        $('#transportersTable').DataTable().ajax.reload(null, false);
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
        });
    </script>
@endpush
