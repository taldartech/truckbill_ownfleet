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
    <!--begin::Header-->
    <div class="card-header">
        <div class="card-title h4">Add New Transporter</div>
    </div>
    <!--end::Header-->
    <!--begin::Form-->
    <form action="{{ route('transporters.store') }}" method="POST" class="needs-validation" novalidate="">
        @csrf
        @php
            $canEditCompany = auth()->check() && auth()->user()->userRole->name === 'super_admin';
            $userCompanyId = auth()->user()->company_id ?? null;
        @endphp
        @error('registered_truck_id')
            <div class="text-danger">{{ $message }}</div>
        @enderror
        <div class="card-body">
            {{-- Company field hidden as requested --}}
            <input type="hidden" name="company_id" value="{{ $userCompanyId }}">
            <input type="hidden" name="transporter_master_id" id="transporter_master_id" value="{{ old('transporter_master_id') }}">
            <input type="hidden" id="pan_verified" value="0">

            <!-- PAN Check Section - must complete before rest of form -->
            <div class="card mb-4 p-3 bg-light" id="panLookupSection">
                <h5 class="mb-3"><i class="fas fa-search me-2"></i> Enter PAN Number First</h5>
                <p class="text-muted small mb-3">Enter the 10-digit PAN and click Check. The form will appear only after a successful PAN check.</p>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="pan_lookup" class="form-label">PAN Number *</label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="pan_lookup" placeholder="Enter 10-digit PAN" maxlength="10" style="text-transform: uppercase;">
                            <button type="button" class="btn btn-brand-primary" id="btnPanLookup">
                                <i class="fas fa-check me-1"></i> Check PAN
                            </button>
                        </div>
                        <div id="panLookupMessage" class="mt-2 small"></div>
                    </div>
                </div>
            </div>

            <!-- Already registered (same company, transporter) -->
            <div id="panAlreadyRegisteredSection" class="alert alert-warning d-none" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <strong>Already registered.</strong> This PAN is already registered as a transporter/agency for this company.
            </div>

            <!-- Rest of form (shown only after PAN check and not already registered) -->
            <div id="restOfForm" class="d-none">
            <!-- Master Data (Read-only when found via PAN) -->
            <div id="masterDataSection" class="d-none">
                <div class="card mb-4 p-3 bg-light">
                    <h5 class="mb-3 text-muted"><i class="fas fa-lock me-2"></i> Master Data (Read-only from PAN)</h5>
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label class="form-label">Transport Name</label>
                            <div class="form-control-plaintext bg-white border rounded px-3 py-2" id="display_name">-</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Email</label>
                            <div class="form-control-plaintext bg-white border rounded px-3 py-2" id="display_email">-</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">PAN Number</label>
                            <div class="form-control-plaintext bg-white border rounded px-3 py-2" id="display_pan">-</div>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label class="form-label">GST Number</label>
                            <div class="form-control-plaintext bg-white border rounded px-3 py-2" id="display_gst">-</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">PAN Active</label>
                            <div class="form-control-plaintext bg-white border rounded px-3 py-2" id="display_pan_active">-</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Address</label>
                            <div class="form-control-plaintext bg-white border rounded px-3 py-2" id="display_address">-</div>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label class="form-label">Country / State / City</label>
                            <div class="form-control-plaintext bg-white border rounded px-3 py-2" id="display_location">-</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- New Master Form (when PAN not found) -->
            <div id="newMasterFormSection">
                <div class="row mb-3">
                    <div class="col-md-4">
                        <label for="name" class="form-label">Transport Name *</label>
                        <input placeholder="Enter Transport Name" type="text" class="form-control" id="name" name="name" value="{{ old('name') }}">
                        @error('name') <div class="text-danger">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-4">
                        <label for="email" class="form-label">Email *</label>
                        <input type="email" class="form-control" id="email" name="email" value="{{ old('email') }}" placeholder="Enter Email">
                        @error('email') <div class="text-danger">{{ $message }}</div> @enderror
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-4">
                        <label for="gst_number" class="form-label">GST Number</label>
                        <input placeholder="Enter GST Number" type="text" class="form-control" id="gst_number" name="gst_number" value="{{ old('gst_number') }}">
                        @error('gst_number') <div class="text-danger">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-4">
                        <label for="pan_number" class="form-label">PAN Number *</label>
                        <input placeholder="Enter PAN Number" type="text" class="form-control" id="pan_number" name="pan_number" value="{{ old('pan_number') }}" maxlength="10" style="text-transform: uppercase;">
                        @error('pan_number') <div class="text-danger">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">PAN is Active *</label><br>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="pan_active" id="pan_active_yes" value="1" {{ old('pan_active') === '1' ? 'checked' : '' }}>
                            <label class="form-check-label" for="pan_active_yes">Yes</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="pan_active" id="pan_active_no" value="0" {{ old('pan_active') === '0' ? 'checked' : '' }}>
                            <label class="form-check-label" for="pan_active_no">No</label>
                        </div>
                        @error('pan_active') <div class="text-danger">{{ $message }}</div> @enderror
                    </div>
                </div>
            </div>

            <!-- Editable: Contact Person & Mobile (always) -->
            <div class="row mb-3">
                <div class="col-md-4">
                    <label for="contact_person_name" class="form-label">Contact Person Name</label>
                    <input type="text" class="form-control @error('contact_person_name') is-invalid @enderror" id="contact_person_name" name="contact_person_name" value="{{ old('contact_person_name') }}" placeholder="Enter Contact Person Name">
                    @error('contact_person_name') <div class="text-danger">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-4">
                    <label for="mobile_number" class="form-label">Mobile Number *</label>
                    <input type="text" class="form-control" id="mobile_number" name="mobile_number" required value="{{ old('mobile_number') }}" placeholder="Enter 10-digit Mobile" maxlength="10">
                    @error('mobile_number') <div class="text-danger">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label">Book Balance</label>
                    <div class="form-control-plaintext bg-light border rounded px-3 py-2">
                        <strong> 0.00</strong>
                        <small class="text-muted d-block">Read-only field</small>
                    </div>
                    <input type="hidden" name="advance_amount" value="0">
                </div>
            </div>

            <!-- Location Details Section (for new master only) -->
            <div id="locationDetailsSection" class="card mb-4 p-3 bg-light">
                <div class="row"><div class="col"><h5 class="mb-3">Location Details</h5></div></div>
                <div class="row mb-3">
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="country_id" class="form-label">Country *</label>
                            <div class="input-group">
                                <select class="form-select" id="country_id" name="country_id" required>
                                    <option value="">Select Country</option>
                                    @foreach($countries as $country)
                                        <option value="{{ $country->id }}" {{ old('country_id') == $country->id ? 'selected' : '' }}>
                                            {{ $country->name }}
                                        </option>
                                    @endforeach
                                </select>
                                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCountryModal" title="Add New Country">
                                    <i class="bi bi-plus"></i>
                                </button>
                            </div>
                            @error('country_id') <div class="text-danger">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-4">
                            <label for="state_id" class="form-label">State *</label>
                            <div class="input-group">
                                <select class="form-select" id="state_id" name="state_id" required>
                                    <option value="">Select State</option>
                                    @if(old('country_id'))
                                        @foreach($states as $state)
                                            <option value="{{ $state->id }}" {{ old('state_id') == $state->id ? 'selected' : '' }}>
                                                {{ $state->name }}
                                            </option>
                                        @endforeach
                                    @endif
                                </select>
                                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addStateModal" title="Add New State">
                                    <i class="bi bi-plus"></i>
                                </button>
                            </div>
                            @error('state_id') <div class="text-danger">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-4">
                            <label for="city_id" class="form-label">City *</label>
                            <div class="input-group">
                                <select class="form-select" id="city_id" name="city_id" required>
                                    <option value="">Select City</option>
                                    @if(old('state_id'))
                                        @foreach($cities as $city)
                                            <option value="{{ $city->id }}" {{ old('city_id') == $city->id ? 'selected' : '' }}>
                                                {{ $city->name }}
                                            </option>
                                        @endforeach
                                    @endif
                                </select>
                                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCityModal" title="Add New City">
                                    <i class="bi bi-plus"></i>
                                </button>
                            </div>
                            @error('city_id') <div class="text-danger">{{ $message }}</div> @enderror
                        </div>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-12">
                        <label for="address" class="form-label">Address</label>
                        <textarea class="form-control" id="address" name="address" rows="3" placeholder="Enter complete address">{{ old('address') }}</textarea>
                        @error('address') <div class="text-danger">{{ $message }}</div> @enderror
                    </div>
                </div>
            </div>

            <div class="card mb-4 p-3 bg-light">
                <div class="row"><div class="col"><h5 class="mb-3">Declaration &amp; TDS</h5></div></div>
                <div class="row mb-3">
                    <div class="col-md-4">
                        <label class="form-label">Declaration given? *</label><br>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="declaration_given" id="decl_yes" value="1" {{ old('declaration_given', 0) == 1 ? 'checked' : '' }} required>
                            <label class="form-check-label" for="decl_yes">Yes</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="declaration_given" id="decl_no" value="0" {{ old('declaration_given', 0) != 1 ? 'checked' : '' }} required>
                            <label class="form-check-label" for="decl_no">No</label>
                        </div>
                        @error('declaration_given') <div class="text-danger">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-4">
                        <label for="tds_percentage" class="form-label">TDS % *</label>
                        <input type="hidden" id="tds_calculated_baseline" value="{{ old('tds_percentage', '0') }}">
                        <input type="number" step="0.01" min="0" max="100" class="form-control @error('tds_percentage') is-invalid @enderror" id="tds_percentage" name="tds_percentage" value="{{ old('tds_percentage', '0') }}" required>
                        @error('tds_percentage') <div class="text-danger">{{ $message }}</div> @enderror
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-12">
                        <label for="tds_adjustment_remark" class="form-label">TDS adjustment remark</label>
                        <textarea class="form-control @error('tds_adjustment_remark') is-invalid @enderror" id="tds_adjustment_remark" name="tds_adjustment_remark" rows="2" placeholder="Required when TDS % differs from the calculated rate">{{ old('tds_adjustment_remark') }}</textarea>
                        @error('tds_adjustment_remark') <div class="text-danger">{{ $message }}</div> @enderror
                    </div>
                </div>
            </div>

            <!-- Quick Actions Section -->
            <div class="card p-3 bg-light">
                <div class="row"><div class="col"><h5 class="mb-3">Quick Actions</h5></div></div>
                <div class="row g-2">
                    <div class="col-md-4">
                        <button class="btn btn-brand-primary w-100 mb-2" type="button" onclick="showConfirmationModal()">💾 Submit Form</button>
                    </div>
                    <div class="col-md-4">
                        <a href="{{ route('transporters.index') }}" class="btn btn-back w-100 mb-2">📄 Back to List</a>
                    </div>
                </div>
            </div>
            </div><!-- /#restOfForm -->
        </div>
    </form>
</div>

<!-- Confirmation Modal -->
<div class="modal fade" id="confirmationModal" tabindex="-1" aria-labelledby="confirmationModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-gradient-primary">
                <h5 class="modal-title" id="confirmationModalLabel">
                    <i class="fas fa-check-circle me-2"></i>Confirm Transporter Details
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    Please review the details below before submitting the form.
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
                    <i class="fas fa-check me-2"></i>Confirm & Submit
                </button>
            </div>
        </div>
    </div>
</div>
<!-- Add Country Modal -->
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

  @endsection
@section('scripts')
@parent
    <script>

        @if($errors->any())
            document.getElementById('restOfForm').classList.remove('d-none');
        @endif
      document.addEventListener('DOMContentLoaded', function() {
        const btnPanLookup = document.getElementById('btnPanLookup');
        const panLookup = document.getElementById('pan_lookup');
        const panField = document.getElementById('pan_number');
        const panVerifiedInput = document.getElementById('pan_verified');
        const panLookupMessage = document.getElementById('panLookupMessage');
        const masterDataSection = document.getElementById('masterDataSection');
        const newMasterFormSection = document.getElementById('newMasterFormSection');
        const transporterMasterId = document.getElementById('transporter_master_id');
        const restOfForm = document.getElementById('restOfForm');
        const panAlreadyRegisteredSection = document.getElementById('panAlreadyRegisteredSection');
        let lastCheckedPan = '';

        function invalidatePanVerification(changedPan) {
          if (!panVerifiedInput) return;
          if (panVerifiedInput.value !== '1') return;
          if ((changedPan || '').toUpperCase() === (lastCheckedPan || '').toUpperCase()) return;
          panVerifiedInput.value = '0';
          if (restOfForm) restOfForm.classList.add('d-none');
          if (panAlreadyRegisteredSection) panAlreadyRegisteredSection.classList.add('d-none');
          panLookupMessage.textContent = 'PAN changed. Please click Check PAN again.';
          panLookupMessage.className = 'mt-2 small text-warning';
        }

        if (btnPanLookup) {
          btnPanLookup.addEventListener('click', function() {
            const pan = (panLookup?.value || '').trim().toUpperCase();
            if (pan.length !== 10 || !/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/.test(pan)) {
              panLookupMessage.textContent = 'Please enter a valid 10-digit PAN.';
              panLookupMessage.className = 'mt-2 small text-danger';
              return;
            }
            const companyId = document.querySelector('input[name="company_id"]')?.value || '';
            if (!companyId) {
              panLookupMessage.textContent = 'Company is required.';
              panLookupMessage.className = 'mt-2 small text-danger';
              return;
            }
            btnPanLookup.disabled = true;
            panLookupMessage.textContent = 'Checking PAN...';
            panLookupMessage.className = 'mt-2 small text-muted';
            if (panAlreadyRegisteredSection) panAlreadyRegisteredSection.classList.add('d-none');
            if (restOfForm) restOfForm.classList.add('d-none');

            const url = '/api/transporter-master-by-pan?pan_number=' + encodeURIComponent(pan) + '&company_id=' + encodeURIComponent(companyId) + '&is_owner=0';
            fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
            .then(r => r.ok ? r.json() : r.json().then(d => { throw new Error(d.error || 'Request failed'); }))
            .then(data => {
              if (data.already_registered) {
                if (panVerifiedInput) panVerifiedInput.value = '0';
                panLookupMessage.textContent = 'This PAN is already registered as a transporter/agency for this company.';
                panLookupMessage.className = 'mt-2 small text-danger';
                if (panAlreadyRegisteredSection) panAlreadyRegisteredSection.classList.remove('d-none');
                return;
              }
              if (restOfForm) restOfForm.classList.remove('d-none');
              if (data.found && data.master) {
                document.getElementById('display_name').textContent = data.master.name || '-';
                document.getElementById('display_email').textContent = data.master.email || '-';
                document.getElementById('display_pan').textContent = data.master.pan_number || '-';
                document.getElementById('display_gst').textContent = data.master.gst_number || '-';
                document.getElementById('display_pan_active').textContent = data.master.pan_active ? 'Yes' : 'No';
                document.getElementById('display_address').textContent = data.master.address || '-';
                document.getElementById('display_location').textContent = [data.master.country, data.master.state, data.master.city].filter(Boolean).join(' / ') || '-';
                transporterMasterId.value = data.master.id;
                masterDataSection.classList.remove('d-none');
                newMasterFormSection.classList.add('d-none');
                document.getElementById('locationDetailsSection')?.classList.add('d-none');
                document.getElementById('name').removeAttribute('required');
                document.getElementById('pan_number').removeAttribute('required');
                document.getElementById('email').removeAttribute('required');
                document.getElementById('country_id').removeAttribute('required');
                document.getElementById('state_id').removeAttribute('required');
                document.getElementById('city_id').removeAttribute('required');
                panLookupMessage.textContent = 'PAN verified. Enter contact person and mobile below.';
                panLookupMessage.className = 'mt-2 small text-success';
              } else {
                transporterMasterId.value = '';
                masterDataSection.classList.add('d-none');
                newMasterFormSection.classList.remove('d-none');
                document.getElementById('locationDetailsSection')?.classList.remove('d-none');
                document.getElementById('name').setAttribute('required', 'required');
                document.getElementById('pan_number').setAttribute('required', 'required');
                document.getElementById('pan_number').value = pan;
                document.getElementById('email').setAttribute('required', 'required');
                document.getElementById('country_id').setAttribute('required', 'required');
                document.getElementById('state_id').setAttribute('required', 'required');
                document.getElementById('city_id').setAttribute('required', 'required');
                panLookupMessage.textContent = 'PAN not in system. Please fill in all master details below.';
                panLookupMessage.className = 'mt-2 small text-info';
              }
              if (panVerifiedInput) panVerifiedInput.value = '1';
              lastCheckedPan = pan;
              window.__agencyTdsUserEdited = false;
              if (typeof refreshAgencyTdsBaseline === 'function') {
                refreshAgencyTdsBaseline();
              }
            })
            .catch((err) => {
              if (panVerifiedInput) panVerifiedInput.value = '0';
              panLookupMessage.textContent = err.message || 'Check failed. Please try again.';
              panLookupMessage.className = 'mt-2 small text-danger';
            })
            .finally(() => { btnPanLookup.disabled = false; });
          });
        }
        if (panLookup) {
          panLookup.addEventListener('input', function() {
            this.value = this.value.toUpperCase();
            invalidatePanVerification(this.value);
            if (panField) {
              panField.value = this.value;
            }
          });
        }
        if (panField) {
          panField.addEventListener('input', function() {
            this.value = this.value.toUpperCase();
            if (panLookup && panLookup.value !== this.value) {
              panLookup.value = this.value;
            }
            invalidatePanVerification(this.value);
          });
        }
        document.querySelectorAll('input[name="declaration_given"]').forEach(function (el) {
          el.addEventListener('change', function () {
            window.__agencyTdsUserEdited = false;
            if (typeof refreshAgencyTdsBaseline === 'function') {
              refreshAgencyTdsBaseline();
            }
          });
        });
        document.querySelectorAll('input[name="pan_active"]').forEach(function (el) {
          el.addEventListener('change', function () {
            window.__agencyTdsUserEdited = false;
            if (typeof refreshAgencyTdsBaseline === 'function') {
              refreshAgencyTdsBaseline();
            }
          });
        });
        const panNumInput = document.getElementById('pan_number');
        if (panNumInput) {
          panNumInput.addEventListener('input', function () {
            window.__agencyTdsUserEdited = false;
            if (document.getElementById('transporter_master_id')?.value) return;
            if (typeof refreshAgencyTdsBaseline === 'function') {
              refreshAgencyTdsBaseline();
            }
          });
        }
        const tdsPctInput = document.getElementById('tds_percentage');
        if (tdsPctInput) {
          tdsPctInput.addEventListener('input', function () { window.__agencyTdsUserEdited = true; });
        }
      });
    </script>
    <script>
      // Function to show confirmation modal with form data
      function showConfirmationModal() {
          const panVerifiedInput = document.getElementById('pan_verified');
          if (!panVerifiedInput || panVerifiedInput.value !== '1') {
              const panLookupMessage = document.getElementById('panLookupMessage');
              if (panLookupMessage) {
                  panLookupMessage.textContent = 'Please check PAN again before submitting.';
                  panLookupMessage.className = 'mt-2 small text-danger';
              }
              return;
          }

          // First validate the form

          const form = document.querySelector('.needs-validation');
          if (!form.checkValidity()) {
              form.classList.add('was-validated');
              return;
          }

          // Get form values with safe access (use display when master found)
          const masterId = document.getElementById('transporter_master_id')?.value;
          const name = masterId ? (document.getElementById('display_name')?.textContent || '-') : (document.getElementById('name')?.value || '-');
          const contactPerson = document.getElementById('contact_person_name')?.value || '-';
          const mobile = document.getElementById('mobile_number')?.value || '-';
          const gstNumber = masterId ? (document.getElementById('display_gst')?.textContent || '-') : (document.getElementById('gst_number')?.value || '-');
          const panNumber = masterId ? (document.getElementById('display_pan')?.textContent || '-') : (document.getElementById('pan_number')?.value || '-');
          const panActive = masterId ? (document.getElementById('display_pan_active')?.textContent || '-') : (document.querySelector('input[name="pan_active"]:checked')?.value === '1' ? 'Yes' : 'No');

          const address = masterId ? (document.getElementById('display_address')?.textContent || '-') : (document.getElementById('address')?.value || '-');
          const countrySelect = document.getElementById('country_id');
          const country = masterId ? (document.getElementById('display_location')?.textContent || '-') : (countrySelect?.options[countrySelect.selectedIndex]?.text || '-');
          const stateSelect = document.getElementById('state_id');
          const state = masterId ? '-' : (stateSelect?.options[stateSelect.selectedIndex]?.text || '-');
          const citySelect = document.getElementById('city_id');
          const city = masterId ? '-' : (citySelect?.options[citySelect.selectedIndex]?.text || '-');

          const isOwner = 'No';
          const declaration = document.querySelector('input[name="declaration_given"]:checked')?.value === '1' ? 'Yes' : 'No';
          const advance = document.querySelector('input[name="advance_amount"]')?.value || '0';

          // Populate modal with values
          document.getElementById('confirm-name').textContent = name;
          document.getElementById('confirm-contact-person').textContent = contactPerson;
          document.getElementById('confirm-mobile').textContent = mobile;

          document.getElementById('confirm-gst-number').textContent = gstNumber;

          document.getElementById('confirm-pan-number').textContent = panNumber;
          document.getElementById('confirm-pan-active').textContent = panActive;

          const tdsField = document.getElementById('tds_percentage');
          calculateTdsPercentageFromApi(panNumber, panActive, function (calculatedStr) {
            const submitted = parseFloat(tdsField?.value || '0');
            const calculated = parseFloat(calculatedStr);
            let text = (isNaN(submitted) ? '0' : submitted.toFixed(2)) + '%';
            if (!isNaN(calculated) && Math.abs(submitted - calculated) > 0.01) {
              text += ' (calculated ' + calculated.toFixed(2) + '%)';
            }
            document.getElementById('confirm-tds-percentage').textContent = text;
          });

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

      // Function to calculate TDS percentage from API
      function calculateTdsPercentageFromApi(panNumber, panActive, onDone) {
          const panActiveValue = panActive === 'Yes' ? '1' : '0';
          const declarationGiven = document.querySelector('input[name="declaration_given"]:checked')?.value === '1';

          fetch('/api/calculate-tds-percentage', {
              method: 'POST',
              headers: {
                  'Content-Type': 'application/json',
                  'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
              },
              body: JSON.stringify({
                  pan_number: panNumber === '-' ? '' : panNumber,
                  pan_active: panActiveValue,
                  declaration_given: declarationGiven ? '1' : '0',
              })
          })
          .then(response => response.json())
          .then(data => {
              if (typeof onDone === 'function') {
                onDone(data.tds_percentage);
              } else {
                document.getElementById('confirm-tds-percentage').textContent = data.tds_percentage + '%';
              }
          })
          .catch(error => {
              console.error('Error calculating TDS percentage:', error);
              document.getElementById('confirm-tds-percentage').textContent = '-';
          });
      }

      window.__agencyTdsUserEdited = false;
      function refreshAgencyTdsBaseline() {
        const masterId = document.getElementById('transporter_master_id')?.value;
        let panNumber = '';
        let panActiveLabel = 'No';
        if (masterId) {
          panNumber = document.getElementById('display_pan')?.textContent?.trim() || '';
          panActiveLabel = document.getElementById('display_pan_active')?.textContent?.trim() === 'Yes' ? 'Yes' : 'No';
        } else {
          panNumber = document.getElementById('pan_number')?.value?.trim() || '';
          panActiveLabel = document.querySelector('input[name="pan_active"]:checked')?.value === '1' ? 'Yes' : 'No';
        }
        if (!panNumber || panNumber === '-') return;
        calculateTdsPercentageFromApi(panNumber, panActiveLabel, function (calculatedStr) {
          const calculated = parseFloat(calculatedStr);
          if (isNaN(calculated)) return;
          document.getElementById('tds_calculated_baseline').value = calculated.toFixed(2);
          if (!window.__agencyTdsUserEdited) {
            document.getElementById('tds_percentage').value = calculated.toFixed(2);
          }
        });
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

        // Fetch all the forms we want to apply custom Bootstrap validation styles to
        const forms = document.querySelectorAll('.needs-validation');

        // Loop over them and prevent submission
        Array.from(forms).forEach((form) => {
          form.addEventListener(
            'submit',
            (event) => {
              // Allow form submission since we handle validation in showConfirmationModal
              form.classList.add('was-validated');
            },
            false,
          );
        });
      })();
    </script>
    <!--end::JavaScript-->
  </div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const modalCountry = document.querySelector('#addCityModal #modal_city_country_id');
        const modalState = document.querySelector('#addCityModal #modal_city_state_id');

        if (modalCountry && modalState) {
            modalCountry.addEventListener('change', function () {
                const countryId = this.value;

                // Clear current state options
                modalState.innerHTML = '<option value="">Select State</option>';

                if (countryId) {
                    fetch(`/api/states/${countryId}`)
                        .then(response => response.json())
                        .then(states => {
                            states.forEach(state => {
                                const option = document.createElement('option');
                                option.value = state.id;
                                option.textContent = state.name;
                                modalState.appendChild(option);
                            });
                            modalState.required = true;
                        })
                        .catch(error => console.error('Error fetching states for modal:', error));
                } else {
                    modalState.required = false;
                }
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
                                'Accept': 'application/json'
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
                                'Accept': 'application/json'
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
                                'Accept': 'application/json'
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

            // Note: Country change is handled by dynamic-dropdowns.js

            // Note: State change is handled by dynamic-dropdowns.js

            // Handle city change with validation
            $('#city_id').on('change', function() {
                const cityId = $(this).val();
                const stateId = $('#state_id').val();
                const countryId = $('#country_id').val();

                // Clear validation errors
                $(this).removeClass('is-invalid');
                $(this).next('.invalid-feedback').remove();

                // Additional client-side validation can be added here
                if (cityId && (!stateId || !countryId)) {
                    $(this).addClass('is-invalid');
                    $(this).after('<div class="invalid-feedback">Please select country and state first.</div>');
                }
            });

            // Function to show success message
            function showSuccessMessage(message) {
                // Remove any existing alerts
                $('.alert-success').remove();

                // Create and show success alert
                const alertHtml = `
                    <div class="alert alert-success alert-dismissible fade show mt-2" role="alert">
                        <i class="bi bi-check-circle-fill"></i> ${message}
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
                        <i class="bi bi-exclamation-triangle-fill"></i> ${message}
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

    // Note: The country, state, and city cascading selects are handled by dynamic-dropdowns.js
    // No need for duplicate event listeners here
    // Helper functions for modal management
    function updateCountryDropdowns(country) {
        const dropdowns = ['country_id', 'modal_state_country_id', 'modal_city_country_id'];
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
        const dropdowns = ['state_id', 'modal_city_state_id'];
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
    <script src="/js/dynamic-dropdowns.js"></script>

@endsection
