

<?php $__env->startSection('title', 'Multi-Branch Assignments'); ?>

<?php $__env->startSection('content'); ?>
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h3 class="mb-1">Multi-Branch Assignments</h3>
            <p class="text-muted mb-0">Super Admin can assign Branch Heads to assist additional branches.</p>
        </div>
        <a href="<?php echo e(route('admin.branch-heads.index')); ?>" class="btn btn-secondary">Back to Branch Admins</a>
    </div>

    <?php if($errors->has('branch_ids')): ?>
        <div class="alert alert-danger"><?php echo e($errors->first('branch_ids')); ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Branch Head</th>
                        <th>Primary Branch</th>
                        <th>Assisting Branches</th>
                        <th>Save Assignments</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $__empty_1 = true; $__currentLoopData = $branchHeads; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $branchHead): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                        <?php
                            $primaryBranchId = $branchHead->getPrimaryBranchId();
                            $assignedBranchIds = $branchHead->assignedBranches->pluck('id')->map(fn ($id) => (int) $id)->all();
                        ?>
                        <tr>
                            <td>
                                <strong><?php echo e($branchHead->name); ?></strong><br>
                                <small class="text-muted"><?php echo e($branchHead->role === 'branch_head' ? 'Branch Head' : 'Branch Admin'); ?></small>
                            </td>
                            <td><?php echo e($branchNames[$primaryBranchId] ?? 'Not assigned'); ?></td>
                            <td>
                                <form id="branch-assignment-<?php echo e($branchHead->id); ?>" method="POST" action="<?php echo e(route('admin.branch-heads.assignments.update', $branchHead)); ?>">
                                    <?php echo csrf_field(); ?>
                                    <?php echo method_field('PUT'); ?>
                                    <div class="d-flex flex-wrap gap-3">
                                        <?php $__currentLoopData = $branches; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $branch): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                            <?php if($branch->id !== $primaryBranchId): ?>
                                                <label class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="branch_ids[]" value="<?php echo e($branch->id); ?>" <?php echo e(in_array($branch->id, $assignedBranchIds, true) ? 'checked' : ''); ?>>
                                                    <span class="form-check-label"><?php echo e($branch->branch_name); ?></span>
                                                </label>
                                            <?php endif; ?>
                                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                    </div>
                                </form>
                            </td>
                            <td>
                                <button type="submit" form="branch-assignment-<?php echo e($branchHead->id); ?>" class="btn btn-sm btn-primary">
                                    Save
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                        <tr>
                            <td colspan="4" class="text-center py-4">No Branch Head accounts found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="card-footer">
            <?php echo e($branchHeads->links('pagination::bootstrap-4')); ?>

        </div>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\ENIGMA_BREAKER\mtcgs-main_09-06-26\mtcgs-ems\resources\views/admin/branch-heads/assignments.blade.php ENDPATH**/ ?>