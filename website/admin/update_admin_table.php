<?php
require_once 'config.php';

try {
    // Check if columns already exist
    $check_sql = "SHOW COLUMNS FROM admin LIKE 'email'";
    $result = $pdo->query($check_sql);
    $email_exists = $result->rowCount() > 0;
    
    $check_sql = "SHOW COLUMNS FROM admin LIKE 'full_name'";
    $result = $pdo->query($check_sql);
    $full_name_exists = $result->rowCount() > 0;
    
    // Add columns if they don't exist
    if (!$email_exists) {
        $sql = "ALTER TABLE admin ADD COLUMN email VARCHAR(255) NOT NULL DEFAULT 'admin@filibustero.com'";
        $pdo->exec($sql);
        echo "Added email column to admin table.<br>";
    } else {
        echo "Email column already exists.<br>";
    }
    
    if (!$full_name_exists) {
        $sql = "ALTER TABLE admin ADD COLUMN full_name VARCHAR(255) NOT NULL DEFAULT 'System Administrator'";
        $pdo->exec($sql);
        echo "Added full_name column to admin table.<br>";
    } else {
        echo "Full name column already exists.<br>";
    }
    
    // Update existing admin record with default values
    $update_sql = "UPDATE admin SET 
                   email = COALESCE(NULLIF(email, ''), 'admin@filibustero.com'),
                   full_name = COALESCE(NULLIF(full_name, ''), 'System Administrator')
                   WHERE username = ?";
    $stmt = $pdo->prepare($update_sql);
    $stmt->execute([ADMIN_USERNAME]);
    
    echo "Admin table updated successfully!<br>";
    
    // Verify the update
    $verify_sql = "SELECT username, email, full_name FROM admin WHERE username = ?";
    $stmt = $pdo->prepare($verify_sql);
    $stmt->execute([ADMIN_USERNAME]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "Current admin data:<br>";
    echo "Username: " . htmlspecialchars($admin['username']) . "<br>";
    echo "Email: " . htmlspecialchars($admin['email']) . "<br>";
    echo "Full Name: " . htmlspecialchars($admin['full_name']) . "<br>";
    
} catch (PDOException $e) {
    die("Error updating admin table: " . $e->getMessage());
}
?>