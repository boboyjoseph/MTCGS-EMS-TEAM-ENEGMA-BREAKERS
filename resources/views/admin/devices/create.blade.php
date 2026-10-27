@extends('layouts.app')

@section('title', 'Add Biometric Device')

@section('content')
<div class="container-fluid" style="max-width: 860px;">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h3 class="mb-1">Add Biometric Device</h3>
            <p class="text-muted mb-0">Register the kiosk once, then download its setup file for the desktop apps.</p>
        </div>
        <a href="{{ route('admin.devices.index') }}" class="btn btn-outline-secondary">Back to Devices</a>
    </div>

    <div class="alert alert-info" role="note">
        Only a Super Admin can register a device. Keep the computer MAC, scanner serial, and device ID from the kiosk's current app configuration nearby.
    </div>

    <div class="card">
        <div class="card-body">
            <form action="{{ route('admin.devices.store') }}" method="POST" data-device-form>
                @csrf

                <h5 class="mb-3">1. Identify the kiosk</h5>
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label for="device_name" class="form-label">Kiosk Name</label>
                        <input type="text" name="device_name" id="device_name" class="form-control" value="{{ old('device_name') }}" placeholder="Buhi Attendance Kiosk" required>
                    </div>
                    <div class="col-md-6">
                        <label for="branch_id" class="form-label">Branch</label>
                        <select name="branch_id" id="branch_id" class="form-select" required>
                            <option value="">Choose a branch</option>
                            @foreach($branches as $branch)
                                <option value="{{ $branch->id }}" {{ old('branch_id') == $branch->id ? 'selected' : '' }}>{{ $branch->branch_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="device_type" class="form-label">Device Type</label>
                        <select name="device_type" id="device_type" class="form-select" required>
                            <option value="biometric_scanner" {{ old('device_type', 'biometric_scanner') === 'biometric_scanner' ? 'selected' : '' }}>Biometric Scanner</option>
                            <option value="kiosk" {{ old('device_type') === 'kiosk' ? 'selected' : '' }}>Attendance Kiosk</option>
                            <option value="computer" {{ old('device_type') === 'computer' ? 'selected' : '' }}>Computer</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="status" class="form-label">Status</label>
                        <select name="status" id="status" class="form-select" required>
                            <option value="active" {{ old('status', 'active') === 'active' ? 'selected' : '' }}>Active</option>
                            <option value="inactive" {{ old('status') === 'inactive' ? 'selected' : '' }}>Inactive</option>
                            <option value="maintenance" {{ old('status') === 'maintenance' ? 'selected' : '' }}>Maintenance</option>
                        </select>
                    </div>
                </div>

                <h5 class="mb-1">2. Connect the fingerprint reader</h5>
                <p class="small text-muted mb-3">Use the values from the kiosk computer and scanner. These identifiers must match the installed desktop app.</p>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="mac_address" class="form-label">Kiosk Computer MAC Address</label>
                        <input type="text" name="mac_address" id="mac_address" class="form-control" value="{{ old('mac_address') }}" placeholder="00:1A:2B:3C:4D:5E" required>
                    </div>
                    <div class="col-md-6">
                        <label for="laptop_mac_address" class="form-label">Kiosk Device ID</label>
                        <input type="text" name="laptop_mac_address" id="laptop_mac_address" class="form-control" value="{{ old('laptop_mac_address') }}" placeholder="DeviceIdentifier from app config" required>
                    </div>
                    <div class="col-md-6">
                        <label for="serial_number" class="form-label">Fingerprint Scanner Serial Number</label>
                        <input type="text" name="serial_number" id="serial_number" class="form-control" value="{{ old('serial_number') }}" placeholder="Serial number on the scanner" required>
                    </div>
                    <div class="col-md-6">
                        <label for="reader_id" class="form-label">Physical Reader ID <span class="text-muted">(if required)</span></label>
                        <input type="text" name="reader_id" id="reader_id" class="form-control" value="{{ old('reader_id') }}" placeholder="ExpectedDpfpReaderId from app config">
                        <small class="text-muted">Required automatically for supported reader-bound scanners.</small>
                    </div>
                </div>

                <details class="mt-4">
                    <summary class="fw-semibold">Optional location and security settings</summary>
                    <div class="row g-3 mt-1">
                        <div class="col-md-6">
                            <label for="location" class="form-label">Kiosk Location</label>
                            <input type="text" name="location" id="location" class="form-control" value="{{ old('location') }}" placeholder="Main office">
                        </div>
                        <div class="col-md-6">
                            <label for="address" class="form-label">Branch Address</label>
                            <input type="text" name="address" id="address" class="form-control" value="{{ old('address') }}" placeholder="Filled from the selected branch">
                        </div>
                        <div class="col-md-4">
                            <label for="latitude" class="form-label">Latitude</label>
                            <input type="number" step="0.0000001" name="latitude" id="latitude" class="form-control" value="{{ old('latitude') }}">
                        </div>
                        <div class="col-md-4">
                            <label for="longitude" class="form-label">Longitude</label>
                            <input type="number" step="0.0000001" name="longitude" id="longitude" class="form-control" value="{{ old('longitude') }}">
                        </div>
                        <div class="col-md-4">
                            <label for="radius_meters" class="form-label">Allowed Radius (meters)</label>
                            <input type="number" name="radius_meters" id="radius_meters" class="form-control" value="{{ old('radius_meters', 100) }}" min="1" max="10000">
                        </div>
                        <div class="col-12 form-check ms-2">
                            <input type="hidden" name="location_check_enabled" value="0">
                            <input type="checkbox" name="location_check_enabled" id="location_check_enabled" class="form-check-input" value="1" {{ old('location_check_enabled', true) ? 'checked' : '' }}>
                            <label for="location_check_enabled" class="form-check-label">Check attendance against this location</label>
                        </div>
                        <div class="col-12">
                            <label for="notes" class="form-label">Notes</label>
                            <textarea name="notes" id="notes" class="form-control" rows="2" placeholder="Optional hardware notes">{{ old('notes') }}</textarea>
                        </div>
                    </div>
                </details>

                <div class="d-flex justify-content-end gap-2 mt-4">
                    <a href="{{ route('admin.devices.index') }}" class="btn btn-outline-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">Register and Create Setup</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.querySelector('#branch_id')?.addEventListener('change', async (event) => {
    if (!event.target.value) return;
    try {
        const response = await fetch('{{ url('/admin/devices/branches') }}/' + encodeURIComponent(event.target.value), {
            headers: { Accept: 'application/json' },
        });
        const payload = await response.json();
        if (!response.ok) throw new Error(payload.message || 'Unable to load branch details.');
        if (payload.success) document.querySelector('#address').value = payload.branch.address || '';
    } catch (error) {
        window.showAppToast(error.message, 'error');
    }
});
</script>
@endpush
