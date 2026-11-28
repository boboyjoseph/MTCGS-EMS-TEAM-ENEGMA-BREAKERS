@extends('layouts.app')

@section('title', 'Device Management')

@push('styles')
<style>
    .device-management-table {
        min-width: 1180px;
    }

    .device-management-table th,
    .device-management-table td {
        vertical-align: middle;
    }

    .device-management-table .device-name-column { min-width: 150px; }
    .device-management-table .device-mac-column { width: 170px; }
    .device-management-table .device-id-column { min-width: 150px; }
    .device-management-table .status-column { width: 128px; }
    .device-management-table .last-used-column {
        width: 145px;
        white-space: nowrap;
    }
    .device-management-table .actions-column {
        width: 112px;
        text-align: right;
    }

    .device-status {
        width: 118px;
        min-width: 118px;
    }

    .device-action-stack {
        display: grid;
        justify-items: stretch;
        gap: 6px;
        width: 92px;
        margin-left: auto;
    }

    .device-action-stack .btn {
        width: 92px;
    }

    .device-action-stack form {
        margin: 0;
    }

    .device-management-table code {
        white-space: normal;
        overflow-wrap: anywhere;
    }

    body.dark-mode .device-management-table,
    body.dark-mode .device-management-table th,
    body.dark-mode .device-management-table td,
    body.dark-mode .device-management-table code {
        color: #f8fafc !important;
    }

    body.dark-mode .device-management-table .text-muted {
        color: #cbd5e1 !important;
    }

    body.dark-mode .device-management-table thead th {
        background: #30243c !important;
        color: #f2eafb !important;
        border-color: #5c476e !important;
    }

    body.dark-mode .device-management-table tbody tr,
    body.dark-mode .device-management-table tbody td {
        background: #1e293b !important;
        color: #f8fafc !important;
        border-color: #334155 !important;
    }

    body.dark-mode .device-management-table.table-hover tbody tr:hover,
    body.dark-mode .device-management-table.table-hover tbody tr:hover td {
        background: #273449 !important;
        color: #ffffff !important;
    }

    body.dark-mode .device-status {
        background: #30243c !important;
        color: #f8fafc !important;
        border-color: #80639d !important;
    }

    body.dark-mode .device-status option {
        background: #30243c;
        color: #f8fafc;
    }

    body.dark-mode .device-management-table + .text-muted {
        color: #cbd5e1 !important;
    }

    body.dark-mode .device-action-stack .btn-outline-primary {
        background: #7048a5 !important;
        border-color: #7048a5 !important;
        color: #ffffff !important;
    }

    body.dark-mode .device-action-stack .btn-warning {
        background: #d97706 !important;
        border-color: #d97706 !important;
        color: #ffffff !important;
    }

    body.dark-mode .device-action-stack .btn-danger {
        background: #dc3545 !important;
        border-color: #dc3545 !important;
        color: #ffffff !important;
    }

    body.dark-mode .row.g-3.mb-4 > [class*="col-"] .card {
        background: #30243c !important;
        border: 1px solid #80639d !important;
        color: #f8fafc !important;
        box-shadow: 0 6px 16px rgba(15, 23, 42, 0.22);
    }

    body.dark-mode .row.g-3.mb-4 > [class*="col-"] .card .text-muted,
    body.dark-mode .row.g-3.mb-4 > [class*="col-"] .card .text-success,
    body.dark-mode .row.g-3.mb-4 > [class*="col-"] .card .text-warning,
    body.dark-mode .row.g-3.mb-4 > [class*="col-"] .card .text-danger,
    body.dark-mode .row.g-3.mb-4 > [class*="col-"] .card .fs-3 {
        color: #f8fafc !important;
    }

    @media (max-width: 991.98px) {
        .device-management-table {
            min-width: 1040px;
        }
    }
</style>
@endpush

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h3 class="mb-1">Biometric Device Management</h3>
            <p class="text-muted mb-0">Authorized scanner and kiosk devices for attendance validation.</p>
        </div>
        <a href="{{ route('admin.devices.create') }}" class="btn btn-primary">
            <i class="fas fa-plus me-1"></i> Register Device
        </a>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted small">Total Devices</div>
                    <div id="deviceTotalCount" class="fs-3 fw-bold">{{ $devices->count() }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted small">Active</div>
                    <div id="deviceActiveCount" class="fs-3 fw-bold text-success">{{ $devices->where('status', 'active')->count() }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted small">Maintenance</div>
                    <div id="deviceMaintenanceCount" class="fs-3 fw-bold text-warning">{{ $devices->where('status', 'maintenance')->count() }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted small">Inactive</div>
                    <div id="deviceInactiveCount" class="fs-3 fw-bold text-danger">{{ $devices->where('status', 'inactive')->count() }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Registered Devices</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0 device-management-table">
                    <thead class="table-light">
                        <tr>
                            <th class="device-name-column">Device Name</th>
                            <th class="device-mac-column">Device MAC Address</th>
                            <th>Scanner Serial</th>
                            <th class="device-id-column">Device ID</th>
                            <th>Type</th>
                            <th>Branch</th>
                            <th>Location</th>
                            <th class="status-column">Status</th>
                            <th class="last-used-column">Last Used</th>
                            <th class="actions-column">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($devices as $device)
                            <tr data-device-id="{{ $device->id }}">
                                <td class="device-name-column">{{ $device->device_name }}</td>
                                <td class="device-mac-column"><code>{{ $device->formatted_mac_address }}</code></td>
                                <td><code>{{ $device->serial_number ?? 'N/A' }}</code></td>
                                <td class="device-id-column"><code>{{ $device->laptop_mac_address ?: 'N/A' }}</code></td>
                                <td>{{ str_replace('_', ' ', ucfirst($device->device_type)) }}</td>
                                <td>{{ $device->branch?->branch_name ?? 'N/A' }}</td>
                                <td>{{ $device->location ?? 'N/A' }}</td>
                                <td class="status-column">
                                    <select class="form-select form-select-sm device-status" data-status-url="{{ route('admin.devices.status', $device) }}" aria-label="Update status for {{ $device->device_name }}">
                                        @foreach(['active' => 'Active', 'inactive' => 'Inactive', 'maintenance' => 'Maintenance'] as $status => $label)
                                            <option value="{{ $status }}" {{ $device->status === $status ? 'selected' : '' }}>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="device-last-used last-used-column">{{ $device->last_used_at ? $device->last_used_at->format('M d, Y h:i A') : 'Never' }}</td>
                                <td class="actions-column">
                                    <div class="device-action-stack">
                                        <a href="{{ route('admin.devices.show', $device) }}" class="btn btn-sm btn-outline-primary">View</a>
                                        <a href="{{ route('admin.devices.edit', $device) }}" class="btn btn-sm btn-warning">Edit</a>
                                        <form action="{{ route('admin.devices.destroy', $device) }}" method="POST"
                                            data-delete-device
                                            data-device-name="{{ $device->device_name }}"
                                            data-branch-name="{{ $device->branch?->branch_name ?? '' }}">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="11" class="text-center py-4 text-muted">No devices registered yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
const deviceStatusDataUrl = @json(route('admin.devices.status-data'));
const statusLabels = { active: 'Active', inactive: 'Inactive', maintenance: 'Maintenance' };

function refreshDeviceStatusSummary(devices) {
    const counts = devices.reduce((summary, device) => {
        summary.total++;
        if (summary[device.status] !== undefined) summary[device.status]++;
        return summary;
    }, { total: 0, active: 0, maintenance: 0, inactive: 0 });
    document.getElementById('deviceTotalCount').textContent = counts.total;
    document.getElementById('deviceActiveCount').textContent = counts.active;
    document.getElementById('deviceMaintenanceCount').textContent = counts.maintenance;
    document.getElementById('deviceInactiveCount').textContent = counts.inactive;
}

async function pollDeviceStatuses() {
    try {
        const response = await fetch(deviceStatusDataUrl, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
        if (!response.ok) return;
        const payload = await response.json();
        const devices = Array.isArray(payload.devices) ? payload.devices : [];
        refreshDeviceStatusSummary(devices);
        devices.forEach((device) => {
            const row = document.querySelector(`[data-device-id="${device.id}"]`);
            if (!row) return;
            const select = row.querySelector('.device-status');
            if (select && select.value !== device.status && !select.disabled) {
                select.value = device.status;
                [...select.options].forEach((option) => { option.defaultSelected = option.value === device.status; });
            }
            const lastUsed = row.querySelector('.device-last-used');
            if (lastUsed) lastUsed.textContent = device.last_used_at || 'Never';
        });
    } catch (error) {
        console.debug('Device status refresh unavailable:', error);
    }
}

pollDeviceStatuses();
window.setInterval(pollDeviceStatuses, 5000);

document.querySelectorAll('.device-status').forEach((select) => select.addEventListener('change', async (event) => {
    const control = event.currentTarget;
    const previousStatus = [...control.options].find((option) => option.defaultSelected)?.value;
    control.disabled = true;
    try {
        const response = await fetch(control.dataset.statusUrl, {
            method: 'PATCH',
            headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
            body: JSON.stringify({ status: control.value }),
        });
        const payload = await response.json();
        if (!response.ok) throw new Error(payload.message || 'Unable to update device status.');
        [...control.options].forEach((option) => { option.defaultSelected = option.value === control.value; });
    } catch (error) {
        if (previousStatus) control.value = previousStatus;
        window.showAppToast(error.message, 'error');
    } finally { control.disabled = false; }
}));

document.querySelectorAll('[data-delete-device]').forEach((form) => form.addEventListener('submit', async (event) => {
    event.preventDefault();
    const branchName = form.dataset.branchName;
    const resetNotice = branchName
        ? `All saved fingerprint templates for ${branchName} will also be permanently cleared. Everyone in that branch must enroll again.`
        : 'No branch is assigned, so no branch fingerprints will be cleared.';
    if (!confirm(`Remove ${form.dataset.deviceName} and revoke its credential?\n\n${resetNotice}`)) return;

    try {
        const response = await fetch(form.action, { method: 'DELETE', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken } });
        const payload = await response.json();
        if (!response.ok) throw new Error(payload.message || 'Unable to remove device.');
        form.closest('tr').remove();
        window.showAppToast(payload.message || 'Device removed successfully.', 'success');
    } catch (error) {
        window.showAppToast(error.message, 'error');
    }
}));
</script>
@endpush
