@extends('layouts.app')

@section('title', 'Edit Device')

@push('styles')
<style>
    .edit-device-page { max-width: 980px; }

    .edit-device-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 1rem;
    }

    .device-form-section {
        border: 1px solid #e4deed;
        border-radius: 8px;
        background: #fff;
        overflow: hidden;
    }

    .device-form-section + .device-form-section { margin-top: 1rem; }

    .device-form-section-header {
        padding: 0.85rem 1rem;
        border-bottom: 1px solid #e4deed;
        background: #f8fafc;
        font-weight: 700;
    }

    .device-form-section-body { padding: 1rem; }

    .device-form-section .form-label {
        font-size: 0.82rem;
        font-weight: 700;
        color: #4b5563;
    }

    .device-form-section .form-control,
    .device-form-section .form-select {
        min-height: 42px;
    }

    .device-form-section .form-text,
    .device-form-section small.text-muted {
        line-height: 1.35;
    }

    .device-location-check {
        display: flex;
        align-items: flex-start;
        gap: 0.65rem;
        padding: 0.85rem 1rem;
        border: 1px solid #ded0ed;
        border-radius: 8px;
        background: #faf8fc;
    }

    .device-location-check .form-check-input {
        margin-top: 0.25rem;
    }

    .device-form-footer {
        position: sticky;
        bottom: 0;
        z-index: 5;
        display: flex;
        justify-content: flex-end;
        gap: 0.75rem;
        margin: 1.25rem -1.25rem -1.25rem;
        padding: 0.85rem 1.25rem;
        border-top: 1px solid #e4deed;
        background: rgba(255, 255, 255, 0.96);
        backdrop-filter: blur(8px);
    }

    body.dark-mode .device-form-section,
    body.dark-mode .device-form-section-header,
    body.dark-mode .device-location-check {
        background: #1e293b;
        border-color: #334155;
        color: #e2e8f0;
    }

    body.dark-mode .device-form-section .form-label,
    body.dark-mode .device-location-check .form-check-label,
    body.dark-mode .device-location-check small { color: #e2e8f0; }

    body.dark-mode .device-form-footer {
        background: rgba(30, 41, 59, 0.96);
        border-top-color: #334155;
    }

    @media (max-width: 767.98px) {
        .edit-device-header { flex-direction: column; }
        .edit-device-header .btn { width: 100%; }
        .device-form-footer {
            position: static;
            margin: 1rem -1rem -1rem;
        }
        .device-form-footer .btn { flex: 1 1 0; }
    }
</style>
@endpush

@section('content')
<div class="container-fluid edit-device-page">
    <div class="edit-device-header mb-3">
        <div>
            <h3 class="mb-1">Edit Device</h3>
            <p class="text-muted mb-0">Update the biometric device record and authorization status.</p>
        </div>
        <a href="{{ route('admin.devices.index') }}" class="btn btn-outline-secondary">Back to List</a>
    </div>

    <div class="card">
        <div class="card-body">
            <form action="{{ route('admin.devices.update', $device) }}" method="POST" data-device-form>
                @csrf
                @method('PUT')

                <section class="device-form-section">
                    <div class="device-form-section-header">Device Identity</div>
                    <div class="device-form-section-body">
                        <div class="row g-3">
                    <div class="col-md-6">
                        <label for="device_name" class="form-label">Device Name</label>
                        <input type="text" name="device_name" id="device_name" class="form-control" value="{{ old('device_name', $device->device_name) }}" required>
                    </div>

                    <div class="col-md-6">
                        <label for="mac_address" class="form-label">Device MAC Address</label>
                        <input type="text" name="mac_address" id="mac_address" class="form-control" value="{{ old('mac_address', $device->formatted_mac_address) }}" placeholder="00:1A:2B:3C:4D:5E" required pattern="^(?:[A-Fa-f0-9]{2}[:-]?){5}[A-Fa-f0-9]{2}$">
                        <small class="text-muted">This single MAC value is used for device and Wi-Fi identification.</small>
                    </div>

                    <div class="col-md-6">
                        <label for="serial_number" class="form-label">Scanner Serial Number</label>
                        <input type="text" name="serial_number" id="serial_number" class="form-control" value="{{ old('serial_number', $device->serial_number) }}" placeholder="S/N from fingerprint scanner">
                    </div>

                    <div class="col-md-6">
                        <label for="reader_id" class="form-label">Expected Physical Reader ID</label>
                        <input type="text" name="reader_id" id="reader_id" class="form-control" value="{{ old('reader_id', $device->reader_id) }}" placeholder="DPFP reader ID">
                        <small class="text-muted">Required for reader-bound scanners. Attendance requests must match the reader ID registered to this device.</small>
                    </div>

                    <div class="col-md-6">
                        <label for="laptop_mac_address" class="form-label">Device ID</label>
                        <input type="text" name="laptop_mac_address" id="laptop_mac_address" class="form-control" value="{{ old('laptop_mac_address', $device->laptop_mac_address) }}" placeholder="Unique provisioned device ID" required>
                    </div>

                    <div class="col-md-4">
                        <label for="device_type" class="form-label">Device Type</label>
                        <select name="device_type" id="device_type" class="form-select" required>
                            <option value="biometric_scanner" {{ old('device_type', $device->device_type) === 'biometric_scanner' ? 'selected' : '' }}>Biometric Scanner</option>
                            <option value="kiosk" {{ old('device_type', $device->device_type) === 'kiosk' ? 'selected' : '' }}>Kiosk</option>
                            <option value="computer" {{ old('device_type', $device->device_type) === 'computer' ? 'selected' : '' }}>Computer</option>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label for="branch_id" class="form-label">Branch</label>
                        <select name="branch_id" id="branch_id" class="form-select" required>
                            <option value="">No branch assigned</option>
                            @foreach($branches as $branch)
                                <option value="{{ $branch->id }}" {{ old('branch_id', $device->branch_id) == $branch->id ? 'selected' : '' }}>{{ $branch->branch_name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label for="status" class="form-label">Status</label>
                        <select name="status" id="status" class="form-select" required>
                            <option value="active" {{ old('status', $device->status) === 'active' ? 'selected' : '' }}>Active</option>
                            <option value="inactive" {{ old('status', $device->status) === 'inactive' ? 'selected' : '' }}>Inactive</option>
                            <option value="maintenance" {{ old('status', $device->status) === 'maintenance' ? 'selected' : '' }}>Maintenance</option>
                        </select>
                    </div>

                        </div>
                    </div>
                </section>

                <section class="device-form-section">
                    <div class="device-form-section-header">Location &amp; Security</div>
                    <div class="device-form-section-body">
                        <div class="row g-3">
                    <div class="col-12">
                        <label for="location" class="form-label">Location</label>
                        <input type="text" name="location" id="location" class="form-control" value="{{ old('location', $device->location ?: 'School') }}" placeholder="School">
                    </div>

                    <div class="col-12">
                        <label for="address" class="form-label">School Address</label>
                        <input type="text" name="address" id="address" class="form-control" value="{{ old('address', $device->address ?: $device->branch?->address) }}" placeholder="School address">
                    </div>

                    <div class="col-md-4">
                        <label for="latitude" class="form-label">School Latitude</label>
                        <input type="number" step="0.0000001" name="latitude" id="latitude" class="form-control" value="{{ old('latitude', $device->latitude) }}">
                    </div>

                    <div class="col-md-4">
                        <label for="longitude" class="form-label">School Longitude</label>
                        <input type="number" step="0.0000001" name="longitude" id="longitude" class="form-control" value="{{ old('longitude', $device->longitude) }}">
                    </div>

                    <div class="col-md-4">
                        <label for="radius_meters" class="form-label">Allowed Radius (meters)</label>
                        <input type="number" name="radius_meters" id="radius_meters" class="form-control" value="{{ old('radius_meters', $device->radius_meters ?: 100) }}" min="1" max="10000">
                    </div>

                    <div class="col-12 device-location-check">
                        <input type="hidden" name="location_check_enabled" value="0">
                        <input type="checkbox" name="location_check_enabled" id="location_check_enabled" class="form-check-input" value="1" {{ old('location_check_enabled', $device->location_check_enabled) ? 'checked' : '' }}>
                        <label for="location_check_enabled" class="form-check-label">Require GPS for attendance and fingerprint enrollment</label>
                        <small class="d-block text-muted">When enabled, the Attendance and Enrollment apps must send a fresh Windows location fix inside this radius.</small>
                    </div>

                        </div>
                    </div>
                </section>

                <section class="device-form-section">
                    <div class="device-form-section-header">Notes</div>
                    <div class="device-form-section-body">
                        <div class="row g-3">
                    <div class="col-12">
                        <label for="notes" class="form-label visually-hidden">Notes</label>
                        <textarea name="notes" id="notes" class="form-control" rows="4" placeholder="Optional notes or hardware notes">{{ old('notes', $device->notes) }}</textarea>
                    </div>
                        </div>
                    </div>
                </section>

                <div class="device-form-footer">
                    <a href="{{ route('admin.devices.index') }}" class="btn btn-outline-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">Update Device</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.querySelector('[data-device-form]')?.addEventListener('submit', async (event) => {
    event.preventDefault();
    const form = event.currentTarget;
    const submitButton = form.querySelector('button[type="submit"]');
    submitButton.disabled = true;
    try {
        const response = await fetch(form.action, { method: 'POST', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }, body: new FormData(form) });
        const payload = await response.json();
        if (!response.ok) throw new Error(Object.values(payload.errors || {}).flat()[0] || payload.message || 'Unable to update device.');
        window.showAppToast(payload.message, 'success');
        window.location.href = '{{ route('admin.devices.index') }}';
    } catch (error) { window.showAppToast(error.message, 'error'); } finally { submitButton.disabled = false; }
});
document.querySelector('#branch_id')?.addEventListener('change', async (event) => {
    if (!event.target.value) return;
    const response = await fetch('{{ url('/admin/devices/branches') }}/' + event.target.value, { headers: { Accept: 'application/json' } });
    const payload = await response.json();
    if (payload.success && payload.branch.address) document.querySelector('#address').value = payload.branch.address;
});
</script>
@endpush
