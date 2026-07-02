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
    <div class="col-sm-6"><h3 class="mb-0">Consignees/Consignors/Customer</h3></div>
    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="d-flex justify-content-end gap-2 mb-3">
        <a href="{{ route('consignees.create') }}" class="btn btn-brand-primary">
            <i class="bi bi-plus"></i> Create
        </a>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <h3 class="card-title">Consignees/Consignors/Customer List</h3>
        </div>
        <div class="card-body">
            <table id="consigneesTable" class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th style="width: 80px;">Sr. No</th>
                        <th>Name</th>
                        <th>City</th>
                        <th>State</th>
                        <th>Country</th>
                        <th>Contact Number</th>
                        @if(auth()->check() && auth()->user()->canActAsAdmin())
                        <th style="width: 120px;">Actions</th>
                        @endif
                    </tr>
                </thead>
            </table>
        </div>
    </div>
</div>

<!-- Consignee Details Modal -->
<div class="modal fade" id="consigneeDetailsModal" tabindex="-1" aria-labelledby="consigneeDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-gradient-primary">
                <h5 class="modal-title" id="consigneeDetailsModalLabel">
                    <i class="bi bi-person-lines-fill"></i> Consignee Details
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="consigneeDetailsContent">
                    <!-- Content will be loaded here -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle"></i> Close
                </button>
                @if (Auth::user()->canActAsAdmin())
                  <a href="#" id="editConsigneeBtn" class="btn btn-primary" style="display: none;">
                      <i class="bi bi-pencil-square"></i> Edit Consignee
                  </a>
                @endif
            </div>
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
    $('#consigneesTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '{{ route("consignees.index") }}',
            type: 'GET'
        },
        columns: [
            {
                data: 'DT_RowIndex',
                name: 'DT_RowIndex',
                width: '80px',
                className: 'text-center',
                orderable: false,
                searchable: false
            },
            {
                data: 'name',
                name: 'name'
            },
            {
                data: 'city',
                name: 'city',
                width: '120px'
            },
            {
                data: 'state',
                name: 'state',
                width: '120px'
            },
            {
                data: 'country',
                name: 'country',
                width: '120px'
            },
            {
                data: 'contact_number',
                name: 'contact_number',
                width: '130px'
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

    // Handle consignee name click to show details
    $(document).on('click', '.consignee-name-link', function(e) {
        e.preventDefault();
        var consigneeId = $(this).data('id');

        // Show modal with loading state
        $('#consigneeDetailsModal').modal('show');
        $('#consigneeDetailsContent').html(`
            <div class="text-center">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-2">Loading consignee details...</p>
            </div>
        `);
        $('#editConsigneeBtn').hide();

        // Fetch consignee details
        $.ajax({
            url: '/api/consignees/' + consigneeId,
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
                                            <td class="fw-bold">Contact Number:</td>
                                            <td>${data.contact_number}</td>
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

                $('#consigneeDetailsContent').html(detailsHtml);
                $('#editConsigneeBtn').show().attr('href', '/consignees/' + consigneeId + '/edit');
            },
            error: function(xhr, status, error) {
                $('#consigneeDetailsContent').html(`
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle"></i>
                        <strong>Error!</strong> Failed to load consignee details. Please try again.
                    </div>
                `);
                $('#editConsigneeBtn').hide();
            }
        });
    });

    // Handle delete confirmation
    $(document).on('submit', 'form[action*="destroy"]', function(e) {
        if (!confirm('Are you sure you want to delete this consignee?')) {
            e.preventDefault();
            return false;
        }
    });
});
</script>
@endpush
