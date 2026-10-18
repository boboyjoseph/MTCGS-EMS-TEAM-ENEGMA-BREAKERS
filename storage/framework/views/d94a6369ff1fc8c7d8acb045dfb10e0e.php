<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Payroll Preview: Sep 1–15, 2026</h5>
        <span class="badge bg-secondary">Not posted</span>
    </div>
    <div class="card-body">
        <p class="text-muted"><?php echo e($cutoffCashAdvances->count()); ?> eligible approved cash advance application<?php echo e($cutoffCashAdvances->count() === 1 ? '' : 's'); ?>. Nothing is posted to payroll.</p>
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Approved Loan Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $__empty_1 = true; $__currentLoopData = $cutoffCashAdvances; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $application): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                        <tr>
                            <td><?php echo e($application->employeeProfile?->first_name); ?> <?php echo e($application->employeeProfile?->last_name); ?></td>
                            <td>₱<?php echo e(number_format((float) ($application->approved_amount ?: $application->requested_amount), 2)); ?></td>
                        </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                        <tr><td colspan="2" class="text-center text-muted py-3">No eligible approved cash advances for this cutoff.</td></tr>
                    <?php endif; ?>
                </tbody>
                <?php if($cutoffCashAdvances->isNotEmpty()): ?>
                    <tfoot>
                        <tr class="fw-semibold">
                            <td>Approved Loan Total</td>
                            <td>₱<?php echo e(number_format($cutoffApprovedTotal, 2)); ?></td>
                        </tr>
                    </tfoot>
                <?php endif; ?>
            </table>
        </div>
    </div>
</div><?php /**PATH C:\xampp\htdocs\ENIGMA_BREAKER\mtcgs-main_09-06-26\mtcgs-ems\resources\views/cash-advances/partials/payroll-preview.blade.php ENDPATH**/ ?>