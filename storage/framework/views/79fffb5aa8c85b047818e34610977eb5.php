<?php $__env->startSection('title', 'Cash Advance'); ?>

<?php $__env->startSection('content'); ?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">Cash Advance</h1>
            <p class="text-muted mb-0">Employee Apply → FO Review → BH Review → HR Approval → FH Final Approval</p>
        </div>
    </div>

    <?php if($profile): ?>
        <div class="card mb-4">
            <div class="card-header"><h5 class="mb-0">Apply for Cash Advance</h5></div>
            <div class="card-body">
                <p class="text-muted">Active employees may apply up to <strong>₱1,000</strong>. New hires are marked for additional BH and FH review.</p>
                <form method="POST" action="<?php echo e(route('cash-advances.store')); ?>" class="row g-3">
                    <?php echo csrf_field(); ?>
                    <div class="col-md-3">
                        <label class="form-label">Requested Amount</label>
                        <input type="number" name="requested_amount" min="1" max="<?php echo e($availableCashAdvanceBalance); ?>" step="0.01" class="form-control" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Available Remaining Limit</label>
                        <input type="text" class="form-control" value="₱<?php echo e(number_format($availableCashAdvanceBalance, 2)); ?>" readonly>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Total Active Cash Advances</label>
                        <input type="text" class="form-control" value="₱<?php echo e(number_format($totalCashAdvances, 2)); ?>" readonly>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Purpose</label>
                        <input type="text" name="purpose" maxlength="2000" class="form-control" required>
                    </div>
                    <div class="col-12"><button class="btn btn-primary"><i class="fas fa-paper-plane me-1"></i> Submit Application</button></div>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <?php echo $__env->make('cash-advances.partials.payroll-preview', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

    <div class="card">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h5 class="mb-0"><?php echo e($historyView ? 'Cash Advance History' : 'Cash Advance Applications'); ?></h5>
                <?php if($canViewHistory): ?>
                    <div class="btn-group" role="group" aria-label="Cash advance views">
                        <a href="<?php echo e(route('cash-advances.index')); ?>" class="btn btn-sm <?php echo e($historyView ? 'btn-outline-primary' : 'btn-primary'); ?>">Pending Applications</a>
                        <a href="<?php echo e(route('cash-advances.index', ['view' => 'history'])); ?>" class="btn btn-sm <?php echo e($historyView ? 'btn-primary' : 'btn-outline-primary'); ?>">Approval History</a>
                        <a href="<?php echo e(route('cash-advances.history')); ?>" class="btn btn-sm btn-outline-primary">Payroll History</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php if($historyView): ?>
            <div class="card-body border-bottom">
                <form method="GET" action="<?php echo e(route('cash-advances.index')); ?>" class="row g-3 align-items-end">
                    <input type="hidden" name="view" value="history">
                    <div class="col-sm-4 col-md-3">
                        <label for="period_start" class="form-label">Period From</label>
                        <input id="period_start" type="date" name="period_start" value="<?php echo e(request('period_start')); ?>" class="form-control">
                    </div>
                    <div class="col-sm-4 col-md-3">
                        <label for="period_end" class="form-label">Period To</label>
                        <input id="period_end" type="date" name="period_end" value="<?php echo e(request('period_end')); ?>" class="form-control">
                    </div>
                    <div class="col-sm-4 col-md-3 d-flex gap-2">
                        <button class="btn btn-primary">Filter</button>
                        <a href="<?php echo e(route('cash-advances.index', ['view' => 'history'])); ?>" class="btn btn-outline-secondary">Clear</a>
                    </div>
                    <div class="col-12"><small class="text-muted">Filter by the date this role reviewed the application.</small></div>
                </form>
            </div>
        <?php endif; ?>
        <div class="table-responsive">
            <?php
                $runningCashAdvanceBalance = 1000;
                $cashAdvanceRowBalances = [];
                foreach ($applications->getCollection()->sortBy('created_at') as $listedApplication) {
                    if (in_array($listedApplication->status, ['approved', 'deducting'], true)) {
                        $listedAmount = (float) ($listedApplication->approved_amount ?: $listedApplication->requested_amount);
                        $runningCashAdvanceBalance = max(0, $runningCashAdvanceBalance - $listedAmount);
                        $cashAdvanceRowBalances[$listedApplication->id] = $runningCashAdvanceBalance;
                    } elseif (in_array($listedApplication->status, ['pending_fo', 'pending_bh', 'pending_hr', 'pending_fh'], true)) {
                        $cashAdvanceRowBalances[$listedApplication->id] = $cashAdvanceAvailableByEmployee[$listedApplication->employee_profile_id] ?? 1000;
                    } else {
                        $cashAdvanceRowBalances[$listedApplication->id] = 0;
                    }
                }
            ?>
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Employee</th><th>Applied Date</th><th>Amount</th>
                        <?php if (! ($historyView)): ?><th>Remaining After This Advance</th><?php endif; ?>
                        <th>Purpose</th><th>Status</th><th><?php echo e($historyView ? 'Reviewed By' : 'Action'); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php $__empty_1 = true; $__currentLoopData = $applications; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $application): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <tr>
                        <td><?php echo e($application->employeeProfile->first_name); ?> <?php echo e($application->employeeProfile->last_name); ?><br><small class="text-muted"><?php echo e($application->employeeProfile->employee_number); ?></small></td>
                        <td>
                            <?php if(strtolower(trim((string) ($application->employeeProfile->status ?? ''))) !== 'new hire'): ?>
                                <?php echo e($application->created_at?->format('M d, Y h:i A') ?? '--'); ?>

                            <?php else: ?>
                                --
                            <?php endif; ?>
                        </td>
                        <td>₱<?php echo e(number_format($application->approved_amount ?: $application->requested_amount, 2)); ?></td>
                        <?php
                            $cashAdvanceAmount = (float) ($application->approved_amount ?: $application->requested_amount);
                            $cashAdvanceRemaining = $cashAdvanceRowBalances[$application->id] ?? 0;
                        ?>
                        <?php if (! ($historyView)): ?>
                            <td>₱<?php echo e(number_format($cashAdvanceRemaining, 2)); ?></td>
                        <?php endif; ?>
                        <td><?php echo e($application->purpose); ?></td>
                        <td><span class="badge bg-<?php echo e(in_array($application->status, ['approved'], true) ? 'success' : (str_contains($application->status, 'pending') ? 'warning text-dark' : 'secondary')); ?>"><?php echo e(str_replace('_', ' ', ucfirst($application->status))); ?></span></td>
                        <td>
                            <?php
                                $reviewRoute = match($application->status) {
                                    'pending_fo' => auth()->user()->role === 'finance_officer' ? route('cash-advances.fo-review', $application) : null,
                                    'pending_bh' => auth()->user()->isBranchAdmin() ? route('cash-advances.bh-review', $application) : null,
                                    'pending_hr' => auth()->user()->isSuperAdmin() ? route('cash-advances.hr-review', $application) : null,
                                    'pending_fh' => auth()->user()->isFinanceHead() ? route('cash-advances.fh-review', $application) : null,
                                    default => null,
                                };
                            ?>
                            <?php if($historyView): ?>
                                <?php
                                    $reviewer = $application->{$reviewerRelation};
                                    $reviewedAt = $application->{$reviewedAtColumn};
                                ?>
                                <span><?php echo e($reviewer?->name ?? 'Reviewer unavailable'); ?></span>
                                <br><small class="text-muted"><?php echo e($reviewedAt?->format('M d, Y h:i A') ?? '--'); ?></small>
                            <?php elseif($reviewRoute): ?>
                                <form method="POST" action="<?php echo e($reviewRoute); ?>" class="d-flex gap-1 flex-wrap">
                                    <?php echo csrf_field(); ?>
                                    <input type="text" name="reason" class="form-control form-control-sm" placeholder="Reason / note">
                                    <button name="decision" value="approve" class="btn btn-sm btn-success">Approve</button>
                                    <button name="decision" value="reject" class="btn btn-sm btn-outline-danger">Reject</button>
                                </form>
                            <?php else: ?>
                                <span class="text-muted small"><?php echo e($application->rejection_reason ?: 'Waiting for next approver'); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    <tr><td colspan="<?php echo e($historyView ? 6 : 7); ?>" class="text-center text-muted py-4"><?php echo e($historyView ? 'No cash advance history found for this period.' : 'No cash advance applications for this account or review stage.'); ?></td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="card-footer"><?php echo e($applications->links()); ?></div>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\MTCGS-EMS-TEAM-ENEGMA-BREAKERS\resources\views\cash-advances\index.blade.php ENDPATH**/ ?>