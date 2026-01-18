<?php
/**
 * Run this script to add payment recording columns to the payments table
 * Usage: Access via browser or run from command line
 */

require_once 'config/db.php';

echo "<h2>Payment System Migration</h2>";
echo "<pre>";

// Check if columns already exist
$check = $conn->query("SHOW COLUMNS FROM payments LIKE 'or_number'");
if ($check->num_rows > 0) {
    echo "Migration already applied - or_number column exists.\n";
    exit;
}

$migrations = [
    "ALTER TABLE `payments` ADD COLUMN `or_number` VARCHAR(50) DEFAULT NULL COMMENT 'Official Receipt Number' AFTER `status`",
    "ALTER TABLE `payments` ADD COLUMN `payment_type` VARCHAR(100) DEFAULT NULL AFTER `or_number`",
    "ALTER TABLE `payments` ADD COLUMN `description` TEXT DEFAULT NULL AFTER `payment_type`",
    "ALTER TABLE `payments` ADD COLUMN `academic_year_id` INT(10) UNSIGNED DEFAULT NULL AFTER `description`",
    "ALTER TABLE `payments` ADD COLUMN `semester` ENUM('1st', '2nd', 'summer') DEFAULT NULL AFTER `academic_year_id`",
    "ALTER TABLE `payments` ADD COLUMN `branch_id` INT(10) UNSIGNED DEFAULT NULL AFTER `semester`",
    "ALTER TABLE `payments` ADD COLUMN `recorded_by` INT(10) UNSIGNED DEFAULT NULL COMMENT 'User ID of registrar who recorded payment' AFTER `branch_id`",
    "ALTER TABLE `payments` ADD COLUMN `reference_no` VARCHAR(50) DEFAULT NULL COMMENT 'System generated reference' AFTER `recorded_by`",
    "ALTER TABLE `payments` ADD COLUMN `payment_method` ENUM('cash', 'bank_transfer', 'gcash', 'maya', 'check', 'other') DEFAULT 'cash' AFTER `reference_no`",
    "ALTER TABLE `payments` ADD COLUMN `verified_by` INT(10) UNSIGNED DEFAULT NULL COMMENT 'User ID who verified payment' AFTER `payment_method`",
    "ALTER TABLE `payments` ADD COLUMN `verified_at` TIMESTAMP NULL DEFAULT NULL AFTER `verified_by`",
    "ALTER TABLE `payments` ADD INDEX `idx_payments_or_number` (`or_number`)",
    "ALTER TABLE `payments` ADD INDEX `idx_payments_branch` (`branch_id`)",
    "ALTER TABLE `payments` ADD INDEX `idx_payments_type` (`payment_type`(50))",
    "ALTER TABLE `payments` ADD INDEX `idx_payments_recorded_by` (`recorded_by`)"
];

$success = 0;
$errors = [];

foreach ($migrations as $sql) {
    if ($conn->query($sql)) {
        echo "✓ SUCCESS: " . substr($sql, 0, 80) . "...\n";
        $success++;
    } else {
        $error = "✗ ERROR: " . $conn->error . " - " . substr($sql, 0, 50) . "...";
        echo $error . "\n";
        $errors[] = $error;
    }
}

echo "\n-----------------------------\n";
echo "Migration Complete!\n";
echo "Success: {$success}/" . count($migrations) . "\n";

if (!empty($errors)) {
    echo "\nErrors encountered:\n";
    foreach ($errors as $error) {
        echo "  - {$error}\n";
    }
}

echo "</pre>";
echo "<p><a href='index.php'>Back to Home</a></p>";
?>
