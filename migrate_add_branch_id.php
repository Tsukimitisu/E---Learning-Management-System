<?php
/**
 * Migration: Add branch_id to user_profiles table
 * Date: January 18, 2026
 */

require_once 'config/db.php';

try {
    // Check if column exists
    $check = $conn->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='user_profiles' AND COLUMN_NAME='branch_id'");
    
    if ($check->num_rows == 0) {
        // Column doesn't exist, add it
        $sql = "ALTER TABLE user_profiles ADD COLUMN branch_id INT(10) UNSIGNED DEFAULT NULL AFTER address";
        
        if ($conn->query($sql) === TRUE) {
            echo "✅ Success: branch_id column added to user_profiles table\n";
        } else {
            echo "❌ Error: " . $conn->error . "\n";
        }
    } else {
        echo "✅ Info: branch_id column already exists in user_profiles table\n";
    }
    
    $conn->close();
} catch (Exception $e) {
    echo "❌ Migration failed: " . $e->getMessage() . "\n";
}
?>
