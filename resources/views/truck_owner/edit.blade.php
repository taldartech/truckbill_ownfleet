@extends('layouts.app')

@section('content')
<link href="{{ 'https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css' }}" rel="stylesheet" />
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

.truck-modal-driver-row .flex-grow-1 {
    min-width: 0;
}

.truck-modal-driver-row .select2-container {
    width: 100% !important;
}

.truck-modal-driver-row .select2-container .select2-selection--single {
    min-height: 38px;
    display: flex;
    align-items: center;
}
</style>
<div class="card card-info card-outline mb-4" style="max-width: 1000px; margin: 0 auto;">
    <div class="card-header">
        <div class="card-title h4">Edit Truck Owner</div>
    </div>

    <!--begin::Form-->
    <form action="{{ route('truck_owners.update', $truck_owner) }}" method="POST" class="needs-validation" novalidate="">
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
            <input type="hidden" name="is_owner" value="1">
            <input type="hidden" name="company_id" value="{{ $userCompanyId }}">
            <input type="hidden" name="advance_amount" value="{{ $truck_owner->advance_amount }}">

            @php $master = $truck_owner->transporterMaster; @endphp
            <div class="card mb-4 p-3 bg-light" id="masterDataSection">
                <div class="row"><div class="col"><h5 class="mb-3 text-muted"><i class="fas fa-lock me-2"></i> Master Data (Read-only)</h5></div></div>
                <div class="row mb-3">
                    <div class="col-md-4">
                        <label class="form-label">Name</label>
                        <div class="form-control-plaintext bg-white border rounded px-3 py-2" id="display_master_name">{{ $master->name ?? '-' }}</div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">PAN Number</label>
                        <div class="form-control-plaintext bg-white border rounded px-3 py-2" id="display_master_pan">{{ $master->pan_number ?? '-' }}</div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">GST Number</label>
                        <div class="form-control-plaintext bg-white border rounded px-3 py-2" id="display_master_gst">{{ $master->gst_number ?? '-' }}</div>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-4">
                        <label class="form-label">Address</label>
                        <div class="form-control-plaintext bg-white border rounded px-3 py-2" id="display_master_address">{{ $master->address ?? '-' }}</div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">City / State / Country</label>
                        <div class="form-control-plaintext bg-white border rounded px-3 py-2" id="display_master_location">{{ optional($master->city)->name ?? '-' }} / {{ optional($master->state)->name ?? '-' }} / {{ optional($master->country)->name ?? '-' }}</div>
                    </div>
                </div>
            </div>

            <div class="card mb-4 p-3 bg-light">
                <div class="row"><div class="col"><h5 class="mb-3">Editable Fields</h5></div></div>
                <div class="row mb-3">
                    <div class="col-md-4">
                        <label for="contact_person_name" class="form-label">Contact Person Name</label>
                        <input type="text" class="form-control" id="contact_person_name" name="contact_person_name" value="{{ old('contact_person_name', $truck_owner->contact_person_name) }}">
                        @error('contact_person_name') <div class="text-danger">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-4">
                        <label for="mobile_number" class="form-label">Mobile Number *</label>
                        <input type="text" class="form-control" id="mobile_number" name="mobile_number" required value="{{ old('mobile_number', $truck_owner->mobile_number) }}" maxlength="10">
                        @error('mobile_number') <div class="text-danger">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-4">
                        <label for="short_name" class="form-label">Short Name</label>
                        @if(auth()->user()->userRole->name === 'super_admin')
                            <input type="text" class="form-control" id="short_name" name="short_name" value="{{ old('short_name', $truck_owner->short_name) }}">
                        @else
                            <div class="form-control-plaintext bg-light border rounded px-3 py-2">{{ $truck_owner->short_name ?? '-' }}</div>
                            <small class="text-muted">Editable only from Super Admin panel</small>
                        @endif
                        @error('short_name') <div class="text-danger">{{ $message }}</div> @enderror
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-4">
                        <label class="form-label">Book Balance</label>
                        <div class="form-control-plaintext bg-light border rounded px-3 py-2"><strong> {{ number_format($truck_owner->advance_amount, 2) }}</strong></div>
                    </div>
                    <div class="col-md-4">
                        <label for="beneficiary_id" class="form-label">Beneficiary ID</label>
                        @if(auth()->user()->userRole->name === 'super_admin')
                            <input type="text" class="form-control" id="beneficiary_id" name="beneficiary_id" value="{{ old('beneficiary_id', $truck_owner->beneficiary_id) }}">
                        @else
                            <div class="form-control-plaintext bg-light border rounded px-3 py-2">{{ $truck_owner->beneficiary_id ?? '-' }}</div>
                            <small class="text-muted">Editable only from Super Admin panel</small>
                        @endif
                        @error('beneficiary_id') <div class="text-danger">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-4">
                        <label for="tds_percentage" class="form-label">TDS % *</label>
                        <input type="hidden" id="tds_calculated_baseline" value="{{ number_format(old('tds_percentage', $truck_owner->tds_percentage ?? 0), 2, '.', '') }}">
                        <input type="number" step="0.01" min="0" max="100" class="form-control @error('tds_percentage') is-invalid @enderror" name="tds_percentage" id="tds_percentage" value="{{ old('tds_percentage', $truck_owner->tds_percentage) }}" required>
                        @error('tds_percentage') <div class="text-danger">{{ $message }}</div> @enderror
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-12">
                        <label for="tds_adjustment_remark" class="form-label">TDS adjustment remark</label>
                        <textarea class="form-control @error('tds_adjustment_remark') is-invalid @enderror" id="tds_adjustment_remark" name="tds_adjustment_remark" rows="2" placeholder="Required when TDS % differs from the calculated rate">{{ old('tds_adjustment_remark', $truck_owner->tds_adjustment_remark) }}</textarea>
                        @error('tds_adjustment_remark') <div class="text-danger">{{ $message }}</div> @enderror
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-4">
                        <label class="form-label">Declaration Given? *</label><br>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="declaration_given" id="decl_yes" value="1" {{ old('declaration_given', $truck_owner->declaration_given) == 1 ? 'checked' : '' }} required>
                            <label class="form-check-label" for="decl_yes">Yes</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="declaration_given" id="decl_no" value="0" {{ old('declaration_given', $truck_owner->declaration_given) == 0 ? 'checked' : '' }} required>
                            <label class="form-check-label" for="decl_no">No</label>
                        </div>
                        @error('declaration_given') <div class="text-danger">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-4">
                        <label for="total_trucks" class="form-label">Total Trucks *</label>
                        <input type="number" class="form-control" id="total_trucks" name="total_trucks" min="0" required value="{{ old('total_trucks', $truck_owner->total_trucks) }}">
                        @error('total_trucks') <div class="text-danger">{{ $message }}</div> @enderror
                    </div>
                </div>
            </div>
            <!-- Registered Trucks Section -->
            <!-- Form Actions -->
            <div class="d-flex justify-content-between align-items-center mt-4">
                <a href="{{ route('truck_owners.index') }}" class="btn btn-back">
                    <i class="fas fa-arrow-left me-2"></i>Back to List
                </a>
                <button type="button" class="btn btn-brand-primary" id="submitFormBtn">
                    <i class="fas fa-save me-2"></i>Update Truck Owner
                </button>
            </div>
        </div>
    </form>
</div>

<!-- Registered Trucks Section -->
<div class="card card-info card-outline mb-4" style="max-width: 1000px; margin: 0 auto;">
    <div class="card-header">
        <div class="card-title h5">
            <i class="bi bi-truck"></i> Registered Trucks
        </div>
        <div class="card-tools">
            <button type="button" class="btn btn-brand-primary btn-sm" id="addNewTruckBtn">
                <i class="bi bi-plus-circle"></i> Add New Truck
            </button>
        </div>
    </div>
    <div class="card-body">
        <div id="trucksContainer">
            <div class="table-responsive">
                <table class="table table-striped table-hover" id="trucksTable">
                    <thead class="table-dark">
                        <tr>
                            <th>Truck Number</th>
                            <th>Truck Type</th>
                            <th>Driver</th>
                            <th>Driver Mobile</th>
                            <th>Registration Date</th>
                            <th width="120px">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($truck_owner->trucks as $truck)
                        <tr id="truck-row-{{ $truck->id }}">
                            <td class="fw-bold">{{ $truck->truck_number }}</td>
                            <td>{{ $truck->truck_type }}</td>
                            <td>{{ $truck->driver->name ?? 'Not Assigned' }}</td>
                            <td>{{ $truck->driver->mobile ?? '-' }}</td>
                            <td>{{ formatDate(optional($truck)->registration_date) }}</td>
                            <td>
                                <div class="btn-group" role="group">
                                    <button type="button" class="btn btn-sm btn-primary edit-truck-btn"
                                            title="Edit Truck"
                                            data-truck-id="{{ $truck->id }}"
                                            data-truck-number="{{ $truck->truck_number }}"
                                            data-truck-type="{{ $truck->truck_type }}"
                                            data-driver-id="{{ $truck->driver_id }}"
                                            data-driver-name="{{ e($truck->driver->name ?? '') }}"
                                            data-driver-mobile="{{ e($truck->driver->mobile ?? '') }}"
                                            data-registration-date="{{ $truck->registration_date }}">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-danger delete-truck-btn"
                                            title="Delete Truck"
                                            data-truck-id="{{ $truck->id }}"
                                            data-truck-number="{{ $truck->truck_number }}">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                    {{-- add remove icon here --}}
                                    <button type="button" class="btn btn-sm btn-danger remove-truck-btn"
                                            title="Remove Truck"
                                            data-truck-id="{{ $truck->id }}"
                                            data-truck-number="{{ $truck->truck_number }}">
                                        <i class="bi bi-x-circle"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                @if($truck_owner->trucks->isEmpty())
                <div class="text-center py-4">
                    <i class="bi bi-truck text-muted" style="font-size: 3rem;"></i>
                    <p class="text-muted mt-2">No trucks registered yet.</p>
                    <button type="button" class="btn btn-primary" id="addFirstTruckBtn">
                        <i class="bi bi-plus-circle"></i> Add First Truck
                    </button>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>

<!-- Add/Edit Truck Modal -->
<div class="modal fade" id="truckModal" tabindex="-1" aria-labelledby="truckModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-gradient-primary">
                <h5 class="modal-title" id="truckModalLabel">
                    <i class="bi bi-truck"></i> <span id="truckModalTitle">Add New Truck</span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="truckForm" method="POST">
                @csrf
                <input type="hidden" id="truck_id" name="truck_id">
                <input type="hidden" name="transporter_id" value="{{ $truck_owner->id }}">
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="truck_owner_display" class="form-label">Truck Owner *</label>
                            <input type="text" class="form-control" id="truck_owner_display" value="{{ $truck_owner->name }}" disabled>
                        </div>
                        <div class="col-md-6">
                            <label for="truck_number" class="form-label">Truck Number *</label>
                            <input type="text" class="form-control" placeholder="e.g., MH12AB1234 or GJ01CD5678" id="truck_number" name="truck_number" required>
                            <div class="invalid-feedback">Please enter a valid truck number (6-15 characters).</div>
                            <small class="form-text text-muted">Format: State code + digits + letters + digits (e.g., MH12AB1234)</small>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="truck_type" class="form-label">Truck Type *</label>
                            <select class="form-select" id="truck_type" name="truck_type" required>
                                <option value="">Select</option>
                                @foreach($truckTypes ?? [] as $type)
                                    <option value="{{ $type->name }}">{{ $type->name }}</option>
                                @endforeach
                            </select>
                            <div class="invalid-feedback">Please select a truck type.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="driver_id" class="form-label">Driver</label>
                            <div class="d-flex gap-2 align-items-stretch truck-modal-driver-row">
                                <div class="flex-grow-1">
                                    <select class="form-select" id="driver_id" name="driver_id">
                                        <option value="">No Driver</option>
                                    </select>
                                </div>
                                <button type="button" class="btn btn-primary flex-shrink-0" id="showAddDriverFormTruck">
                                    <i class="bi bi-plus-circle"></i> Add New
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="registration_date" class="form-label">Registration Date</label>
                            <input type="date" class="form-control" id="registration_date" name="registration_date">
                            <small class="form-text text-muted">Optional field</small>
                        </div>
                    </div>

                    <!-- Add New Driver Form (Initially Hidden) -->
                    <div id="addDriverFormTruck" style="display: none;">
                        <hr class="my-4">
                        <div class="card border-primary">
                            <div class="card-header bg-gradient-primary">
                                <h6 class="mb-0"><i class="bi bi-person-plus"></i> Add New Driver</h6>
                            </div>
                            <div class="card-body">
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="new_driver_name_truck" class="form-label">Name *</label>
                                        <input type="text" class="form-control" id="new_driver_name_truck" name="new_driver_name">
                                        <div class="invalid-feedback">Please enter a name.</div>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="new_driver_mobile_truck" class="form-label">Mobile *</label>
                                        <input type="text" class="form-control" id="new_driver_mobile_truck" name="new_driver_mobile" pattern="[0-9]{10}" maxlength="10">
                                        <div class="invalid-feedback">Please enter a valid 10-digit mobile number.</div>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="new_driver_license_truck" class="form-label">License Number</label>
                                        <input type="text" class="form-control" id="new_driver_license_truck" name="new_driver_license">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="new_driver_address_truck" class="form-label">Address</label>
                                        <input type="text" class="form-control" id="new_driver_address_truck" name="new_driver_address">
                                    </div>
                                </div>
                                <div class="d-flex gap-2">
                                    <button type="button" class="btn btn-primary" id="addDriverBtnTruck">
                                        <i class="bi bi-check-circle"></i> Add Driver
                                    </button>
                                    <button type="button" class="btn btn-secondary" id="cancelAddDriverTruck">
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
                    <button type="submit" class="btn btn-brand-primary" id="saveTruckBtn">
                        <i class="bi bi-save"></i> <span id="saveTruckText">Add Truck</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Confirmation Modal -->
<div class="modal fade" id="confirmationModal" tabindex="-1" aria-labelledby="confirmationModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-gradient-primary">
                <h5 class="modal-title" id="confirmationModalLabel">
                    <i class="fas fa-check-circle me-2"></i>Confirm Truck Owner Details
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    Please review the details below before updating the truck owner.
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
                        <h6 class="text-primary mb-3"><i class="fas fa-truck me-2"></i>Business Details</h6>
                        <table class="table table-sm table-borderless">
                            <tr><td class="fw-bold">Declaration Given:</td><td id="confirm-declaration">-</td></tr>
                            <tr><td class="fw-bold">Total Trucks:</td><td id="confirm-total-trucks">-</td></tr>
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

{{-- JS for confirmation modal --}}
<script>
    // Master data (read-only from TransporterMaster)
    const masterData = {
        name: {!! json_encode($master->name ?? '') !!},
        panNumber: {!! json_encode($master->pan_number ?? '') !!},
        panActive: {{ $master->pan_active ? 'true' : 'false' }},
        gstNumber: {!! json_encode($master->gst_number ?? '') !!},
        address: {!! json_encode($master->address ?? '') !!},
        location: {!! json_encode((optional($master->city)->name ?? '-') . ' / ' . (optional($master->state)->name ?? '-') . ' / ' . (optional($master->country)->name ?? '-')) !!}
    };

    window.__truckOwnerEditTdsUserEdited = false;
    function refreshTruckOwnerEditTdsBaseline() {
        fetch('/api/calculate-truck-owner-tds-percentage', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({
                pan_number: masterData.panNumber,
                pan_active: masterData.panActive ? '1' : '0',
                declaration_given: document.querySelector('input[name="declaration_given"]:checked')?.value === '1' ? '1' : '0'
            })
        })
        .then(r => r.json())
        .then(data => {
            const calculated = parseFloat(data.tds_percentage);
            if (isNaN(calculated)) return;
            const b = document.getElementById('tds_calculated_baseline');
            if (b) b.value = calculated.toFixed(2);
            if (!window.__truckOwnerEditTdsUserEdited) {
                const inp = document.getElementById('tds_percentage');
                if (inp) inp.value = calculated.toFixed(2);
            }
        })
        .catch(() => {});
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('input[name="declaration_given"]').forEach(function (el) {
            el.addEventListener('change', function () {
                window.__truckOwnerEditTdsUserEdited = false;
                refreshTruckOwnerEditTdsBaseline();
            });
        });
        const te = document.getElementById('tds_percentage');
        if (te) {
            te.addEventListener('input', function () { window.__truckOwnerEditTdsUserEdited = true; });
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

        // Get editable form values
        const contactPerson = document.getElementById('contact_person_name')?.value || '-';
        const mobile = document.getElementById('mobile_number')?.value || '-';
        const declaration = document.querySelector('input[name="declaration_given"]:checked')?.value === '1' ? 'Yes' : 'No';
        const totalTrucks = document.getElementById('total_trucks')?.value || '0';
        const advance = document.querySelector('input[name="advance_amount"]')?.value || '{{ $truck_owner->advance_amount }}';

        // Populate modal - master data (read-only)
        document.getElementById('confirm-name').textContent = masterData.name || '-';
        document.getElementById('confirm-gst-number').textContent = masterData.gstNumber || '-';
        document.getElementById('confirm-pan-number').textContent = masterData.panNumber || '-';
        document.getElementById('confirm-pan-active').textContent = masterData.panActive ? 'Yes' : 'No';
        document.getElementById('confirm-address').textContent = masterData.address || '-';
        const locParts = (masterData.location || '-').split(' / ');
        document.getElementById('confirm-country').textContent = locParts[2] || '-';
        document.getElementById('confirm-state').textContent = locParts[1] || '-';
        document.getElementById('confirm-city').textContent = locParts[0] || '-';

        // Editable fields
        document.getElementById('confirm-contact-person').textContent = contactPerson;
        document.getElementById('confirm-mobile').textContent = mobile;
        document.getElementById('confirm-declaration').textContent = declaration;
        document.getElementById('confirm-total-trucks').textContent = totalTrucks;
        document.getElementById('confirm-advance').textContent = '' + parseFloat(advance || 0).toFixed(2);

        const tdsField = document.getElementById('tds_percentage');
        fetch('/api/calculate-truck-owner-tds-percentage', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({
                pan_number: masterData.panNumber,
                pan_active: masterData.panActive ? '1' : '0',
                declaration_given: declaration === 'Yes' ? '1' : '0'
            })
        })
        .then(r => r.json())
        .then(data => {
            const submitted = parseFloat(tdsField?.value || '0');
            const calculated = parseFloat(data.tds_percentage);
            let text = (isNaN(submitted) ? '0' : submitted.toFixed(2)) + '%';
            if (!isNaN(calculated) && Math.abs(submitted - calculated) > 0.01) {
                text += ' (calculated ' + calculated.toFixed(2) + '%)';
            }
            document.getElementById('confirm-tds-percentage').textContent = text;
        })
        .catch(() => {
            document.getElementById('confirm-tds-percentage').textContent = (tdsField?.value || '0') + '%';
        });

        // Show the modal
        const modal = new bootstrap.Modal(document.getElementById('confirmationModal'));
        modal.show();
    }

    // Function to submit the form
    function submitForm() {
        document.querySelector('.needs-validation').submit();
    }

    // Attach event listener to submit button
    document.getElementById('submitFormBtn').addEventListener('click', showConfirmationModal);
</script>

<script>
    // Truck Management JavaScript
    let isEditMode = false;
    let currentTruckId = null;

    function destroyTruckModalDriverSelect2() {
        const $el = $('#driver_id');
        if ($el.length && $el.hasClass('select2-hidden-accessible')) {
            $el.select2('destroy');
        }
    }

    function truckModalDriverAjaxConfig(url) {
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

    function initTruckModalDriverSelect2(selectedDriver) {
        if (!window.jQuery || !window.jQuery.fn.select2) {
            return;
        }
        const $el = $('#driver_id');
        destroyTruckModalDriverSelect2();
        $el.empty();
        $el.append('<option value="">No Driver</option>');
        if (selectedDriver && selectedDriver.id) {
            const text = selectedDriver.text || String(selectedDriver.id);
            $el.append(new Option(text, String(selectedDriver.id), true, true));
        }
        $el.select2({
            theme: 'bootstrap-5',
            dropdownParent: $('#truckModal'),
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
            ajax: truckModalDriverAjaxConfig('/api/drivers/search')
        });
    }

    // Show truck modal for adding new truck
    $('#addNewTruckBtn, #addFirstTruckBtn').on('click', function() {
        isEditMode = false;
        currentTruckId = null;
        $('#truckModalTitle').text('Add New Truck');
        $('#saveTruckText').text('Add Truck');
        $('#truckForm')[0].reset();
        $('#truck_id').val('');
        initTruckModalDriverSelect2(null);
        $('#truckModal').modal('show');
    });

    // Show truck modal for editing existing truck
    $(document).on('click', '.edit-truck-btn', function() {
        const $btn = $(this);
        isEditMode = true;
        currentTruckId = $btn.data('truck-id');

        $('#truckModalTitle').text('Edit Truck');
        $('#saveTruckText').text('Update Truck');

        // Populate form with truck data
        $('#truck_id').val(currentTruckId);
        $('#truck_number').val($btn.data('truck-number'));
        $('#truck_type').val($btn.data('truck-type'));
        $('#registration_date').val($btn.data('registration-date'));

        const driverId = $btn.data('driver-id');
        let selectedDriver = null;
        if (driverId) {
            const name = $btn.data('driver-name') || '';
            const mobile = $btn.data('driver-mobile') || '';
            selectedDriver = {
                id: driverId,
                text: name + (mobile ? ' (' + mobile + ')' : '')
            };
        }
        initTruckModalDriverSelect2(selectedDriver);

        $('#truckModal').modal('show');
    });

    // Handle truck form submission
    $('#truckForm').on('submit', function(e) {
        e.preventDefault();

        const formData = $(this).serialize();
        const url = isEditMode ? `/trucks/${currentTruckId}` : '{{ route("trucks.store") }}';
        const method = isEditMode ? 'PUT' : 'POST';

        // Add method field for PUT requests
        let data = formData;
        if (isEditMode) {
            data += '&_method=PUT';
        }

        $.ajax({
            url: url,
            type: 'POST',
            data: data,
            success: function(response) {
                // Show success message
                $('body').prepend(`
                    <div class="alert alert-success alert-dismissible fade show position-fixed" style="top: 20px; right: 20px; z-index: 9999; min-width: 300px;">
                        <i class="bi bi-check-circle"></i>
                        <strong>Success!</strong> Truck has been ${isEditMode ? 'updated' : 'added'} successfully.
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                `);

                setTimeout(function() {
                    $('.alert-success').fadeOut();
                }, 5000);

                $('#truckModal').modal('hide');
                location.reload(); // Reload page to show updated trucks
            },
            error: function(xhr) {

                if (xhr.status === 422) {
                    // Handle validation errors
                    const errors = xhr.responseJSON.errors;

                    // Clear previous validation states
                    $('#truckForm .form-control, #truckForm .form-select').removeClass('is-invalid');
                    $('#truckForm .invalid-feedback').hide();

                    Object.keys(errors).forEach(function(field) {
                        const input = $(`#${field}`);
                        input.addClass('is-invalid');
                        input.siblings('.invalid-feedback').text(errors[field][0]).show();
                    });

                    // Show general validation error message
                    $('body').prepend(`
                        <div class="alert alert-warning alert-dismissible fade show position-fixed" style="top: 20px; right: 20px; z-index: 9999; min-width: 300px;">
                            <i class="bi bi-exclamation-triangle"></i>
                            <strong>Validation Error!</strong> Please check the form fields and try again.
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    `);

                    setTimeout(function() {
                        $('.alert-warning').fadeOut();
                    }, 5000);
                } else {
                    // Show detailed error information
                    let errorMessage = 'Unable to ' + (isEditMode ? 'update' : 'add') + ' truck. ';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMessage += xhr.responseJSON.message;
                    } else {
                        errorMessage += `Server returned ${xhr.status} error.`;
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
            }
        });
    });

    // Handle truck deletion
    $(document).on('click', '.delete-truck-btn', function() {
        const truckId = $(this).data('truck-id');
        const truckNumber = $(this).data('truck-number');

        if (confirm(`Are you sure you want to delete truck "${truckNumber}"? This action cannot be undone.`)) {
            $.ajax({
                url: `/trucks/${truckId}`,
                type: 'POST',
                data: {
                    '_token': $('meta[name="csrf-token"]').attr('content'),
                    '_method': 'DELETE'
                },
                success: function(response) {
                    $('body').prepend(`
                        <div class="alert alert-success alert-dismissible fade show position-fixed" style="top: 20px; right: 20px; z-index: 9999; min-width: 300px;">
                            <i class="bi bi-check-circle"></i>
                            <strong>Success!</strong> Truck "${truckNumber}" has been deleted successfully.
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    `);

                    setTimeout(function() {
                        $('.alert-success').fadeOut();
                    }, 5000);

                    // Remove truck row from table
                    $(`#truck-row-${truckId}`).fadeOut(500, function() {
                        $(this).remove();

                        // Show empty state if no trucks left
                        if ($('#trucksTable tbody tr').length === 0) {
                            location.reload();
                        }
                    });
                },
                error: function(xhr) {
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
    });

    // Handle truck removal (unlink from transporter)
    function showAlert(type, message) {
        const alertId = 'alert-' + Math.random().toString(36).substr(2, 9);
        const $alert = $(`
        <div id="${alertId}" class="alert alert-${type} alert-dismissible fade show position-fixed"
             style="top: 20px; right: 20px; z-index: 1060; min-width: 300px;">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    `);

        $('.alert-dismissible.position-fixed').remove();
        $('body').prepend($alert);
        setTimeout(() => {
            $alert.alert('close');
        }, 5000);
    }

    $(document).on('click', '.remove-truck-btn', function () {
        const truckId = $(this).data('truck-id');
        const truckNumber = $(this).data('truck-number');
        const $row = $(`#truck-row-${truckId}`);
        const $button = $(this);

        if (confirm(`Are you sure you want to remove truck "${truckNumber}" from this transporter?`)) {
            $button.prop('disabled', true).html('<i class="bi bi-arrow-repeat fa-spin"></i> Removing...');

            $.ajax({
                url: `/trucks/${truckId}/remove-transporter`,
                type: 'POST',
                data: {
                    '_token': $('meta[name="csrf-token"]').attr('content'),
                    '_method': 'PATCH'
                },
                success: function (response) {
                    $button.prop('disabled', false).html('<i class="bi bi-x-circle"></i>');

                    if (response.success) {
                        $row.fadeOut(400, function () {
                            $(this).remove();
                        });
                        showAlert('success', `<i class="bi bi-check-circle"></i> ${response.success}`);
                    } else if (response.error) {
                        showAlert('danger', `<i class="bi bi-exclamation-triangle"></i> ${response.error}`);
                    } else {
                        showAlert('danger', '<i class="bi bi-exclamation-triangle"></i> An unknown error occurred');
                    }
                },
                error: function (xhr, status, error) {
                    console.error('AJAX Error:', status, error);
                    console.error('Response:', xhr.responseJSON);

                    const errorMsg = xhr.responseJSON && xhr.responseJSON.error
                        ? xhr.responseJSON.error
                        : 'An error occurred while removing the truck. Please try again.';

                    showAlert('danger', `<i class="bi bi-exclamation-triangle"></i> ${errorMsg}`);
                    $button.prop('disabled', false).html('<i class="bi bi-x-circle"></i>');
                }
            });
        }
    });

    // Reset truck modal when hidden
    $('#truckModal').on('hidden.bs.modal', function() {
        destroyTruckModalDriverSelect2();
        $('#driver_id').empty().append('<option value="">No Driver</option>');
        $('#truckForm')[0].reset();
        $('#truckForm .form-control, #truckForm .form-select').removeClass('is-invalid is-valid');
        $('#truckForm .invalid-feedback').hide();
        $('#addDriverFormTruck').hide();
        $('#showAddDriverFormTruck').show();
        isEditMode = false;
        currentTruckId = null;
    });

    // Show/Hide Add Driver Form in Truck Modal
    $('#showAddDriverFormTruck').on('click', function() {
        $('#addDriverFormTruck').slideDown();
        $(this).hide();
    });

    $('#cancelAddDriverTruck').on('click', function() {
        $('#addDriverFormTruck').slideUp();
        $('#showAddDriverFormTruck').show();
        // Reset driver form fields
        $('#new_driver_name_truck, #new_driver_mobile_truck, #new_driver_license_truck, #new_driver_address_truck').val('');
        $('#addDriverFormTruck .form-control').removeClass('is-invalid is-valid');
    });

    // Handle new driver addition in truck modal
    $('#addDriverBtnTruck').on('click', function(e) {
        e.preventDefault();

        // Get form data
        const driverData = {
            name: $('#new_driver_name_truck').val().trim(),
            mobile: $('#new_driver_mobile_truck').val().trim(),
            license_number: $('#new_driver_license_truck').val().trim(),
            address: $('#new_driver_address_truck').val().trim(),
            _token: $('meta[name="csrf-token"]').attr('content')
        };

        // Basic validation
        let isValid = true;
        if (!driverData.name) {
            $('#new_driver_name_truck').addClass('is-invalid');
            isValid = false;
        } else {
            $('#new_driver_name_truck').removeClass('is-invalid').addClass('is-valid');
        }

        if (!driverData.mobile || !/^[0-9]{10}$/.test(driverData.mobile)) {
            $('#new_driver_mobile_truck').addClass('is-invalid');
            isValid = false;
        } else {
            $('#new_driver_mobile_truck').removeClass('is-invalid').addClass('is-valid');
        }

        if (!isValid) {
            return;
        }

        // Show loading state
        const originalText = $(this).html();
        $(this).html('<i class="bi bi-hourglass-split"></i> Adding...').prop('disabled', true);

        $.ajax({
            url: '{{ route("drivers.store") }}',
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
                    $('#new_driver_name_truck, #new_driver_mobile_truck, #new_driver_license_truck, #new_driver_address_truck').val('');
                    $('#addDriverFormTruck .form-control').removeClass('is-invalid is-valid');
                    $('#addDriverFormTruck').slideUp();
                    $('#showAddDriverFormTruck').show();

                    const d = response.driver;
                    const label = (d.name || '') + (d.mobile ? ' (' + d.mobile + ')' : '');
                    const opt = new Option(label, String(d.id), true, true);
                    $('#driver_id').append(opt).trigger('change');

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
                $('#addDriverBtnTruck').html(originalText).prop('disabled', false);
            }
        });
    });

    // Fresh approach using fetch API for modal submissions
    document.addEventListener('DOMContentLoaded', function() {

        function loadStatesIntoSelect(stateSelectEl, countryId) {
            if (!stateSelectEl || !countryId) {
                return Promise.resolve();
            }
            stateSelectEl.innerHTML = '<option value="">Select State</option>';
            return fetch('/api/states/' + encodeURIComponent(countryId), {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
                .then(function (response) {
                    return response.json();
                })
                .then(function (states) {
                    states.forEach(function (state) {
                        const option = document.createElement('option');
                        option.value = state.id;
                        option.textContent = state.name;
                        stateSelectEl.appendChild(option);
                    });
                })
                .catch(function () {
                    console.error('Error loading states for country');
                });
        }

        const modalCityCountry = document.getElementById('modal_city_country_id');
        const modalCityState = document.getElementById('modal_city_state_id');
        if (modalCityCountry && modalCityState) {
            modalCityCountry.addEventListener('change', function () {
                loadStatesIntoSelect(modalCityState, this.value);
            });
        }

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

                    if (response.ok && data.success && data.country) {
                        updateCountryDropdowns(data.country);

                        const mainCountryEl = document.getElementById('country_id');
                        if (mainCountryEl) {
                            mainCountryEl.value = String(data.country.id);
                            mainCountryEl.dispatchEvent(new Event('change'));
                        }
                        ['modal_state_country_id', 'modal_city_country_id'].forEach(function (id) {
                            const sel = document.getElementById(id);
                            if (sel) {
                                sel.value = String(data.country.id);
                                sel.dispatchEvent(new Event('change'));
                            }
                        });

                        // Clean close modal
                        closeModalClean('addCountryModal');

                        // Reset form
                        this.reset();
                    } else {
                        // Handle validation errors or server errors
                        let errorMessage = data.message || 'Failed to add country';

                        if (data.errors) {
                            // Handle validation errors
                            let errorHtml = '<ul class="mb-0">';
                            Object.values(data.errors).forEach(errors => {
                                errors.forEach(error => {
                                    errorHtml += `<li>${error}</li>`;
                                });
                            });
                            errorHtml += '</ul>';
                            errorDiv.innerHTML = errorHtml;
                        } else {
                            errorDiv.innerHTML = errorMessage;
                        }
                        errorDiv.classList.remove('d-none');
                    }
                } catch (error) {
                    console.error('Error:', error);
                    if (errorDiv) {
                        errorDiv.innerHTML = 'An error occurred while adding the country. Please try again.';
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

                    if (response.ok && data.success && data.state) {
                        updateStateDropdowns(data.state);

                        const mainStateEl = document.getElementById('state_id');
                        if (mainStateEl) {
                            mainStateEl.value = String(data.state.id);
                            mainStateEl.dispatchEvent(new Event('change'));
                        }
                        const modalCityStateSelect = document.getElementById('modal_city_state_id');
                        if (modalCityStateSelect) {
                            modalCityStateSelect.value = String(data.state.id);
                        }

                        // Clean close modal
                        closeModalClean('addStateModal');

                        // Reset form
                        this.reset();
                    } else {
                        // Handle validation errors or server errors
                        let errorMessage = data.message || 'Failed to add state';

                        if (data.errors) {
                            // Handle validation errors
                            let errorHtml = '<ul class="mb-0">';
                            Object.values(data.errors).forEach(errors => {
                                errors.forEach(error => {
                                    errorHtml += `<li>${error}</li>`;
                                });
                            });
                            errorHtml += '</ul>';
                            errorDiv.innerHTML = errorHtml;
                        } else {
                            errorDiv.innerHTML = errorMessage;
                        }
                        errorDiv.classList.remove('d-none');
                    }
                } catch (error) {
                    console.error('Error:', error);
                    if (errorDiv) {
                        errorDiv.innerHTML = 'An error occurred while adding the state. Please try again.';
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

                    if (response.ok && data.success && data.city) {
                        updateCityDropdown(data.city);

                        const mainCityEl = document.getElementById('city_id');
                        if (mainCityEl) {
                            mainCityEl.value = String(data.city.id);
                            mainCityEl.dispatchEvent(new Event('change'));
                        }

                        // Clean close modal
                        closeModalClean('addCityModal');

                        // Reset form
                        this.reset();
                    } else {
                        // Handle validation errors or server errors
                        let errorMessage = data.message || 'Failed to add city';

                        if (data.errors) {
                            // Handle validation errors
                            let errorHtml = '<ul class="mb-0">';
                            Object.values(data.errors).forEach(errors => {
                                errors.forEach(error => {
                                    errorHtml += `<li>${error}</li>`;
                                });
                            });
                            errorHtml += '</ul>';
                            errorDiv.innerHTML = errorHtml;
                        } else {
                            errorDiv.innerHTML = errorMessage;
                        }
                        errorDiv.classList.remove('d-none');
                    }
                } catch (error) {
                    console.error('Error:', error);
                    if (errorDiv) {
                        errorDiv.innerHTML = 'An error occurred while adding the city. Please try again.';
                        errorDiv.classList.remove('d-none');
                    }
                } finally {
                    submitBtn.disabled = false;
                    spinner?.classList.add('d-none');
                }
            });
        }
    });

    // Helper functions for modal management
    function updateCountryDropdowns(country) {
        const dropdowns = ['modal_state_country_id', 'modal_city_country_id', 'country_id'];
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
        const dropdowns = ['modal_city_state_id', 'state_id'];
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

@push('scripts')
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
@endpush

@endsection
