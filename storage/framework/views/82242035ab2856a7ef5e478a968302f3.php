

<?php $__env->startSection('title', 'Device Details'); ?>

<?php
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
?>

<?php $__env->startPush('styles'); ?>
<style>
    .device-details-page {
        max-width: 980px;
    }

    .device-details-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 1rem;
    }

    .device-credential-banner {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 1rem;
        padding: 1rem 1.1rem;
        margin-bottom: 1rem;
        border: 1px solid #e4deed;
        border-left: 4px solid #7048a5;
        border-radius: 8px;
        background: #faf8fc;
    }

    .device-credential-copy {
        min-width: 0;
        color: #5f6673;
        line-height: 1.45;
    }

    .device-details-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 1rem;
    }

    .device-detail-card {
        height: 100%;
        border: 1px solid #e4deed;
        border-radius: 8px;
        background: #fff;
        overflow: hidden;
    }

    .device-detail-card-header {
        padding: 0.85rem 1rem;
        border-bottom: 1px solid #e4deed;
        background: #f8fafc;
        font-weight: 700;
    }

    .device-detail-list {
        display: grid;
        grid-template-columns: minmax(105px, 0.38fr) minmax(0, 0.62fr);
        margin: 0;
    }

    .device-detail-list dt,
    .device-detail-list dd {
        margin: 0;
        padding: 0.72rem 1rem;
        border-bottom: 1px solid #eef0f3;
    }

    .device-detail-list dt {
        color: #6b7280;
        font-size: 0.78rem;
        font-weight: 700;
        background: #fbfcfd;
    }

    .device-detail-list dd {
        min-width: 0;
        color: #2f3340;
        overflow-wrap: anywhere;
    }

    .device-detail-list dt:last-of-type,
    .device-detail-list dd:last-of-type {
        border-bottom: 0;
    }

    body.dark-mode .device-credential-banner,
    body.dark-mode .device-detail-card,
    body.dark-mode .device-detail-card-header,
    body.dark-mode .device-detail-list dt {
        background: #1e293b;
        border-color: #334155;
        color: #e2e8f0;
    }

    body.dark-mode .device-detail-list dd {
        background: #172033;
        border-color: #334155;
        color: #f8fafc;
    }

    body.dark-mode .device-credential-copy,
    body.dark-mode .device-detail-list dt {
        color: #cbd5e1;
    }

    @media (max-width: 767.98px) {
        .device-details-header,
        .device-credential-banner {
            align-items: stretch;
            flex-direction: column;
        }

        .device-credential-banner .btn {
            width: 100%;
        }

        .device-details-grid {
            grid-template-columns: 1fr;
        }

        .device-detail-list {
            grid-template-columns: 1fr;
        }

        .device-detail-list dt {
            padding-bottom: 0.25rem;
            border-bottom: 0;
        }

        .device-detail-list dd {
            padding-top: 0.25rem;
        }
    }
</style>
<?php $__env->stopPush(); ?>

<?php $__env->startSection('content'); ?>
<div class="container-fluid device-details-page">
    <div class="device-details-header mb-3">
        <div>
            <h3 class="mb-1">Device Details</h3>
            <p class="text-muted mb-0">View the registered device information and authorization status.</p>
        </div>
        <a href="<?php echo e(route('admin.devices.index')); ?>" class="btn btn-outline-secondary">Back to List</a>
    </div>

    <div class="card">
        <div class="card-body">
            <?php if(session('new_device_credential')): ?>
                <div class="alert alert-warning" role="alert">
                    <strong>Save this device credential now.</strong> It is shown only once. Download the setup file before leaving this page.
                    <div class="input-group mt-2">
                        <input id="device-credential" class="form-control font-monospace" value="<?php echo e(session('new_device_credential')); ?>" readonly>
                        <button class="btn btn-outline-secondary" type="button" data-copy-credential>Copy</button>
                    </div>
                    <button class="btn btn-primary btn-sm mt-2" type="button" data-download-device-setup>Download setup package</button>
                    <div class="small mt-2">Import this setup package from the Attendance and Enrollment apps. The credential is plain text in this download, so keep it private and delete it after importing.</div>
                </div>
            <?php endif; ?>

            <div class="device-credential-banner">
                <div class="device-credential-copy">
                    <strong>Biometric API credential</strong>
                    <span class="badge ms-1 <?php echo e($credentialStatus === 'provisioned' ? 'bg-success' : 'bg-warning text-dark'); ?>">
                        <?php echo e(match ($credentialStatus) {
                            'provisioned' => 'Provisioned',
                            'unreadable' => 'Cannot be decrypted',
                            default => 'Not provisioned',
                        }); ?>

                    </span>
                    <div class="small mt-1">
                        <?php if($credentialStatus === 'unreadable'): ?>
                            The stored credential cannot be decrypted. Generate a new credential and import the setup package again.
                        <?php else: ?>
                            Rotating it disables the previous credential immediately.
                        <?php endif; ?>
                    </div>
                </div>
                <button type="button" class="btn btn-outline-danger btn-sm"
                    data-rotate-credential
                    data-url="<?php echo e(route('admin.devices.credential.rotate', $device)); ?>"
                    data-csrf="<?php echo e(csrf_token()); ?>">
                    <?php echo e($credentialStatus === 'not_provisioned' ? 'Generate Credential' : 'Rotate Credential'); ?>

                </button>
            </div>
            <div class="alert alert-warning d-none" role="alert" data-credential-result></div>
            <div class="small text-muted mb-3">When the credential changes, download a fresh setup package and import it in Attendance and Enrollment. New app versions are checked automatically at startup; device credentials and local attendance data are retained during app updates.</div>

            <div class="device-details-grid">
                <section class="device-detail-card">
                    <div class="device-detail-card-header">Device Identity</div>
                    <dl class="device-detail-list">
                <dt>Device Name</dt>
                <dd><?php echo e($device->device_name); ?></dd>

                <dt>MAC Address</dt>
                <dd><code><?php echo e($device->formatted_mac_address); ?></code></dd>

                <dt>Scanner Serial</dt>
                <dd><code><?php echo e($device->serial_number ?: 'N/A'); ?></code></dd>

                <dt>Device ID</dt>
                <dd><code><?php echo e($device->laptop_mac_address ?: 'N/A'); ?></code></dd>

                <dt>Device Type</dt>
                <dd><?php echo e(str_replace('_', ' ', ucfirst($device->device_type))); ?></dd>

                <dt>Status</dt>
                <dd><span class="badge <?php echo e($device->status === 'active' ? 'bg-success' : ($device->status === 'maintenance' ? 'bg-warning text-dark' : 'bg-secondary')); ?>"><?php echo e(ucfirst($device->status)); ?></span></dd>
                    </dl>
                </section>

                <section class="device-detail-card">
                    <div class="device-detail-card-header">Location &amp; Security</div>
                    <dl class="device-detail-list">

                <dt>Branch</dt>
                <dd><?php echo e($device->branch?->branch_name ?? 'N/A'); ?></dd>

                <dt>Location</dt>
                <dd><?php echo e($device->location ?: 'N/A'); ?></dd>

                <dt>School Address</dt>
                <dd><?php echo e($device->address ?: 'N/A'); ?></dd>

                <dt>Coordinates</dt>
                <dd><?php echo e($device->latitude !== null && $device->longitude !== null ? "{$device->latitude}, {$device->longitude}" : 'N/A'); ?></dd>

                <dt>Allowed Radius</dt>
                <dd><?php echo e($device->radius_meters ? "{$device->radius_meters} meters" : 'N/A'); ?></dd>

                <dt>Location Check</dt>
                <dd><span class="badge <?php echo e($device->location_check_enabled ? 'bg-success' : 'bg-secondary'); ?>"><?php echo e($device->location_check_enabled ? 'Enabled' : 'Disabled'); ?></span></dd>

                <dt>Last Used</dt>
                <dd><?php echo e($device->last_used_at?->format('M d, Y h:i A') ?? 'Never'); ?></dd>

                <dt>Notes</dt>
                <dd><?php echo e($device->notes ?: 'N/A'); ?></dd>
                    </dl>
                </section>
            </div>

            <div class="d-flex justify-content-end gap-2 mt-4">
                <a href="<?php echo e(route('admin.devices.edit', $device)); ?>" class="btn btn-primary">Edit Device</a>
            </div>
        </div>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
<script>
const deviceSetupConfig = <?php echo json_encode($deviceSetupPackage, 15, 512) ?>;

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
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\ENIGMA_BREAKER\mtcgs-main_09-06-26\mtcgs-ems\resources\views\admin\devices\show.blade.php ENDPATH**/ ?>