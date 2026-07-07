<?php
require_once 'config.php';

header('Content-Type: application/json');

$response = ['success' => false, 'error' => ''];

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'login':
            $username = trim($_POST['username'] ?? '');
            $password = trim($_POST['password'] ?? '');

            if (empty($username) || empty($password)) {
                throw new Exception('Username and password are required');
            }

            // Check admin table
            $stmt = $pdo->prepare("SELECT * FROM admin WHERE username = ?");
            $stmt->execute([$username]);
            $admin = $stmt->fetch();

            if (!$admin) {
                throw new Exception('Invalid username or password');
            }

            // Verify password
            $passwordValid = false;
            
            // Check if it's a hashed password
            if (password_get_info($admin['password_hash'])['algo']) {
                // It's hashed, use password_verify
                $passwordValid = password_verify($password, $admin['password_hash']);
            } else {
                // It's plain text (for backward compatibility during migration)
                $passwordValid = ($password === $admin['password_hash']);
                
                // If valid, rehash it for security
                if ($passwordValid) {
                    $newHash = password_hash($password, PASSWORD_DEFAULT);
                    $updateStmt = $pdo->prepare("UPDATE admin SET password_hash = ? WHERE id = ?");
                    $updateStmt->execute([$newHash, $admin['id']]);
                }
            }

            if (!$passwordValid) {
                throw new Exception('Invalid username or password');
            }

            // Update last login

            // Set session
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_username'] = $admin['username'];
            $_SESSION['admin_email'] = $admin['email'] ?? '';
            $_SESSION['admin_full_name'] = $admin['full_name'];

            $response['success'] = true;
            $response['admin'] = [
                'id' => $admin['id'],
                'username' => $admin['username'],
                'email' => $admin['email'] ?? '',
                'full_name' => $admin['full_name']
            ];
            break;

        case 'update_profile':
            if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
                throw new Exception('Not authenticated');
            }

            $adminId = $_SESSION['admin_id'];
            $currentPassword = trim($_POST['current_password'] ?? '');
            
            if (empty($currentPassword)) {
                throw new Exception('Current password is required');
            }

            // Verify current password
            $stmt = $pdo->prepare("SELECT password_hash FROM admin WHERE id = ?");
            $stmt->execute([$adminId]);
            $admin = $stmt->fetch();

            if (!$admin) {
                throw new Exception('Admin not found');
            }

            // Verify password (handle both hashed and plain text)
            $passwordValid = false;
            if (password_get_info($admin['password_hash'])['algo']) {
                $passwordValid = password_verify($currentPassword, $admin['password_hash']);
            } else {
                $passwordValid = ($currentPassword === $admin['password_hash']);
            }

            if (!$passwordValid) {
                throw new Exception('Current password is incorrect');
            }

            // Build update query dynamically
            $updates = [];
            $params = [];

            $newUsername = trim($_POST['new_username'] ?? '');
            if (!empty($newUsername)) {
                // Check if username already exists
                $checkStmt = $pdo->prepare("SELECT id FROM admin WHERE username = ? AND id != ?");
                $checkStmt->execute([$newUsername, $adminId]);
                if ($checkStmt->fetch()) {
                    throw new Exception('Username already exists');
                }
                $updates[] = "username = ?";
                $params[] = $newUsername;
            }

            $newEmail = trim($_POST['new_email'] ?? '');
            if (!empty($newEmail)) {
                if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
                    throw new Exception('Invalid email format');
                }
                // Check if email already exists
                $checkStmt = $pdo->prepare("SELECT id FROM admin WHERE email = ? AND id != ?");
                $checkStmt->execute([$newEmail, $adminId]);
                if ($checkStmt->fetch()) {
                    throw new Exception('Email already exists');
                }
                $updates[] = "email = ?";
                $params[] = $newEmail;
            }

            $newFullName = trim($_POST['new_full_name'] ?? '');
            if (!empty($newFullName)) {
                $updates[] = "full_name = ?";
                $params[] = $newFullName;
            }

            $newPassword = trim($_POST['new_password'] ?? '');
            $confirmPassword = trim($_POST['confirm_password'] ?? '');
            if (!empty($newPassword)) {
                if ($newPassword !== $confirmPassword) {
                    throw new Exception('New password and confirmation do not match');
                }
                if (strlen($newPassword) < 6) {
                    throw new Exception('Password must be at least 6 characters');
                }
                $updates[] = "password_hash = ?";
                $params[] = password_hash($newPassword, PASSWORD_DEFAULT);
            }

            if (empty($updates)) {
                throw new Exception('No changes to update');
            }

            // Add admin_id to params
            $params[] = $adminId;

            // Execute update
            $sql = "UPDATE admin SET " . implode(", ", $updates) . " WHERE id = ?";
            $updateStmt = $pdo->prepare($sql);
            $updateStmt->execute($params);

            // Refresh session data
            $stmt = $pdo->prepare("SELECT * FROM admin WHERE id = ?");
            $stmt->execute([$adminId]);
            $updatedAdmin = $stmt->fetch();

            $_SESSION['admin_username'] = $updatedAdmin['username'];
            $_SESSION['admin_email'] = $updatedAdmin['email'] ?? '';
            $_SESSION['admin_full_name'] = $updatedAdmin['full_name'];

            $response['success'] = true;
            $response['message'] = 'Profile updated successfully';
            $response['admin'] = [
                'username' => $updatedAdmin['username'],
                'email' => $updatedAdmin['email'] ?? '',
                'full_name' => $updatedAdmin['full_name']
            ];
            break;

        default:
            throw new Exception('Invalid action');
    }

} catch (Exception $e) {
    $response['error'] = $e->getMessage();
}

echo json_encode($response);