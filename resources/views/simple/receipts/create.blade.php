@extends('layouts.simple-app')

@section('title', 'Simple Create Receipt')

@push('styles')
    <style>
        .wizard-step {
            display: none;
        }

        .wizard-step.active {
            display: block;
        }

        .step-pill {
            border: 1px solid var(--brand-border);
            border-radius: 999px;
            padding: 0.35rem 0.75rem;
            font-size: 0.875rem;
            background: #fff;
        }

        .step-pill.active {
            background: var(--brand-active-bg);
            border-color: var(--brand-primary);
            color: var(--brand-primary-dark);
            font-weight: 600;
        }
    </style>
@endpush

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <h2 class="h4 m-0">Create Good Receipt (Simple)</h2>
        <a href="{{ route('lorry_receipts.create') }}" class="btn btn-outline-primary">Advanced Form</a>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="d-flex flex-wrap gap-2 mb-3" id="stepPills">
        <span class="step-pill active" data-step-pill="1">1. Basic</span>
        <span class="step-pill" data-step-pill="2">2. Parties</span>
        <span class="step-pill" data-step-pill="3">3. Charges</span>
        <span class="step-pill" data-step-pill="4">4. Review</span>
    </div>

    <form method="POST" action="{{ route('lorry_receipts.store') }}" id="simpleReceiptForm">
        @csrf
        <div class="simple-card p-3">
            <div class="wizard-step active" data-step="1">
                <h3 class="h6 mb-3">Basic Details</h3>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="lr_number">LR Number *</label>
                        <input id="lr_number" name="lr_number" class="form-control" required value="{{ old('lr_number') }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="gr_date">GR Date *</label>
                        <input id="gr_date" type="date" name="gr_date" class="form-control" required value="{{ old('gr_date') }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="invoice_number">Invoice Number</label>
                        <input id="invoice_number" name="invoice_number" class="form-control" value="{{ old('invoice_number') }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="gr_number">GR Number</label>
                        <input id="gr_number" name="gr_number" class="form-control" value="{{ old('gr_number') }}">
                    </div>
                </div>
            </div>

            <div class="wizard-step" data-step="2">
                <h3 class="h6 mb-3">Parties & Route</h3>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="customer_id">Customer *</label>
                        <select id="customer_id" name="customer_id" class="form-select" required>
                            <option value="">Select</option>
                            @foreach ($consignees as $consignee)
                                <option value="{{ $consignee->id }}" {{ old('customer_id') == $consignee->id ? 'selected' : '' }}>
                                    {{ $consignee->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="consignor_id">Consignor *</label>
                        <select id="consignor_id" name="consignor_id" class="form-select" required>
                            <option value="">Select</option>
                            @foreach ($consignees as $consignee)
                                <option value="{{ $consignee->id }}" {{ old('consignor_id') == $consignee->id ? 'selected' : '' }}>
                                    {{ $consignee->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="consignee_id">Consignee *</label>
                        <select id="consignee_id" name="consignee_id" class="form-select" required>
                            <option value="">Select</option>
                            @foreach ($consignees as $consignee)
                                <option value="{{ $consignee->id }}" {{ old('consignee_id') == $consignee->id ? 'selected' : '' }}>
                                    {{ $consignee->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="truck_id">Truck</label>
                        <select id="truck_id" name="truck_id" class="form-select">
                            <option value="">Select (optional)</option>
                            @foreach ($trucks as $truck)
                                <option value="{{ $truck->id }}" {{ old('truck_id') == $truck->id ? 'selected' : '' }}>
                                    {{ $truck->truck_number }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="source_city">Source City *</label>
                        <input id="source_city" name="source_city" class="form-control" required value="{{ old('source_city') }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="source_state">Source State *</label>
                        <input id="source_state" name="source_state" class="form-control" required value="{{ old('source_state') }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="destination_city">Destination City *</label>
                        <input id="destination_city" name="destination_city" class="form-control" required value="{{ old('destination_city') }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="destination_state">Destination State *</label>
                        <input id="destination_state" name="destination_state" class="form-control" required value="{{ old('destination_state') }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="unloading_station">Unloading Station</label>
                        <input id="unloading_station" name="unloading_station" class="form-control" value="{{ old('unloading_station') }}">
                    </div>
                </div>
            </div>

            <div class="wizard-step" data-step="3">
                <h3 class="h6 mb-3">Charges & Transport</h3>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label" for="good_type">Goods Type *</label>
                        <select id="good_type" name="good_type" class="form-select" required>
                            <option value="">Select</option>
                            @foreach ($goodsTypes as $goodsType)
                                <option value="{{ $goodsType->name }}" {{ old('good_type') == $goodsType->name ? 'selected' : '' }}>
                                    {{ $goodsType->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="transporter_type">Payment Type *</label>
                        <select id="transporter_type" name="transporter_type" class="form-select" required>
                            <option value="agency" {{ old('transporter_type', 'agency') === 'agency' ? 'selected' : '' }}>Agency</option>
                            <option value="owner" {{ old('transporter_type') === 'owner' ? 'selected' : '' }}>Truck Owner</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="transporter_id">Transporter</label>
                        <select id="transporter_id" name="transporter_id" class="form-select">
                            <option value="">Select (optional)</option>
                            @foreach ($transporters as $transporter)
                                <option value="{{ $transporter->id }}" {{ old('transporter_id') == $transporter->id ? 'selected' : '' }}>
                                    {{ $transporter->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="weight">Weight *</label>
                        <input id="weight" type="number" step="0.001" min="0" name="weight" class="form-control" required value="{{ old('weight') }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="fr_rate">Freight Rate *</label>
                        <input id="fr_rate" type="number" step="0.01" min="0" name="fr_rate" class="form-control" required value="{{ old('fr_rate') }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="amount">Amount *</label>
                        <input id="amount" type="number" step="0.01" min="0" name="amount" class="form-control" required value="{{ old('amount') }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="no_of_units">No. of Units</label>
                        <input id="no_of_units" type="number" min="1" name="no_of_units" class="form-control" value="{{ old('no_of_units') }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="goods_value">Goods Value</label>
                        <input id="goods_value" type="number" step="0.01" min="0" name="goods_value" class="form-control" value="{{ old('goods_value') }}">
                    </div>
                </div>
                <input type="hidden" name="lane" value="Road">
            </div>

            <div class="wizard-step" data-step="4">
                <h3 class="h6 mb-2">Review & Submit</h3>
                <p class="text-muted mb-3">Check values quickly, then submit. You can always edit later from Operations.</p>
                <div class="simple-card p-3">
                    <div class="small text-muted mb-2">Quick checks</div>
                    <ul class="mb-0">
                        <li>LR Number and GR Date are filled</li>
                        <li>Customer, Consignor, and Consignee are selected</li>
                        <li>Weight, Rate, and Amount are set</li>
                    </ul>
                </div>
            </div>

            <div class="d-flex justify-content-between mt-4">
                <button type="button" id="prevStepBtn" class="btn btn-outline-secondary" disabled>Back</button>
                <div class="d-flex gap-2">
                    <a href="{{ route('simple.operations.receipts.index') }}" class="btn btn-outline-danger">Cancel</a>
                    <button type="button" id="nextStepBtn" class="btn btn-primary">Next</button>
                    <button type="submit" id="submitBtn" class="btn btn-primary d-none">Submit</button>
                </div>
            </div>
        </div>
    </form>

    <script>
        (function () {
            const totalSteps = 4;
            let currentStep = 1;
            const form = document.getElementById('simpleReceiptForm');
            const prevButton = document.getElementById('prevStepBtn');
            const nextButton = document.getElementById('nextStepBtn');
            const submitButton = document.getElementById('submitBtn');
            const weightInput = document.getElementById('weight');
            const rateInput = document.getElementById('fr_rate');
            const amountInput = document.getElementById('amount');

            function showStep(step) {
                form.querySelectorAll('.wizard-step').forEach(function (section) {
                    section.classList.toggle('active', Number(section.dataset.step) === step);
                });
                document.querySelectorAll('[data-step-pill]').forEach(function (pill) {
                    pill.classList.toggle('active', Number(pill.dataset.stepPill) === step);
                });

                prevButton.disabled = step === 1;
                nextButton.classList.toggle('d-none', step === totalSteps);
                submitButton.classList.toggle('d-none', step !== totalSteps);
            }

            function isCurrentStepValid() {
                const currentSection = form.querySelector('.wizard-step.active');
                const requiredInputs = currentSection.querySelectorAll('[required]');
                for (const input of requiredInputs) {
                    if (!input.checkValidity()) {
                        input.reportValidity();
                        return false;
                    }
                }
                return true;
            }

            function calculateAmount() {
                const weight = Number(weightInput.value || 0);
                const rate = Number(rateInput.value || 0);
                if (weight > 0 && rate > 0) {
                    amountInput.value = (weight * rate).toFixed(2);
                }
            }

            weightInput.addEventListener('input', calculateAmount);
            rateInput.addEventListener('input', calculateAmount);

            nextButton.addEventListener('click', function () {
                if (!isCurrentStepValid()) {
                    return;
                }
                if (currentStep < totalSteps) {
                    currentStep += 1;
                    showStep(currentStep);
                }
            });

            prevButton.addEventListener('click', function () {
                if (currentStep > 1) {
                    currentStep -= 1;
                    showStep(currentStep);
                }
            });

            showStep(currentStep);
        })();
    </script>
@endsection
