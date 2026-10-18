-- Legacy DTR and balances import for 2026-09-01 through 2026-09-15.
-- Prerequisite: import the old dump into `mtcgs-ems_8080` on this MySQL server.
-- This file writes to `mtcgs-ems`; adjust the target database name if needed.
-- Employee profiles are matched by employee_number. Attendance in this date range
-- is replaced from the legacy source where a matching employee/date row exists.
-- No fingerprint, device, GPS, or biometric-verification fields are copied.

USE `mtcgs-ems`;
START TRANSACTION;

-- Attendance clock times are retained so the application can calculate Present,
-- Half Day, Late, and Early Out using its existing DTR rules.
UPDATE `mtcgs-ems`.`attendance_logs` AS target_log
JOIN `mtcgs-ems_8080`.`attendance_logs` AS legacy_log
    ON legacy_log.`attendance_date` = target_log.`attendance_date`
JOIN `mtcgs-ems_8080`.`employee_profiles` AS legacy_profile
    ON legacy_profile.`id` = COALESCE(legacy_log.`employee_profile_id`, legacy_log.`employee_id`)
JOIN `mtcgs-ems`.`employee_profiles` AS target_profile
    ON target_profile.`employee_number` = legacy_profile.`employee_number`
   AND target_profile.`id` = target_log.`employee_profile_id`
SET target_log.`employee_id` = target_profile.`id`,
    target_log.`branch_id` = target_profile.`branch_id`,
    target_log.`am_in` = legacy_log.`am_in`,
    target_log.`am_out` = legacy_log.`am_out`,
    target_log.`break_in` = legacy_log.`break_in`,
    target_log.`break_out` = legacy_log.`break_out`,
    target_log.`pm_in` = legacy_log.`pm_in`,
    target_log.`pm_out` = legacy_log.`pm_out`,
    target_log.`status` = legacy_log.`status`,
    target_log.`override_status` = legacy_log.`override_status`,
    target_log.`override_reason` = legacy_log.`override_reason`,
    target_log.`corrected_time_in` = legacy_log.`corrected_time_in`,
    target_log.`corrected_time_out` = legacy_log.`corrected_time_out`,
    target_log.`corrected_pm_in` = legacy_log.`corrected_pm_in`,
    target_log.`late_minutes` = legacy_log.`late_minutes`,
    target_log.`overtime_hours` = legacy_log.`overtime_hours`,
    target_log.`created_at` = legacy_log.`created_at`,
    target_log.`updated_at` = legacy_log.`updated_at`
WHERE legacy_log.`attendance_date` BETWEEN '2026-09-01' AND '2026-09-15';

INSERT INTO `attendance_logs` (
    `employee_profile_id`, `employee_id`, `branch_id`, `attendance_date`,
    `am_in`, `am_out`, `break_in`, `break_out`, `pm_in`, `pm_out`, `status`,
    `override_status`, `override_reason`, `corrected_time_in`,
    `corrected_time_out`, `corrected_pm_in`, `late_minutes`, `overtime_hours`,
    `created_at`, `updated_at`
)
SELECT
    target_profile.`id`, target_profile.`id`, target_profile.`branch_id`, legacy_log.`attendance_date`,
    legacy_log.`am_in`, legacy_log.`am_out`, legacy_log.`break_in`, legacy_log.`break_out`,
    legacy_log.`pm_in`, legacy_log.`pm_out`, legacy_log.`status`, legacy_log.`override_status`,
    legacy_log.`override_reason`, legacy_log.`corrected_time_in`, legacy_log.`corrected_time_out`,
    legacy_log.`corrected_pm_in`, legacy_log.`late_minutes`, legacy_log.`overtime_hours`,
    legacy_log.`created_at`, legacy_log.`updated_at`
FROM `mtcgs-ems_8080`.`attendance_logs` AS legacy_log
JOIN `mtcgs-ems_8080`.`employee_profiles` AS legacy_profile
    ON legacy_profile.`id` = COALESCE(legacy_log.`employee_profile_id`, legacy_log.`employee_id`)
JOIN `mtcgs-ems`.`employee_profiles` AS target_profile
    ON target_profile.`employee_number` = legacy_profile.`employee_number`
WHERE legacy_log.`attendance_date` BETWEEN '2026-09-01' AND '2026-09-15'
  AND NOT EXISTS (
      SELECT 1
      FROM `mtcgs-ems`.`attendance_logs` AS existing_log
      WHERE existing_log.`employee_profile_id` = target_profile.`id`
        AND existing_log.`attendance_date` = legacy_log.`attendance_date`
  );

-- DTR period headers for the exact requested cutoff; calculations use the logs above.
INSERT INTO `dtrs` (
    `employee_profile_id`, `period_start`, `period_end`, `total_hours`, `days_present`,
    `days_absent`, `overtime_hours`, `late_minutes`, `status`, `remarks`,
    `created_at`, `updated_at`, `approved_at`
)
SELECT
    target_profile.`id`, legacy_dtr.`period_start`, legacy_dtr.`period_end`,
    legacy_dtr.`total_hours`, legacy_dtr.`days_present`, legacy_dtr.`days_absent`,
    legacy_dtr.`overtime_hours`, legacy_dtr.`late_minutes`, legacy_dtr.`status`,
    legacy_dtr.`remarks`, legacy_dtr.`created_at`, legacy_dtr.`updated_at`, legacy_dtr.`approved_at`
FROM `mtcgs-ems_8080`.`dtrs` AS legacy_dtr
JOIN `mtcgs-ems_8080`.`employee_profiles` AS legacy_profile
    ON legacy_profile.`id` = legacy_dtr.`employee_profile_id`
JOIN `mtcgs-ems`.`employee_profiles` AS target_profile
    ON target_profile.`employee_number` = legacy_profile.`employee_number`
WHERE legacy_dtr.`period_start` = '2026-09-01'
  AND legacy_dtr.`period_end` = '2026-09-15'
  AND legacy_dtr.`deleted_at` IS NULL
  AND NOT EXISTS (
      SELECT 1
      FROM `mtcgs-ems`.`dtrs` AS existing_dtr
      WHERE existing_dtr.`employee_profile_id` = target_profile.`id`
        AND existing_dtr.`period_start` = legacy_dtr.`period_start`
        AND existing_dtr.`period_end` = legacy_dtr.`period_end`
  );

-- Copy 2026 leave balances, including cash-charge counters where present.
-- The source is authoritative for the matching employee/year balance.
INSERT INTO `leave_balances` (
    `employee_profile_id`, `year`, `sick_leave_total`, `sick_leave_used`,
    `vacation_leave_total`, `vacation_leave_used`, `emergency_leave_total`,
    `emergency_leave_used`, `birthday_leave_total`, `birthday_leave_used`,
    `cash_charge_total`, `cash_charge_used`, `maternity_leave_total`,
    `maternity_leave_used`, `paternity_leave_total`, `paternity_leave_used`,
    `service_incentive_leave_total`, `service_incentive_leave_used`, `created_at`, `updated_at`
)
SELECT
    target_profile.`id`, legacy_balance.`year`, legacy_balance.`sick_leave_total`,
    legacy_balance.`sick_leave_used`, legacy_balance.`vacation_leave_total`,
    legacy_balance.`vacation_leave_used`, legacy_balance.`emergency_leave_total`,
    legacy_balance.`emergency_leave_used`, legacy_balance.`birthday_leave_total`,
    legacy_balance.`birthday_leave_used`, legacy_balance.`cash_charge_total`,
    legacy_balance.`cash_charge_used`, legacy_balance.`maternity_leave_total`,
    legacy_balance.`maternity_leave_used`, legacy_balance.`paternity_leave_total`,
    legacy_balance.`paternity_leave_used`, legacy_balance.`service_incentive_leave_total`,
    legacy_balance.`service_incentive_leave_used`, legacy_balance.`created_at`, legacy_balance.`updated_at`
FROM `mtcgs-ems_8080`.`leave_balances` AS legacy_balance
JOIN `mtcgs-ems_8080`.`employee_profiles` AS legacy_profile
    ON legacy_profile.`id` = legacy_balance.`employee_profile_id`
JOIN `mtcgs-ems`.`employee_profiles` AS target_profile
    ON target_profile.`employee_number` = legacy_profile.`employee_number`
WHERE legacy_balance.`year` = 2026
ON DUPLICATE KEY UPDATE
    `sick_leave_total` = VALUES(`sick_leave_total`),
    `sick_leave_used` = VALUES(`sick_leave_used`),
    `vacation_leave_total` = VALUES(`vacation_leave_total`),
    `vacation_leave_used` = VALUES(`vacation_leave_used`),
    `emergency_leave_total` = VALUES(`emergency_leave_total`),
    `emergency_leave_used` = VALUES(`emergency_leave_used`),
    `birthday_leave_total` = VALUES(`birthday_leave_total`),
    `birthday_leave_used` = VALUES(`birthday_leave_used`),
    `cash_charge_total` = VALUES(`cash_charge_total`),
    `cash_charge_used` = VALUES(`cash_charge_used`),
    `maternity_leave_total` = VALUES(`maternity_leave_total`),
    `maternity_leave_used` = VALUES(`maternity_leave_used`),
    `paternity_leave_total` = VALUES(`paternity_leave_total`),
    `paternity_leave_used` = VALUES(`paternity_leave_used`),
    `service_incentive_leave_total` = VALUES(`service_incentive_leave_total`),
    `service_incentive_leave_used` = VALUES(`service_incentive_leave_used`),
    `updated_at` = VALUES(`updated_at`);

-- Cash-advance applications are copied as ledger rows so remaining balances can
-- continue to be calculated by the existing system. User IDs are intentionally omitted.
INSERT INTO `cash_advance_applications` (
    `employee_profile_id`, `requested_amount`, `approved_amount`, `installment_amount`,
    `installments`, `deducted_installments`, `deduction_started_at`, `completed_at`,
    `purpose`, `eligibility_category`, `status`, `rejection_reason`,
    `fo_reviewed_at`, `bh_reviewed_at`, `hr_reviewed_at`, `fh_reviewed_at`,
    `created_at`, `updated_at`
)
SELECT
    target_profile.`id`, legacy_advance.`requested_amount`, legacy_advance.`approved_amount`,
    legacy_advance.`installment_amount`, legacy_advance.`installments`,
    legacy_advance.`deducted_installments`, legacy_advance.`deduction_started_at`,
    legacy_advance.`completed_at`, legacy_advance.`purpose`, legacy_advance.`eligibility_category`,
    legacy_advance.`status`, legacy_advance.`rejection_reason`, legacy_advance.`fo_reviewed_at`,
    legacy_advance.`bh_reviewed_at`, legacy_advance.`hr_reviewed_at`, legacy_advance.`fh_reviewed_at`,
    legacy_advance.`created_at`, legacy_advance.`updated_at`
FROM `mtcgs-ems_8080`.`cash_advance_applications` AS legacy_advance
JOIN `mtcgs-ems_8080`.`employee_profiles` AS legacy_profile
    ON legacy_profile.`id` = legacy_advance.`employee_profile_id`
JOIN `mtcgs-ems`.`employee_profiles` AS target_profile
    ON target_profile.`employee_number` = legacy_profile.`employee_number`
WHERE NOT EXISTS (
    SELECT 1
    FROM `mtcgs-ems`.`cash_advance_applications` AS existing_advance
    WHERE existing_advance.`employee_profile_id` = target_profile.`id`
      AND existing_advance.`requested_amount` = legacy_advance.`requested_amount`
      AND existing_advance.`purpose` = legacy_advance.`purpose`
      AND existing_advance.`created_at` <=> legacy_advance.`created_at`
);

-- Cash charges retain amount, installment, reason, status, and dates; no evidence
-- paths or user IDs are copied because those may point to records/files not in target.
INSERT INTO `cash_charges` (
    `employee_profile_id`, `branch_id`, `amount`, `installment_per_cutoff`, `reason`,
    `status`, `archived_from_status`, `archived_by_role`, `approved_at`, `created_at`, `updated_at`
)
SELECT
    target_profile.`id`, target_profile.`branch_id`, legacy_charge.`amount`,
    legacy_charge.`installment_per_cutoff`, legacy_charge.`reason`, legacy_charge.`status`,
    legacy_charge.`archived_from_status`, legacy_charge.`archived_by_role`,
    legacy_charge.`approved_at`, legacy_charge.`created_at`, legacy_charge.`updated_at`
FROM `mtcgs-ems_8080`.`cash_charges` AS legacy_charge
JOIN `mtcgs-ems_8080`.`employee_profiles` AS legacy_profile
    ON legacy_profile.`id` = legacy_charge.`employee_profile_id`
JOIN `mtcgs-ems`.`employee_profiles` AS target_profile
    ON target_profile.`employee_number` = legacy_profile.`employee_number`
WHERE NOT EXISTS (
    SELECT 1
    FROM `mtcgs-ems`.`cash_charges` AS existing_charge
    WHERE existing_charge.`employee_profile_id` = target_profile.`id`
      AND existing_charge.`amount` = legacy_charge.`amount`
      AND existing_charge.`reason` = legacy_charge.`reason`
      AND existing_charge.`created_at` <=> legacy_charge.`created_at`
);

-- Leave requests intersecting the requested dates, including Leave Without Pay
-- (`is_absent`), are copied with their full original date range.
INSERT INTO `leave_requests` (
    `employee_profile_id`, `employee_id`, `leave_type`, `start_date`, `end_date`,
    `total_days`, `reason`, `status`, `is_absent`, `approval_remarks`, `approved_at`,
    `created_at`, `updated_at`
)
SELECT
    target_profile.`id`, target_profile.`user_id`, legacy_leave.`leave_type`,
    legacy_leave.`start_date`, legacy_leave.`end_date`, legacy_leave.`total_days`,
    legacy_leave.`reason`, legacy_leave.`status`, legacy_leave.`is_absent`,
    legacy_leave.`approval_remarks`, legacy_leave.`approved_at`,
    legacy_leave.`created_at`, legacy_leave.`updated_at`
FROM `mtcgs-ems_8080`.`leave_requests` AS legacy_leave
JOIN `mtcgs-ems_8080`.`employee_profiles` AS legacy_profile
    ON legacy_profile.`id` = legacy_leave.`employee_profile_id`
    OR (legacy_leave.`employee_profile_id` IS NULL AND legacy_profile.`user_id` = legacy_leave.`employee_id`)
JOIN `mtcgs-ems`.`employee_profiles` AS target_profile
    ON target_profile.`employee_number` = legacy_profile.`employee_number`
WHERE legacy_leave.`start_date` <= '2026-09-15'
  AND legacy_leave.`end_date` >= '2026-09-01'
  AND NOT EXISTS (
      SELECT 1
      FROM `mtcgs-ems`.`leave_requests` AS existing_leave
      WHERE existing_leave.`employee_profile_id` = target_profile.`id`
        AND existing_leave.`leave_type` = legacy_leave.`leave_type`
        AND existing_leave.`start_date` = legacy_leave.`start_date`
        AND existing_leave.`end_date` = legacy_leave.`end_date`
        AND existing_leave.`created_at` <=> legacy_leave.`created_at`
  );

-- Import only holidays and suspensions in the date window. Match the creator by
-- email and the branch by branch_code; rows without a matching target creator/branch
-- are not copied rather than being assigned to the wrong account or made global.
INSERT INTO `calendar_events` (
    `title`, `description`, `event_date`, `event_type`, `created_by`, `branch_id`,
    `approval_status`, `approved_at`, `created_at`, `updated_at`
)
SELECT
    legacy_event.`title`, legacy_event.`description`, legacy_event.`event_date`,
    legacy_event.`event_type`, target_creator.`id`, target_branch.`id`,
    legacy_event.`approval_status`, legacy_event.`approved_at`,
    legacy_event.`created_at`, legacy_event.`updated_at`
FROM `mtcgs-ems_8080`.`calendar_events` AS legacy_event
JOIN `mtcgs-ems_8080`.`users` AS legacy_creator
    ON legacy_creator.`id` = legacy_event.`created_by`
JOIN `mtcgs-ems`.`users` AS target_creator
    ON target_creator.`email` = legacy_creator.`email`
LEFT JOIN `mtcgs-ems_8080`.`branches` AS legacy_branch
    ON legacy_branch.`id` = legacy_event.`branch_id`
LEFT JOIN `mtcgs-ems`.`branches` AS target_branch
    ON target_branch.`branch_code` = legacy_branch.`branch_code`
WHERE legacy_event.`event_date` BETWEEN '2026-09-01' AND '2026-09-15'
  AND legacy_event.`event_type` IN ('holiday', 'suspension')
  AND (legacy_event.`branch_id` IS NULL OR target_branch.`id` IS NOT NULL)
  AND NOT EXISTS (
      SELECT 1
      FROM `mtcgs-ems`.`calendar_events` AS existing_event
      WHERE existing_event.`title` = legacy_event.`title`
        AND existing_event.`event_date` = legacy_event.`event_date`
        AND existing_event.`event_type` = legacy_event.`event_type`
        AND existing_event.`branch_id` <=> target_branch.`id`
  );

COMMIT;
