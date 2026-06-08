@extends('layouts.app')

@section('content')
    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
    <!-- SweetAlert2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet" />
    <style>
        /* Prevent form expansion on validation errors */
        .form-control,
        .form-select {
            min-height: 38px;
            box-sizing: border-box;
        }

        .text-danger {
            font-size: 0.875rem;
            margin-top: 0.25rem;
            word-wrap: break-word;
        }

        /* Select2: theme draws border on .select2-selection — never put border/background on __rendered (nested box bug) */
        .card-body .select2-container {
            width: 100% !important;
            max-width: 100%;
        }

        .select2-container--bootstrap-5 .select2-selection {
            min-height: 38px;
        }

        .select2-container--bootstrap-5 .select2-selection--single {
            min-height: 38px;
        }

        .select2-container--bootstrap-5 .select2-selection--single .select2-selection__rendered {
            display: block;
            padding-left: 0.75rem;
            padding-right: 2rem;
            padding-top: 0.375rem;
            padding-bottom: 0.375rem;
            line-height: 1.35;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .select2-container--bootstrap-5.select2-container--focus .select2-selection--single,
        .select2-container--bootstrap-5.select2-container--open .select2-selection--single {
            border-color: var(--bs-primary);
            box-shadow: 0 0 0 0.25rem rgba(var(--bs-primary-rgb), 0.25);
        }

        .select2-container--bootstrap-5 .select2-selection--single .select2-selection__arrow {
            height: auto;
            min-height: 36px;
            top: 0;
            right: 0.5rem;
        }

        .select2-result-truck {
            padding: 4px 0;
        }

        .truck-info {
            font-size: 14px;
            line-height: 1.4;
        }

        .select2-dropdown {
            border: 1px solid #dee2e6;
            border-radius: 0.375rem;
        }

        .select2-results__option--highlighted {
            background-color: var(--bs-primary);
            color: white;
        }

        /* Highlight transporter input when declaration not given */
        #transporter_id.declaration-warning {
            border-color: #ffc107 !important;
            border-width: 2px !important;
            box-shadow: 0 0 0 0.2rem rgba(255, 193, 7, 0.25) !important;
        }

        /* Highlight Select2 container when declaration not given */
        .select2-container.declaration-warning {
            border-color: #ffc107 !important;
            border-width: 2px !important;
            box-shadow: 0 0 0 0.2rem rgba(255, 193, 7, 0.25) !important;
        }

        .select2-container.declaration-warning .select2-selection__rendered {
            background-color: #fff3cd !important;
        }

        .lr-payee-select-wrap .select2-container {
            width: 100% !important;
            max-width: 100%;
        }

        .lr-payee-select-wrap .select2-container .select2-selection--single {
            min-height: 38px;
            height: auto;
        }

        .lr-payee-select-wrap .select2-selection--single .select2-selection__rendered {
            white-space: normal;
            word-break: break-word;
            overflow-wrap: anywhere;
            text-overflow: clip;
        }
    </style>
    <div class="card card-info card-outline mb-4" style="max-width: 1000px; margin: 0 auto;">
        <div class="card-header">
            <div class="d-flex w-100">
                <div class="card-title h4">Add New Good Receipt (Bilty)</div>
                <div class="ms-auto">
                    <a href="{{ route('consignees.create') }}" class="btn btn-brand-primary">
                        <i class="bi bi-plus"></i> Create Consignee/Consignor/Customer</a>
                </div>
            </div>
        </div>
        <!--begin::Form-->
        <form method="POST" action="{{ route('lorry_receipts.store') }}" id="lorryReceiptForm" class="needs-validation"
            novalidate="">
            @csrf
            <input type="hidden" name="company_id" value="{{ auth()->user()->company_id }}">
            <div class="card-body">
                <!-- Declaration Warning (shown at top when truck owner declaration not submitted) -->
                <div id="declarationWarning" class="alert alert-warning mb-3" style="display: none;">
                    <i class="bi bi-exclamation-triangle"></i>
                    <strong>Warning:</strong> Declaration is not submitted for the selected truck owner.
                    <a href="#" id="editTruckOwnerLink" target="_blank" class="alert-link">
                        <i class="bi bi-pencil-square"></i> Edit Truck Owner
                    </a>
                </div>
                <!-- General Details -->
                <div class="card mb-4 p-3 bg-light">
                    <div class="row">
                        <div class="col">
                            <h5 class="mb-3">General Details</h5>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="lr_number" class="form-label">LR Number * <small class="text-muted">(Must be
                                    unique)</small></label>
                            <input type="text" class="form-control" id="lr_number" name="lr_number"
                                value="{{ old('lr_number') }}" required placeholder="Enter unique LR Number">
                            @error('lr_number')
                                <div class="text-danger">{{ $message }}</div>
                            @enderror
                            <div class="invalid-feedback">Please enter a unique LR number.</div>
                            <div id="lr-number-feedback" class="mt-1"></div>
                        </div>
                        <div class="col-md-4">
                            <label for="gr_date" class="form-label">GR Date *</label>
                            <input type="date" class="form-control" id="gr_date" name="gr_date" required
                                value="{{ old('gr_date') }}">
                            @error('gr_date')
                                <div class="text-danger">{{ $message }}</div>
                            @enderror
                            <div class="invalid-feedback">Please select a GR date.</div>
                        </div>
                        <div class="col-md-4">
                            <label for="customer_id" class="form-label">Customer *</label>
                            <select class="form-select" id="customer_id" name="customer_id" required
                                data-placeholder="Search and select customer...">
                                <option value="">Select Customer</option>
                                @php($selectedCustomer = $consignees->firstWhere('id', old('customer_id')))
                                @if($selectedCustomer)
                                    <option value="{{ $selectedCustomer->id }}" selected>
                                        {{ $selectedCustomer->name }}
                                    </option>
                                @endif
                            </select>
                            @error('customer_id')
                                <div class="text-danger">{{ $message }}</div>
                            @enderror
                            <div class="invalid-feedback">Please select a customer.</div>
                        </div>
                    </div>
                    <!-- Memo ID field removed from creation form as it cannot be present at creation time -->
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="truck_id" class="form-label">Truck *</label>
                            <select class="form-select" id="truck_id" name="truck_id" required
                                data-placeholder="Search and select truck...">
                                <option value="">Select Trucks</option>
                                @php($selectedTruck = $trucks->firstWhere('id', old('truck_id')))
                                @if($selectedTruck)
                                    <option value="{{ $selectedTruck->id }}"
                                        data-transporter-id="{{ $selectedTruck->transporter_id }}"
                                        data-transporter-name="{{ $selectedTruck->transporter ? $selectedTruck->transporter->name : '' }}"
                                        selected>
                                        {{ $selectedTruck->truck_number }}
                                        @if ($selectedTruck->driver)
                                            - Driver: {{ $selectedTruck->driver->name }}
                                        @endif
                                        ({{ $selectedTruck->transporter ? $selectedTruck->transporter->name : 'No Transporter' }})
                                    </option>
                                @endif
                            </select>
                            @error('truck_id')
                                <div class="text-danger">{{ $message }}</div>
                            @enderror
                            <div class="invalid-feedback">Please select a truck.</div>
                        </div>
                        <div class="col-md-4">
                            <label for="transporter_type" class="form-label" id="transporterTypeLabel">Select Payment Type
                                *</label>
                            <select class="form-select" id="transporter_type" name="transporter_type" required>
                                <option value="">Select Type</option>
                                <option value="owner" {{ old('transporter_type') === 'owner' ? 'selected' : '' }}>Truck Owner</option>
                                <option value="agency" {{ old('transporter_type') === 'agency' ? 'selected' : '' }}>Agency</option>
                            </select>
                            <div class="invalid-feedback">Please select transporter type.</div>
                        </div>
                        <div class="col-md-4 min-w-0">
                            <label for="transporter_id" id="transporterLabel" class="form-label">Transporter *</label>
                            <div class="lr-payee-select-wrap">
                                <select class="form-select" id="transporter_id" name="transporter_id" required
                                    onchange="window.LREntity.showTdsPercentage(this.value)">
                                    <option value="">Select Transporter</option>
                                    @if (! empty($payeeTransporter))
                                        <option value="{{ $payeeTransporter->id }}" selected>
                                            {{ $payeeTransporter->name }}
                                        </option>
                                    @endif
                                </select>
                            </div>

                            @error('transporter_id')
                                <div class="text-danger">{{ $message }}</div>
                            @enderror
                            <div class="invalid-feedback">Please select a transporter.</div>
                            <div id="tdsRateDisplay" class="mt-2" style="display: none;">
                                <span class="badge bg-warning text-dark fs-6">
                                    🎯 TDS Rate: <span id="tdsPercentageValue">--</span>
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-4 min-w-0">
                            <label for="reference_by" class="form-label">Reference By</label>
                            <div class="lr-payee-select-wrap">
                                <select class="form-select" id="reference_by" name="reference_by">
                                    <option value="">Select Reference</option>
                                    @php($selectedAgency = $agencies->firstWhere('id', old('reference_by')))
                                    @if($selectedAgency)
                                        <option value="{{ $selectedAgency->id }}" selected>
                                            {{ $selectedAgency->name }}
                                        </option>
                                    @endif
                                </select>
                            </div>
                            @error('reference_by')
                                <div class="text-danger">{{ $message }}</div>
                            @enderror
                            <div class="invalid-feedback">Please select a reference.</div>
                        </div>
                        <div class="col-md-4">
                            <label for="invoice_number" class="form-label">Invoice Number <small
                                    class="text-muted">(Optional)</small></label>
                            <input type="text" class="form-control" id="invoice_number" name="invoice_number"
                                value="{{ old('invoice_number') }}" placeholder="Enter Invoice Number">
                            @error('invoice_number')
                                <div class="text-danger">{{ $message }}</div>
                            @enderror
                            <div class="invalid-feedback">Please enter a valid invoice number.</div>
                            <div id="invoice-number-feedback" class="mt-1"></div>
                        </div>
                        <div class="col-md-4">
                            <label for="gr_number" class="form-label">GR Number <small
                                    class="text-muted">(Optional)</small></label>
                            <input type="text" class="form-control" id="gr_number" name="gr_number"
                                value="{{ old('gr_number') }}" placeholder="Enter GR Number">
                            @error('gr_number')
                                <div class="text-danger">{{ $message }}</div>
                            @enderror
                            <div class="invalid-feedback">Please enter a valid GR number.</div>
                            <div id="gr-number-feedback" class="mt-1"></div>
                        </div>
                    </div>
                </div>

                <!-- Consignor & Consignee Details -->
                <div class="card mb-4 p-3 bg-light">
                    <div class="row">
                        <div class="col">
                            <h5 class="mb-3">Consignor & Consignee Details</h5>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="consignor_id" class="form-label">Consignor *</label>
                            <select class="form-select" id="consignor_id" name="consignor_id" required
                                data-placeholder="Search and select consignor...">
                                <option value="">Select Consignor</option>
                                @php($selectedConsignor = $consignees->firstWhere('id', old('consignor_id')))
                                @if($selectedConsignor)
                                    <option value="{{ $selectedConsignor->id }}" selected>
                                        {{ $selectedConsignor->name }}
                                    </option>
                                @endif
                            </select>
                            @error('consignor_id')
                                <div class="text-danger">{{ $message }}</div>
                            @enderror
                            <div class="invalid-feedback">Please select a consignor.</div>
                        </div>
                        <div class="col-md-4">
                            <label for="consignor_contact_number" class="form-label">Consignor Contact Number</label>
                            <input type="text" class="form-control" id="consignor_contact_number"
                                name="consignor_contact_number" readonly value="{{ old('consignor_contact_number') }}">
                        </div>

                    </div>
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="consignee_id" class="form-label">Consignee *</label>
                            <select class="form-select" id="consignee_id" name="consignee_id" required
                                data-placeholder="Search and select consignee...">
                                <option value="">Select Consignee</option>
                                @php($selectedConsignee = $consignees->firstWhere('id', old('consignee_id')))
                                @if($selectedConsignee)
                                    <option value="{{ $selectedConsignee->id }}" selected>
                                        {{ $selectedConsignee->name }}
                                    </option>
                                @endif
                            </select>
                            @error('consignee_id')
                                <div class="text-danger">{{ $message }}</div>
                            @enderror
                            <div class="invalid-feedback">Please select a consignee.</div>
                        </div>
                        <div class="col-md-4">
                            <label for="consignee_contact_number" class="form-label">Consignee Contact Number</label>
                            <input type="text" class="form-control" id="consignee_contact_number"
                                name="consignee_contact_number" readonly value="{{ old('consignee_contact_number') }}">
                        </div>
                    </div>
                </div>
                <!-- Route Section -->
                <div class="card mb-4 p-3 bg-light">
                    <div class="row">
                        <div class="col">
                            <h5 class="mb-3">Route Details</h5>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="source_city" class="form-label">Source City *</label>
                            <input type="text" class="form-control" id="source_city" name="source_city" required
                                value="{{ old('source_city') }}" readonly>
                        </div>
                        <div class="col-md-6">
                            <label for="source_state" class="form-label">Source State *</label>
                            <input type="text" class="form-control" id="source_state" name="source_state" required
                                value="{{ old('source_state') }}" readonly>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="destination_city" class="form-label">Destination City *</label>
                            <input type="text" class="form-control" id="destination_city" name="destination_city"
                                required value="{{ old('destination_city') }}" readonly>
                        </div>
                        <div class="col-md-4">
                            <label for="destination_state" class="form-label">Destination State *</label>
                            <input type="text" class="form-control" id="destination_state" name="destination_state"
                                required value="{{ old('destination_state') }}" readonly>
                        </div>
                        <div class="col-md-4">
                            <label for="unloading_station" class="form-label">Unloading Station</label>
                            <input type="text" class="form-control" id="unloading_station" name="unloading_station"
                                 value="{{ old('unloading_station') }}">
                        </div>
                    </div>
                </div>
                <!-- Goods & Charges Section -->
                <div class="card mb-4 p-3 bg-light">
                    <div class="row">
                        <div class="col">
                            <h5 class="mb-3">Goods & Charges</h5>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <input type="hidden" id="lane" name="lane" value="Road">
                        <div class="col-md-4">
                            <label for="good_type" class="form-label">Goods Type *</label>
                            <div class="input-group flex-nowrap">
                                <select class="form-select" id="good_type" name="good_type" required>
                                    <option value="">Select Goods Type</option>
                                    @foreach ($goodsTypes as $goodsType)
                                        <option value="{{ $goodsType->name }}"
                                            {{ old('good_type') == $goodsType->name ? 'selected' : '' }}>
                                            {{ $goodsType->name }}
                                        </option>
                                    @endforeach
                                </select>
                                <button type="button" class="btn btn-primary" data-bs-toggle="modal"
                                    data-bs-target="#addGoodsTypeModal" title="Add New Goods Type">
                                    <i class="bi bi-plus"></i>
                                </button>
                            </div>
                            @error('good_type')
                                <div class="text-danger">{{ $message }}</div>
                            @enderror
                            <div class="invalid-feedback">Please select a goods type.</div>
                        </div>

                        <div class="col-md-4">
                            <label for="no_of_units" class="form-label">No. of Units <small
                                    class="text-muted">(Optional)</small></label>
                            <input type="number" class="form-control" id="no_of_units" name="no_of_units"
                                value="{{ old('no_of_units') }}" min="1" max="100000"
                                title="Maximum 100,000 units allowed">
                            @error('no_of_units')
                                <div class="text-danger">{{ $message }}</div>
                            @enderror
                            <div class="invalid-feedback">Please enter a valid number of units (max 100,000).</div>
                            <small class="text-muted">📦 Max: 100,000 units</small>
                        </div>
                        <div class="col-md-4">
                            <label for="goods_value" class="form-label">Goods Value <small
                                    class="text-muted">(Optional)</small></label>
                            <input type="number" step="0.01" class="form-control" id="goods_value" name="goods_value"
                                value="{{ old('goods_value') }}" min="0" placeholder="">
                            @error('goods_value')
                                <div class="text-danger">{{ $message }}</div>
                            @enderror
                            <small class="text-muted">Value of goods ()</small>
                        </div>

                    </div>
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="weight" class="form-label">Weight * <small class="text-muted">(in
                                    Tons)</small></label>
                            <input type="number" step="0.001" class="form-control" id="weight" name="weight"
                                required value="{{ old('weight') }}" min="0.1" max="100"
                                title="Weight must be between 0.1 and 100 tons">
                            @error('weight')
                                <div class="text-danger">{{ $message }}</div>
                            @enderror
                            <div class="invalid-feedback">Please enter a weight between 0.1 and 100 tons.</div>
                            <small class="text-muted">📏 Range: 0.1 - 100 tons</small>
                        </div>
                        <div class="col-md-4">
                            <label for="fr_rate" class="form-label">Freight Rate * <small class="text-muted">( per
                                    ton)</small></label>
                            <input type="number" step="0.01" class="form-control" id="fr_rate" name="fr_rate"
                                required value="{{ old('fr_rate') }}" min="1" max="20000"
                                title="Freight rate must be between 1 and 20,000 per ton">
                            @error('fr_rate')
                                <div class="text-danger">{{ $message }}</div>
                            @enderror
                            <div class="invalid-feedback">Please enter a freight rate between 1 and 20,000.</div>
                            <small class="text-muted">💰 Range: 1 - 20,000 per ton</small>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label d-flex justify-content-between align-items-center">
                                <span>Amount *</span>
                                <span class="form-check form-check-inline mb-0">
                                    <input class="form-check-input" type="checkbox" id="is_fixed_amount"
                                        name="is_fixed_amount" value="1" {{ old('is_fixed_amount') ? 'checked' : '' }}>
                                    <label class="form-check-label" for="is_fixed_amount">Use fixed amount</label>
                                </span>
                            </label>
                            <input type="number" step="0.01" class="form-control" id="amount" name="amount"
                                required value="{{ old('amount') }}"
                                title="Auto-calculated: Weight × Freight Rate (editable, or fixed when enabled)">
                            @error('amount')
                                <div class="text-danger">{{ $message }}</div>
                            @enderror
                            <div class="invalid-feedback">Please enter an amount.</div>
                            <small class="text-muted" id="amountHelpText">
                                💡 Calculated as: Weight × Freight Rate (uncheck \"Use fixed amount\" to auto-calculate).
                            </small>
                        </div>
                    </div>
                </div>
                <!-- Submit Actions -->
                <div class="card mb-4 p-3 bg-light">
                    <div class="row">
                        <div class="col">
                            <h5 class="mb-3">Submit Actions</h5>
                        </div>
                    </div>
                    <div class="row g-2">
                        <div class="col-md-4 offset-md-4">
                            <button class="btn btn-brand-primary w-100" type="submit">💾 Create Good Receipt (Bilty)</button>
                        </div>

                        <div class="col-md-4">
                            <a href="{{ route('lorry_receipts.index') }}" class="btn btn-back w-100">📄 Back to
                                List</a>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
    <!-- Add Goods Type Modal -->
    <div class="modal fade" id="addGoodsTypeModal" tabindex="-1" aria-labelledby="addGoodsTypeModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-md">
            <div class="modal-content">
                <form id="addGoodsTypeForm">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title" id="addGoodsTypeModalLabel">Add Goods Type</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    <div class="modal-body">
                        <div id="goodsTypeFormErrors" class="alert alert-danger d-none"></div>

                        <div class="mb-3">
                            <label for="goods_type_name" class="form-label">Goods Type Name *</label>
                            <input type="text" class="form-control" id="goods_type_name" name="name" required>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-brand-primary">
                            <span class="spinner-border spinner-border-sm d-none" role="status"
                                aria-hidden="true"></span>
                            Submit
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Load Select2 from CDN -->
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <!-- Load SweetAlert2 from CDN -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- Common LR entity confirmation helpers -->
    <script src="/js/lr-entity.js"></script>

    <script>
        // Wait for DOM and jQuery to be ready
        document.addEventListener('DOMContentLoaded', function() {
            // Wait for jQuery to be available
            function waitForJQuery() {
                if (typeof $ !== 'undefined' && typeof $.fn !== 'undefined') {
                    checkSelect2();
                } else {
                    setTimeout(waitForJQuery, 100);
                }
            }

            function checkSelect2() {
                if (typeof $.fn.select2 !== 'undefined') {
                    initializeLorryReceiptForm();
                } else {
                    var script = document.createElement('script');
                    script.src = 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js';
                    script.onload = function() {
                        initializeLorryReceiptForm();
                    };
                    document.head.appendChild(script);
                }
            }

            waitForJQuery();
        });

        function initializeLorryReceiptForm() {
            $(document).ready(function() {
                // Initialize other dropdowns with Select2
                const ajaxConsigneesConfig = {
                    url: '/api/consignees/search',
                    dataType: 'json',
                    delay: 250,
                    data: function (params) {
                        return {
                            q: params.term || '',
                            page: params.page || 1,
                        };
                    },
                    processResults: function (data, params) {
                        return {
                            results: data.results || [],
                            pagination: {
                                more: data.pagination?.more || false,
                            },
                        };
                    },
                    cache: true
                };

                const ajaxTrucksConfig = {
                    url: '/api/trucks/search',
                    dataType: 'json',
                    delay: 250,
                    data: function (params) {
                        return {
                            q: params.term || '',
                            page: params.page || 1,
                        };
                    },
                    processResults: function (data, params) {
                        return {
                            results: data.results || [],
                            pagination: {
                                more: data.pagination?.more || false,
                            },
                        };
                    },
                    cache: true
                };

                const ajaxAgenciesConfig = {
                    url: '/api/agencies/search',
                    dataType: 'json',
                    delay: 250,
                    data: function (params) {
                        return {
                            q: params.term || '',
                            page: params.page || 1,
                        };
                    },
                    processResults: function (data, params) {
                        return {
                            results: data.results || [],
                            pagination: {
                                more: data.pagination?.more || false,
                            },
                        };
                    },
                    cache: true
                };

                const select2AjaxTransport = function (params, success, failure) {
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
                };

                const ajaxTruckOwnersConfig = {
                    url: '/api/truck-owners/search',
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
                    transport: select2AjaxTransport,
                    cache: true
                };

                const lrAddTransporterBtn = document.getElementById('lr-add-transporter-btn');
                const lrAddTransporterWrap = document.getElementById('lr-add-transporter-wrap');

                function syncLrTransporterAddButton(paymentType) {
                    if (lrAddTransporterWrap) {
                        if (paymentType === 'owner') {
                            lrAddTransporterWrap.classList.remove('d-none');
                        } else {
                            lrAddTransporterWrap.classList.add('d-none');
                        }
                    }
                    if (lrAddTransporterBtn) {
                        lrAddTransporterBtn.href = lrAddTransporterBtn.getAttribute('data-href-owner') || lrAddTransporterBtn.href;
                    }
                }

                function bindTransporterSelect2Title($t) {
                    function setTitleFromSelection() {
                        const inst = $t.select2('data');
                        const fromInst = inst && inst[0] && inst[0].text ? String(inst[0].text) : '';
                        const fromOpt = ($t.find('option:selected').text() || '').trim();
                        const text = (fromInst || fromOpt).trim();
                        $t.next('.select2-container').find('.select2-selection__rendered').attr('title', text);
                    }
                    $t.off('select2:select.lrPayeeTitle select2:clear.lrPayeeTitle change.lrPayeeTitle')
                        .on('select2:select.lrPayeeTitle', function (e) {
                            const t = e.params && e.params.data && e.params.data.text ? String(e.params.data.text) : '';
                            $(this).next('.select2-container').find('.select2-selection__rendered').attr('title', t);
                        })
                        .on('select2:clear.lrPayeeTitle', function () {
                            $(this).next('.select2-container').find('.select2-selection__rendered').attr('title', '');
                        })
                        .on('change.lrPayeeTitle', function () {
                            setTimeout(setTitleFromSelection, 0);
                        });
                }

                function rebindLrTransporterSelect2(paymentType) {
                    const $t = $('#transporter_id');
                    if (!$t.length || typeof $t.select2 !== 'function') {
                        return;
                    }
                    if ($t.hasClass('select2-hidden-accessible')) {
                        $t.select2('destroy');
                    }
                    const mode = paymentType === 'owner' ? 'owner' : 'agency';
                    syncLrTransporterAddButton(mode);
                    if (mode === 'owner') {
                        $t.select2({
                            theme: 'bootstrap-5',
                            width: '100%',
                            allowClear: true,
                            minimumInputLength: 1,
                            placeholder: 'Search truck owner (type first letter)...',
                            language: {
                                inputTooShort: function () {
                                    return 'Please type at least 1 character to search';
                                },
                                searching: function () {
                                    return 'Searching...';
                                }
                            },
                            ajax: ajaxTruckOwnersConfig
                        });
                    } else {
                        $t.select2({
                            theme: 'bootstrap-5',
                            width: '100%',
                            allowClear: true,
                            minimumInputLength: 0,
                            placeholder: 'Search agency...',
                            ajax: ajaxAgenciesConfig
                        });
                    }
                    bindTransporterSelect2Title($t);
                    $t.trigger('change.select2');
                }

                window.lrCreateRebindTransporterSelect2 = rebindLrTransporterSelect2;

                // Customer / consignor / consignee
                $('#customer_id').select2({
                    theme: 'bootstrap-5',
                    width: '100%',
                    minimumInputLength: 1,
                    placeholder: 'Search and select customer...',
                    ajax: ajaxConsigneesConfig
                });
                $('#consignor_id').select2({
                    theme: 'bootstrap-5',
                    width: '100%',
                    minimumInputLength: 1,
                    placeholder: 'Search and select consignor...',
                    ajax: ajaxConsigneesConfig
                });
                $('#consignee_id').select2({
                    theme: 'bootstrap-5',
                    width: '100%',
                    minimumInputLength: 1,
                    placeholder: 'Search and select consignee...',
                    ajax: ajaxConsigneesConfig
                });

                // Truck & transporter/reference (truck-owner confirmation flow uses Select2 result data)
                $('#truck_id').select2({
                    theme: 'bootstrap-5',
                    width: '100%',
                    minimumInputLength: 1,
                    placeholder: 'Search and select truck...',
                    ajax: ajaxTrucksConfig
                });
                const initialPayeeMode = document.getElementById('transporter_type')?.value === 'owner' ? 'owner' : 'agency';
                rebindLrTransporterSelect2(initialPayeeMode);

                $('#reference_by').select2({
                    theme: 'bootstrap-5',
                    width: '100%',
                    allowClear: true,
                    minimumInputLength: 0,
                    placeholder: 'Search reference agency...',
                    ajax: ajaxAgenciesConfig
                });
                bindTransporterSelect2Title($('#reference_by'));

                // Small/static lists
                $('#good_type').select2({
                    theme: 'bootstrap-5',
                    width: '100%',
                    minimumResultsForSearch: 0
                });

                // Ensure Select2 search input gets autofocus on open (works for dynamic elements too)
                $(document).off('select2:open.autofocus').on('select2:open.autofocus', function() {
                    // Delay to let the search input render
                    setTimeout(function() {
                        const searchInput = document.querySelector('.select2-container--open .select2-search__field');
                        if (searchInput) {
                            searchInput.focus();
                        }
                    }, 0);
                });

                const transporterTypeSelectEl = document.getElementById('transporter_type');
                const truckSelectEl = document.getElementById('truck_id');
                const transporterSelectEl = document.getElementById('transporter_id');
                const transporterLabelEl = document.getElementById('transporterLabel');
                if (transporterTypeSelectEl && truckSelectEl && transporterSelectEl && transporterLabelEl) {
                    window.LREntity.initLorryReceiptTransporterSelectionFlow({
                        transporterTypeSelect: transporterTypeSelectEl,
                        truckSelect: truckSelectEl,
                        transporterSelect: transporterSelectEl,
                        transporterLabel: transporterLabelEl,
                        trucksData: {},
                        mode: 'create',
                        allowTransporterChanges: true,
                        afterTransporterTypeChange: function (selectedType) {
                            const mode = selectedType === 'owner' ? 'owner' : 'agency';
                            if (typeof window.lrCreateRebindTransporterSelect2 === 'function') {
                                window.lrCreateRebindTransporterSelect2(mode);
                            }
                        },
                    });
                }

                // Trigger Select2 to refresh and show selected values
                setTimeout(function() {
                    $('#customer_id, #truck_id, #transporter_id, #reference_by, #consignor_id, #consignee_id, #good_type')
                        .trigger('change.select2');
                    const payeeId = $('#transporter_id').val();
                    if (payeeId && typeof window.LREntity.showTdsPercentage === 'function') {
                        window.LREntity.showTdsPercentage(payeeId);
                    }
                }, 500);
            });
        }
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {

            const { showValidationError, clearValidationError } = window.LREntity;

            // Handle consignor contact number and location details
            const consignorSelect = document.getElementById('consignor_id');
            const consignorContactInput = document.getElementById('consignor_contact_number');
            const sourceCityInput = document.getElementById('source_city');
            const sourceStateInput = document.getElementById('source_state');

            // Handle consignor selection with Select2 support
            function handleConsignorSelection(consignorId) {
                // Check if consignor and consignee are the same
                const consigneeId = consigneeSelect.value;
                if (consignorId && consigneeId && consignorId === consigneeId) {
                    console.warn('⚠️ Consignor and Consignee cannot be the same!');
                    showValidationError(consignorSelect, 'Consignor and Consignee cannot be the same');

                    // Clear the consignor selection
                    $('#consignor_id').val(null).trigger('change');
                    consignorContactInput.value = '';
                    sourceCityInput.value = '';
                    sourceStateInput.value = '';
                    return;
                } else {
                    clearValidationError(consignorSelect);
                }

                if (consignorId) {
                    fetch(`/api/consignees/${consignorId}`)
                        .then(response => {
                            if (!response.ok) {
                                throw new Error(`HTTP error! status: ${response.status}`);
                            }
                            return response.json();
                        })
                        .then(data => {
                            // Auto-populate consignor fields
                            consignorContactInput.value = data.contact_number || '';
                            // Use city and state names from the API response
                            sourceCityInput.value = data.city || '';
                            sourceStateInput.value = data.state || '';
                        })
                        .catch(error => {
                            console.error('❌ Error fetching consignor data:', error);
                        });
                } else {
                    consignorContactInput.value = '';
                    sourceCityInput.value = '';
                    sourceStateInput.value = '';
                }
            }

            // Handle Select2 consignor selection
            $('#consignor_id').on('select2:select', function(e) {
                const consignorId = e.params.data.id;
                handleConsignorSelection(consignorId);
            });

            // Handle regular consignor selection (fallback)
            consignorSelect.addEventListener('change', function() {
                const consignorId = this.value;
                handleConsignorSelection(consignorId);
            });

            // Handle consignee contact number and location details
            const consigneeSelect = document.getElementById('consignee_id');
            const consigneeContactInput = document.getElementById('consignee_contact_number');
            const destinationCityInput = document.getElementById('destination_city');
            const destinationStateInput = document.getElementById('destination_state');

            // Handle consignee selection with Select2 support
            function handleConsigneeSelection(consigneeId) {
                // Check if consignor and consignee are the same
                const consignorId = consignorSelect.value;
                if (consigneeId && consignorId && consigneeId === consignorId) {
                    console.warn('⚠️ Consignee and Consignor cannot be the same!');
                    showValidationError(consigneeSelect, 'Consignee and Consignor cannot be the same');

                    // Clear the consignee selection
                    $('#consignee_id').val(null).trigger('change');
                    consigneeContactInput.value = '';
                    destinationCityInput.value = '';
                    destinationStateInput.value = '';
                    return;
                } else {
                    clearValidationError(consigneeSelect);
                }

                if (consigneeId) {
                    fetch(`/api/consignees/${consigneeId}`)
                        .then(response => {
                            if (!response.ok) {
                                throw new Error(`HTTP error! status: ${response.status}`);
                            }
                            return response.json();
                        })
                        .then(data => {
                            // Auto-populate consignee fields
                            consigneeContactInput.value = data.contact_number || '';
                            // Use city and state names from the API response
                            destinationCityInput.value = data.city || '';
                            destinationStateInput.value = data.state || '';
                        })
                        .catch(error => {
                            console.error('❌ Error fetching consignee data:', error);
                        });
                } else {
                    consigneeContactInput.value = '';
                    destinationCityInput.value = '';
                    destinationStateInput.value = '';
                }
            }

            // Handle Select2 consignee selection
            $('#consignee_id').on('select2:select', function(e) {
                const consigneeId = e.params.data.id;
                handleConsigneeSelection(consigneeId);
            });

            // Handle regular consignee selection (fallback)
            consigneeSelect.addEventListener('change', function() {
                const consigneeId = this.value;
                handleConsigneeSelection(consigneeId);
            });

            // Handle truck selection for transporter info
            const truckSelect = document.getElementById('truck_id');
            if (truckSelect) {
                truckSelect.addEventListener('change', function() {
                    const truckId = this.value;
                    const tdsRateDisplay = document.getElementById('tdsRateDisplay');

                    if (truckId) {
                        fetch(`/api/trucks/${truckId}`)
                            .then(response => response.json())
                            .then(data => {
                                if (data.transporter && tdsRateDisplay) {
                                    tdsRateDisplay.textContent =
                                        `TDS Rate: ${data.transporter.tds_rate || 'N/A'}%`;
                                }
                            });
                    } else if (tdsRateDisplay) {
                        tdsRateDisplay.textContent = '--';
                    }
                });
            }

            // Custom form validation function
            function validateConsignorConsignee() {
                const consignorId = consignorSelect.value;
                const consigneeId = consigneeSelect.value;

                if (consignorId && consigneeId && consignorId === consigneeId) {
                    showValidationError(consignorSelect, 'Consignor and Consignee cannot be the same');
                    showValidationError(consigneeSelect, 'Consignor and Consignee cannot be the same');
                    return false;
                }

                clearValidationError(consignorSelect);
                clearValidationError(consigneeSelect);
                return true;
            }

            // Bootstrap form validation with custom validation and confirmation dialog
            (() => {
                'use strict';
                const forms = document.querySelectorAll('.needs-validation');
                Array.from(forms).forEach(form => {
                    form.addEventListener('submit', event => {
                        // Always prevent default submission first
                        event.preventDefault();
                        event.stopPropagation();

                        // Run custom validation first
                        const customValidationPassed = validateConsignorConsignee();

                        if (!form.checkValidity() || !customValidationPassed) {
                            if (!customValidationPassed) {
                                console.error(
                                    '🚨 Form submission blocked: Consignor and Consignee cannot be the same'
                                );
                            }
                            form.classList.add('was-validated');
                            return;
                        }

                        // If validation passes, show confirmation dialog
                        showConfirmationDialog(form);
                        form.classList.add('was-validated');
                    }, false);
                });
            })();

            // Auto-calculate amount based on weight × freight rate
            const weightInput = document.getElementById('weight');
            const frRateInput = document.getElementById('fr_rate');
            const amountInput = document.getElementById('amount');
            const isFixedAmountCheckbox = document.getElementById('is_fixed_amount');
            const amountHelpText = document.getElementById('amountHelpText');

            function calculateAmount() {
                if (isFixedAmountCheckbox && isFixedAmountCheckbox.checked) {
                    return; // Do not auto-calculate when using fixed amount
                }
                const weight = parseFloat(weightInput.value) || 0;
                const frRate = parseFloat(frRateInput.value) || 0;

                if (weight > 0 && frRate > 0) {
                    const calculatedAmount = weight * frRate;
                    amountInput.value = calculatedAmount.toFixed(2);

                    // Add visual feedback to show it's auto-calculated
                    amountInput.style.backgroundColor = '#e8f5e8';
                    amountInput.title = `Auto-calculated: ${weight} × ${frRate} = ${calculatedAmount.toFixed(2)}`;

                }
            }

            // Add event listeners for weight and freight rate changes
            weightInput.addEventListener('input', function() {
                validateWeight();
                calculateAmount();
            });
            frRateInput.addEventListener('input', calculateAmount);

            // Weight validation function
            function validateWeight() {
                const weight = parseFloat(weightInput.value);

                if (isNaN(weight)) {
                    return; // Let HTML5 validation handle empty/invalid input
                }

                if (weight < 0.1) {
                    weightInput.setCustomValidity('Weight must be at least 0.1 tons');
                    weightInput.classList.add('is-invalid');
                    console.warn('⚠️ Weight too low:', weight);
                } else if (weight > 100) {
                    weightInput.setCustomValidity('Weight cannot exceed 100 tons');
                    weightInput.classList.add('is-invalid');
                    console.warn('⚠️ Weight too high:', weight);
                } else {
                    weightInput.setCustomValidity('');
                    weightInput.classList.remove('is-invalid');
                }
            }

            // Add blur validation for weight
            weightInput.addEventListener('blur', validateWeight);

            // Allow user to manually edit amount (remove auto-calculated styling when manually edited)
            amountInput.addEventListener('input', function() {
                // Remove auto-calculated styling when user manually edits
                this.style.backgroundColor = '';
                if (!(isFixedAmountCheckbox && isFixedAmountCheckbox.checked)) {
                    this.title = 'Manually edited amount';
                } else {
                    this.title = 'Fixed amount (manual)';
                }
            });

            if (isFixedAmountCheckbox) {
                // Initial state: when unchecked (auto), amount is read-only
                if (!isFixedAmountCheckbox.checked) {
                    amountInput.readOnly = true;
                } else {
                    amountInput.readOnly = false;
                }

                isFixedAmountCheckbox.addEventListener('change', function() {
                    if (this.checked) {
                        amountHelpText.textContent =
                            '💡 Fixed amount mode: this value will be used instead of Weight × Freight Rate.';
                        amountInput.style.backgroundColor = '';
                        amountInput.title = 'Fixed amount (manual)';
                        amountInput.readOnly = false;
                    } else {
                        amountHelpText.textContent =
                            '💡 Calculated as: Weight × Freight Rate (uncheck \"Use fixed amount\" to auto-calculate).';
                        amountInput.readOnly = true;
                        calculateAmount();
                    }
                });
            }

            // Calculate amount on page load if values exist
            if (weightInput.value && frRateInput.value) {
                calculateAmount();
            }

            // LR Number uniqueness validation
            const lrNumberInput = document.getElementById('lr_number');
            const lrNumberFeedback = document.getElementById('lr-number-feedback');
            let lrNumberTimeout;

            function checkLrNumberUniqueness(lrNumber) {
                if (!lrNumber || lrNumber.length < 2) {
                    lrNumberFeedback.innerHTML = '';
                    lrNumberInput.classList.remove('is-valid', 'is-invalid');
                    return;
                }

                // Show loading state
                lrNumberFeedback.innerHTML = '<small class="text-info">🔍 Checking availability...</small>';
                lrNumberInput.classList.remove('is-valid', 'is-invalid');

                fetch('/api/check-lr-number-uniqueness', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute(
                                'content')
                        },
                        body: JSON.stringify({
                            lr_number: lrNumber
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.valid) {
                            lrNumberFeedback.innerHTML = '<small class="text-success">✅ ' + data.message +
                                '</small>';
                            lrNumberInput.classList.remove('is-invalid');
                            lrNumberInput.classList.add('is-valid');
                            lrNumberInput.setCustomValidity('');
                        } else {
                            lrNumberFeedback.innerHTML = '<small class="text-danger">❌ ' + data.message +
                                '</small>';
                            lrNumberInput.classList.remove('is-valid');
                            lrNumberInput.classList.add('is-invalid');
                            lrNumberInput.setCustomValidity(data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Error checking LR Number uniqueness:', error);
                        lrNumberFeedback.innerHTML =
                            '<small class="text-warning">⚠️ Unable to check availability</small>';
                        lrNumberInput.classList.remove('is-valid', 'is-invalid');
                    });
            }

            // Add debounced input listener for LR Number
            lrNumberInput.addEventListener('input', function() {
                const lrNumber = this.value.trim();

                // Clear previous timeout
                clearTimeout(lrNumberTimeout);

                // Set new timeout for debounced checking
                lrNumberTimeout = setTimeout(() => {
                    checkLrNumberUniqueness(lrNumber);
                }, 500); // 500ms delay
            });

            // Check on blur as well
            lrNumberInput.addEventListener('blur', function() {
                const lrNumber = this.value.trim();
                if (lrNumber) {
                    clearTimeout(lrNumberTimeout);
                    checkLrNumberUniqueness(lrNumber);
                }
            });

            // Invoice Number uniqueness validation
            const invoiceNumberInput = document.getElementById('invoice_number');
            const invoiceNumberFeedback = document.getElementById('invoice-number-feedback');
            const grNumberInput = document.getElementById('gr_number');
            const grNumberFeedback = document.getElementById('gr-number-feedback');
            let invoiceNumberTimeout;

            function checkInvoiceNumberUniqueness(invoiceNumber) {
                if (!invoiceNumber) {
                    invoiceNumberFeedback.innerHTML = '';
                    invoiceNumberInput.classList.remove('is-valid', 'is-invalid');
                    return;
                }

                // Show loading state
                invoiceNumberFeedback.innerHTML = '<small class="text-info">🔍 Checking availability...</small>';
                invoiceNumberInput.classList.remove('is-valid', 'is-invalid');

                fetch('/api/check-invoice-number-uniqueness', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute(
                                'content')
                        },
                        body: JSON.stringify({
                            invoice_number: invoiceNumber
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.valid) {
                            invoiceNumberFeedback.innerHTML = '<small class="text-success">✅ ' + data.message +
                                '</small>';
                            invoiceNumberInput.classList.remove('is-invalid');
                            invoiceNumberInput.classList.add('is-valid');
                            invoiceNumberInput.setCustomValidity('');
                        } else {
                            invoiceNumberFeedback.innerHTML = '<small class="text-danger">❌ ' + data.message +
                                '</small>';
                            invoiceNumberInput.classList.remove('is-valid');
                            invoiceNumberInput.classList.add('is-invalid');
                            invoiceNumberInput.setCustomValidity(data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Error checking Invoice Number uniqueness:', error);
                        invoiceNumberFeedback.innerHTML =
                            '<small class="text-warning">⚠️ Unable to check availability</small>';
                        invoiceNumberInput.classList.remove('is-valid', 'is-invalid');
                    });
            }

            // Add debounced input listener for Invoice Number
            invoiceNumberInput.addEventListener('input', function() {
                const invoiceNumber = this.value.trim();

                // Clear previous timeout
                clearTimeout(invoiceNumberTimeout);

                // Set new timeout for debounced checking
                invoiceNumberTimeout = setTimeout(() => {
                    checkInvoiceNumberUniqueness(invoiceNumber);
                }, 500); // 500ms delay
            });

            // Check on blur as well
            invoiceNumberInput.addEventListener('blur', function() {
                const invoiceNumber = this.value.trim();
                if (invoiceNumber) {
                    clearTimeout(invoiceNumberTimeout);
                    checkInvoiceNumberUniqueness(invoiceNumber);
                }
            });

            // GR Number uniqueness validation
        // Add this function for GR number validation
function checkGRNumberUniqueness(grNumber) {
    if (!grNumber) {
        grNumberFeedback.innerHTML = '';
        grNumberInput.classList.remove('is-valid', 'is-invalid');
        return;
    }

    // Show loading state
    grNumberFeedback.innerHTML = '<small class="text-info">🔍 Checking availability...</small>';
    grNumberInput.classList.remove('is-valid', 'is-invalid');

    fetch('/api/check-gr-number-uniqueness', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({
                gr_number: grNumber
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.available) {
                grNumberFeedback.innerHTML = '<small class="text-success">✅ ' + data.message + '</small>';
                grNumberInput.classList.remove('is-invalid');
                grNumberInput.classList.add('is-valid');
                grNumberInput.setCustomValidity('');
            } else {
                grNumberFeedback.innerHTML = '<small class="text-danger">❌ ' + data.message + '</small>';
                grNumberInput.classList.remove('is-valid');
                grNumberInput.classList.add('is-invalid');
                grNumberInput.setCustomValidity(data.message);
            }
        })
        .catch(error => {
            console.error('Error checking GR Number uniqueness:', error);
            grNumberFeedback.innerHTML = '<small class="text-warning">⚠️ Unable to check availability</small>';
            grNumberInput.classList.remove('is-valid', 'is-invalid');
        });
}

// Add debounced input listener for GR Number
let grNumberTimeout;
grNumberInput.addEventListener('input', function() {
    const grNumber = this.value.trim();
    clearTimeout(grNumberTimeout);
    grNumberTimeout = setTimeout(() => {
        checkGRNumberUniqueness(grNumber);
    }, 500);
});

// Check on blur as well
grNumberInput.addEventListener('blur', function() {
    const grNumber = this.value.trim();
    if (grNumber) {
        clearTimeout(grNumberTimeout);
        checkGRNumberUniqueness(grNumber);
    }
});

            // No. of Units validation
            const noOfUnitsInput = document.getElementById('no_of_units');

            function validateNoOfUnits() {
                const value = parseFloat(noOfUnitsInput.value);

                if (noOfUnitsInput.value && (isNaN(value))) {
                    noOfUnitsInput.setCustomValidity('Please enter a valid number of units between 1 and 100,000.');
                    noOfUnitsInput.classList.add('is-invalid');
                    noOfUnitsInput.classList.remove('is-valid');
                } else {
                    noOfUnitsInput.setCustomValidity('');
                    noOfUnitsInput.classList.remove('is-invalid');
                    if (noOfUnitsInput.value) {
                        noOfUnitsInput.classList.add('is-valid');
                    } else {
                        noOfUnitsInput.classList.remove('is-valid');
                    }
                }
            }

            // Add event listeners for No. of Units validation
            noOfUnitsInput.addEventListener('input', validateNoOfUnits);
            noOfUnitsInput.addEventListener('blur', validateNoOfUnits);

            // Freight Rate validation (using existing frRateInput variable)
            function validateFreightRate() {
                const value = parseFloat(frRateInput.value);

                if (frRateInput.value && (isNaN(value) || value < 1 || value > 20000)) {
                    frRateInput.setCustomValidity('Please enter a valid freight rate between 1 and 20,000.');
                    frRateInput.classList.add('is-invalid');
                    frRateInput.classList.remove('is-valid');
                } else if (frRateInput.value) {
                    frRateInput.setCustomValidity('');
                    frRateInput.classList.remove('is-invalid');
                    frRateInput.classList.add('is-valid');
                } else {
                    frRateInput.setCustomValidity('');
                    frRateInput.classList.remove('is-invalid', 'is-valid');
                }
            }

            // Add event listeners for Freight Rate validation
            frRateInput.addEventListener('input', validateFreightRate);
            frRateInput.addEventListener('blur', validateFreightRate);

            // Function to format date to DD-MM-YYYY
            function formatDate(dateString) {
                if (!dateString) return 'Not selected';
                const date = new Date(dateString);
                if (isNaN(date.getTime())) return dateString; // Return original if invalid date

                const day = String(date.getDate()).padStart(2, '0');
                const month = String(date.getMonth() + 1).padStart(2, '0');
                const year = date.getFullYear();
                return `${day}-${month}-${year}`;
            }

            // Function to get current date and time in DD-MM-YYYY HH:MM format
            function getCurrentDateTime() {
                const now = new Date();
                const day = String(now.getDate()).padStart(2, '0');
                const month = String(now.getMonth() + 1).padStart(2, '0');
                const year = now.getFullYear();
                const hours = String(now.getHours()).padStart(2, '0');
                const minutes = String(now.getMinutes()).padStart(2, '0');
                return `${day}-${month}-${year} ${hours}:${minutes}`;
            }

            // Function to show confirmation dialog with form details
            function showConfirmationDialog(form) {
                const formData = new FormData(form);

                // Get display values for dropdowns
                const customerName = document.querySelector('#customer_id option:checked')?.textContent ||
                    'Not selected';
                const consignorName = document.querySelector('#consignor_id option:checked')?.textContent ||
                    'Not selected';
                const consigneeName = document.querySelector('#consignee_id option:checked')?.textContent ||
                    'Not selected';
                const truckNumber = document.querySelector('#truck_id option:checked')?.textContent ||
                    'Not selected';
                const transporterName = document.querySelector('#transporter_id option:checked')?.textContent ||
                    'Not selected';
                const referenceBy = document.querySelector('#reference_by option:checked')?.textContent?.trim() ||
                    '—';

                // Format dates
                const formattedGrDate = formatDate(formData.get('gr_date'));
                const currentDateTime = getCurrentDateTime();

                // Build confirmation message
                const confirmationHTML = `
            <div class="confirmation-details text-start">
                <div class="alert alert-soft-primary bg-primary bg-opacity-10 border-0 mb-3 py-2 px-3 d-flex align-items-center rounded-3">
                    <i class="fas fa-info-circle text-primary me-2"></i>
                    <h6 class="mb-0 text-primary fw-bold" style="font-size:0.9rem;">Review Lorry Receipt Details</h6>
                </div>

                <div class="row g-2">
                    <div class="col-md-6">
                        <div class="card h-100 border-0 shadow-sm rounded-3 border-top border-3 border-primary">
                            <div class="card-header bg-transparent border-bottom-0 pt-2 pb-0">
                                <h6 class="fw-bold text-dark mb-0" style="font-size:0.9rem;"><i class="fas fa-file-invoice text-primary me-1"></i> Basic Info</h6>
                            </div>
                            <div class="card-body p-2">
                                <div class="d-flex justify-content-between mb-1 pb-1 border-bottom border-light">
                                    <span class="text-muted small">LR No.</span>
                                    <span class="badge bg-primary bg-opacity-10 text-primary rounded px-2 py-0">${formData.get('lr_number') || 'Not entered'}</span>
                                </div>
                                <div class="d-flex justify-content-between mb-1 pb-1 border-bottom border-light">
                                    <span class="text-muted small">GR Date</span>
                                    <span class="fw-bold text-dark small">${formattedGrDate}</span>
                                </div>
                                <div class="d-flex justify-content-between mb-1 pb-1 border-bottom border-light">
                                    <span class="text-muted small">Created</span>
                                    <span class="fw-medium text-dark small">${currentDateTime}</span>
                                </div>
                                <div class="d-flex justify-content-between mb-1 pb-1 border-bottom border-light">
                                    <span class="text-muted small">Customer</span>
                                    <span class="fw-bold text-dark text-truncate ms-2 text-end small" style="max-width: 180px;" title="${customerName}">${customerName}</span>
                                </div>
                                <div class="d-flex justify-content-between mb-1 pb-1 border-bottom border-light">
                                    <span class="text-muted small">Invoice</span>
                                    <span class="fw-medium text-dark small">${formData.get('invoice_number') || '<span class="text-muted fst-italic">N/A</span>'}</span>
                                </div>
                                <div class="d-flex justify-content-between mb-1 pb-1 border-bottom border-light">
                                    <span class="text-muted small">Reference By</span>
                                    <span class="fw-medium text-dark text-truncate ms-2 text-end small" style="max-width: 180px;" title="${referenceBy}">${referenceBy}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="row g-2">
                            <div class="col-12">
                                <div class="card border-0 shadow-sm rounded-3 border-top border-3 border-info">
                                    <div class="card-header bg-transparent border-bottom-0 pt-2 pb-0">
                                        <h6 class="fw-bold text-dark mb-0" style="font-size:0.9rem;"><i class="fas fa-users text-info me-1"></i> Parties & Route</h6>
                                    </div>
                                    <div class="card-body p-2">
                                        <div class="d-flex justify-content-between mb-1 pb-1 border-bottom border-light">
                                            <span class="text-muted small">Consignor</span>
                                            <span class="fw-medium text-dark text-truncate ms-2 text-end small" style="max-width: 180px;" title="${consignorName}">${consignorName}</span>
                                        </div>
                                        <div class="d-flex justify-content-between mb-1 pb-1 border-bottom border-light">
                                            <span class="text-muted small">Consignee</span>
                                            <span class="fw-medium text-dark text-truncate ms-2 text-end small" style="max-width: 180px;" title="${consigneeName}">${consigneeName}</span>
                                        </div>
                                        <div class="d-flex justify-content-between mt-1 text-center bg-light rounded p-1 align-items-center">
                                            <div class="w-100 small fw-bold text-dark text-truncate px-1">${formData.get('source_city') || '-'}</div>
                                            <i class="fas fa-arrow-right text-muted mx-1" style="font-size:0.7rem;"></i>
                                            <div class="w-100 small fw-bold text-dark text-truncate px-1">${formData.get('destination_city') || '-'}</div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-12">
                                <div class="card border-0 shadow-sm rounded-3 border-top border-3 border-warning">
                                    <div class="card-header bg-transparent border-bottom-0 pt-2 pb-0">
                                        <h6 class="fw-bold text-dark mb-0" style="font-size:0.9rem;"><i class="fas fa-truck text-warning me-1"></i> Transport Info</h6>
                                    </div>
                                    <div class="card-body p-2">
                                        <div class="d-flex justify-content-between mb-1 pb-1 border-bottom border-light">
                                            <span class="text-muted small">Truck</span>
                                            <span class="badge bg-dark bg-opacity-10 text-dark fw-bold rounded px-2 py-0">${truckNumber}</span>
                                        </div>
                                        <div class="d-flex justify-content-between">
                                            <span class="text-muted small">Transporter</span>
                                            <span class="fw-medium text-dark text-truncate ms-2 text-end small" style="max-width: 180px;" title="${transporterName}">${transporterName}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12">
                        <div class="card border-0 shadow-sm rounded-3 bg-light border-top border-3 border-success mb-2">
                            <div class="card-body p-2">
                                <div class="row align-items-center g-2">
                                    <div class="col-md-7">
                                        <div class="d-flex align-items-center mb-1">
                                            <div class="bg-success bg-opacity-10 rounded p-1 me-2">
                                                <i class="fas fa-box-open text-success" style="font-size:0.9rem;"></i>
                                            </div>
                                            <span class="fw-bold text-dark" style="font-size:0.9rem;">${formData.get('good_type') || 'Not selected'}</span>
                                        </div>
                                        <div class="d-flex gap-2">
                                            <span class="badge bg-white border border-secondary border-opacity-25 text-dark fw-medium" style="font-size:0.75rem;">
                                                Wt: ${formData.get('weight') || '-'} t
                                            </span>
                                            <span class="badge bg-white border border-secondary border-opacity-25 text-dark fw-medium" style="font-size:0.75rem;">
                                                Qty: ${formData.get('no_of_units') || '-'}
                                            </span>
                                            <span class="badge bg-white border border-secondary border-opacity-25 text-dark fw-medium" style="font-size:0.75rem;">
                                                Rate: ${formData.get('fr_rate') || '-'}
                                            </span>
                                        </div>
                                    </div>
                                    <div class="col-md-5 d-flex justify-content-md-end align-items-center">
                                        <div class="bg-success bg-opacity-10 px-3 py-1 rounded-3 border border-success border-opacity-25 text-end w-100">
                                            <span class="text-success fw-bold text-uppercase d-block mb-0" style="font-size: 0.7rem;">Total Amount</span>
                                            <h5 class="mb-0 text-success fw-bold">${formData.get('amount') || '0.00'}</h5>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;

                // Show confirmation dialog using SweetAlert2 or Bootstrap modal
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        title: '📋 Confirm Good Receipt (Bilty) Details',
                        html: confirmationHTML,
                        icon: 'question',
                        showCancelButton: true,
                        showDenyButton: true,
                        confirmButtonColor: '#2FB36F',
                        cancelButtonColor: '#6c757d',
                        denyButtonColor: '#2FB36F',
                        denyButtonText: '💾 Save & Create FR',
                        confirmButtonText: '✅ Yes, Submit',
                        cancelButtonText: '❌ Cancel',
                        width: '90%',
                        customClass: {
                            popup: 'swal-wide'
                        }
                    }).then((result) => {
                        if (result.isConfirmed) {
                            // Actually submit the form
                            form.submit();
                        } else if (result.isDenied) {
                            // Save & Create FR
                            saveAndRedirect();
                        }
                    });
                } else {
                    // Fallback to native confirm dialog
                    const confirmText = `Please confirm the following details:\n\n` +
                        `LR Number: ${formData.get('lr_number')}\n` +
                        `Customer: ${customerName}\n` +
                        `Consignor: ${consignorName}\n` +
                        `Consignee: ${consigneeName}\n` +
                        `Weight: ${formData.get('weight')} tons\n` +
                        `Amount: ${formData.get('amount')}\n\n` +
                        `Do you want to submit this good receipt (bilty)?`;

                    if (confirm(confirmText)) {
                        form.submit();
                    }
                }
            }

            function saveAndRedirect() {
                // Get form
                let form = $('#lorryReceiptForm');

                // Append hidden input to tell backend to redirect to FR
                if ($('#redirect_to_fr').length === 0) {
                    form.append('<input type="hidden" name="redirect_to_fr" id="redirect_to_fr" value="1">');

                }

                form.submit();
            }
        });
        // Handle Add Goods Type Form Submission
        document.getElementById('addGoodsTypeForm').addEventListener('submit', function(e) {
            e.preventDefault();

            let form = this;
            let submitBtn = form.querySelector('button[type="submit"]');
            let spinner = submitBtn.querySelector('.spinner-border');
            let errorBox = document.getElementById('goodsTypeFormErrors');

            // Reset error
            errorBox.classList.add('d-none');
            errorBox.innerHTML = '';

            // Show spinner
            spinner.classList.remove('d-none');
            submitBtn.disabled = true;

            fetch("{{ route('master.goods-types.store') }}", {
                    method: "POST",
                    headers: {
                        "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').content,
                        "Accept": "application/json",
                        "Content-Type": "application/json"
                    },
                    body: JSON.stringify({
                        name: document.getElementById('goods_type_name').value,
                        from_modal: true
                    })
                })
                .then(response => response.json())
                .then(data => {
                    spinner.classList.add('d-none');
                    submitBtn.disabled = false;

                    if (data.success) {
                        // Add new option to Goods Type dropdown
                        let select = document.getElementById('good_type');
                        let newOption = new Option(data.goodsType.name, data.goodsType.name, true, true);
                        select.add(newOption);
                        select.value = data.goodsType.name;

                        // Close modal properly using Bootstrap's API
                        const modalElement = document.getElementById('addGoodsTypeModal');
                        const modal = bootstrap.Modal.getInstance(modalElement);

                        if (modal) {
                            modal.hide();

                            // Properly clean up modal after it's hidden
                            modalElement.addEventListener('hidden.bs.modal', function(event) {
                                // Remove all modal backdrops
                                document.querySelectorAll('.modal-backdrop').forEach(backdrop => {
                                    backdrop.remove();
                                });

                                // Fix body classes and styles
                                document.body.classList.remove('modal-open');
                                document.body.style.overflow = '';
                                document.body.style.paddingRight = '';
                            }, {
                                once: true
                            }); // Only listen once
                        } else {
                            // Fallback for older Bootstrap versions
                            $('#addGoodsTypeModal').modal('hide');

                            setTimeout(() => {
                                // Clean up modal backdrop and body classes
                                $('.modal-backdrop').remove();
                                $('body').removeClass('modal-open').css({
                                    'overflow': '',
                                    'padding-right': ''
                                });
                            }, 500);
                        }

                        // Reset form
                        form.reset();
                    } else {
                        errorBox.classList.remove('d-none');
                        errorBox.innerHTML = 'Failed to add goods type';
                    }
                })
                .catch(error => {
                    spinner.classList.add('d-none');
                    submitBtn.disabled = false;
                    errorBox.classList.remove('d-none');
                    errorBox.innerHTML = '⚠️ Error: ' + error.message;
                });
        });
    </script>
@endsection
