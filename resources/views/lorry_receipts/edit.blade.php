@extends('layouts.app')

@section('content')
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
    <!-- SweetAlert2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet" />
    @if (session('warning'))
        <script>
            Swal.fire({
                icon: 'warning',
                title: 'Warning',
                text: "{{ session('warning') }}",
                confirmButtonColor: '#f39c12'
            });
        </script>
    @endif
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

        /* Ensure consistent column widths */
        .row .col-md-4 {
            flex: 0 0 33.333333%;
            max-width: 33.333333%;
        }

        /* Prevent card from expanding */
        .card {
            overflow: hidden;
        }

        /* Consistent spacing for form groups */
        .mb-3 {
            margin-bottom: 1rem !important;
        }

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
                <div class="card-title h4">📝 Edit Good Receipt (Bilty)</div>
                <div class="ms-auto">
                    <a href="{{ route('consignees.create') }}" class="btn btn-brand-primary">
                        <i class="bi bi-plus"></i> Create Consignee/Consignor/Customer</a>
                </div>
            </div>
        </div>
        <!--begin::Form-->
        <form method="POST" action="{{ route('lorry_receipts.update', $lorry_receipt->id) }}{{ \App\Support\ListingQueryString::currentSuffix(\App\Support\ListingQueryString::LORRY_RECEIPT_KEYS) }}" id="lorryReceiptForm"
            class="needs-validation" novalidate="">
            @csrf
            @method('PUT')
            <input type="hidden" name="company_id" value="{{ auth()->user()->company_id }}">
            @foreach (\App\Support\ListingQueryString::params(request(), \App\Support\ListingQueryString::LORRY_RECEIPT_KEYS) as $listingKey => $listingValue)
                <input type="hidden" name="{{ $listingKey }}" value="{{ $listingValue }}">
            @endforeach

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
                                value="{{ old('lr_number', $lorry_receipt->lr_number) }}" required
                                placeholder="Enter unique LR Number">
                            @error('lr_number')
                                <div class="text-danger">{{ $message }}</div>
                            @enderror
                            <div class="invalid-feedback">Please enter a unique LR number.</div>
                            <div id="lr-number-feedback" class="mt-1"></div>
                        </div>
                        <div class="col-md-4">
                            <label for="gr_date" class="form-label">GR Date *</label>
                            <input type="date" class="form-control" id="gr_date" name="gr_date" required
                                value="{{ old('gr_date', $lorry_receipt->gr_date) }}">
                            @error('gr_date')
                                <div class="text-danger">{{ $message }}</div>
                            @enderror
                            <div class="invalid-feedback">Please select a GR date.</div>
                        </div>
                        <div class="col-md-4">
                            <label for="customer_id" class="form-label">Customer *</label>
                            @php($selectedCustomer = $customers->firstWhere('id', old('customer_id', $lorry_receipt->customer_id)))
                            <select class="form-select" id="customer_id" name="customer_id" required>
                                <option value="">Select Customer</option>
                                @if ($selectedCustomer)
                                    <option value="{{ $selectedCustomer->id }}" selected>
                                        {{ $selectedCustomer->name }}
                                    </option>
                                @endif
                            </select>
                            <script>
                                window.selectedCustomerId = @json(old('customer_id', $lorry_receipt->customer_id));
                                window.selectedCustomerName = @json($selectedCustomer?->name ?? '');
                            </script>
                            @error('customer_id')
                                <div class="text-danger">{{ $message }}</div>
                            @enderror
                            <div class="invalid-feedback">Please select a customer.</div>
                        </div>
                    </div>
                    @if ($lorry_receipt->delivery_status == 'Delivered')
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="memo_id" class="form-label">Memo ID</label>
                                <input type="text" class="form-control @error('memo_id') is-invalid @enderror"
                                    id="memo_id" name="memo_id" value="{{ old('memo_id', $lorry_receipt->memo_id) }}">

                                {{-- Backend validation error --}}
                                @error('memo_id')
                                    <small class="text-danger">{{ $message }}</small>
                                @enderror

                                {{-- AJAX validation error/success --}}
                                <small id="memo_id_feedback"></small>
                            </div>
                        </div>
                    @endif
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="truck_id" class="form-label">Truck *</label>
                            <select class="form-select" id="truck_id" name="truck_id" required
                                data-placeholder="Search and select truck...">
                                <option value="">Select Trucks</option>
                                @php($selectedTruck = $trucks->firstWhere('id', old('truck_id', $lorry_receipt->truck_id)))
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
                            <label for="transporter_type" class="form-label">Select Type *</label>
                            <select class="form-select" id="transporter_type" name="transporter_type" required>
                                <option value="">Select Type</option>
                                <option value="owner" {{ old('transporter_type', $lorry_receipt->transporter_type) === 'owner' ? 'selected' : '' }}>
                                    Truck Owner</option>
                                <option value="agency"
                                    {{ old('transporter_type', $lorry_receipt->transporter_type) === 'agency' ? 'selected' : '' }}>Agency</option>
                            </select>
                            <div class="invalid-feedback">Please select transporter type.</div>
                        </div>
                        <div class="col-md-4 min-w-0">
                            <label for="transporter_id" id="transporterLabel" class="form-label">Transporter *</label>
                            <div class="lr-payee-select-wrap">
                                <select class="form-select" id="transporter_id" name="transporter_id" required onchange="window.LREntity.showTdsPercentage(this.value)" {{ (!isset($canChangeTransporter) || !$canChangeTransporter) ? 'disabled' : '' }}>
                                    <option value="">Select Transporter</option>
                                    @php($selectedTransporter = $transporters->firstWhere('id', old('transporter_id', $lorry_receipt->transporter_id)))
                                    @if($selectedTransporter)
                                        <option value="{{ $selectedTransporter->id }}" selected>
                                            {{ $selectedTransporter->name }}
                                        </option>
                                    @endif
                                </select>
                            </div>

                            @if (!isset($canChangeTransporter) || !$canChangeTransporter)
                                <input type="hidden" name="transporter_id" value="{{ old('transporter_id', $lorry_receipt->transporter_id) }}">
                                <small class="text-danger d-block mt-1">⚠️ Transporter cannot be changed because TDS has already been submitted.</small>
                            @endif
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
                            <label for="reference_by" class="form-label">Reference By (Agency)</label>
                            <div class="lr-payee-select-wrap">
                                <select class="form-select" id="reference_by" name="reference_by">
                                    <option value="">Select Reference</option>
                                    @php($selectedAgency = $agencies->firstWhere('id', old('reference_by', $lorry_receipt->reference_by)))
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
                            <div class="invalid-feedback">Please select a reference if applicable.</div>
                        </div>

                        <div class="col-md-4">
                            <label for="invoice_number" class="form-label">Invoice Number <small
                                    class="text-muted">(Optional)</small></label>
                            <input type="text" class="form-control" id="invoice_number" name="invoice_number"
                                value="{{ old('invoice_number', $lorry_receipt->invoice_number) }}"
                                placeholder="Enter Invoice Number">
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
                                value="{{ old('gr_number', $lorry_receipt->gr_number) }}"
                                placeholder="Enter GR Number">
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
                                @php($selectedConsignor = $consignors->firstWhere('id', old('consignor_id', $lorry_receipt->consignor_id)))
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
                                name="consignor_contact_number"
                                value="{{ old('consignor_contact_number', $selectedConsignor->contact_number ?? $lorry_receipt->consignor->contact_number ?? '') }}" readonly>
                        </div>

                    </div>
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="consignee_id" class="form-label">Consignee *</label>
                            <select class="form-select" id="consignee_id" name="consignee_id" required
                                data-placeholder="Search and select consignee...">
                                <option value="">Select Consignee</option>
                                @php($selectedConsignee = $consignees->firstWhere('id', old('consignee_id', $lorry_receipt->consignee_id)))
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
                                name="consignee_contact_number"
                                value="{{ old('consignee_contact_number', $selectedConsignee->contact_number ?? $lorry_receipt->consignee->contact_number ?? '') }}" readonly>
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
                                value="{{ old('source_city', $lorry_receipt->source_city) }}" readonly>
                        </div>
                        <div class="col-md-6">
                            <label for="source_state" class="form-label">Source State *</label>
                            <input type="text" class="form-control" id="source_state" name="source_state" required
                                value="{{ old('source_state', $lorry_receipt->source_state) }}" readonly>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="destination_city" class="form-label">Destination City *</label>
                            <input type="text" class="form-control" id="destination_city" name="destination_city"
                                required value="{{ old('destination_city', $lorry_receipt->destination_city) }}" readonly>
                        </div>
                        <div class="col-md-4">
                            <label for="destination_state" class="form-label">Destination State *</label>
                            <input type="text" class="form-control" id="destination_state" name="destination_state"
                                required value="{{ old('destination_state', $lorry_receipt->destination_state) }}"
                                readonly>
                        </div>
                        <div class="col-md-4">
                            <label for="unloading_station" class="form-label">Unloading Station</label>
                            <input type="text" class="form-control" id="unloading_station" name="unloading_station"
                                 value="{{ old('unloading_station', $lorry_receipt->unloading_station) }}"
                                >
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
                        <div class="col-md-3">
                            <label for="good_type" class="form-label">Goods Type *</label>
                            <select class="form-select" id="good_type" name="good_type" required>
                                <option value="">Select Goods Type</option>
                                @foreach ($goodsTypes as $goodsType)
                                    <option value="{{ $goodsType->name }}"
                                        {{ $goodsType->name == $lorry_receipt->good_type ? 'selected' : '' }}>
                                        {{ $goodsType->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('good_type')
                                <div class="text-danger">{{ $message }}</div>
                            @enderror
                            <div class="invalid-feedback">Please select a goods type.</div>
                        </div>

                        <div class="col-md-3">
                            <label for="no_of_units" class="form-label">No. of Units <small
                                    class="text-muted">(Optional)</small></label>
                            <input type="number" class="form-control" id="no_of_units" name="no_of_units"
                                value="{{ old('no_of_units', $lorry_receipt->no_of_units) }}" min="1"
                                max="100000" title="Maximum 100,000 units allowed">
                            @error('no_of_units')
                                <div class="text-danger">{{ $message }}</div>
                            @enderror
                            <div class="invalid-feedback">Please enter a valid number of units (max 100,000).</div>
                            <small class="text-muted">📦 Max: 100,000 units</small>
                        </div>
                        <div class="col-md-3">
                            <label for="goods_value" class="form-label">Goods Value <small
                                    class="text-muted">(Optional)</small></label>
                            <input type="number" step="0.01" class="form-control" id="goods_value" name="goods_value"
                                value="{{ old('goods_value', $lorry_receipt->goods_value) }}" min="0" placeholder="">
                            @error('goods_value')
                                <div class="text-danger">{{ $message }}</div>
                            @enderror
                            <small class="text-muted">Value of goods ()</small>
                        </div>
                        <div class="col-md-3">
                            <label for="unloading_weight" class="form-label">Unloading Weight * <small class="text-muted">(in
                                    Tons)</small></label>
                            <input type="number" step="0.001" class="form-control" id="unloading_weight" name="unloading_weight"
                                required value="{{ old('unloading_weight', $lorry_receipt->unloading_weight) }}" min="0.1"
                                max="100" title="Unloading weight must be between 0.1 and 100 tons" disabled>
                            @error('unloading_weight')
                                <div class="text-danger">{{ $message }}</div>
                            @enderror
                            <div class="invalid-feedback">Please enter an unloading weight between 0.1 and 100 tons.</div>
                            <small class="text-muted">📏 Range: 0.1 - 100 tons</small>
                        </div>

                    </div>
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="weight" class="form-label">Weight * <small class="text-muted">(in
                                    Tons)</small></label>
                            <input type="number" step="0.001" class="form-control" id="weight" name="weight"
                                required value="{{ old('weight', $lorry_receipt->weight) }}" min="0.1"
                                max="100" title="Weight must be between 0.1 and 100 tons">
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
                                required value="{{ old('fr_rate', $lorry_receipt->fr_rate) }}" min="1"
                                max="20000" title="Freight rate must be between 1 and 20,000 per ton">
                            @error('fr_rate')
                                <div class="text-danger">{{ $message }}</div>
                            @enderror
                            <div class="invalid-feedback">Please enter a freight rate between 1 and 20,000.</div>
                            <small class="text-muted">💰 Range: 1 - 20,000 per ton</small>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label d-flex justify-content-between align-items-center">
                                <span>Use Fixed Amount</span>
                                <span class="form-check form-check-inline mb-0">
                                    <input class="form-check-input" type="checkbox" id="is_fixed_amount"
                                        name="is_fixed_amount" value="1"
                                        {{ old('is_fixed_amount', $lorry_receipt->is_fixed_amount ?? false) ? 'checked' : '' }}>
                                </span>
                            </label>
                            <input type="hidden" id="shortage_amount" name="shortage_amount"
                                value="{{ old('shortage_amount', $lorry_receipt->shortage_amount) }}">
                            <label for="shortage_amount" class="form-label">Shortage Amount</label>
                            <input type="number" step="0.01" class="form-control" id="shortage_amount"
                                value="{{ old('shortage_amount', $lorry_receipt->shortage_amount) }}" disabled>
                            <small class="text-muted">🔒 Auto-filled</small>
                        </div>

                        <div class="col-md-2">
                            <input type="hidden" id="damage_amount" name="damage_amount"
                                value="{{ old('damage_amount', $lorry_receipt->damage_amount) }}">
                            <label for="damage_amount" class="form-label">Damage Amount</label>
                            <input type="number" step="0.01" class="form-control" id="damage_amount"
                                value="{{ old('damage_amount', $lorry_receipt->damage_amount) }}" disabled>
                            <small class="text-muted">🔒 Auto-filled</small>
                        </div>
                        <div class="col-md-4">
                            <label for="actual_amount" class="form-label">Actual Amount * <small
                                    class="text-muted">(Weight × Freight Rate)</small></label>
                            <input type="number" step="0.01" class="form-control" id="actual_amount" name="actual_amount"
                                required value="{{ old('actual_amount', $lorry_receipt->actual_amount ?? $lorry_receipt->amount) }}"
                                title="Actual Amount: Weight × Freight Rate (editable)">
                            @error('actual_amount')
                                <div class="text-danger">{{ $message }}</div>
                            @enderror
                            <div class="invalid-feedback">Please enter an actual amount.</div>
                            <small class="text-muted">💡 Actual Amount = Weight × Freight Rate</small>
                            <input type="hidden" id="amount" name="amount" value="{{ old('amount', $lorry_receipt->amount) }}">
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
                        <div class="col-md-4">
                            <button class="btn btn-brand-primary w-100" type="submit">💾 Update Good Receipt (Bilty)</button>
                        </div>
                        <div class="col-md-4">
                            <a href="{{ route('lorry_receipts.index') }}{{ \App\Support\ListingQueryString::currentSuffix(\App\Support\ListingQueryString::LORRY_RECEIPT_KEYS) }}" class="btn btn-back w-100">📄 Back to List</a>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <script>
        // Bootstrap form validation
        (function() {
            'use strict';
            window.addEventListener('load', function() {
                // Fetch all the forms we want to apply custom Bootstrap validation styles to
                var forms = document.getElementsByClassName('needs-validation');

                // Loop over them and prevent submission
                Array.from(forms).forEach((form) => {
                    form.addEventListener('submit', function(event) {
                        if (form.checkValidity() === false) {
                            event.preventDefault();
                            event.stopPropagation();
                        }
                        form.classList.add('was-validated');
                    }, false);
                });
            }, false);
        })();
    </script>

    <!-- Load SweetAlert2 from CDN -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- Common LR entity confirmation helpers -->
    <script src="/js/lr-entity.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {

            // Auto-calculate actual_amount and amount based on weight × freight rate (when not fixed)
            const weightInput = document.getElementById('weight');
            const frRateInput = document.getElementById('fr_rate');
            const unloadingWeightInput = document.getElementById('unloading_weight');
            const actualAmountInput = document.getElementById('actual_amount');
            const amountInput = document.getElementById('amount');
            const shortageAmountInput = document.getElementById('shortage_amount');
            const damageAmountInput = document.getElementById('damage_amount');
            const isFixedAmountInput = document.getElementById('is_fixed_amount');

            function calculateAmount() {
                const weight = parseFloat(weightInput.value) || 0;
                const unloadingWeight = unloadingWeightInput ? (parseFloat(unloadingWeightInput.value) || weight) : weight;
                const frRate = parseFloat(frRateInput.value) || 0;
                const shortageAmount = parseFloat(shortageAmountInput.value) || 0;
                const damageAmount = parseFloat(damageAmountInput.value) || 0;
                const isFixed = isFixedAmountInput && isFixedAmountInput.checked;

                if (!isFixed && weight > 0 && frRate > 0) {
                    // When not fixed: recalculate actual_amount from weight / rate
                    const weightsEqual = Math.abs(weight - unloadingWeight) < 0.01;

                    if (!weightsEqual) {
                        const effectiveWeight = Math.min(weight, unloadingWeight);
                        const calculatedActualAmount = effectiveWeight * frRate;
                        actualAmountInput.value = calculatedActualAmount.toFixed(2);
                    } else {
                        const calculatedActualAmount = weight * frRate;
                        actualAmountInput.value = calculatedActualAmount.toFixed(2);
                    }
                }

                // Always calculate amount = actual_amount - shortage - damage (readonly, user cannot edit)
                const actualAmount = parseFloat(actualAmountInput.value) || 0;
                const calculatedAmount = actualAmount - shortageAmount - damageAmount;
                amountInput.value = Math.max(0, calculatedAmount).toFixed(2);
            }

            // Auto-calculate on weight, unloading_weight, freight rate, or fixed toggle change
            if (weightInput) {
                weightInput.addEventListener('input', function() {
                    calculateAmount();
                });
            }
            if (unloadingWeightInput) {
                unloadingWeightInput.addEventListener('input', function() {
                    calculateAmount();
                });
            }
            if (frRateInput) {
                frRateInput.addEventListener('input', function() {
                    calculateAmount();
                });
            }
            if (isFixedAmountInput) {
                isFixedAmountInput.addEventListener('change', function() {
                    calculateAmount();
                });
            }

            // Update amount when actual_amount, shortage, or damage changes
            // User can manually edit actual_amount, but if weight/rate changes, it will be recalculated
            if (actualAmountInput && amountInput) {
                actualAmountInput.addEventListener('input', function() {
                    const actualAmount = parseFloat(actualAmountInput.value) || 0;
                    const shortageAmount = parseFloat(shortageAmountInput.value) || 0;
                    const damageAmount = parseFloat(damageAmountInput.value) || 0;
                    const calculatedAmount = actualAmount - shortageAmount - damageAmount;
                    amountInput.value = Math.max(0, calculatedAmount).toFixed(2);
                });
            }
            if (shortageAmountInput) {
                shortageAmountInput.addEventListener('input', calculateAmount);
            }
            if (damageAmountInput) {
                damageAmountInput.addEventListener('input', calculateAmount);
            }

            // Initialize calculation on page load
            calculateAmount();

            // Bootstrap form validation
            const forms = document.querySelectorAll('.needs-validation');
            Array.from(forms).forEach(form => {
                form.addEventListener('submit', event => {
                    if (!form.checkValidity()) {
                        event.preventDefault();
                        event.stopPropagation();
                    }
                    form.classList.add('was-validated');
                }, false);
            });
        });
    </script>

    <script>
        // Handle consignor and consignee contact number and location details
        document.addEventListener('DOMContentLoaded', function() {
            const consignorSelect = document.getElementById('consignor_id');
            const consignorContact = document.getElementById('consignor_contact_number');
            const consignorLocation = document.getElementById('consignor_location');
            const consigneeSelect = document.getElementById('consignee_id');
            const consigneeContact = document.getElementById('consignee_contact_number');
            const consigneeLocation = document.getElementById('consignee_location');

            function updateConsignorDetails() {
                const selectedConsignor = consignorSelect.value;
                if (!selectedConsignor) {
                    consignorContact.value = '';
                    consignorLocation.value = '';
                    return;
                }

                fetch(`/api/consignors/${selectedConsignor}`, {
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'Content-Type': 'application/json'
                        }
                    })
                    .then(response => response.json())
                    .then(consignor => {
                        consignorContact.value = consignor.contact_number;
                        consignorLocation.value = `${consignor.city}, ${consignor.state}`;
                    })
                    .catch(error => console.error('Error:', error));
            }

            function updateConsigneeDetails() {
                const selectedConsignee = consigneeSelect.value;
                if (!selectedConsignee) {
                    consigneeContact.value = '';
                    consigneeLocation.value = '';
                    return;
                }

                fetch(`/api/consignees/${selectedConsignee}`, {
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'Content-Type': 'application/json'
                        }
                    })
                    .then(response => response.json())
                    .then(consignee => {
                        consigneeContact.value = consignee.contact_number;
                        consigneeLocation.value = `${consignee.city}, ${consignee.state}`;
                    })
                    .catch(error => console.error('Error:', error));
            }

            if (consignorSelect) {
                consignorSelect.addEventListener('change', updateConsignorDetails);
            }
            if (consigneeSelect) {
                consigneeSelect.addEventListener('change', updateConsigneeDetails);
            }
        });
    </script>

    <script>
        // Handle truck selection and transporter details
        const truckSelectEl = document.getElementById('truck_id');
        if (truckSelectEl) {
            truckSelectEl.addEventListener('change', function() {
                let truckId = this.value;

                if (truckId) {
                    fetch(`/get-transporter-name/${truckId}`)
                        .then(response => response.json())
                        .then(data => {
                            if (data.transporter_name) {
                                document.getElementById('transporter_name').value = data.transporter_name;
                                if (data.transporter.is_owner == 1) {
                                    document.getElementById('ownerTransporterSelect').style.display = 'block';
                                }
                            } else {
                                document.getElementById('ownerTransporterSelect').style.display = 'none';
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            document.getElementById('ownerTransporterSelect').style.display = 'none';
                        });
                } else {
                    document.getElementById('ownerTransporterSelect').style.display = 'none';
                }
            });
        }
    </script>

@endsection

<!-- SweetAlert2 JS -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.js"></script>

<!-- Ensure proper script loading -->
<script>
    // Wait for DOM and jQuery to be ready
    document.addEventListener('DOMContentLoaded', function() {
        let jqueryLoadStarted = false;

        // Wait for jQuery to be available
        function waitForJQuery() {
            if (typeof $ !== 'undefined' && typeof $.fn !== 'undefined') {
                checkSelect2();
            } else {
                if (!jqueryLoadStarted) {
                    jqueryLoadStarted = true;
                    var jqueryScript = document.createElement('script');
                    jqueryScript.src = 'https://code.jquery.com/jquery-3.7.1.min.js';
                    jqueryScript.defer = true;
                    document.head.appendChild(jqueryScript);
                }
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

            // Initialize other dropdowns with Select2 (AJAX driven)
            const ajaxConsigneesConfig = {
                url: '/api/consignees/search',
                dataType: 'json',
                delay: 250,
                data: function (params) {
                    return { q: params.term || '', page: params.page || 1 };
                },
                processResults: function (data) {
                    return {
                        results: data.results || [],
                        pagination: { more: data.pagination?.more || false }
                    };
                },
                cache: true
            };

            const ajaxTrucksConfig = {
                url: '/api/trucks/search',
                dataType: 'json',
                delay: 250,
                data: function (params) {
                    return { q: params.term || '', page: params.page || 1 };
                },
                processResults: function (data) {
                    return {
                        results: data.results || [],
                        pagination: { more: data.pagination?.more || false }
                    };
                },
                cache: true
            };

            const ajaxAgenciesConfig = {
                url: '/api/agencies/search',
                dataType: 'json',
                delay: 250,
                data: function (params) {
                    return { q: params.term || '', page: params.page || 1 };
                },
                processResults: function (data) {
                    return {
                        results: data.results || [],
                        pagination: { more: data.pagination?.more || false }
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

            const allowPayeeAjax = @json(!isset($canChangeTransporter) || $canChangeTransporter);

            function rebindLrEditTransporterSelect2(paymentType) {
                const $t = $('#transporter_id');
                if (!$t.length || typeof $t.select2 !== 'function') {
                    return;
                }
                if (!allowPayeeAjax || $t.prop('disabled')) {
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

            window.lrEditRebindTransporterSelect2 = rebindLrEditTransporterSelect2;

            $('#truck_id').select2({
                theme: 'bootstrap-5',
                width: '100%',
                minimumInputLength: 1,
                placeholder: 'Search and select truck...',
                ajax: ajaxTrucksConfig
            });

            const trucks = @json($trucks->keyBy('id'));
            const dbTransporterType = @json(old('transporter_type', $lorry_receipt->transporter_type) ?? '');

            if (dbTransporterType) {
                $('#transporter_type').val(dbTransporterType);
            } else {
                const currentTruckIdForType = $('#truck_id').val();
                if (currentTruckIdForType && trucks[currentTruckIdForType]) {
                    const transporterTypeFromTruck = trucks[currentTruckIdForType].transporter_type;
                    if (transporterTypeFromTruck) {
                        $('#transporter_type').val(transporterTypeFromTruck);
                    }
                }
            }

            const transporterLabelElInit = document.getElementById('transporterLabel');
            const typeValForLabel = $('#transporter_type').val();
            if (transporterLabelElInit && typeValForLabel) {
                transporterLabelElInit.textContent = typeValForLabel === 'owner' ? 'Truck Owner *' : 'Agency *';
            }

            if (allowPayeeAjax) {
                const initialPayeeMode = document.getElementById('transporter_type')?.value === 'owner' ? 'owner' : 'agency';
                rebindLrEditTransporterSelect2(initialPayeeMode);
            }

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

            $('#good_type').select2({
                theme: 'bootstrap-5',
                width: '100%',
                minimumResultsForSearch: 0
            });

            $('#reference_by').select2({
                theme: 'bootstrap-5',
                width: '100%',
                allowClear: true,
                minimumInputLength: 0,
                placeholder: 'Search reference agency...',
                ajax: ajaxAgenciesConfig
            });
            bindTransporterSelect2Title($('#reference_by'));

            $(document).off('select2:open.autofocusLrEdit').on('select2:open.autofocusLrEdit', function () {
                setTimeout(function () {
                    const searchInput = document.querySelector('.select2-container--open .select2-search__field');
                    if (searchInput) {
                        searchInput.focus();
                    }
                }, 0);
            });

            // Initialize customer dropdown with Select2
            $('#customer_id').select2({
                theme: 'bootstrap-5',
                width: '100%',
                minimumInputLength: 1,
                placeholder: 'Search and select customer...',
                ajax: ajaxConsigneesConfig
            }).on('select2:open', function() {
                // Log when dropdown is opened
            });

            // Create a function to ensure the customer is selected
            function ensureCustomerSelected() {
                if (window.selectedCustomerId) {
                    // Make sure the option exists
                    const optionExists = $('#customer_id option[value="' + window.selectedCustomerId + '"]')
                        .length > 0;

                    if (!optionExists) {
                        // Create the option if it doesn't exist
                        const newOption = new Option(window.selectedCustomerName, window.selectedCustomerId,
                            true, true);
                        $('#customer_id').append(newOption);
                    }

                    // Set the value and trigger change
                    $('#customer_id').val(window.selectedCustomerId).trigger('change');

                    // Verify the selection
                    const currentVal = $('#customer_id').val();

                    if (currentVal == window.selectedCustomerId) {
                    } else {
                    }
                }
            }

            // Call immediately
            ensureCustomerSelected();

            // And also after a short delay to ensure Select2 is fully initialized
            setTimeout(ensureCustomerSelected, 300);

            // Ensure customer dropdown is properly initialized with the correct value
            const customerSelect = $('#customer_id');
            if (customerSelect.length) {
                const customerId = @json(old('customer_id', $lorry_receipt->customer_id));
                if (customerId) {
                    customerSelect.val(customerId).trigger('change');
                }
            }

            // Store consignor and consignee data for contact number population
            const consignors = @json($consignors->keyBy('id'));
            const consignees = @json($consignees->keyBy('id'));
            const transporters = @json($transporters->keyBy('id'));

            // Populate contact numbers and details on page load
            updateConsignorContact();
            updateConsigneeContact();
            updateTruckDetails();
            updateTransporterDetails();

            // Trigger Select2 to refresh and show selected values
            setTimeout(function() {
                $('#customer_id, #truck_id, #transporter_id, #reference_by, #consignor_id, #consignee_id, #good_type')
                    .trigger('change.select2');
                const payeeId = $('#transporter_id').val();
                if (payeeId && typeof window.LREntity.showTdsPercentage === 'function') {
                    window.LREntity.showTdsPercentage(payeeId);
                }
            }, 500);

            // Handle consignor selection change
            $('#consignor_id').on('change', function() {
                updateConsignorContact();
            });

            // Handle consignee selection change
            $('#consignee_id').on('change', function() {
                updateConsigneeContact();
            });

            // Handle truck selection change
            $('#truck_id').on('change', function() {
                updateTruckDetails();
            });

            // Handle transporter selection change
            $('#transporter_id').on('change', function() {
                updateTransporterDetails();
            });

            function updateConsignorContact() {
                const consignorId = $('#consignor_id').val();
                if (consignorId && consignors[consignorId]) {
                    $('#consignor_contact_number').val(consignors[consignorId].contact_number || '');
                } else {
                    $('#consignor_contact_number').val('');
                }
            }

            function updateConsigneeContact() {
                const consigneeId = $('#consignee_id').val();
                if (consigneeId && consignees[consigneeId]) {
                    $('#consignee_contact_number').val(consignees[consigneeId].contact_number || '');
                } else {
                    $('#consignee_contact_number').val('');
                }
            }

            function updateTruckDetails() {
                const dbSourceCity = "{{ $lorry_receipt->source_city ?? '' }}";
                const dbSourceState = "{{ $lorry_receipt->source_state ?? '' }}";
                const dbDestinationCity = "{{ $lorry_receipt->destination_city ?? '' }}";
                const dbDestinationState = "{{ $lorry_receipt->destination_state ?? '' }}";

                // Check if fields are already filled (from DB in edit form)
                if ($('#source_city').val() || $('#destination_city').val()) {
                    // Do nothing, keep DB values
                    return;
                }

                const truckId = $('#truck_id').val();
                if (truckId && trucks[truckId]) {
                    const truck = trucks[truckId];
                    $('#source_city').val(truck.source_city || '');
                    $('#source_state').val(truck.source_state || '');
                    $('#destination_city').val(truck.destination_city || '');
                    $('#destination_state').val(truck.destination_state || '');
                } else {
                    $('#source_city, #source_state, #destination_city, #destination_state').val('');
                }
            }

            function updateTransporterDetails() {
                const transporterId = $('#transporter_id').val();
                if (transporterId && transporters[transporterId]) {
                    const transporter = transporters[transporterId];
                    const tdsPercentage = transporter.tds_percentage || 0;

                    // Update TDS badge
                    if (tdsPercentage > 0) {
                        $('#tds-badge').text(`TDS: ${tdsPercentage}%`).removeClass('d-none');
                    } else {
                        $('#tds-badge').addClass('d-none');
                    }
                } else {
                    $('#tds-badge').addClass('d-none');
                }
            }

            // Check if unloading_weight field exists (for loose weight mode)
            const unloadingWeightInput = $('#unloading_weight');
            const hasUnloadingWeight = unloadingWeightInput.length > 0;

            // Auto-calculate actual_amount and amount when weight, unloading_weight, freight rate, or fixed toggle changes (when not fixed)
            $('#weight, #fr_rate').on('input', function() {
                calculateAmount();
            });
            $('#is_fixed_amount').on('change', function() {
                calculateAmount();
            });

            if (hasUnloadingWeight) {
                $('#unloading_weight').on('input', function() {
                    calculateAmount();
                });
            }

            // Update amount when actual_amount, shortage, or damage changes
            $('#actual_amount, #shortage_amount, #damage_amount').on('input', function() {
                calculateAmount();
            });

            function calculateAmount() {
                const weight = parseFloat($('#weight').val()) || 0;
                const unloadingWeight = hasUnloadingWeight ? (parseFloat($('#unloading_weight').val()) || weight) : weight;
                const frRate = parseFloat($('#fr_rate').val()) || 0;
                const shortageAmount = parseFloat($('#shortage_amount').val()) || 0;
                const damageAmount = parseFloat($('#damage_amount').val()) || 0;
                const isFixed = $('#is_fixed_amount').is(':checked');

                if (!isFixed && weight > 0 && frRate > 0) {
                    // Only recalculate actual_amount if loading_weight != unloading_weight
                    // If weights are equal, use manually entered actual_amount (allow manual editing)
                    const weightsEqual = Math.abs(weight - unloadingWeight) < 0.01;

                    if (!weightsEqual) {
                        // Weights are different - always recalculate actual_amount using min(loading, unloading)
                        const effectiveWeight = Math.min(weight, unloadingWeight);
                        const calculatedActualAmount = effectiveWeight * frRate;
                        $('#actual_amount').val(calculatedActualAmount.toFixed(2));
                    } else {
                        // Weights are equal - only auto-calculate if actual_amount is empty or matches calculation
                        // This allows manual editing when weights are equal
                        const currentActualAmount = parseFloat($('#actual_amount').val()) || 0;
                        const calculatedActualAmount = weight * frRate;
                        if (currentActualAmount === 0 || Math.abs(currentActualAmount - calculatedActualAmount) < 0.01) {
                            $('#actual_amount').val(calculatedActualAmount.toFixed(2));
                        }
                    }
                }

                // Always calculate amount = actual_amount - shortage - damage
                const actualAmount = parseFloat($('#actual_amount').val()) || 0;
                const amount = actualAmount - (shortageAmount + damageAmount);
                $('#amount').val(Math.max(0, amount).toFixed(2));
            }

            // Validate no_of_units field
            $('#no_of_units').on('input blur', function() {
                const value = parseInt($(this).val());
                const isValid = !value || (value >= 1 && value <= 100000);

                $(this).toggleClass('is-valid', isValid && value)
                    .toggleClass('is-invalid', !isValid && value);

                if (!isValid && value) {
                    this.setCustomValidity('Number of units must be between 1 and 100,000');
                } else {
                    this.setCustomValidity('');
                }
            });

            // Form validation
            $('form').on('submit', function(e) {
                const noOfUnits = parseInt($('#no_of_units').val());
                if (noOfUnits && (noOfUnits < 1 || noOfUnits > 100000)) {
                    e.preventDefault();
                    $('#no_of_units').addClass('is-invalid');
                    alert('Number of units must be between 1 and 100,000');
                    return false;
                }
            });
        });
    }
</script>

<!-- Load Select2 from CDN -->
<!-- Load SweetAlert2 from CDN -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {

        const transporterTypeSelect = document.getElementById('transporter_type');
        const truckSelect = document.getElementById('truck_id');
        const transporterSelect = document.getElementById('transporter_id');
        const transporterLabel = document.getElementById('transporterLabel');

        const initialTrucksData = {
          @foreach ($trucks as $truck)
            {{ $truck->id }}: {
                transporterId: {{ $truck->transporter_id ?? 'null' }},
                transporterName: '{{ $truck->transporter ? $truck->transporter->name : '' }}',
                truckText: '{{ $truck->truck_number }}' + '\n'
            },
          @endforeach
        };

        const allowTransporterChanges = @json(!isset($canChangeTransporter) || $canChangeTransporter);

        window.LREntity.initLorryReceiptTransporterSelectionFlow({
            transporterTypeSelect,
            truckSelect,
            transporterSelect,
            transporterLabel,
            trucksData: initialTrucksData,
            mode: 'edit',
            allowTransporterChanges,
            afterTransporterTypeChange: function (selectedType) {
                if (typeof window.lrEditRebindTransporterSelect2 === 'function') {
                    window.lrEditRebindTransporterSelect2(selectedType === 'owner' ? 'owner' : 'agency');
                }
            },
        });

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
        if (consignorSelect) {
            consignorSelect.addEventListener('change', function() {
                const consignorId = this.value;
                handleConsignorSelection(consignorId);
            });
        }

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
        if (consigneeSelect) {
            consigneeSelect.addEventListener('change', function() {
                const consigneeId = this.value;
                handleConsigneeSelection(consigneeId);
            });
        }

        // Handle truck selection for transporter info
        if (truckSelect) {
            truckSelect.addEventListener('change', function() {
                const truckId = this.value;
                if (truckId) {
                    fetch(`/api/trucks/${truckId}`)
                        .then(response => response.json())
                        .then(data => {
                            const tdsRateDisplay = document.getElementById('tdsRateDisplay');
                            if (data.transporter && tdsRateDisplay) {
                                tdsRateDisplay.textContent = `TDS Rate: ${data.transporter.tds_rate || 'N/A'}%`;
                            }
                        });
                } else {
                    const tdsRateDisplay = document.getElementById('tdsRateDisplay');
                    if (tdsRateDisplay) {
                        tdsRateDisplay.textContent = '--';
                    }
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

        // Auto-calculate actual_amount and amount based on weight × freight rate (when not fixed)
        const weightInput = document.getElementById('weight');
        const frRateInput = document.getElementById('fr_rate');
        const unloadingWeightInput = document.getElementById('unloading_weight');
        const actualAmountInput = document.getElementById('actual_amount');
        const amountInput = document.getElementById('amount');
        const shortageAmountInput = document.getElementById('shortage_amount');
        const damageAmountInput = document.getElementById('damage_amount');
        const isFixedAmountInput = document.getElementById('is_fixed_amount');

        function calculateAmount() {
            const weight = parseFloat(weightInput.value) || 0;
            const unloadingWeight = unloadingWeightInput ? (parseFloat(unloadingWeightInput.value) || weight) : weight;
            const frRate = parseFloat(frRateInput.value) || 0;
            const shortageAmount = parseFloat(shortageAmountInput.value) || 0;
            const damageAmount = parseFloat(damageAmountInput.value) || 0;
            const isFixed = isFixedAmountInput && isFixedAmountInput.checked;

            if (!isFixed && weight > 0 && frRate > 0) {
                // Only recalculate actual_amount if loading_weight != unloading_weight
                // If weights are equal, use manually entered actual_amount (allow manual editing)
                const weightsEqual = Math.abs(weight - unloadingWeight) < 0.01;

                if (!weightsEqual) {
                    // Weights are different - always recalculate actual_amount using min(loading, unloading)
                    const effectiveWeight = Math.min(weight, unloadingWeight);
                    const calculatedActualAmount = effectiveWeight * frRate;
                    actualAmountInput.value = calculatedActualAmount.toFixed(2);
                } else {
                    // Weights are equal - only auto-calculate if actual_amount is empty or matches calculation
                    // This allows manual editing when weights are equal
                    const currentActualAmount = parseFloat(actualAmountInput.value) || 0;
                    const calculatedActualAmount = weight * frRate;
                    if (currentActualAmount === 0 || Math.abs(currentActualAmount - calculatedActualAmount) < 0.01) {
                        actualAmountInput.value = calculatedActualAmount.toFixed(2);
                    }
                }
            }

            // Always calculate amount = actual_amount - shortage - damage
            const actualAmount = parseFloat(actualAmountInput.value) || 0;
            const calculatedAmount = actualAmount - (shortageAmount + damageAmount);
            amountInput.value = Math.max(0, calculatedAmount).toFixed(2);
        }

        // Add event listeners for weight and freight rate changes
        if (weightInput) {
            weightInput.addEventListener('input', function() {
                validateWeight();
                calculateAmount();
            });
        }
        if (frRateInput) {
            frRateInput.addEventListener('input', calculateAmount);
        }

        // Update amount when actual_amount, unloading_weight, shortage, or damage changes
        if (unloadingWeightInput) {
            unloadingWeightInput.addEventListener('input', calculateAmount);
        }
        if (actualAmountInput) {
            actualAmountInput.addEventListener('input', calculateAmount);
        }
        if (shortageAmountInput) {
            shortageAmountInput.addEventListener('input', calculateAmount);
        }
        if (damageAmountInput) {
            damageAmountInput.addEventListener('input', calculateAmount);
        }

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
        if (weightInput) {
            weightInput.addEventListener('blur', validateWeight);
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
                        lr_number: lrNumber,
                        exclude_id: '{{ $lorry_receipt->id }}' // Exclude current LR ID for edit
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
        if (lrNumberInput) {
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
        }

        // Invoice Number uniqueness validation
        const invoiceNumberInput = document.getElementById('invoice_number');
        const invoiceNumberFeedback = document.getElementById('invoice-number-feedback');
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
                        invoice_number: invoiceNumber,
                        exclude_id: '{{ $lorry_receipt->id }}' // Exclude current LR ID for edit
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
        if (invoiceNumberInput) {
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
        }

        const grNumberInput = document.getElementById('gr_number');
        const grNumberFeedback = document.getElementById('gr-number-feedback');

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
if (grNumberInput) {
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
}

        // No. of Units validation
        const noOfUnitsInput = document.getElementById('no_of_units');

        function validateNoOfUnits() {
            const value = parseFloat(noOfUnitsInput.value);

            if (noOfUnitsInput.value && (isNaN(value) || value < 1 || value > 100000)) {
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
        if (noOfUnitsInput) {
            noOfUnitsInput.addEventListener('input', validateNoOfUnits);
            noOfUnitsInput.addEventListener('blur', validateNoOfUnits);
        }

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
        if (frRateInput) {
            frRateInput.addEventListener('input', validateFreightRate);
            frRateInput.addEventListener('blur', validateFreightRate);
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
            const referenceBy = document.querySelector('#reference_by option:checked')?.textContent ||
                'Not selected';

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
                                    <span class="fw-bold text-dark small">${formData.get('gr_date') || 'Not selected'}</span>
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
                                    <span class="text-muted small">Ref By</span>
                                    <span class="fw-medium text-dark text-truncate ms-2 text-end small" style="max-width: 180px;">${referenceBy}</span>
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
                    confirmButtonColor: '#2FB36F',
                    cancelButtonColor: '#6c757d',
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
    });
    // Handle Add Goods Type Form Submission
    const addGoodsTypeForm = document.getElementById('addGoodsTypeForm');
    if (addGoodsTypeForm) {
    addGoodsTypeForm.addEventListener('submit', function(e) {
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

                    // Close modal
                    bootstrap.Modal.getInstance(document.getElementById('addGoodsTypeModal')).hide();

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
    }

</script>
