<?php $__env->startSection('title', 'Biometric Management'); ?>

<?php $__env->startSection('content'); ?>
<style>
    .biometric-status-list .list-group-item {
        padding: 0.9rem 1rem;
    }

    .biometric-status-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 0.75rem;
        width: 100%;
        flex-wrap: nowrap;
    }

    .biometric-status-copy {
        min-width: 0;
        flex: 1 1 auto;
    }

    .biometric-status-copy strong,
    .biometric-status-copy small {
        display: block;
        word-break: break-word;
        overflow-wrap: anywhere;
    }

    .biometric-status-badge {
        flex-shrink: 0;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.35rem;
        white-space: nowrap;
        min-width: 132px;
        font-size: 0.8rem;
        padding: 0.5rem 0.7rem;
    }

    .biometric-profile-card {
        display: flex;
        align-items: center;
        gap: 0.8rem;
        min-width: 0;
    }

    .biometric-profile-avatar {
        width: 46px;
        height: 46px;
        flex: 0 0 46px;
        object-fit: cover;
        border-radius: 50%;
        border: 2px solid rgba(13, 110, 253, 0.2);
    }

    .biometric-profile-initials {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: #0d6efd;
        color: #fff;
        font-weight: 700;
        font-size: 0.9rem;
    }

    @media (max-width: 576px) {
        .biometric-status-list .list-group-item {
            padding: 0.8rem 0.75rem;
        }

        .biometric-status-item {
            flex-direction: column;
            align-items: flex-start;
            gap: 0.6rem;
        }

        .biometric-status-copy {
            width: 100%;
        }

        .biometric-status-badge {
            width: 100%;
            min-width: 0;
            justify-content: center;
        }
    }

    body.dark-mode .biometric-page .card-header.bg-white,
    body.dark-mode .biometric-page .biometric-status-list .list-group-item,
    body.dark-mode .biometric-page .biometric-status-list .list-group-item.bg-light {
        background-color: #1e293b !important;
        color: #e2e8f0 !important;
        border-color: #334155 !important;
    }

    body.dark-mode .biometric-page .biometric-status-list .list-group-item strong,
    body.dark-mode .biometric-page .biometric-status-list .list-group-item small {
        color: #e2e8f0 !important;
    }

    body.dark-mode .biometric-page .text-primary {
        color: #FFD166 !important;
    }

    .biometric-profile-link {
        color: inherit;
        text-decoration: none;
    }

    .biometric-profile-link:hover {
        text-decoration: underline;
    }

    body.dark-mode .biometric-page .biometric-profile-link {
        color: #FFD166 !important;
    }
</style>
<div class="container-fluid biometric-page">
    <div class="row mb-4">
        <div class="col-12">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h2><i class="fas fa-fingerprint me-2"></i> Biometric Management</h2>
                    <p class="mb-0">Manage fingerprint registrations for eligible employees in your branch.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Admin / Branch Head's Own DTR Card -->
    <?php
        $user = Auth::user();
        $adminProfile = $user ? $user->getAdminProfile() : null;
        $isHrAdmin = $user && $user->role === 'admin'
            && (($user->admin_type ?? '') === 'hr' || strtolower((string) ($adminProfile?->admin_level ?? '')) === 'hr');
        $branchHeadProfile = $user ? $user->getBranchHeadProfile() : null;
        $employeeProfile = $adminProfile ? App\Models\EmployeeProfile::find($adminProfile->employee_profile_id) : ($branchHeadProfile ? App\Models\EmployeeProfile::find($branchHeadProfile->employee_profile_id) : null);
        $todayAttendance = $employeeProfile ? App\Models\AttendanceLog::where('employee_profile_id', $employeeProfile->id)->whereDate('attendance_date', today())->first() : null;
        $canSelfRegisterAdminFingerprint = $user && $user->role === 'admin' && ($user->admin_type ?? '') === 'branch_admin';
        $isBranchHeadSelfAttendance = $user && $user->role === 'branch_head';
    ?>

    <?php if($adminProfile && $canSelfRegisterAdminFingerprint && !$isHrAdmin): ?>
    <div class="row mb-4">
        <div class="col-md-6 mx-auto">
            <div class="card shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="fas fa-user-shield text-primary me-2"></i> My Admin Fingerprint</h5>
                </div>
                <div class="card-body text-center">
                    <?php if($adminProfile->hasRegisteredFingerprint()): ?>
                        <div class="alert alert-success mb-0">
                            <i class="fas fa-check-circle"></i> Your fingerprint is registered.
                        </div>
                    <?php elseif(empty($adminProfile->employee_number) || empty($adminProfile->first_name) || empty($adminProfile->last_name)): ?>
                        <div class="alert alert-warning mb-0">
                            An employee number, first name, and last name are required in your admin profile before fingerprint enrollment.
                        </div>
                    <?php else: ?>
                        <div id="adminFingerprintStatus" class="alert alert-info d-none" role="status"></div>
                        <button id="registerAdminFingerprintButton" onclick="registerAdminFingerprint()" class="btn btn-primary">
                            <i class="fas fa-fingerprint"></i> Register My Fingerprint
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if(false && $employeeProfile && $canSelfRegisterAdminFingerprint): ?>
    <div class="row mb-4">
        <div class="col-md-6 mx-auto">
            <div class="card shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="fas fa-user-shield text-danger me-2"></i> <?php echo e($isBranchHeadSelfAttendance ? 'My Attendance (Branch Head)' : 'My Attendance (Admin)'); ?></h5>
                </div>
                <div class="card-body text-center">
                    <div id="adminAttendanceStatus">
                        <?php if(!$adminProfile->is_fingerprint_registered): ?>
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle"></i> Please register your fingerprint first.
                            </div>
                            <button onclick="registerAdminFingerprint()" class="btn btn-primary">
                                <i class="fas fa-fingerprint"></i> Register My Fingerprint
                            </button>
                        <?php elseif(!$todayAttendance || !$todayAttendance->am_in || !$todayAttendance->am_out || !$todayAttendance->pm_in || !$todayAttendance->pm_out): ?>
                            <?php
                                $adminAttendanceButtonClass = 'success';
                                if ($todayAttendance && $todayAttendance->am_in && !$todayAttendance->am_out) {
                                    $adminAttendanceButtonClass = 'warning';
                                } elseif ($todayAttendance && $todayAttendance->am_out && !$todayAttendance->pm_in) {
                                    $adminAttendanceButtonClass = 'info';
                                } elseif ($todayAttendance && $todayAttendance->pm_in && !$todayAttendance->pm_out) {
                                    $adminAttendanceButtonClass = 'danger';
                                }
                            ?>
                            <button onclick="processAdminAttendance()" class="btn btn-<?php echo e($adminAttendanceButtonClass); ?> btn-lg px-5 py-3">
                                <i class="fas fa-fingerprint fa-2x d-block mb-2"></i>
                                FINGERPRINT ATTENDANCE
                            </button>
                        <?php else: ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle"></i> Completed for Today
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Register Personnel Section -->
    <div class="card mb-4">
        <div class="card-header bg-white">
            <h5 class="mb-0"><i class="fas fa-users text-primary me-2"></i> Fingerprint Registration</h5>
        </div>
        <div class="card-body">
            <div class="mb-3">
                <h6 class="text-primary mb-2"><i class="fas fa-user-tie me-2"></i>Employees</h6>
                <small class="text-muted">HR approval is separate from fingerprint registration. All employees remain listed with their current fingerprint status.</small>
            </div>
            <div id="unregisteredEmployeesList" class="biometric-status-list">
                <div class="text-center">
                    <div class="spinner-border spinner-border-sm"></div> Loading employees...
                </div>
            </div>

    <?php if(Auth::user()->isSuperAdmin() || (Auth::user()->role === 'admin' && Auth::user()->admin_type === 'branch_admin')): ?>
        <div class="mt-4 pt-3 border-top">
            <h6 class="text-primary mb-2"><i class="fas fa-chart-line me-2"></i>Finance Officers</h6>
                <div id="unregisteredFinanceOfficerList">
                    <div class="text-center">
                        <div class="spinner-border spinner-border-sm"></div> Loading finance officers...
                    </div>
                </div>
        </div>

    <?php if(Auth::user()->isSuperAdmin()): ?>
        <div class="mt-4 pt-3 border-top">
            <h6 class="text-primary mb-2"><i class="fas fa-user-shield me-2"></i>Branch Admins</h6>
                <div id="unregisteredBranchAdminList">
                    <div class="text-center">
                        <div class="spinner-border spinner-border-sm"></div> Loading branch admins...
                    </div>
                </div>
        </div>
    <?php endif; ?>
        </div>
        </div>
    <?php endif; ?>
</div>

<?php
    $adminFingerprintProfile = $adminProfile ? [
        'first_name' => $adminProfile->first_name,
        'last_name' => $adminProfile->last_name,
        'employee_number' => $adminProfile->employee_number,
        'email' => $adminProfile->user->email ?? '',
        'branch' => $adminProfile->branch->branch_name ?? '',
        'position' => $adminProfile->position ?? '',
    ] : null;
?>
<script>
const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
const appBaseUrl = <?php echo json_encode(request()->getBaseUrl(), 15, 512) ?>;
const apiUrl = (path) => `${appBaseUrl}/api/${path}`;
const jsonHeaders = {
    'Accept': 'application/json',
    'X-CSRF-TOKEN': csrfToken
};

function getOrCreateFingerprint(key, seed) {
    const stored = localStorage.getItem(key);
    if (stored) {
        return stored;
    }

    const generated = btoa(`${seed}_${Date.now()}`);
    localStorage.setItem(key, generated);
    return generated;
}

// Admin's own fingerprint registration
async function registerAdminFingerprint() {
    const button = document.getElementById('registerAdminFingerprintButton');
    const status = document.getElementById('adminFingerprintStatus');
    const profile = <?php echo e(\Illuminate\Support\Js::from($adminFingerprintProfile)); ?>;

    if (!profile || !profile.employee_number || !profile.first_name || !profile.last_name) {
        if (status) {
            status.className = 'alert alert-danger';
            status.textContent = 'Your admin profile must have an employee number, first name, and last name before fingerprint enrollment.';
        }
        return;
    }
    
    const query = new URLSearchParams({
        first_name: profile.first_name,
        last_name: profile.last_name,
        employee_number: profile.employee_number,
        email: profile.email || '',
        role: 'admin',
        branch: profile.branch || '',
        position: profile.position || ''
    });
    const uri = 'mtcgs-enroll:/register' +
        '?' + query.toString();
    
    try {
        const pollResponse = await fetch(apiUrl('biometric/admin-status'), {
            credentials: 'same-origin',
            headers: jsonHeaders
        });
        const currentStatus = await pollResponse.json();
        if (!pollResponse.ok || !currentStatus.success) {
            throw new Error(currentStatus.message || 'Could not check the admin fingerprint status.');
        }
        if (currentStatus.is_registered) {
            if (status) {
                status.className = 'alert alert-success';
                status.textContent = 'Your fingerprint is already registered.';
            }
            return;
        }

        if (button) {
            button.disabled = true;
        }
        if (status) {
            status.className = 'alert alert-info';
            status.textContent = 'Waiting for the Enrollment app and assigned fingerprint scanner...';
        }

        let attempts = 0;
        const pollInterval = setInterval(async () => {
            attempts++;
            if (attempts > 120) {
                clearInterval(pollInterval);
                if (button) {
                    button.disabled = false;
                }
                if (status) {
                    status.className = 'alert alert-warning';
                    status.textContent = 'No completed enrollment was received. Check the Enrollment app and scanner, then try again.';
                }
                return;
            }

            try {
                const response = await fetch(apiUrl('biometric/admin-status'), {
                    credentials: 'same-origin',
                    headers: jsonHeaders
                });
                const result = await response.json();
                if (response.ok && result.success && result.is_registered) {
                    clearInterval(pollInterval);
                    if (status) {
                        status.className = 'alert alert-success';
                        status.textContent = 'Fingerprint registered successfully.';
                    }
                    location.reload();
                } else if (!response.ok || !result.success) {
                    clearInterval(pollInterval);
                    if (button) {
                        button.disabled = false;
                    }
                    if (status) {
                        status.className = 'alert alert-danger';
                        status.textContent = result.message || 'Could not verify the admin fingerprint status.';
                    }
                }
            } catch (error) {
                clearInterval(pollInterval);
                if (button) {
                    button.disabled = false;
                }
                if (status) {
                    status.className = 'alert alert-danger';
                    status.textContent = error.message || 'Could not check the admin fingerprint status.';
                }
            }
        }, 1000);

        window.location.href = uri;
    } catch (error) {
        if (button) {
            button.disabled = false;
        }
        if (status) {
            status.className = 'alert alert-danger';
            status.textContent = error.message || 'Could not launch the Enrollment app. Make sure it is installed and the mtcgs-enroll link is registered.';
        }
    }
}

async function processAdminAttendance() {
    const fakeFingerprint = getOrCreateFingerprint('mtcgs_admin_fingerprint', 'admin_fingerprint');
    
    const response = await fetch(apiUrl('biometric/admin-attendance'), {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            ...jsonHeaders
        },
        body: JSON.stringify({ fingerprint_data: fakeFingerprint })
    });
    
    const result = await response.json();
    
    if (result.success) {
        alert(result.message);
        location.reload();
    } else {
        alert('Error: ' + (result.error || 'Please follow schedule'));
    }
}

// Load unregistered employees grouped by branch
async function loadUnregisteredEmployees() {
    const container = document.getElementById('unregisteredEmployeesList');
    if (!container) {
        return;
    }

    try {
        const response = await fetch(apiUrl('biometric/unregistered-employees'), {
            headers: jsonHeaders
        });
        const result = await response.json();
        if (!response.ok || !result.success || !Array.isArray(result.data)) {
            throw new Error(result.error || 'The employee list could not be loaded.');
        }

        if (result.data.length === 0) {
            container.innerHTML = '<div class="alert alert-info">No employee profiles were found.</div>';
            return;
        }

        const branches = {};
        result.data.forEach(emp => {
            const branchName = emp.branch_name || 'Unknown Branch';
            if (!branches[branchName]) {
                branches[branchName] = [];
            }
            branches[branchName].push(emp);
        });

        const branchEntries = Object.entries(branches);
        let html = '<div class="list-group biometric-status-list">';

        branchEntries.forEach(([branchName, employees]) => {
            html += `
                <div class="list-group-item bg-light">
                    <h6 class="mb-0 fw-bold text-primary">${branchName}</h6>
                </div>
            `;

            employees.forEach(emp => {
                const isRegistered = Boolean(emp.is_fingerprint_registered);
                const buttonLabel = isRegistered ? 'Registered' : 'Not Registered';
                const statusClass = isRegistered ? 'bg-success' : 'bg-warning';
                const buttonIcon = isRegistered ? 'fa-check-circle' : 'fa-clock';

                html += `
                    <div class="list-group-item">
                        <div class="biometric-status-item">
                            <div class="biometric-profile-card biometric-status-copy">
                                ${profileAvatar(emp)}
                                <div>
                                    ${profileLink(emp)}
                                <small class="text-muted">${emp.employee_number} - ${emp.position || 'Staff'}</small>
                                </div>
                            </div>
                            <span class="badge ${statusClass} text-white biometric-status-badge">
                                <i class="fas ${buttonIcon}"></i> ${buttonLabel}
                            </span>
                        </div>
                    </div>
                `;
            });
        });

        html += '</div>';
        container.innerHTML = html;
    } catch (error) {
        container.innerHTML = '';
        const alert = document.createElement('div');
        alert.className = 'alert alert-danger';
        alert.setAttribute('role', 'alert');
        alert.textContent = `Employee list unavailable: ${error.message}`;
        container.appendChild(alert);
    }
}

function profileAvatar(item) {
    const initials = (item.name || '?')
        .split(/\s+/)
        .filter(Boolean)
        .map(part => part.charAt(0).toUpperCase())
        .slice(0, 2)
        .join('');

    if (item.profile_photo_url) {
        return `<img src="${item.profile_photo_url}" alt="${item.name || 'Profile photo'}" class="biometric-profile-avatar">`;
    }

    return `<span class="biometric-profile-avatar biometric-profile-initials" aria-hidden="true">${initials}</span>`;
}

function profileLink(item) {
    const name = item.name || 'Unnamed profile';
    if (!item.id || !item.profile_type) {
        return `<strong>${name}</strong>`;
    }

    const profileType = encodeURIComponent(item.profile_type);
    const profileId = encodeURIComponent(item.id);
    return `<a class="biometric-profile-link fw-bold" href="${appBaseUrl}/admin/employee/${profileType}/${profileId}/profile">${name}</a>`;
}

// Render list of status items for a target container
function renderStatusList(container, items, emptyMessage, accentClass, labelPrefix, groupByBranch = false) {
    if (!container) {
        return;
    }

    if (!items || items.length === 0) {
        container.innerHTML = `<div class="alert alert-success">${emptyMessage}</div>`;
        return;
    }

    const groups = groupByBranch
        ? items.reduce((result, item) => {
            const branchName = item.branch_name || 'Unknown Branch';
            (result[branchName] ||= []).push(item);
            return result;
        }, {})
        : { '': items };

    let html = '<div class="list-group biometric-status-list">';
    Object.entries(groups).forEach(([branchName, groupItems]) => {
        if (groupByBranch) {
            html += `
                <div class="list-group-item bg-light">
                    <h6 class="mb-0 fw-bold text-primary"><i class="fas fa-building me-2"></i>${branchName}</h6>
                </div>
            `;
        }

        groupItems.forEach(item => {
        const isRegistered = Boolean(item.is_fingerprint_registered);
        const buttonLabel = isRegistered ? 'Registered' : 'Not Registered';
        const buttonIcon = isRegistered ? 'fa-check-circle' : 'fa-clock';
        const statusClass = isRegistered ? 'bg-success' : accentClass;
        const emailText = item.email || item.position || 'N/A';
        const employeeLabel = item.employee_number ? `${item.employee_number}` : '';
        const secondaryLine = item.email
            ? `${item.email}${employeeLabel ? ` - ${employeeLabel}` : ''}`
            : (employeeLabel || emailText);

        html += `
            <div class="list-group-item">
                <div class="biometric-status-item">
                    <div class="biometric-profile-card biometric-status-copy">
                        ${profileAvatar(item)}
                        <div>
                            ${profileLink(item)}
                            <small class="text-muted">${secondaryLine}</small>
                        </div>
                    </div>
                    <span class="badge ${statusClass} text-white biometric-status-badge">
                        <i class="fas ${buttonIcon}"></i> ${buttonLabel}
                    </span>
                </div>
            </div>
        `;
        });
    });
    html += '</div>';
    container.innerHTML = html;
}

// Load unregistered finance officers
async function loadUnregisteredFinanceOfficers() {
    const financeOfficerList = document.getElementById('unregisteredFinanceOfficerList');
    if (!financeOfficerList) {
        return;
    }

    try {
        const response = await fetch(apiUrl('biometric/unregistered-finance-officers'), {
            headers: jsonHeaders
        });
        const result = await response.json();
        if (!response.ok || !result.success || !Array.isArray(result.data)) {
            throw new Error(result.error || 'The finance officer list could not be loaded.');
        }
        renderStatusList(financeOfficerList, result.data, 'No finance officer profiles were found.', 'bg-primary', 'Finance Officer', true);
    } catch (error) {
        financeOfficerList.innerHTML = '';
        const alert = document.createElement('div');
        alert.className = 'alert alert-danger';
        alert.setAttribute('role', 'alert');
        alert.textContent = `Finance officer list unavailable: ${error.message}`;
        financeOfficerList.appendChild(alert);
    }
}

// Load unregistered admins
async function loadUnregisteredAdmins() {
    const branchAdminList = document.getElementById('unregisteredBranchAdminList');
    if (!branchAdminList) {
        return;
    }

    try {
        const response = await fetch(apiUrl('biometric/unregistered-admins'), {
            headers: jsonHeaders
        });
        const result = await response.json();
        if (!response.ok || !result.success || !Array.isArray(result.data)) {
            throw new Error(result.error || 'The branch administrator list could not be loaded.');
        }
        renderStatusList(branchAdminList, result.data, 'No branch administrator profiles were found.', 'bg-warning', 'Branch Admin', true);
    } catch (error) {
        branchAdminList.innerHTML = '';
        const alert = document.createElement('div');
        alert.className = 'alert alert-danger';
        alert.setAttribute('role', 'alert');
        alert.textContent = `Branch administrator list unavailable: ${error.message}`;
        branchAdminList.appendChild(alert);
    }
}

// Register employee fingerprint
async function registerEmployeeFingerprint(employeeId, employeeName) {
    const confirmed = window.confirm(`Are you sure you want to register the fingerprint for ${employeeName}?`);
    if (!confirmed) {
        return;
    }

    const fakeFingerprint = btoa('employee_fingerprint_' + Date.now());
    
    const response = await fetch(apiUrl('biometric/register'), {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            ...jsonHeaders
        },
        body: JSON.stringify({ employee_id: employeeId, fingerprint_data: fakeFingerprint })
    });
    
    const result = await response.json();
    
    if (result.success) {
        alert(`✅ Fingerprint registered for ${employeeName}`);
        await loadUnregisteredEmployees();
    } else {
        alert('Error: ' + result.error);
    }
}

// Register finance officer fingerprint (Super Admin)
async function registerFinanceOfficerFingerprint(financeId, financeName) {
    const confirmed = window.confirm(`Are you sure you want to register the fingerprint for ${financeName}?`);
    if (!confirmed) {
        return;
    }

    const fakeFingerprint = btoa('finance_officer_fingerprint_' + Date.now());
    
    const response = await fetch(apiUrl('biometric/register-finance-officer'), {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            ...jsonHeaders
        },
        body: JSON.stringify({ finance_id: financeId, fingerprint_data: fakeFingerprint })
    });
    
    const result = await response.json();
    
    if (result.success) {
        alert(`✅ Fingerprint registered for finance officer: ${financeName}`);
        await loadUnregisteredFinanceOfficers();
    } else {
        alert('Error: ' + result.error);
    }
}

// Register admin fingerprint (Super Admin)
async function registerAdminOfficerFingerprint(adminId, adminName) {
    const confirmed = window.confirm(`Are you sure you want to register the fingerprint for ${adminName}?`);
    if (!confirmed) {
        return;
    }

    const fakeFingerprint = btoa('admin_officer_fingerprint_' + Date.now());
    
    const response = await fetch(apiUrl('biometric/register-admin-officer'), {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            ...jsonHeaders
        },
        body: JSON.stringify({ admin_id: adminId, fingerprint_data: fakeFingerprint })
    });
    
    const result = await response.json();
    
    if (result.success) {
        alert(`✅ Fingerprint registered for admin: ${adminName}`);
        await loadUnregisteredAdmins();
    } else {
        alert('Error: ' + result.error);
    }
}

// Load all sections
loadUnregisteredEmployees();
if (document.getElementById('unregisteredFinanceHeadList') || document.getElementById('unregisteredFinanceOfficerList') || document.getElementById('unregisteredFinanceList')) {
    loadUnregisteredFinanceOfficers();
}
if (document.getElementById('unregisteredHrList') || document.getElementById('unregisteredBranchAdminList') || document.getElementById('unregisteredAdminsList')) {
    loadUnregisteredAdmins();
}
</script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\ENIGMA_BREAKER\mtcgs-main_09-06-26\mtcgs-ems\resources\views/admin/biometric.blade.php ENDPATH**/ ?>