@extends('layouts.app')

@section('content')
<style>
/* Prevent form expansion on validation errors */
.form-control, .form-select {
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
</style>
<div class="card card-info card-outline mb-4" style="max-width: 1000px; margin: 0 auto;">
    <div class="card-header">
        <div class="card-title h4">Edit Transporter</div>
    </div>
    <!--begin::Form-->
    <form action="{{ route('transporters.update', $transporter->id) }}" method="POST" class="needs-validation" novalidate="">
        @csrf
        @method('PUT')
        @php
            $canEditCompany = auth()->check() && auth()->user()->userRole->name === 'super_admin';
            $userCompanyId = auth()->user()->company_id ?? null;
        @endphp
        @error('registered_truck_id')
            <div class="text-danger">{{ $message }}</div>
        @enderror
        <div class="card-body">
            <input type="hidden" name="company_id" value="{{ $userCompanyId }}">
            <input type="hidden" name="total_trucks" value="{{ old('total_trucks', $transporter->total_trucks ?? 0) }}">

            @php $master = $transporter->transporterMaster; @endphp
            <div id="transporterEditMeta" class="d-none" data-transport-name="{{ $master->name ?? '' }}" data-pan="{{ $master->pan_number ?? '' }}" data-pan-active="{{ ($master->pan_active ?? false) ? '1' : '0' }}" data-gst="{{ $master->gst_number ?? '' }}"></div>
            <div class="card mb-4 p-3 bg-light">
                <div class="row"><div class="col"><h5 class="mb-3 text-muted"><i class="fas fa-lock me-2"></i> Master Data (Read-only)</h5></div></div>
                <div class="row mb-3">
                    <div class="col-md-4">
                        <label class="form-label">Name</label>
                        <div class="form-control-plaintext bg-white border rounded px-3 py-2">{{ $master->name ?? '-' }}</div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Email</label>
                        <div class="form-control-plaintext bg-white border rounded px-3 py-2">{{ $master->email ?? '-' }}</div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">PAN Number</label>
                        <div class="form-control-plaintext bg-white border rounded px-3 py-2">{{ $master->pan_number ?? '-' }}</div>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-4">
                        <label class="form-label">GST Number</label>
                        <div class="form-control-plaintext bg-white border rounded px-3 py-2">{{ $master->gst_number ?? '-' }}</div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Address</label>
                        <div class="form-control-plaintext bg-white border rounded px-3 py-2">{{ $master->address ?? '-' }}</div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">City / State / Country</label>
                        <div class="form-control-plaintext bg-white border rounded px-3 py-2">{{ optional($master->city)->name ?? '-' }} / {{ optional($master->state)->name ?? '-' }} / {{ optional($master->country)->name ?? '-' }}</div>
                    </div>
                </div>
            </div>

            <div class="card mb-4 p-3 bg-light">
                <div class="row"><div class="col"><h5 class="mb-3">Editable Fields</h5></div></div>
                <div class="row mb-3">
                    <div class="col-md-4">
                        <label for="contact_person_name" class="form-label">Contact Person Name</label>
                        <input type="text" class="form-control @error('contact_person_name') is-invalid @enderror" id="contact_person_name" name="contact_person_name" value="{{ old('contact_person_name', $transporter->contact_person_name) }}">
                        @error('contact_person_name') <div class="text-danger">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-4">
                        <label for="mobile_number" class="form-label">Mobile Number *</label>
                        <input type="text" class="form-control @error('mobile_number') is-invalid @enderror" id="mobile_number" name="mobile_number" value="{{ old('mobile_number', $transporter->mobile_number) }}" required pattern="[0-9]{10}" maxlength="10">
                        @error('mobile_number') <div class="text-danger">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-4">
                        <label for="short_name" class="form-label">Short Name</label>
                        @if(auth()->user()->userRole->name === 'super_admin')
                            <input type="text" class="form-control @error('short_name') is-invalid @enderror" id="short_name" name="short_name" value="{{ old('short_name', $transporter->short_name) }}">
                        @else
                            <div class="form-control-plaintext bg-light border rounded px-3 py-2">{{ $transporter->short_name ?? '-' }}</div>
                            <small class="text-muted">Editable only from Super Admin panel</small>
                        @endif
                        @error('short_name') <div class="text-danger">{{ $message }}</div> @enderror
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-4">
                        <label class="form-label">Book Balance</label>
                        <div class="form-control-plaintext bg-light border rounded px-3 py-2"><strong> {{ number_format($transporter->advance_amount, 2) }}</strong></div>
                        <input type="hidden" name="advance_amount" value="{{ $transporter->advance_amount }}">
                    </div>
                    <div class="col-md-4">
                        <label for="beneficiary_id" class="form-label">Beneficiary ID</label>
                        @if(auth()->user()->userRole->name === 'super_admin')
                            <input type="text" class="form-control" id="beneficiary_id" name="beneficiary_id" value="{{ old('beneficiary_id', $transporter->beneficiary_id) }}">
                        @else
                            <div class="form-control-plaintext bg-light border rounded px-3 py-2">{{ $transporter->beneficiary_id ?? '-' }}</div>
                            <small class="text-muted">Editable only from Super Admin panel</small>
                        @endif
                        @error('beneficiary_id') <div class="text-danger">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Declaration given? *</label><br>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="declaration_given" id="decl_yes" value="1" {{ old('declaration_given', $transporter->declaration_given) == 1 ? 'checked' : '' }} required>
                            <label class="form-check-label" for="decl_yes">Yes</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="declaration_given" id="decl_no" value="0" {{ old('declaration_given', $transporter->declaration_given) != 1 ? 'checked' : '' }} required>
                            <label class="form-check-label" for="decl_no">No</label>
                        </div>
                        @error('declaration_given') <div class="text-danger">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-4">
                        <label for="tds_percentage" class="form-label">TDS % *</label>
                        <input type="hidden" id="tds_calculated_baseline" value="{{ number_format(old('tds_percentage', $transporter->tds_percentage ?? 0), 2, '.', '') }}">
                        <input type="number" step="0.01" min="0" max="100" class="form-control @error('tds_percentage') is-invalid @enderror" name="tds_percentage" id="tds_percentage" value="{{ old('tds_percentage', $transporter->tds_percentage) }}" required>
                        @error('tds_percentage') <div class="text-danger">{{ $message }}</div> @enderror
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-12">
                        <label for="tds_adjustment_remark" class="form-label">TDS adjustment remark</label>
                        <textarea class="form-control @error('tds_adjustment_remark') is-invalid @enderror" id="tds_adjustment_remark" name="tds_adjustment_remark" rows="2" placeholder="Required when TDS % differs from the calculated rate">{{ old('tds_adjustment_remark', $transporter->tds_adjustment_remark) }}</textarea>
                        @error('tds_adjustment_remark') <div class="text-danger">{{ $message }}</div> @enderror
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions Section -->
        <div class="card p-3 bg-light">
            <div class="row"><div class="col"><h5 class="mb-3">Quick Actions</h5></div></div>
            <div class="row g-2">
                <div class="col-md-4">
                    <button class="btn btn-brand-primary w-100 mb-2" type="button" onclick="showConfirmationModal()">💾 Update Transporter</button>
                </div>
                <div class="col-md-4">
                    <a href="{{ route('transporters.index') }}" class="btn btn-back w-100 mb-2">📄 Back to List</a>
                </div>
            </div>
        </div>
    </form>
</div>

<!-- Confirmation Modal -->
<div class="modal fade" id="confirmationModal" tabindex="-1" aria-labelledby="confirmationModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-gradient-primary">
                <h5 class="modal-title" id="confirmationModalLabel">
                    <i class="fas fa-check-circle me-2"></i>Confirm Transporter Update
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    Please review the updated details below before submitting the form.
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <h6 class="text-primary mb-3"><i class="fas fa-user me-2"></i>Basic Information</h6>
                        <table class="table table-sm table-borderless">
                            <tr><td class="fw-bold">Name:</td><td id="confirm-name">-</td></tr>
                            <tr><td class="fw-bold">Contact Person:</td><td id="confirm-contact-person">-</td></tr>
                            <tr><td class="fw-bold">Mobile Number:</td><td id="confirm-mobile">-</td></tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-primary mb-3"><i class="fas fa-file-alt me-2"></i>Tax Information</h6>
                        <table class="table table-sm table-borderless">
                            <tr><td class="fw-bold">GST Number:</td><td id="confirm-gst-number">-</td></tr>
                            <tr><td class="fw-bold">PAN Number:</td><td id="confirm-pan-number">-</td></tr>
                            <tr><td class="fw-bold">PAN Active:</td><td id="confirm-pan-active">-</td></tr>
                            <tr class="table-warning"><td class="fw-bold">🎯 TDS Percentage:</td><td id="confirm-tds-percentage" class="fw-bold text-danger fs-5">-</td></tr>
                        </table>
                    </div>
                </div>

                <div class="row mt-3">
                    <div class="col-md-6">
                        <h6 class="text-primary mb-3"><i class="fas fa-map-marker-alt me-2"></i>Location Details</h6>
                        <table class="table table-sm table-borderless">
                            <tr><td class="fw-bold">Address:</td><td id="confirm-address">-</td></tr>
                            <tr><td class="fw-bold">Country:</td><td id="confirm-country">-</td></tr>
                            <tr><td class="fw-bold">State:</td><td id="confirm-state">-</td></tr>
                            <tr><td class="fw-bold">City:</td><td id="confirm-city">-</td></tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-primary mb-3"><i class="fas fa-cog me-2"></i>Other Details</h6>
                        <table class="table table-sm table-borderless">
                            <tr><td class="fw-bold">Is Owner:</td><td id="confirm-is-owner">-</td></tr>
                            <tr><td class="fw-bold">Declaration Given:</td><td id="confirm-declaration">-</td></tr>
                            <tr><td class="fw-bold">Book Balance:</td><td id="confirm-advance">-</td></tr>
                        </table>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i>Cancel
                </button>
                <button type="button" class="btn btn-brand-primary" onclick="submitForm()">
                    <i class="fas fa-check me-2"></i>Confirm & Update
                </button>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="addCountryModal" tabindex="-1" aria-labelledby="addCountryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <form id="addCountryForm">
          @csrf
          <div class="modal-header">
            <h5 class="modal-title" id="addCountryModalLabel">Add Country</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>

          <div class="modal-body">
            <div id="countryFormErrors" class="alert alert-danger d-none"></div>
            <div class="row g-3">
              <div class="col-md-6">
                <label for="country_name" class="form-label">Country Name *</label>
                <input type="text" class="form-control" id="country_name" name="name" required>
              </div>

              <div class="col-md-6">
                <label for="country_code" class="form-label">Country Code *</label>
                <input type="text" class="form-control" id="country_code" name="code" required>
              </div>
            </div>
          </div>

          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-brand-primary">
              <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
              Submit form
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
  <!-- Add State Modal -->
<div class="modal fade" id="addStateModal" tabindex="-1" aria-labelledby="addStateModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <form id="addStateForm">
          @csrf
          <div class="modal-header">
            <h5 class="modal-title" id="addStateModalLabel">Add State</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>

          <div class="modal-body">
            <div id="stateFormErrors" class="alert alert-danger d-none"></div>
            <div class="row g-3">
              <div class="col-md-6">
                <label for="state_name" class="form-label">State Name *</label>
                <input type="text" class="form-control" id="state_name" name="name" required>
              </div>

              <div class="col-md-6">
                <label for="state_code" class="form-label">State Code *</label>
                <input type="text" class="form-control" id="state_code" name="code" required maxlength="10">
              </div>

              <div class="col-md-12">
                <label for="modal_state_country_id" class="form-label">Country *</label>
                <select class="form-select" id="modal_state_country_id" name="country_id" required>
                  <option value="">Select Country</option>
                  @foreach($countries as $country)
                    <option value="{{ $country->id }}">{{ $country->name }}</option>
                  @endforeach
                </select>
              </div>
            </div>
          </div>

          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-brand-primary">
              <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
              Submit form
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
  <!-- Add City Modal -->
<div class="modal fade" id="addCityModal" tabindex="-1" aria-labelledby="addCityModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <form id="addCityForm">
          @csrf
          <div class="modal-header">
            <h5 class="modal-title" id="addCityModalLabel">Add City</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>

          <div class="modal-body">
            <div id="cityFormErrors" class="alert alert-danger d-none"></div>
            <div class="row g-3">
              <div class="col-md-6">
                <label for="modal_city_name" class="form-label">City Name *</label>
                <input type="text" class="form-control" id="modal_city_name" name="name" required>
              </div>

              <div class="col-md-6">
                <label for="modal_city_country_id" class="form-label">Country *</label>
                <select class="form-select" id="modal_city_country_id" name="country_id" required>
                  <option value="">Select Country</option>
                  @foreach($countries as $country)
                    <option value="{{ $country->id }}">{{ $country->name }}</option>
                  @endforeach
                </select>

              </div>

              <div class="col-md-6">
                <label for="modal_city_state_id" class="form-label">State *</label>
                <select class="form-select" id="modal_city_state_id" name="state_id" required>
                  <option value="">Select State</option>
                </select>
              </div>
            </div>
          </div>

          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-brand-primary">
              <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
              Submit form
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

<script>
function transporterEditCalculateTdsApi(panNumber, panActiveYesNo, onDone) {
    const declarationGiven = document.querySelector('input[name="declaration_given"]:checked')?.value === '1';
    fetch('/api/calculate-tds-percentage', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify({
            pan_number: panNumber === '-' ? '' : panNumber,
            pan_active: panActiveYesNo === 'Yes' ? '1' : '0',
            declaration_given: declarationGiven ? '1' : '0',
        })
    })
    .then(r => r.json())
    .then(data => { if (typeof onDone === 'function') onDone(data.tds_percentage); })
    .catch(() => {});
}

function refreshTransporterEditTdsBaseline() {
    const meta = document.getElementById('transporterEditMeta');
    if (!meta) return;
    const pan = meta.dataset.pan || '';
    const panActive = meta.dataset.panActive === '1' ? 'Yes' : 'No';
    transporterEditCalculateTdsApi(pan, panActive, function (calculatedStr) {
        const calculated = parseFloat(calculatedStr);
        if (isNaN(calculated)) return;
        document.getElementById('tds_calculated_baseline').value = calculated.toFixed(2);
        if (!window.__transporterEditTdsUserEdited) {
            document.getElementById('tds_percentage').value = calculated.toFixed(2);
        }
    });
}

document.addEventListener('DOMContentLoaded', function () {
    window.__transporterEditTdsUserEdited = false;
    document.querySelectorAll('input[name="declaration_given"]').forEach(function (el) {
        el.addEventListener('change', function () {
            window.__transporterEditTdsUserEdited = false;
            refreshTransporterEditTdsBaseline();
        });
    });
    const tdsEl = document.getElementById('tds_percentage');
    if (tdsEl) {
        tdsEl.addEventListener('input', function () { window.__transporterEditTdsUserEdited = true; });
    }
});

// Function to show confirmation modal with form data
function showConfirmationModal() {
    // First validate the form
    const form = document.querySelector('.needs-validation');
    if (!form.checkValidity()) {
        form.classList.add('was-validated');
        return;
    }

    const meta = document.getElementById('transporterEditMeta');
    const name = meta?.dataset?.transportName || '-';
    const contactPerson = document.getElementById('contact_person_name')?.value || '-';
    const mobile = document.getElementById('mobile_number')?.value || '-';

    const gstNumber = meta?.dataset?.gst || '-';
    const panNumber = meta?.dataset?.pan || '-';
    const panActive = meta?.dataset?.panActive === '1' ? 'Yes' : 'No';

    const address = '-';
    const country = '-';
    const state = '-';
    const city = '-';

    const isOwner = 'No';
    const declaration = document.querySelector('input[name="declaration_given"]:checked')?.value === '1' ? 'Yes' : 'No';
    const advance = document.querySelector('input[name="advance_amount"]')?.value || '0';

    const tdsField = document.getElementById('tds_percentage');
    transporterEditCalculateTdsApi(panNumber, panActive, function (calculatedStr) {
        const submitted = parseFloat(tdsField?.value || '0');
        const calculated = parseFloat(calculatedStr);
        let text = (isNaN(submitted) ? '0' : submitted.toFixed(2)) + '%';
        if (!isNaN(calculated) && Math.abs(submitted - calculated) > 0.01) {
            text += ' (calculated ' + calculated.toFixed(2) + '%)';
        }
        document.getElementById('confirm-tds-percentage').textContent = text;
    });

    // Populate modal with values
    document.getElementById('confirm-name').textContent = name;
    document.getElementById('confirm-contact-person').textContent = contactPerson;
    document.getElementById('confirm-mobile').textContent = mobile;

    document.getElementById('confirm-gst-number').textContent = gstNumber;
    document.getElementById('confirm-pan-number').textContent = panNumber;
    document.getElementById('confirm-pan-active').textContent = panActive;

    document.getElementById('confirm-address').textContent = address;
    document.getElementById('confirm-country').textContent = country;
    document.getElementById('confirm-state').textContent = state;
    document.getElementById('confirm-city').textContent = city;

    document.getElementById('confirm-is-owner').textContent = isOwner;
    document.getElementById('confirm-declaration').textContent = declaration;
    document.getElementById('confirm-advance').textContent = '' + parseFloat(advance).toFixed(2);

    // Show modal
    const modal = new bootstrap.Modal(document.getElementById('confirmationModal'));
    modal.show();
}

// Function to submit the form after confirmation
function submitForm() {
    const form = document.querySelector('.needs-validation');

    // Hide modal
    const modal = bootstrap.Modal.getInstance(document.getElementById('confirmationModal'));
    modal.hide();

    // Submit form
    form.submit();
}

// Example starter JavaScript for disabling form submissions if there are invalid fields
(() => {
    'use strict';
    window.addEventListener('load', () => {
        // Fetch all the forms we want to apply custom Bootstrap validation styles to
        const forms = document.querySelectorAll('.needs-validation');
        // Loop over them and prevent submission
        Array.prototype.slice.call(forms).forEach((form) => {
            form.addEventListener('submit', (event) => {
                if (!form.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            }, false);
        });
    }, false);
})();

// PAN number uppercase conversion (if field exists)
const panNumEl = document.getElementById('pan_number');
if (panNumEl) {
    panNumEl.addEventListener('input', function() {
        this.value = this.value.toUpperCase();
    });
}

// Mobile number validation (10 digits only)
document.getElementById('mobile_number').addEventListener('input', function() {
    this.value = this.value.replace(/[^0-9]/g, '').slice(0, 10);
});

$(document).ready(function() {
    // Load states when country changes
    $('#country_id').change(function() {
        var countryId = $(this).val();
        if (countryId) {
            loadStates(countryId);
        } else {
            $('#state_id').empty().append('<option value="">Select State</option>');
            $('#city_id').empty().append('<option value="">Select City</option>');
        }
    });

    // Load cities when state changes
    $('#state_id').change(function() {
        var stateId = $(this).val();
        if (stateId) {
            loadCities(stateId);
        } else {
            $('#city_id').empty().append('<option value="">Select City</option>');
        }
    });

    // Load states and cities on page load if country and state are already selected
    var selectedCountryId = $('#country_id').val();
    var selectedStateId = '{{ old("state_id", $transporter->state_id) }}';
    var selectedCityId = '{{ old("city_id", $transporter->city_id) }}';

    if (selectedCountryId && selectedStateId) {
        loadStates(selectedCountryId, function() {
            $('#state_id').val(selectedStateId);
            if (selectedStateId && selectedCityId) {
                loadCities(selectedStateId, function() {
                    $('#city_id').val(selectedCityId);
                });
            }
        });
    }

    function loadStates(countryId, callback = null) {
        $.get('/api/states/' + countryId, function(data) {
            $('#state_id').empty().append('<option value="">Select State</option>');
            $.each(data, function(key, state) {
                $('#state_id').append('<option value="' + state.id + '">' + state.name + '</option>');
            });
            $('#city_id').empty().append('<option value="">Select City</option>');
            if (callback) callback();
        });
    }

    function loadCities(stateId, callback = null) {
        $.get('/api/cities/' + stateId, function(data) {
            $('#city_id').empty().append('<option value="">Select City</option>');
            $.each(data, function(key, city) {
                $('#city_id').append('<option value="' + city.id + '">' + city.name + '</option>');
            });
            if (callback) callback();
        });
    }
});

</script>
<script>
    // Example starter JavaScript for disabling form submissions if there are invalid fields
    (() => {
        'use strict';

        // Fetch all the forms we want to apply custom Bootstrap validation styles to
        const forms = document.querySelectorAll('.needs-validation');

        // Loop over them and prevent submission
        Array.from(forms).forEach((form) => {
            form.addEventListener(
                'submit',
                (event) => {
                    if (!form.checkValidity()) {
                        event.preventDefault();
                        event.stopPropagation();
                    }

                    form.classList.add('was-validated');
                },
                false,
            );
        });
    })();

    // Modal form submissions with AJAX
    $(document).ready(function() {
        // Handle Country Modal Form Submission
        $('#addCountryForm').on('submit', function(e) {
            e.preventDefault();

            const form = $(this);
            const submitBtn = form.find('button[type="submit"]');
            const spinner = submitBtn.find('.spinner-border');
            const errorDiv = $('#countryFormErrors');
            const countryName = $('#country_name').val();
            const countryCode = $('#country_code').val();
            const csrfToken = $('meta[name="csrf-token"]').attr('content');

            // Show loading state
            submitBtn.prop('disabled', true);
            spinner.removeClass('d-none');
            errorDiv.addClass('d-none');

            // Create FormData object
            const formData = new FormData();
            formData.append('name', countryName);
            formData.append('code', countryCode);
            formData.append('from_modal', '1');
            formData.append('_token', csrfToken);

            // Use jQuery AJAX instead of fetch for better FormData handling
            $.ajax({
                url: '{{ route("master.countries.store") }}',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                success: function(data) {
                    if (data && data.success && data.country) {
                        // Add new country to the main dropdown
                        $('#country_id').append(`<option value="${data.country.id}">${data.country.name}</option>`);

                        // Add new country to modal dropdowns
                        $('#modal_state_country_id').append(`<option value="${data.country.id}">${data.country.name}</option>`);
                        $('#modal_city_country_id').append(`<option value="${data.country.id}">${data.country.name}</option>`);

                        // Select the newly created country
                        $('#country_id').val(data.country.id).trigger('change');

                        // Close modal and remove backdrop
                        $('#addCountryModal').modal('hide');
                        $('.modal-backdrop').remove();
                        $('body').removeClass('modal-open').css('padding-right', '');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error:', error);
                    if (xhr.responseJSON && xhr.responseJSON.errors) {
                        let errorMessages = Object.values(xhr.responseJSON.errors).flat().join('<br>');
                        errorDiv.html(errorMessages).removeClass('d-none');
                    } else {
                        errorDiv.html('An error occurred while adding the country.').removeClass('d-none');
                    }
                },
                complete: function() {
                    // Hide loading state
                    submitBtn.prop('disabled', false);
                    spinner.addClass('d-none');
                }
            });
        });

        // Handle State Modal Form Submission
        $('#addStateForm').on('submit', function(e) {
            e.preventDefault();

            const form = $(this);
            const submitBtn = form.find('button[type="submit"]');
            const spinner = submitBtn.find('.spinner-border');
            const errorDiv = $('#stateFormErrors');
            const stateName = $('#state_name').val();
            const stateCode = $('#state_code').val();
            const countryId = $('#modal_state_country_id').val();
            const csrfToken = $('meta[name="csrf-token"]').attr('content');

            // Show loading state
            submitBtn.prop('disabled', true);
            spinner.removeClass('d-none');
            errorDiv.addClass('d-none');

            // Create FormData object
            const formData = new FormData();
            formData.append('name', stateName);
            formData.append('code', stateCode);
            formData.append('country_id', countryId);
            formData.append('from_modal', '1');
            formData.append('_token', csrfToken);

            // Use jQuery AJAX instead of fetch for better FormData handling
            $.ajax({
                url: '{{ route("master.states.store") }}',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                success: function(data) {
                    if (data && data.success && data.state) {
                        // Add new state to the main dropdown
                        $('#state_id').append(`<option value="${data.state.id}">${data.state.name}</option>`);

                        // Add new state to city modal dropdown
                        $('#modal_city_state_id').append(`<option value="${data.state.id}">${data.state.name}</option>`);

                        // Select the newly created state
                        $('#state_id').val(data.state.id).trigger('change');

                        // Close modal and remove backdrop
                        $('#addStateModal').modal('hide');
                        $('.modal-backdrop').remove();
                        $('body').removeClass('modal-open').css('padding-right', '');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error:', error);
                    if (xhr.responseJSON && xhr.responseJSON.errors) {
                        let errorMessages = Object.values(xhr.responseJSON.errors).flat().join('<br>');
                        errorDiv.html(errorMessages).removeClass('d-none');
                    } else {
                        errorDiv.html('An error occurred while adding the state.').removeClass('d-none');
                    }
                },
                complete: function() {
                    // Hide loading state
                    submitBtn.prop('disabled', false);
                    spinner.addClass('d-none');
                }
            });
        });

        // Handle City Modal Form Submission
        $('#addCityForm').on('submit', function(e) {
            e.preventDefault();

            const form = $(this);
            const submitBtn = form.find('button[type="submit"]');
            const spinner = submitBtn.find('.spinner-border');
            const errorDiv = $('#cityFormErrors');
            const cityName = $('#modal_city_name').val();
            const stateId = $('#modal_city_state_id').val();

            const csrfToken = $('meta[name="csrf-token"]').attr('content');
            // Show loading state
            submitBtn.prop('disabled', true);
            spinner.removeClass('d-none');
            errorDiv.addClass('d-none');

            // Create FormData object
            const formData = new FormData();
            formData.append('name', cityName);
            formData.append('state_id', stateId);

            formData.append('from_modal', '1');
            formData.append('_token', csrfToken);

            // Use jQuery AJAX instead of fetch for better FormData handling
            $.ajax({
                url: '{{ route("master.cities.store") }}',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                success: function(data) {
                    if (data && data.success && data.city) {
                        // Add new city to the main dropdown
                        $('#city_id').append(`<option value="${data.city.id}">${data.city.name}</option>`);

                        // Select new city
                        $('#city_id').val(data.city.id).trigger('change');

                        // Close modal and remove backdrop
                        $('#addCityModal').modal('hide');
                        $('.modal-backdrop').remove();
                        $('body').removeClass('modal-open').css('padding-right', '');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error:', error);
                    if (xhr.responseJSON && xhr.responseJSON.errors) {
                        let errorMessages = Object.values(xhr.responseJSON.errors).flat().join('<br>');
                        errorDiv.html(errorMessages).removeClass('d-none');
                    } else {
                        errorDiv.html('An error occurred while adding the city.').removeClass('d-none');
                    }
                },
                complete: function() {
                    // Hide loading state
                    submitBtn.prop('disabled', false);
                    spinner.addClass('d-none');
                }
            });
        });

        // Handle country change in city modal
        $('#modal_city_country_id').on('change', function() {
            const countryId = $(this).val();
            const stateSelect = $('#modal_city_state_id');

            stateSelect.html('<option value="">Select State</option>');

            if (countryId) {
                $.get(`/api/states/${countryId}`)
                    .done(function(data) {
                        data.forEach(function(state) {
                            stateSelect.append(`<option value="${state.id}">${state.name}</option>`);
                        });
                    })
                    .fail(function() {
                        showErrorMessage('Error loading states');
                    });
            }
        });

        // Function to show success message
        function showSuccessMessage(message) {
            // Remove any existing alerts
            $('.alert-success').remove();

            // Create and show success alert
            const alertHtml = `
                <div class="alert alert-success alert-dismissible fade show mt-2" role="alert">
                    <i class="fas fa-check-circle-fill"></i> ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            `;

            // Insert after the page title or at the top of the form
            if ($('.card-header').length) {
                $('.card-header').after(alertHtml);
            } else {
                $('form').before(alertHtml);
            }

            // Auto-hide after 5 seconds
            setTimeout(function() {
                $('.alert-success').fadeOut();
            }, 5000);
        }

        // Function to show error message
        function showErrorMessage(message) {
            // Remove any existing alerts
            $('.alert-danger').remove();

            // Create and show error alert
            const alertHtml = `
                <div class="alert alert-danger alert-dismissible fade show mt-2" role="alert">
                    <i class="fas fa-exclamation-triangle-fill"></i> ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            `;

            // Insert after the page title or at the top of the form
            if ($('.card-header').length) {
                $('.card-header').after(alertHtml);
            } else {
                $('form').before(alertHtml);
            }
        }
    });
</script>

<script>
// Example starter JavaScript for disabling form submissions if there are invalid fields
(() => {
    'use strict';

    // Fetch all the forms we want to apply custom Bootstrap validation styles to
    const forms = document.querySelectorAll('.needs-validation');

    // Loop over them and prevent submission
    Array.from(forms).forEach((form) => {
        form.addEventListener(
            'submit',
            (event) => {
                if (!form.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                }

                form.classList.add('was-validated');
            },
            false,
        );
    });
})();

// Country, State, City cascading selects for edit form
const countrySelect = document.getElementById('country_id');
const stateSelect = document.getElementById('state_id');
const citySelect = document.getElementById('city_id');

if (countrySelect && stateSelect && citySelect) {
    countrySelect.addEventListener('change', function() {
        const countryId = this.value;
        stateSelect.length = 0;
        stateSelect.innerHTML = '<option value="">Select State</option>';
        citySelect.length = 0;
        citySelect.innerHTML = '<option value="">Select City</option>';

        if (countryId) {
            fetch(`/api/states/${countryId}`)
                .then(response => response.json())
                .then(states => {
                    states.forEach(state => {
                        const option = document.createElement('option');
                        option.value = state.id;
                        option.textContent = state.name;
                        stateSelect.appendChild(option);
                    });
                });
        }
    });

    stateSelect.addEventListener('change', function() {
        const stateId = this.value;
        citySelect.length = 0;
        citySelect.innerHTML = '<option value="">Select City</option>';

        if (stateId) {
            fetch(`/api/cities/${stateId}`)
                .then(response => response.json())
                .then(cities => {
                    cities.forEach(city => {
                        const option = document.createElement('option');
                        option.value = city.id;
                        option.textContent = city.name;
                        citySelect.appendChild(option);
                    });
                });
        }
    });
}

    // Fresh approach using fetch API for modal submissions
    document.addEventListener('DOMContentLoaded', function() {

        // Country Modal Handler
        const countryForm = document.getElementById('addCountryForm');
        if (countryForm) {
            countryForm.addEventListener('submit', async function(e) {
                e.preventDefault();

                const submitBtn = this.querySelector('button[type="submit"]');
                const spinner = submitBtn.querySelector('.spinner-border');
                const errorDiv = document.getElementById('countryFormErrors');

                // Show loading
                submitBtn.disabled = true;
                spinner?.classList.remove('d-none');
                errorDiv?.classList.add('d-none');

                try {
                    const formData = new FormData(this);
                    formData.append('from_modal', '1');

                    const response = await fetch('{{ route("master.countries.store") }}', {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        }
                    });

                    const data = await response.json();

                    if (data.success && data.country) {
                        // Update all dropdowns
                        updateCountryDropdowns(data.country);

                        // Select new country
                        document.getElementById('country_id').value = data.country.id;
                        document.getElementById('country_id').dispatchEvent(new Event('change'));

                        // Clean close modal
                        closeModalClean('addCountryModal');

                        // Reset form
                        this.reset();
                    } else {
                        throw new Error(data.message || 'Failed to add country');
                    }
                } catch (error) {
                    console.error('Error:', error);
                    if (errorDiv) {
                        errorDiv.innerHTML = error.message || 'An error occurred while adding the country.';
                        errorDiv.classList.remove('d-none');
                    }
                } finally {
                    submitBtn.disabled = false;
                    spinner?.classList.add('d-none');
                }
            });
        }

        // State Modal Handler
        const stateForm = document.getElementById('addStateForm');
        if (stateForm) {
            stateForm.addEventListener('submit', async function(e) {
                e.preventDefault();

                const submitBtn = this.querySelector('button[type="submit"]');
                const spinner = submitBtn.querySelector('.spinner-border');
                const errorDiv = document.getElementById('stateFormErrors');

                // Show loading
                submitBtn.disabled = true;
                spinner?.classList.remove('d-none');
                errorDiv?.classList.add('d-none');

                try {
                    const formData = new FormData(this);
                    formData.append('from_modal', '1');

                    const response = await fetch('{{ route("master.states.store") }}', {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        }
                    });

                    const data = await response.json();

                    if (data.success && data.state) {
                        // Update dropdowns
                        updateStateDropdowns(data.state);

                        // Select new state
                        document.getElementById('state_id').value = data.state.id;
                        document.getElementById('state_id').dispatchEvent(new Event('change'));

                        // Clean close modal
                        closeModalClean('addStateModal');

                        // Reset form
                        this.reset();
                    } else {
                        throw new Error(data.message || 'Failed to add state');
                    }
                } catch (error) {
                    console.error('Error:', error);
                    if (errorDiv) {
                        errorDiv.innerHTML = error.message || 'An error occurred while adding the state.';
                        errorDiv.classList.remove('d-none');
                    }
                } finally {
                    submitBtn.disabled = false;
                    spinner?.classList.add('d-none');
                }
            });
        }

        // City Modal Handler
        const cityForm = document.getElementById('addCityForm');
        if (cityForm) {
            cityForm.addEventListener('submit', async function(e) {
                e.preventDefault();

                const submitBtn = this.querySelector('button[type="submit"]');
                const spinner = submitBtn.querySelector('.spinner-border');
                const errorDiv = document.getElementById('cityFormErrors');

                // Show loading
                submitBtn.disabled = true;
                spinner?.classList.remove('d-none');
                errorDiv?.classList.add('d-none');

                try {
                    const formData = new FormData(this);
                    formData.append('from_modal', '1');

                    const response = await fetch('{{ route("master.cities.store") }}', {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        }
                    });

                    const data = await response.json();

                    if (data.success && data.city) {
                        // Update dropdown
                        updateCityDropdown(data.city);

                        // Select new city
                        document.getElementById('city_id').value = data.city.id;
                        document.getElementById('city_id').dispatchEvent(new Event('change'));

                        // Clean close modal
                        closeModalClean('addCityModal');

                        // Reset form
                        this.reset();
                    } else {
                        throw new Error(data.message || 'Failed to add city');
                    }
                } catch (error) {
                    console.error('Error:', error);
                    if (errorDiv) {
                        errorDiv.innerHTML = error.message || 'An error occurred while adding the city.';
                        errorDiv.classList.remove('d-none');
                    }
                } finally {
                    submitBtn.disabled = false;
                    spinner?.classList.add('d-none');
                }
            });
        }

        // Note: The country, state, and city cascading selects are handled by dynamic-dropdowns.js
        // No need for duplicate event listeners here

        // Handle city change with validation
        $('#city_id').on('change', function() {
            const cityId = $(this).val();
            const stateId = $('#state_id').val();
            const countryId = $('#country_id').val();

            // Clear validation errors
            $(this).removeClass('is-invalid');
            $(this).next('.invalid-feedback').remove();

            // Additional client-side validation
            if (cityId && (!stateId || !countryId)) {
                $(this).addClass('is-invalid');
                $(this).after('<div class="invalid-feedback">Please select country and state first.</div>');
            }
        });
    });

    // Helper functions for modal management
    function updateCountryDropdowns(country) {
        const dropdowns = ['country_id', 'state_country_id', 'city_country_id'];
        dropdowns.forEach(id => {
            const select = document.getElementById(id);
            if (select) {
                const option = document.createElement('option');
                option.value = country.id;
                option.textContent = country.name;
                select.appendChild(option);
            }
        });
    }

    function updateStateDropdowns(state) {
        const dropdowns = ['state_id', 'city_state_id'];
        dropdowns.forEach(id => {
            const select = document.getElementById(id);
            if (select) {
                const option = document.createElement('option');
                option.value = state.id;
                option.textContent = state.name;
                select.appendChild(option);
            }
        });
    }

    function updateCityDropdown(city) {
        const select = document.getElementById('city_id');
        if (select) {
            const option = document.createElement('option');
            option.value = city.id;
            option.textContent = city.name;
            select.appendChild(option);
        }
    }

    function closeModalClean(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            // Use Bootstrap's modal instance
            const bsModal = bootstrap.Modal.getInstance(modal) || new bootstrap.Modal(modal);
            bsModal.hide();

            // Clean up after hide completes
            modal.addEventListener('hidden.bs.modal', function cleanup() {
                document.querySelectorAll('.modal-backdrop').forEach(backdrop => backdrop.remove());
                document.body.classList.remove('modal-open');
                document.body.style.overflow = '';
                document.body.style.paddingRight = '';
                modal.removeEventListener('hidden.bs.modal', cleanup);
            }, { once: true });
        }
    }

</script>

<script src="/js/dynamic-dropdowns.js"></script>

@endsection

@section('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Function to convert input to uppercase
        function convertToUpperCase(input) {
            input.value = input.value.toUpperCase();
        }

        // PAN Number field
        const panField = document.getElementById('pan_number');
        if (panField) {
            panField.addEventListener('input', function() {
                convertToUpperCase(this);
            });
        }

        // GST Number field
        const gstField = document.getElementById('gst_number');
        if (gstField) {
            gstField.addEventListener('input', function() {
                convertToUpperCase(this);
            });
        }
    });
</script>
@endsection
