@extends('layouts.app')

@section('title', 'Device Details')

@php
    $normalizedScannerSerial = preg_replace('/[^A-Z0-9]/', '', strtoupper((string) $device->serial_number));
    $deviceSecretFile = match ($normalizedScannerSerial) {
        'J320E01822' => 'buhi-biometric-device-secret.bin',
        'J62OE23640' => 'iriga-biometric-device-secret.bin',
        default => 'scanner-' . $normalizedScannerSerial . '-biometric-device-secret.bin',
    };
    $deviceSetupPackage = [
        'format' => 'mtcgs-biometric-device-setup',
        'version' => 1,
        'apiBaseUrl' => rtrim(config('app.url'), '/') . '/api',
        'device' => [
            'name' => $device->device_name,
            'branch' => $device->branch?->branch_name,
            'type' => $device->device_type,
            'serialNumber' => $device->serial_number,
            'readerId' => $device->reader_id,
            'macAddress' => $device->formatted_mac_address,
            'deviceIdentifier' => $device->laptop_mac_address,
            'location' => $device->location,
        ],
        'appSettings' => [
            'ApiBaseUrl' => rtrim(config('app.url'), '/') . '/api',
            'DeviceLocation' => $device->branch?->branch_name,
            'ScannerSerialNumber' => $device->serial_number,
            'ExpectedDpfpReaderId' => $device->reader_id,
            'DeviceMacAddress' => $device->formatted_mac_address,
            'DeviceIdentifier' => $device->laptop_mac_address,
        ],
        'windowsCredentialFile' => '%LOCALAPPDATA%\\MTCGS-EMS\\' . $deviceSecretFile,
        'instructions' => [
            'Install the MTCGS Attendance and Enrollment desktop apps and the fingerprint reader driver on the registered kiosk computer.',
            'In Attendance and Enrollment, click IMPORT SETUP and choose this JSON package. The apps save device settings and protect the credential for the current Windows user.',
            'Restart each app when prompted. After rotating a credential or deleting and re-registering the same kiosk, import the newly downloaded package; reinstalling the desktop apps is not required.',
            'Delete this setup package after both apps are updated. It contains a one-time plain-text credential; do not send it through a public chat or email.',
        ],
    ];
@endphp

@section('content')
<div class="container-fluid" style="max-width: 900px;">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h3 class="mb-1">Device Details</h3>
            <p class="text-muted mb-0">View the registered device information and authorization status.</p>
        </div>
        <a href="{{ route('admin.devices.index') }}" class="btn btn-outline-secondary">Back to List</a>
    </div>

    <div class="card">
        <div class="card-body">
            @if (session('new_device_credential'))
                <div class="alert alert-warning" role="alert">
                    <strong>Save this device credential now.</strong> It is shown only once. Download the setup file before leaving this page.
                    <div class="input-group mt-2">
                        <input id="device-credential" class="form-control font-monospace" value="{{ session('new_device_credential') }}" readonly>
                        <button class="btn btn-outline-secondary" type="button" data-copy-credential>Copy</button>
                    </div>
                    <button class="btn btn-primary btn-sm mt-2" type="button" data-download-device-setup>Download setup package</button>
                    <div class="small mt-2">Import this setup package from the Attendance and Enrollment apps. The credential is plain text in this download, so keep it private and delete it after importing.</div>
                </div>
            @endif

            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <strong>Biometric API credential:</strong>
                    <span class="badge {{ $credentialStatus === 'provisioned' ? 'bg-success' : 'bg-warning text-dark' }}">
                        {{ match ($credentialStatus) {
                            'provisioned' => 'Provisioned',
                            'unreadable' => 'Cannot be decrypted',
                            default => 'Not provisioned',
                        } }}
                    </span>
                    <div class="small text-muted">
                        @if ($credentialStatus === 'unreadable')
                            The stored credential cannot be decrypted with the current application key. Generate a new credential and import the new setup package in both kiosk apps.
                        @else
                            The secret is not displayed again. Rotating it immediately disables the previous device credential.
                        @endif
                    </div>
                </div>
                <button type="button" class="btn btn-outline-danger btn-sm"
                    data-rotate-credential
                    data-url="{{ route('admin.devices.credential.rotate', $device) }}"
                    data-csrf="{{ csrf_token() }}">
                    {{ $credentialStatus === 'not_provisioned' ? 'Generate Credential' : 'Rotate Credential' }}
                </button>
            </div>
            <div class="alert alert-warning d-none" role="alert" data-credential-result></div>
            <div class="small text-muted mb-3">When the credential changes, download a fresh setup package and import it in Attendance and Enrollment. New app versions are checked automatically at startup; device credentials and local attendance data are retained during app updates.</div>

            <dl class="row mb-0">
                <dt class="col-sm-4">Device Name</dt>
                <dd class="col-sm-8">{{ $device->device_name }}</dd>

                <dt class="col-sm-4">Device MAC Address</dt>
                <dd class="col-sm-8"><code>{{ $device->formatted_mac_address }}</code></dd>

                <dt class="col-sm-4">Scanner Serial Number</dt>
                <dd class="col-sm-8">{{ $device->serial_number ?: 'N/A' }}</dd>

                <dt class="col-sm-4">Device ID</dt>
                <dd class="col-sm-8">{{ $device->laptop_mac_address ?: 'N/A' }}</dd>

                <dt class="col-sm-4">Device Type</dt>
                <dd class="col-sm-8">{{ str_replace('_', ' ', ucfirst($device->device_type)) }}</dd>

                <dt class="col-sm-4">Branch</dt>
                <dd class="col-sm-8">{{ $device->branch?->branch_name ?? 'N/A' }}</dd>

                <dt class="col-sm-4">Status</dt>
                <dd class="col-sm-8">{{ ucfirst($device->status) }}</dd>

                <dt class="col-sm-4">Location</dt>
                <dd class="col-sm-8">{{ $device->location ?: 'N/A' }}</dd>

                <dt class="col-sm-4">School Address</dt>
                <dd class="col-sm-8">{{ $device->address ?: 'N/A' }}</dd>

                <dt class="col-sm-4">School Coordinates</dt>
                <dd class="col-sm-8">
                    {{ $device->latitude !== null && $device->longitude !== null ? "{$device->latitude}, {$device->longitude}" : 'N/A' }}
                </dd>

                <dt class="col-sm-4">Allowed Radius</dt>
                <dd class="col-sm-8">{{ $device->radius_meters ? "{$device->radius_meters} meters" : 'N/A' }}</dd>

                <dt class="col-sm-4">School Location Check</dt>
                <dd class="col-sm-8">{{ $device->location_check_enabled ? 'Enabled' : 'Disabled' }}</dd>

                <dt class="col-sm-4">Last Used</dt>
                <dd class="col-sm-8">{{ $device->last_used_at?->format('M d, Y h:i A') ?? 'Never' }}</dd>

                <dt class="col-sm-4">Notes</dt>
                <dd class="col-sm-8">{{ $device->notes ?: 'N/A' }}</dd>
            </dl>

            <div class="d-flex justify-content-end gap-2 mt-4">
                <a href="{{ route('admin.devices.edit', $device) }}" class="btn btn-primary">Edit Device</a>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
const deviceSetupConfig = @json($deviceSetupPackage);

function downloadDeviceSetupPackage(credential) {
    const setup = { ...deviceSetupConfig, credential };
    const blob = new Blob([JSON.stringify(setup, null, 2)], { type: 'application/json' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    const safeName = (deviceSetupConfig.device.name || 'biometric-device')
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-|-$/g, '') || 'biometric-device';
    link.href = url;
    link.download = safeName + '-setup.json';
    document.body.append(link);
    link.click();
    link.remove();
    setTimeout(() => URL.revokeObjectURL(url), 1000);
    window.showAppToast('Setup package downloaded. Keep it private and delete it after installation.', 'success');
}

document.querySelector('[data-download-device-setup]')?.addEventListener('click', () => {
    const credential = document.querySelector('#device-credential')?.value;
    if (!credential) {
        window.showAppToast('The one-time credential is no longer available. Rotate it to create a new setup package.', 'error');
        return;
    }
    downloadDeviceSetupPackage(credential);
});

document.querySelector('[data-rotate-credential]')?.addEventListener('click', async (event) => {
    const button = event.currentTarget;
    if (!confirm('Generate a new credential? The existing installer will stop working immediately.')) return;

    button.disabled = true;
    try {
        const response = await fetch(button.dataset.url, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': button.dataset.csrf,
            },
        });
        const payload = await response.json();
        if (!response.ok) throw new Error(payload.message || 'Could not rotate device credential.');

        const result = document.querySelector('[data-credential-result]');
        result.classList.remove('d-none');
        result.replaceChildren();
        const notice = document.createElement('strong');
        notice.textContent = 'Credential rotated. Download the new setup package now.';
        result.append(notice, document.createTextNode(' It will not be shown again. Import the package in both kiosk apps before recording attendance or enrolling fingerprints.'));

        const inputGroup = document.createElement('div');
        inputGroup.className = 'input-group mt-2';
        const input = document.createElement('input');
        input.className = 'form-control font-monospace';
        input.readOnly = true;
        input.value = payload.credential;
        const copyButton = document.createElement('button');
        copyButton.className = 'btn btn-outline-secondary';
        copyButton.type = 'button';
        copyButton.textContent = 'Copy';
        copyButton.addEventListener('click', async () => {
            await navigator.clipboard.writeText(input.value);
        });
        inputGroup.append(input, copyButton);
        result.append(inputGroup);
        const downloadButton = document.createElement('button');
        downloadButton.className = 'btn btn-primary btn-sm mt-2';
        downloadButton.type = 'button';
        downloadButton.textContent = 'Download setup package';
        downloadButton.addEventListener('click', () => downloadDeviceSetupPackage(payload.credential));
        result.append(downloadButton);
        button.textContent = 'Credential Rotated';
        window.showAppToast('Credential rotated. Download the package and import it in Attendance and Enrollment.', 'success');
    } catch (error) {
        window.showAppToast(error.message, 'error');
    } finally {
        button.disabled = false;
    }
});

document.querySelectorAll('[data-copy-credential]').forEach(button => {
    button.addEventListener('click', async () => {
        const input = button.parentElement.querySelector('input');
        await navigator.clipboard.writeText(input.value);
    });
});
</script>
@endpush
