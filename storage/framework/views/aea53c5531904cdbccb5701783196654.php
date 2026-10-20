

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

    <?php $__empty_1 = true; $__currentLoopData = $entries->getCollection()->groupBy('payroll_period_id'); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $periodEntries): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
        <?php
            $historyPeriod = $periodEntries->first()->payrollPeriod;
        ?>
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <strong><?php echo e($historyPeriod?->period_code ?? $historyPeriod?->name ?? 'Payroll'); ?></strong>
                <span class="text-muted small">
                    <?php echo e($historyPeriod?->start_date?->format('M d, Y')); ?> – <?php echo e($historyPeriod?->end_date?->format('M d, Y')); ?>

                </span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Employee Name</th>
                            <th>Employee ID</th>
                            <th>Position</th>
                            <th>Payroll Status</th>
                            <th class="text-end">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $__currentLoopData = $periodEntries; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $entry): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <tr>
                                <td><?php echo e($historyPeriod?->payment_date?->format('M d, Y') ?? $historyPeriod?->end_date?->format('M d, Y') ?? '--'); ?></td>
                                <td><?php echo e($entry->employeeProfile?->first_name); ?> <?php echo e($entry->employeeProfile?->last_name); ?></td>
                                <td><?php echo e($entry->employeeProfile?->employee_number ?? '--'); ?></td>
                                <td><?php echo e($entry->employeeProfile?->position ?? '--'); ?></td>
                                <td><span class="badge bg-success"><?php echo e(ucfirst($historyPeriod?->status ?? $entry->status)); ?></span></td>
                                <td class="text-end">₱<?php echo e(number_format((float) $entry->cash_advance_deduction, 2)); ?></td>
                            </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </tbody>
                    <tfoot>
                        <tr class="fw-semibold">
                            <td colspan="5" class="text-end">Period Total</td>
                            <td class="text-end">₱<?php echo e(number_format($periodEntries->sum('cash_advance_deduction'), 2)); ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
        <div class="card">
            <div class="card-body text-center text-muted py-4">
                No cash advance deductions from fully approved payrolls found.
            </div>
        </div>
    <?php endif; ?>
    <?php if($entries->hasPages()): ?>
        <div class="card-footer"><?php echo e($entries->links()); ?></div>
    <?php endif; ?>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\ENIGMA_BREAKER\mtcgs-main_09-06-26\mtcgs-ems\resources\views\cash-advances\history.blade.php ENDPATH**/ ?>