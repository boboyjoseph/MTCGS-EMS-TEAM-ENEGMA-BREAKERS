<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Recorded</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background-color: #4CAF50; color: white; padding: 20px; text-align: center; }
        .content { padding: 20px; background-color: #f9f9f9; }
        .attendance-details { background-color: white; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Attendance Recorded</h1>
        </div>

        <div class="content">
            <p>Dear <?php echo e($employeeProfile->first_name); ?> <?php echo e($employeeProfile->last_name); ?>,</p>

            <p>Your attendance has been successfully recorded.</p>

            <div class="attendance-details">
                <h3>Attendance Details:</h3>
                <p><strong>Type:</strong> <?php echo e(ucwords(str_replace('_', ' ', $attendanceType))); ?></p>
                <p><strong>Date:</strong> <?php echo e($attendanceLog->attendance_date->format('F d, Y')); ?></p>
                <p><strong>Time:</strong>
                    <?php switch($attendanceType):
                        case ('am_in'): ?>
                            <?php echo e($attendanceLog->am_in ? $attendanceLog->am_in->format('h:i A') : 'N/A'); ?>

                            <?php break; ?>
                        <?php case ('am_out'): ?>
                            <?php echo e($attendanceLog->am_out ? $attendanceLog->am_out->format('h:i A') : 'N/A'); ?>

                            <?php break; ?>
                        <?php case ('pm_in'): ?>
                            <?php echo e($attendanceLog->pm_in ? $attendanceLog->pm_in->format('h:i A') : 'N/A'); ?>

                            <?php break; ?>
                        <?php case ('pm_out'): ?>
                            <?php echo e($attendanceLog->pm_out ? $attendanceLog->pm_out->format('h:i A') : 'N/A'); ?>

                            <?php break; ?>
                    <?php endswitch; ?>
                </p>

                <?php if(\App\Models\DTR::normalizeLateMinutesForLog($attendanceLog) > 0): ?>
                    <p><strong>Late Minutes:</strong> <?php echo e(\App\Models\DTR::normalizeLateMinutesForLog($attendanceLog)); ?> minutes</p>
                <?php endif; ?>

                <?php if($attendanceLog->overtime_hours > 0): ?>
                    <p><strong>Overtime Hours:</strong> <?php echo e($attendanceLog->overtime_hours); ?> hours</p>
                <?php endif; ?>

                <p><strong>Status:</strong> <?php echo e($attendanceLog->getDtrStatus()); ?></p>
            </div>

            <p>You can view your complete attendance record by logging into your account and checking your DTR (Daily Time Record).</p>

            <p>Best regards,<br>
            MTCGS Attendance System</p>
        </div>

        <div class="footer">
            <p>This is an automated message. Please do not reply to this email.</p>
        </div>
    </div>
</body>
</html><?php /**PATH C:\xampp\htdocs\ENIGMA_BREAKER\mtcgs-main_09-06-26\mtcgs-ems\resources\views\emails\attendance-recorded.blade.php ENDPATH**/ ?>