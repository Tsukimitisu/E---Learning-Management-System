<?php
require_once 'config/init.php';

echo "<h2>Create Branch Admin Account</h2>";

$email = 'branchadmin@elms.com';
$password = 'admin123';
$new_hash = password_hash($password, PASSWORD_DEFAULT);

$check = $conn->query("SELECT id FROM users WHERE email = '$email'");

if ($check->num_rows > 0) {
    echo "<p style='color:orange;'>Branch Admin already exists.</p>";
} else {
    $conn->query("INSERT INTO users (email, password, status) VALUES ('$email', '$new_hash', 'active')");
    $admin_id = $conn->insert_id;
    
    $conn->query("INSERT INTO user_profiles (user_id, first_name, last_name, contact_no) 
                  VALUES ($admin_id, 'Branch', 'Coordinator', '09201234567')");
    
    $conn->query("INSERT INTO user_roles (user_id, role_id) VALUES ($admin_id, 3)");
    
    echo "<p style='color:green;'>✓ Branch Admin created!</p>";
}

echo "<div style='background:lightgreen; padding:20px; border-radius:5px;'>";
echo "<h3>LOGIN AS BRANCH ADMIN:</h3>";
echo "Email: <strong>branchadmin@elms.com</strong><br>";
echo "Password: <strong>admin123</strong>";
echo "</div>";
echo "<br><a href='index.php' style='background:#800000; color:white; padding:10px 20px; text-decoration:none; border-radius:5px;'>GO TO LOGIN</a>";
?>