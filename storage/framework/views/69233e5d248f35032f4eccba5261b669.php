<?php $__empty_1 = true; $__currentLoopData = $cutoffPreviews; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $cutoffPreview): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Payroll Preview: <?php echo e($cutoffPreview->period->branch?->branch_name); ?> | <?php echo e($cutoffPreview->period->start_date->format('M j')); ?>–<?php echo e($cutoffPreview->period->end_date->format('M j, Y')); ?></h5>
            <span class="badge bg-secondary">Not posted</span>
        </div>
        <div class="card-body">
            <p class="text-muted"><?php echo e($cutoffPreview->applications->count()); ?> eligible approved cash advance application<?php echo e($cutoffPreview->applications->count() === 1 ? '' : 's'); ?>. Nothing is posted to payroll.</p>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Approved Loan Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $__empty_2 = true; $__currentLoopData = $cutoffPreview->applications; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $application): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_2 = false; ?>
                            <tr>
                                <td><?php echo e($application->employeeProfile?->first_name); ?> <?php echo e($application->employeeProfile?->last_name); ?></td>
                                <td>₱<?php echo e(number_format((float) ($application->approved_amount ?: $application->requested_amount), 2)); ?></td>
                            </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_2): ?>
                            <tr><td colspan="2" class="text-center text-muted py-3">No eligible approved cash advances for this cutoff.</td></tr>
                        <?php endif; ?>
                    </tbody>
                    <?php if($cutoffPreview->applications->isNotEmpty()): ?>
                        <tfoot>
                            <tr class="fw-semibold">
                                <td>Approved Loan Total</td>
                                <td>₱<?php echo e(number_format($cutoffPreview->approvedTotal, 2)); ?></td>
                            </tr>
                        </tfoot>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    </div>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Payroll Preview</h5>
            <span class="badge bg-secondary">Not posted</span>
        </div>
        <div class="card-body text-muted">No draft payroll periods are available for your branch scope.</div>
    </div>
<?php endif; ?>

<div class="text-end mb-4">
    <a href="<?php echo e(route('cash-advances.history', request()->query('scope') === 'my' ? ['scope' => 'my'] : [])); ?>" class="btn btn-sm btn-outline-primary">
        <i class="fas fa-history me-1"></i> View Full History
    </a>
</div><?php /**PATH C:\xampp\htdocs\ENIGMA_BREAKER\mtcgs-main_09-06-26\mtcgs-ems\resources\views\cash-advances\partials\payroll-preview.blade.php ENDPATH**/ ?>