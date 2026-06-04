<div class="row g-3">
    <div class="col-md-6">
        <label for="name" class="form-label">Template Name *</label>
        <input type="text" id="name" name="name" class="form-control @error('name') is-invalid @enderror"
            value="{{ old('name', $template->name) }}" required>
        @error('name')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-6">
        <label for="key" class="form-label">Event Key *</label>
        <input type="text" id="key" name="key" class="form-control @error('key') is-invalid @enderror"
            value="{{ old('key', $template->key) }}" required>
        <small class="text-muted">Example: <code>LR_STATUS_DELIVERED</code></small>
        @error('key')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-12">
        <label for="company_id" class="form-label">Company (optional)</label>
        <select id="company_id" name="company_id" class="form-control select2">
            <option value="">All Companies</option>
            @foreach ($companies as $company)
                <option value="{{ $company->id }}" @selected((string) old('company_id', $template->company_id) === (string) $company->id)>
                    {{ $company->name }}
                </option>
            @endforeach
        </select>
        @error('company_id')
            <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-12">
        <label for="subject" class="form-label">Subject *</label>
        <input type="text" id="subject" name="subject" class="form-control @error('subject') is-invalid @enderror"
            value="{{ old('subject', $template->subject) }}" required>
        @error('subject')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-12">
        <div class="card border-0 bg-light">
            <div class="card-body py-2 px-3">
                <div class="row g-2">
                    <div class="col-md-4">
                        <div class="form-check form-switch mt-2">
                            <input type="hidden" name="send_to_transporter" value="0">
                            <input type="checkbox" id="send_to_transporter" name="send_to_transporter" value="1"
                                class="form-check-input" @checked((bool) old('send_to_transporter', $template->send_to_transporter ?? false))>
                            <label for="send_to_transporter" class="form-check-label">
                                Send to Transporter
                                <span class="badge {{ old('send_to_transporter', $template->send_to_transporter ?? false) ? 'bg-success' : 'bg-secondary' }}">
                                    {{ old('send_to_transporter', $template->send_to_transporter ?? false) ? 'ON' : 'OFF' }}
                                </span>
                            </label>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-check form-switch mt-2">
                            <input type="hidden" name="send_to_customer" value="0">
                            <input type="checkbox" id="send_to_customer" name="send_to_customer" value="1"
                                class="form-check-input" @checked((bool) old('send_to_customer', $template->send_to_customer ?? false))>
                            <label for="send_to_customer" class="form-check-label">
                                Send to Customer
                                <span class="badge {{ old('send_to_customer', $template->send_to_customer ?? false) ? 'bg-success' : 'bg-secondary' }}">
                                    {{ old('send_to_customer', $template->send_to_customer ?? false) ? 'ON' : 'OFF' }}
                                </span>
                            </label>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-check mt-2">
                            <input type="hidden" name="is_active" value="0">
                            <input type="checkbox" id="is_active" name="is_active" value="1"
                                class="form-check-input" @checked((bool) old('is_active', $template->is_active ?? true))>
                            <label for="is_active" class="form-check-label">Active Template</label>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-12 mt-1">
        <h6 class="mb-1">Template Content</h6>
        <small class="text-muted">Manage variables and message body together.</small>
    </div>

    <div class="col-md-4">
        <label for="allowed_variables" class="form-label">Allowed Variables</label>
        @php
            $selectedVariables = old('allowed_variables', $template->allowed_variables ?? []);
        @endphp
        <select id="allowed_variables" name="allowed_variables[]"
            class="form-control @error('allowed_variables') is-invalid @enderror select2" multiple data-placeholder="Select variables">
            @foreach ($availableVariables as $variable)
                <option value="{{ $variable }}" @selected(in_array($variable, $selectedVariables, true))>
                    {{ $variable }}
                </option>
            @endforeach
        </select>
        <small class="text-muted">Use: <code>@{{name}}</code>, <code>@{{lrNumber}}</code></small>
        @error('allowed_variables')
            <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror
        @error('allowed_variables.*')
            <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror

        <label for="allowed_roles" class="form-label mt-3">Allowed Internal Roles</label>
        @php
            $selectedRoles = old('allowed_roles', $template->allowed_roles ?? []);
        @endphp
        <select id="allowed_roles" name="allowed_roles[]"
            class="form-control @error('allowed_roles') is-invalid @enderror select2" multiple data-placeholder="Select internal roles">
            @foreach ($availableRoles as $role)
                <option value="{{ $role }}" @selected(in_array($role, $selectedRoles, true))>
                    {{ $role }}
                </option>
            @endforeach
        </select>
        <small class="text-muted">Internal role alerts (matrix ke andar).</small>
        @error('allowed_roles')
            <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror
        @error('allowed_roles.*')
            <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-8">
        <label for="body_html" class="form-label">Body HTML *</label>
        <textarea id="body_html" name="body_html" rows="14"
            class="form-control texteditor @error('body_html') is-invalid @enderror" required>{{ old('body_html', $template->body_html) }}</textarea>
        @error('body_html')
            <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror
    </div>
</div>
