<?php $__env->startSection('title', 'User Profile'); ?>

<?php $__env->startSection('content'); ?>
<?php
    $storedFingerprint = trim((string) ($profile->fingerprint_template ?? ''));
    $decodedFingerprint = json_decode($storedFingerprint, true);
    $handFingerStatus = [
        'Left' => [
            'Thumb' => false,
            'Index Finger' => false,
            'Middle Finger' => false,
            'Ring Finger' => false,
            'Pinky' => false,
        ],
        'Right' => [
            'Thumb' => false,
            'Index Finger' => false,
            'Middle Finger' => false,
            'Ring Finger' => false,
            'Pinky' => false,
        ],
    ];
    $fingerEntries = [];

    if (is_array($decodedFingerprint) && isset($decodedFingerprint['templates']) && is_array($decodedFingerprint['templates'])) {
        foreach (array_values($decodedFingerprint['templates']) as $index => $template) {
            $hasTemplateData = is_string($template) && $template !== '';
            $hasTemplateData = $hasTemplateData
                || (is_array($template) && is_string($template['fingerprint_data'] ?? null) && $template['fingerprint_data'] !== '');

            if (!$hasTemplateData) {
                continue;
            }

            $fingerEntries[] = [
                'number' => $index + 1,
                'name' => is_array($template) && is_string($template['finger_name'] ?? null)
                    ? trim($template['finger_name'])
                    : '',
            ];
        }
    } elseif ($storedFingerprint !== '' && strtolower($storedFingerprint) !== 'null') {
        $fingerEntries[] = ['number' => 1, 'name' => ''];
    }

    $unidentifiedFingers = [];
    $fingerMatchers = [
        'Thumb' => ['thumb'],
        'Index Finger' => ['index'],
        'Middle Finger' => ['middle'],
        'Ring Finger' => ['ring'],
        'Pinky' => ['pinky', 'pinkie', 'little'],
    ];

    foreach ($fingerEntries as $fingerEntry) {
        $normalizedName = preg_replace('/[^a-z]/', '', strtolower($fingerEntry['name']));
        $hand = strpos($normalizedName, 'left') !== false
            ? 'Left'
            : (strpos($normalizedName, 'right') !== false ? 'Right' : null);
        $finger = null;

        foreach ($fingerMatchers as $fingerLabel => $matchers) {
            foreach ($matchers as $matcher) {
                if (strpos($normalizedName, $matcher) !== false) {
                    $finger = $fingerLabel;
                    break 2;
                }
            }
        }

        if ($hand !== null && $finger !== null) {
            $handFingerStatus[$hand][$finger] = true;
            continue;
        }

        $unidentifiedFingers[] = [
            'name' => $fingerEntry['name'] !== ''
                ? $fingerEntry['name']
                : 'Finger ' . $fingerEntry['number'] . ' (name not recorded)',
        ];
    }

    $hasRegisteredFingerprints = (bool) ($profile->is_fingerprint_registered ?? false) || $fingerEntries !== [];
?>
<div class="container-fluid profile-page">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1 fw-bold"><i class="fas fa-id-card text-primary me-2"></i>User Profile</h2>
            <p class="text-muted mb-0">Complete profile information</p>
        </div>
        <a href="<?php echo e(request()->routeIs('admin.employee.profile') ? route('admin.biometric') : route('finance.employees')); ?>" class="btn btn-outline-primary">
            <i class="fas fa-arrow-left me-1"></i> Back to <?php echo e(request()->routeIs('admin.employee.profile') ? 'Biometric Management' : 'Employees'); ?>

        </a>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><?php echo e($profile->first_name); ?> <?php echo e($profile->last_name); ?></h5>
                </div>
                <div class="card-body">
                    <?php
                        $formatProfileDate = static function ($value): string {
                            if (!$value) {
                                return 'Not specified';
                            }

                            return $value instanceof \DateTimeInterface
                                ? $value->format('M d, Y')
                                : \Carbon\Carbon::parse($value)->format('M d, Y');
                        };
                    ?>
                    <div class="row g-3">
                        <div class="col-md-6"><div class="text-muted small">Employee Number</div><div class="fw-semibold"><?php echo e($profile->employee_number ?? 'Not specified'); ?></div></div>
                        <div class="col-md-6"><div class="text-muted small">Role</div><div class="fw-semibold"><?php echo e(str_replace('_', ' ', ucfirst($profile->user?->role ?? 'User'))); ?></div></div>
                        <div class="col-md-6"><div class="text-muted small">Position</div><div class="fw-semibold"><?php echo e($profile->position ?? 'Not specified'); ?></div></div>
                        <div class="col-md-6"><div class="text-muted small">Department</div><div class="fw-semibold"><?php echo e($profile->department ?? 'Not specified'); ?></div></div>
                        <div class="col-md-6"><div class="text-muted small">Email</div><div class="fw-semibold"><?php echo e($profile->user?->email ?? 'Not specified'); ?></div></div>
                        <div class="col-md-6"><div class="text-muted small">Date Hired</div><div class="fw-semibold"><?php echo e($formatProfileDate($profile->date_hired)); ?></div></div>
                        <div class="col-md-6"><div class="text-muted small">Birthdate</div><div class="fw-semibold"><?php echo e($formatProfileDate($profile->date_of_birth)); ?></div></div>
                        <div class="col-md-6"><div class="text-muted small">Gender</div><div class="fw-semibold"><?php echo e($profile->gender ?? 'Not specified'); ?></div></div>
                        <div class="col-md-6"><div class="text-muted small">Branch</div><div class="fw-semibold"><?php echo e($profile->branch?->branch_name ?? 'Not assigned'); ?></div></div>
                        <div class="col-md-6"><div class="text-muted small">Status</div><div class="fw-semibold"><?php echo e($profile->status ?? 'Not specified'); ?></div></div>
                    </div>
                    <hr class="my-4">
                    <h6 class="text-primary mb-3"><i class="fas fa-id-card me-2"></i>Government Numbers</h6>
                    <div class="row g-3">
                        <div class="col-md-6"><div class="text-muted small">SSS Number</div><div class="fw-semibold"><?php echo e($profile->sss_number ?? 'Not specified'); ?></div></div>
                        <div class="col-md-6"><div class="text-muted small">PhilHealth Number</div><div class="fw-semibold"><?php echo e($profile->philhealth_number ?? 'Not specified'); ?></div></div>
                        <div class="col-md-6"><div class="text-muted small">Pag-IBIG Number</div><div class="fw-semibold"><?php echo e($profile->pagibig_number ?? 'Not specified'); ?></div></div>
                        <div class="col-md-6"><div class="text-muted small">TIN Number</div><div class="fw-semibold"><?php echo e($profile->tin_number ?? 'Not specified'); ?></div></div>
                    </div>
                    <hr class="my-4">
                    <h6 class="text-primary mb-3"><i class="fas fa-fingerprint me-2"></i>Fingerprint Status</h6>
                    <div class="row g-3">
                        <?php $__currentLoopData = $handFingerStatus; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $hand => $fingers): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <div class="col-md-6">
                                <div class="card h-100">
                                    <div class="card-header bg-light">
                                        <h6 class="mb-0"><?php echo e($hand); ?> Hand</h6>
                                    </div>
                                    <div class="list-group list-group-flush">
                                        <?php $__currentLoopData = $fingers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $fingerName => $isRegistered): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                            <div class="list-group-item d-flex justify-content-between align-items-center"
                                                 aria-label="<?php echo e($hand); ?> <?php echo e($fingerName); ?>: <?php echo e($isRegistered ? 'Registered' : 'Not Registered'); ?>">
                                                <span><?php echo e($fingerName); ?></span>
                                                <span class="badge <?php echo e($isRegistered ? 'bg-success' : 'bg-secondary'); ?>">
                                                    <?php echo e($isRegistered ? 'Registered' : 'Not Registered'); ?>

                                                </span>
                                            </div>
                                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </div>
                    <?php if(count($unidentifiedFingers) > 0): ?>
                        <div class="alert alert-warning mt-3 mb-0">
                            <div class="fw-semibold mb-2">Registered fingerprint with unknown finger</div>
                            <?php $__currentLoopData = $unidentifiedFingers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $finger): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span><?php echo e($finger['name']); ?></span>
                                    <span class="badge bg-success">Registered</span>
                                </div>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </div>
                    <?php elseif($hasRegisteredFingerprints && !in_array(true, array_merge(...array_values($handFingerStatus)), true)): ?>
                        <p class="text-muted mt-3 mb-0">Fingerprint is registered, but the finger name is not available in this record.</p>
                    <?php elseif(!$hasRegisteredFingerprints): ?>
                        <p class="text-muted mt-3 mb-0">No registered fingerprints.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm text-center h-100">
                <div class="card-header bg-white"><h5 class="mb-0">Profile Picture</h5></div>
                <div class="card-body d-flex flex-column align-items-center justify-content-center">
                    <?php
                        $hasProfilePhoto = $profile->profile_photo && \Illuminate\Support\Facades\Storage::disk('public')->exists($profile->profile_photo);
                    ?>
                    <?php if($hasProfilePhoto): ?>
                        <img src="<?php echo e(asset('storage/' . ltrim($profile->profile_photo, '/') . '?v=' . $profile->updated_at?->timestamp)); ?>" alt="<?php echo e($profile->first_name); ?> <?php echo e($profile->last_name); ?>" class="rounded-circle border shadow-sm mb-3" style="width: 190px; height: 190px; object-fit: cover;">
                    <?php else: ?>
                        <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center shadow-sm mb-3" style="width: 190px; height: 190px; font-size: 4rem;">
                            <?php echo e(strtoupper(substr($profile->first_name, 0, 1) . substr($profile->last_name, 0, 1))); ?>

                        </div>
                        <p class="text-muted mb-0">No profile picture uploaded</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\MTCGS-EMS-TEAM-ENEGMA-BREAKERS\resources\views\finance\employee-profile.blade.php ENDPATH**/ ?>