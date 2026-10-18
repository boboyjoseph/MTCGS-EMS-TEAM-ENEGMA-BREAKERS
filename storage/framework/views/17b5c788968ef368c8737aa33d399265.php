

<?php $__env->startSection('title', 'Cash Advance History'); ?>

<?php $__env->startSection('content'); ?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">Cash Advance History</h1>
            <p class="text-muted mb-0">Cash advance deductions from completed and approved payrolls.</p>
        </div>
        <a href="<?php echo e(route('cash-advances.index', request()->query('scope') === 'my' ? ['scope' => 'my'] : [])); ?>" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i> Back to Cash Advance
        </a>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Payroll Period</th>
                        <th>Employee</th>
                        <th>Payroll Status</th>
                        <th class="text-end">Cash Advance Deducted</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $__empty_1 = true; $__currentLoopData = $entries; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $entry): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                        <tr>
                            <td>
                                <div class="fw-semibold"><?php echo e($entry->payrollPeriod?->period_code ?? $entry->payrollPeriod?->name ?? 'Payroll'); ?></div>
                                <small class="text-muted">
                                    <?php echo e($entry->payrollPeriod?->start_date?->format('M d, Y')); ?> – <?php echo e($entry->payrollPeriod?->end_date?->format('M d, Y')); ?>

                                </small>
                            </td>
                            <td>
                                <?php echo e($entry->employeeProfile?->first_name); ?> <?php echo e($entry->employeeProfile?->last_name); ?>

                                <small class="d-block text-muted"><?php echo e($entry->employeeProfile?->employee_number); ?></small>
                            </td>
                            <td><span class="badge bg-success"><?php echo e(ucfirst($entry->payrollPeriod?->status ?? $entry->status)); ?></span></td>
                            <td class="text-end">₱<?php echo e(number_format((float) $entry->cash_advance_deduction, 2)); ?></td>
                        </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                        <tr>
                            <td colspan="4" class="text-center text-muted py-4">No previous payroll cash advance deductions found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if($entries->hasPages()): ?>
            <div class="card-footer"><?php echo e($entries->links()); ?></div>
        <?php endif; ?>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\ENIGMA_BREAKER\mtcgs-main_09-06-26\mtcgs-ems\resources\views/cash-advances/history.blade.php ENDPATH**/ ?>