<?php
// seed.php is inside config folder, so we need to go back one level
require_once __DIR__ . '/init.php';

$db = new Database();
$conn = $db->connect();

$messages = [];

// Check and seed roles
$roles = ['Super Admin', 'School Admin', 'Branch Admin', 'Registrar', 'Teacher', 'Student'];

foreach ($roles as $role_name) {
    $check_sql = "SELECT id FROM roles WHERE name = ?";
    $stmt = $conn->prepare($check_sql);
    $stmt->bind_param("s", $role_name);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 0) {
        $insert_sql = "INSERT INTO roles (name, created_at, updated_at) VALUES (?, NOW(), NOW())";
        $insert_stmt = $conn->prepare($insert_sql);
        $insert_stmt->bind_param("s", $role_name);
        
        if ($insert_stmt->execute()) {
            $messages[] = "✓ Role '$role_name' created successfully.";
        } else {
            $messages[] = "✗ Error creating role '$role_name': " . $conn->error;
        }
        $insert_stmt->close();
    } else {
        $messages[] = "• Role '$role_name' already exists.";
    }
    $stmt->close();
}

// Check and seed Super Admin user
$admin_email = 'admin@elms.com';
$check_user_sql = "SELECT id FROM users WHERE email = ?";
$stmt = $conn->prepare($check_user_sql);
$stmt->bind_param("s", $admin_email);
$stmt->execute();
$user_result = $stmt->get_result();

if ($user_result->num_rows == 0) {
    // Create Super Admin user
    $password = password_hash('admin123', PASSWORD_DEFAULT);
    $first_name = 'Super';
    $last_name = 'Admin';
    $status = 'active';
    
    $insert_user_sql = "INSERT INTO users (first_name, last_name, email, password, status, created_at, updated_at) 
                        VALUES (?, ?, ?, ?, ?, NOW(), NOW())";
    $user_stmt = $conn->prepare($insert_user_sql);
    $user_stmt->bind_param("sssss", $first_name, $last_name, $admin_email, $password, $status);
    
    if ($user_stmt->execute()) {
        $user_id = $conn->insert_id;
        $messages[] = "✓ Super Admin user created successfully (ID: $user_id).";
        
        // Get Super Admin role ID
        $role_sql = "SELECT id FROM roles WHERE name = 'Super Admin'";
        $role_result = $conn->query($role_sql);
        
        if ($role_result && $role_result->num_rows > 0) {
            $role_row = $role_result->fetch_assoc();
            $role_id = $role_row['id'];
            
            // Assign role to user
            $assign_role_sql = "INSERT INTO user_roles (user_id, role_id, created_at) VALUES (?, ?, NOW())";
            $assign_stmt = $conn->prepare($assign_role_sql);
            $assign_stmt->bind_param("ii", $user_id, $role_id);
            
            if ($assign_stmt->execute()) {
                $messages[] = "✓ Super Admin role assigned to user.";
            } else {
                $messages[] = "✗ Error assigning role: " . $conn->error;
            }
            $assign_stmt->close();
        }
    } else {
        $messages[] = "✗ Error creating Super Admin user: " . $conn->error;
    }
    $user_stmt->close();
} else {
    $messages[] = "• Super Admin user already exists.";
}

$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ELMS Database Seeder</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #800000 0%, #003366 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }
        .seed-container {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            max-width: 600px;
            width: 100%;
        }
        .success { color: #28a745; }
        .error { color: #dc3545; }
        .info { color: #6c757d; }
    </style>
</head>
<body>
    <div class="seed-container">
        <h2 class="text-center mb-4" style="color: #800000;">ELMS Database Seeder</h2>
        <div class="alert alert-info">
            <strong>Note:</strong> Run this script only once to initialize the database.
        </div>
        
        <div class="seeding-results">
            <h5>Seeding Results:</h5>
            <ul class="list-unstyled">
                <?php foreach ($messages as $message): ?>
                    <li class="mb-2">
                        <?php
                        if (strpos($message, '✓') !== false) {
                            echo '<span class="success">' . $message . '</span>';
                        } elseif (strpos($message, '✗') !== false) {
                            echo '<span class="error">' . $message . '</span>';
                        } else {
                            echo '<span class="info">' . $message . '</span>';
                        }
                        ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
        
        <div class="alert alert-success mt-4">
            <strong>Default Credentials:</strong><br>
            Email: admin@elms.com<br>
            Password: admin123
        </div>
        
        <div class="text-center mt-4">
            <a href="../index.php" class="btn btn-lg" style="background-color: #800000; color: white;">Go to Login Page</a>
        </div>
    </div>
</body>
</html>