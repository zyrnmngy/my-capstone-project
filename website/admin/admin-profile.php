<?php
require_once 'config.php';

// Check if admin is logged in
$is_logged_in = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'];
if (!$is_logged_in) {
    header('Location: ../index.html');
    exit;
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: ../index.html');
    exit;
}

// Handle admin profile update
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'update_admin_profile') {
    $adminId = $_SESSION['admin_id'];
    $currentPassword = trim($_POST['current_password'] ?? '');
    
    if (empty($currentPassword)) {
        $_SESSION['profile_message'] = 'Current password is required';
        $_SESSION['profile_success'] = false;
        header('Location: admin-profile.php');
        exit;
    }

    try {
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

        // Build update query
        $updates = [];
        $params = [];

        $newUsername = trim($_POST['new_username'] ?? '');
        if (!empty($newUsername)) {
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

        $params[] = $adminId;
        $sql = "UPDATE admin SET " . implode(", ", $updates) . " WHERE id = ?";
        $updateStmt = $pdo->prepare($sql);
        $updateStmt->execute($params);

        // Refresh session
        $stmt = $pdo->prepare("SELECT * FROM admin WHERE id = ?");
        $stmt->execute([$adminId]);
        $updatedAdmin = $stmt->fetch();

        $_SESSION['admin_username'] = $updatedAdmin['username'];
        $_SESSION['admin_email'] = $updatedAdmin['email'] ?? '';
        $_SESSION['admin_full_name'] = $updatedAdmin['full_name'];

        $_SESSION['profile_message'] = 'Profile updated successfully!';
        $_SESSION['profile_success'] = true;

    } catch (Exception $e) {
        $_SESSION['profile_message'] = $e->getMessage();
        $_SESSION['profile_success'] = false;
    }
    
    header('Location: admin-profile.php');
    exit;
}

// Get current admin data from database
$stmt = $pdo->prepare("SELECT * FROM admin WHERE id = ?");
$stmt->execute([$_SESSION['admin_id']]);
$adminData = $stmt->fetch();

if (!$adminData) {
    session_destroy();
    header('Location: ../index.html');
    exit;
}

// Get message from session
$profile_message = $_SESSION['profile_message'] ?? '';
$profile_success = $_SESSION['profile_success'] ?? null;
unset($_SESSION['profile_message']);
unset($_SESSION['profile_success']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Profile - Filibustero</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Orbitron:wght@400;700;900&display=swap');
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #f8f4e9 0%, #f1e8d5 100%);
            color: #4a4235;
            min-height: 100vh;
            overflow-x: hidden;
            position: relative;
        }
        
        .background-decoration {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: 
                repeating-linear-gradient(
                    0deg,
                    rgba(212, 162, 89, 0.03) 0%,
                    rgba(212, 162, 89, 0.03) 1px,
                    transparent 1px,
                    transparent 3px
                ),
                radial-gradient(circle at 10% 20%, rgba(212, 162, 89, 0.05) 0%, transparent 50%),
                radial-gradient(circle at 90% 80%, rgba(184, 134, 58, 0.04) 0%, transparent 50%);
            pointer-events: none;
            z-index: -1;
            animation: scanlines 8s linear infinite;
        }
        
        @keyframes scanlines {
            0% { background-position: 0 0; }
            100% { background-position: 0 4px; }
        }
        
        .dashboard-container {
            display: flex;
            min-height: 100vh;
            width: 100%;
        }
        
        .sidebar {
            width: 180px;
            background: linear-gradient(135deg, #fffcf7 0%, #f9f5ed 100%);
            border-right: 2px solid #d4a259;
            color: #5a5043;
            padding: 0;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            transition: all 0.3s;
            z-index: 1000;
            box-shadow: 
                4px 0 20px rgba(212, 162, 89, 0.15),
                inset 0 0 20px rgba(212, 162, 89, 0.05);
        }
        
        .sidebar-header {
            padding: 1.2rem 0.8rem;
            border-bottom: 2px solid #d4a259;
            text-align: center;
            background: linear-gradient(135deg, #faf6ef 0%, #f5f1eb 100%);
        }
        
        .sidebar-header .logo {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #d4a259 0%, #b8863a 100%);
            border-radius: 50%;
            margin: 0 auto 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fffcf7;
            font-size: 1rem;
            font-weight: 700;
            box-shadow: 
                0 6px 20px rgba(212, 162, 89, 0.4),
                inset 0 1px 0px rgba(255, 252, 247, 0.3);
            border: 2px solid #fffcf7;
            overflow: hidden;
        }
        
        .sidebar-header .logo img {
            width: 150%;
            height: 150%;
            object-fit: contain;
            border-radius: 50%;
        }
        
        .sidebar-header h3 {
            margin: 0;
            font-weight: 700;
            font-size: 0.9rem;
            color: #3d3529;
            letter-spacing: -0.5px;
            font-family: 'Orbitron', monospace;
        }
        
        .sidebar-header .subtitle {
            color: #7a6f5d;
            font-size: 0.7rem;
            margin-top: 0.3rem;
            font-family: 'Orbitron', monospace;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .sidebar-menu {
            list-style: none;
            padding: 1rem 0;
            margin: 0;
        }
        
        .sidebar-menu li {
            margin: 0.2rem 0;
        }
        
        .sidebar-menu a {
            color: #5a5043;
            padding: 0.7rem 0.8rem;
            display: block;
            text-decoration: none;
            transition: all 0.3s ease;
            border-left: 4px solid transparent;
            position: relative;
            font-weight: 600;
            font-size: 0.75rem;
            font-family: 'Orbitron', monospace;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .sidebar-menu a:hover, .sidebar-menu a.active {
            background: linear-gradient(135deg, #fef9f0 0%, #fcf5e8 100%);
            border-left: 4px solid #d4a259;
            color: #b8863a;
            box-shadow: inset 0 0 15px rgba(212, 162, 89, 0.1);
            transform: translateX(5px);
        }
        
        .sidebar-menu i {
            margin-right: 8px;
            width: 14px;
            text-align: center;
            font-size: 0.9rem;
            color: #b8863a;
        }

        .main-content {
            flex: 1;
            margin-left: 180px;
            padding: 0;
            min-height: 100vh;
            width: calc(100% - 180px);
        }
        
        .navbar {
            background: linear-gradient(135deg, #fffcf7 0%, #f9f5ed 100%);
            padding: 1rem 1.5rem;
            box-shadow: 
                0 4px 20px rgba(212, 162, 89, 0.15),
                0 0 40px rgba(212, 162, 89, 0.1);
            position: sticky;
            top: 0;
            z-index: 999;
            border-bottom: 2px solid #d4a259;
        }
        
        .nav-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 100%;
            max-width: none;
        }
        
        .logo {
            font-size: 1.3rem;
            font-weight: 700;
            color: #b8863a;
            letter-spacing: -0.5px;
            font-family: 'Orbitron', monospace;
        }
        
        .header-actions {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .welcome-text {
            font-weight: 600;
            color: #5a5043;
            font-family: 'Orbitron', monospace;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.5px;
        }
        
        .dropdown {
            position: relative;
        }
        
        .dropdown-toggle {
            background: linear-gradient(135deg, #d4a259 0%, #b8863a 100%);
            border: 2px solid #9c6d2e;
            color: #fffcf7;
            padding: 0.5rem;
            border-radius: 0;
            cursor: pointer;
            font-size: 1rem;
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            box-shadow: 
                0 6px 20px rgba(212, 162, 89, 0.4),
                inset 0 1px 0px rgba(255, 252, 247, 0.3);
        }
        
        .dropdown-toggle:hover {
            background: linear-gradient(135deg, #b8863a 0%, #9c6d2e 100%);
            box-shadow: 
                0 8px 30px rgba(212, 162, 89, 0.6),
                inset 0 1px 0px rgba(255, 252, 247, 0.5);
            transform: translateY(-2px);
        }
        
        .dropdown-menu {
            position: absolute;
            top: 100%;
            right: 0;
            background: linear-gradient(135deg, #fffcf7 0%, #f9f5ed 100%);
            border: 2px solid #d4a259;
            min-width: 180px;
            box-shadow: 
                0 0 40px rgba(212, 162, 89, 0.4),
                0 10px 25px rgba(139, 107, 66, 0.15);
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px);
            transition: all 0.2s ease;
            z-index: 1001;
            margin-top: 0.5rem;
        }
        
        .dropdown-menu.show {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }
        
        .dropdown-item {
            display: flex;
            align-items: center;
            padding: 0.7rem 1rem;
            color: #5a5043;
            text-decoration: none;
            transition: all 0.2s ease;
            border-bottom: 1px solid #f0dfb8;
            font-weight: 600;
            font-family: 'Orbitron', monospace;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .dropdown-item:last-child {
            border-bottom: none;
        }
        
        .dropdown-item:hover {
            background: #faf6ef;
            color: #b8863a;
            box-shadow: inset 0 0 10px rgba(212, 162, 89, 0.2);
        }
        
        .dropdown-item i {
            color: #b8863a;
            margin-right: 0.5rem;
            width: 14px;
        }
        
        .dropdown-divider {
            height: 1px;
            background: #d4a259;
            margin: 0.5rem 0;
        }
        
        .container {
            width: 100%;
            padding: 1.5rem;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .profile-container {
            background: linear-gradient(135deg, #fffcf7 0%, #f9f5ed 100%);
            border: 2px solid #d4a259;
            border-radius: 0;
            padding: 2rem;
            box-shadow: 
                0 8px 30px rgba(212, 162, 89, 0.2),
                0 0 40px rgba(212, 162, 89, 0.1),
                inset 0 0 20px rgba(212, 162, 89, 0.05);
            position: relative;
            animation: fadeInUp 1.2s ease-out;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .profile-container::before {
            content: '';
            position: absolute;
            top: -2px;
            left: -2px;
            right: -2px;
            bottom: -2px;
            background: linear-gradient(135deg, #d4a259, #b8863a, #d4a259);
            border-radius: 0;
            z-index: -1;
            opacity: 0.3;
            animation: borderGlow 3s ease-in-out infinite;
        }
        
        @keyframes borderGlow {
            0%, 100% { opacity: 0.3; }
            50% { opacity: 0.6; }
        }
        
        .header {
            text-align: center;
            margin-bottom: 1.5rem;
            border-bottom: 2px solid #d4a259;
            padding-bottom: 1.5rem;
        }
        
        .header .logo {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #d4a259 0%, #b8863a 100%);
            border-radius: 50%;
            margin: 0 auto 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            font-weight: 700;
            box-shadow: 
                0 0 30px rgba(212, 162, 89, 0.5),
                inset 0 0 20px rgba(255, 252, 247, 0.2),
                0 4px 12px rgba(212, 162, 89, 0.3);
            border: 2px solid #fffcf7;
            animation: logoFloat 3s ease-in-out infinite;
            color: #fffcf7;
            overflow: hidden;
        }
        
        @keyframes logoFloat {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
        }
        
        .logo img {
            width: 150%;
            height: 150%;
            object-fit: contain;
            border-radius: 50%;
        }
        
        .header h1 {
            font-size: 1.8rem;
            font-weight: 900;
            color: #3d3529;
            margin-bottom: 0.5rem;
            font-family: 'Orbitron', monospace;
            letter-spacing: 1px;
            text-transform: uppercase;
            text-shadow: 2px 2px 0px rgba(212, 162, 89, 0.2);
        }
        
        .header p {
            color: #7a6f5d;
            font-size: 0.9rem;
            font-weight: 600;
            font-family: 'Orbitron', monospace;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .navigation {
            margin-bottom: 1.5rem;
            text-align: center;
        }
        
        .nav-btn {
            background: linear-gradient(135deg, #faf6ef 0%, #f5f1eb 100%);
            color: #5a5043;
            border: 2px solid #d4a259;
            padding: 0.6rem 1.2rem;
            border-radius: 0;
            text-decoration: none;
            font-weight: 700;
            margin: 0 0.5rem;
            transition: all 0.3s ease;
            display: inline-block;
            font-family: 'Orbitron', monospace;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            box-shadow: 0 4px 12px rgba(212, 162, 89, 0.1);
        }
        
        .nav-btn:hover {
            background: linear-gradient(135deg, #d4a259 0%, #b8863a 100%);
            color: #fffcf7;
            border-color: #9c6d2e;
            transform: translateY(-2px);
            box-shadow: 
                0 6px 20px rgba(212, 162, 89, 0.4),
                inset 0 1px 0px rgba(255, 252, 247, 0.3);
        }
        
        .nav-btn i {
            margin-right: 0.5rem;
        }
        
        .profile-layout {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        @media (max-width: 1024px) {
            .profile-layout {
                grid-template-columns: 1fr;
            }
        }
        
        .current-info-column, .update-info-column {
            background: linear-gradient(135deg, #faf6ef 0%, #f5f1eb 100%);
            border-radius: 0;
            padding: 1.5rem;
            border: 2px solid #d4a259;
            box-shadow: inset 0 0 10px rgba(212, 162, 89, 0.1);
            position: relative;
        }
        
        .section-title {
            font-size: 1.2rem;
            font-weight: 900;
            color: #3d3529;
            margin-bottom: 1.2rem;
            text-align: center;
            font-family: 'Orbitron', monospace;
            letter-spacing: 1px;
            text-transform: uppercase;
            text-shadow: 2px 2px 0px rgba(212, 162, 89, 0.2);
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1.2rem;
        }
        
        .form-group {
            margin-bottom: 1.2rem;
        }
        
        .form-label {
            display: block;
            color: #5a5043;
            font-weight: 700;
            margin-bottom: 0.6rem;
            font-size: 0.85rem;
            font-family: 'Orbitron', monospace;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .required {
            color: #b36f59;
        }
        
        .form-input {
            width: 100%;
            padding: 0.8rem 1rem;
            background: #fffcf7;
            border: 2px solid #d4a259;
            border-radius: 0;
            color: #4a4235;
            font-size: 0.9rem;
            font-family: 'Inter', sans-serif;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: inset 0 2px 4px rgba(212, 162, 89, 0.1);
        }
        
        .form-input:focus {
            outline: none;
            border-color: #b8863a;
            box-shadow: 
                0 0 0 3px rgba(212, 162, 89, 0.2),
                inset 0 2px 4px rgba(212, 162, 89, 0.1);
            background: #fffcf7;
        }
        
        .form-input::placeholder {
            color: #a39582;
            opacity: 0.7;
            font-weight: 500;
        }
        
        .password-field {
            position: relative;
        }
        
        .password-toggle {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #7a6f5d;
            cursor: pointer;
            font-size: 1rem;
            transition: color 0.2s ease;
            padding: 0.5rem;
        }
        
        .password-toggle:hover {
            color: #d4a259;
        }
        
        .alert {
            padding: 1rem 1.2rem;
            border-radius: 0;
            margin-bottom: 1.2rem;
            border: 2px solid;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 700;
            font-family: 'Orbitron', monospace;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-size: 0.8rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        
        .alert-success {
            background: linear-gradient(135deg, #f9f7f3 0%, #f5f1eb 100%);
            color: #8b6b3f;
            border-color: #d4a259;
            border-left: 5px solid #8b6b3f;
        }
        
        .alert-danger {
            background: linear-gradient(135deg, #fdf5f3 0%, #fce8e1 100%);
            color: #b36f59;
            border-color: #d4a259;
            border-left: 5px solid #b36f59;
        }
        
        .action-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-top: 1.5rem;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 0.9rem 1.8rem;
            border: none;
            border-radius: 0;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.85rem;
            text-align: center;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-family: 'Orbitron', monospace;
            border: 2px solid transparent;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #d4a259 0%, #b8863a 100%);
            color: #fffcf7;
            box-shadow: 
                0 6px 20px rgba(212, 162, 89, 0.4),
                inset 0 1px 0px rgba(255, 252, 247, 0.3);
            border-color: #9c6d2e;
        }
        
        .btn-primary:hover:not(:disabled) {
            background: linear-gradient(135deg, #b8863a 0%, #9c6d2e 100%);
            transform: translateY(-2px);
            box-shadow: 
                0 8px 30px rgba(212, 162, 89, 0.6),
                inset 0 1px 0px rgba(255, 252, 247, 0.5);
        }
        
        .btn-primary:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }
        
        .btn-secondary {
            background: linear-gradient(135deg, #faf6ef 0%, #f5f1eb 100%);
            color: #5a5043;
            border: 2px solid #d4a259;
            box-shadow: 0 4px 12px rgba(212, 162, 89, 0.1);
        }
        
        .btn-secondary:hover {
            background: linear-gradient(135deg, #f5f0e6 0%, #f0e8db 100%);
            border-color: #b8863a;
            color: #b8863a;
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(212, 162, 89, 0.2);
        }
        
        .loading-spinner {
            display: none;
            width: 16px;
            height: 16px;
            border: 2px solid rgba(255, 252, 247, 0.3);
            border-top: 2px solid #fffcf7;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-right: 0.5rem;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        @media (max-width: 768px) {
            .dashboard-container {
                flex-direction: column;
            }
            
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
            }
            
            .main-content {
                margin-left: 0;
                width: 100%;
            }
            
            .container {
                padding: 1rem;
            }
            
            .navbar {
                padding: 1rem;
            }
            
            .nav-content {
                flex-direction: column;
                gap: 1rem;
            }
            
            .action-buttons {
                flex-direction: column;
                align-items: stretch;
            }
            
            .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="background-decoration"></div>
    
    <div class="dashboard-container">
        <!-- Sidebar Navigation -->
        <div class="sidebar">
            <div class="sidebar-header">
                <div class="logo">
                    <img src="../logo.png" alt="Filibustero">
                </div>
                <h3>Filibustero Admin</h3>
                <div class="subtitle">Management Portal</div>
            </div>
            <ul class="sidebar-menu">
                <li><a href="admin.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li><a href="teacher-requests.php"><i class="fas fa-user-clock"></i> Teacher Requests</a></li>
                <li><a href="manage-teachers.php"><i class="fas fa-chalkboard-teacher"></i> Manage Teachers</a></li>
                <li><a href="manage-students.php"><i class="fas fa-user-graduate"></i> Manage Students</a></li>
                <li><a href="all-users.php"><i class="fas fa-users"></i> All Users</a></li>
                <li><a href="admin-profile.php" class="active"><i class="fas fa-user-cog"></i> Admin Profile</a></li>
            </ul>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <nav class="navbar">
                <div class="nav-content">
                    <div class="logo">Admin Profile</div>
                    <div class="header-actions">
                        <span class="welcome-text">Welcome, <?php echo htmlspecialchars($_SESSION['admin_username'] ?? 'Admin'); ?></span>
                        <div class="dropdown">
                            <button class="dropdown-toggle" onclick="toggleDropdown()">
                                <i class="fas fa-user-circle"></i>
                            </button>
                            <div class="dropdown-menu" id="dropdownMenu">
                                <a href="admin-profile.php" class="dropdown-item">
                                    <i class="fas fa-user-edit"></i>Admin Profile
                                </a>
                                <div class="dropdown-divider"></div>
                                <a href="#" class="dropdown-item" onclick="handleLogout()">
                                    <i class="fas fa-sign-out-alt"></i>Logout
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </nav>

            <div class="container">
                <div class="profile-container">
                    <div class="header">
                        <div class="logo">
                            <img src="../logo.png" alt="Filibustero Logo">
                        </div>
                        <h1>Admin Profile</h1>
                        <p>Manage your administrator account</p>
                    </div>

                    <div class="navigation">
                        <a href="admin.php" class="nav-btn">
                            <i class="fas fa-arrow-left"></i>Back to Dashboard
                        </a>
                    </div>

                    <?php if ($profile_message): ?>
                        <div class="alert <?php echo $profile_success ? 'alert-success' : 'alert-danger'; ?>">
                            <i class="fas <?php echo $profile_success ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
                            <?php echo htmlspecialchars($profile_message); ?>
                        </div>
                    <?php endif; ?>

                    <!-- Two-column layout -->
                    <form id="adminProfileForm" method="POST">
                        <input type="hidden" name="action" value="update_admin_profile">
                        
                        <div class="profile-layout">
                            <!-- Current Information Column -->
                            <div class="current-info-column">
                                <h3 class="section-title">Current Information</h3>
                                <div class="form-grid">
                                    <div class="form-group">
                                        <input type="text" id="currentUsername" name="current_username" class="form-input" required 
                                               placeholder="Enter your current username" value="<?php echo htmlspecialchars($adminData['username']); ?>">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label class="form-label" for="currentEmail">
                                            Email Address <span class="required">*</span>
                                        </label>
                                        <input type="email" id="currentEmail" name="current_email" class="form-input" required 
                                               placeholder="Enter your email address" value="<?php echo htmlspecialchars($adminData['email'] ?? ''); ?>">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label class="form-label" for="currentFullName">
                                            Full Name <span class="required">*</span>
                                        </label>
                                        <input type="text" id="currentFullName" name="current_full_name" class="form-input" required 
                                               placeholder="Enter your full name" value="<?php echo htmlspecialchars($adminData['full_name']); ?>">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label class="form-label" for="currentPassword">
                                            Current Password <span class="required">*</span>
                                        </label>
                                        <div class="password-field">
                                            <input type="password" id="currentPassword" name="current_password" class="form-input" required 
                                                   placeholder="Enter your current password">
                                            <button type="button" class="password-toggle" onclick="togglePassword('currentPassword')">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Update Information Column -->
                            <div class="update-info-column">
                                <h3 class="section-title">Update Information (Optional)</h3>
                                <div class="form-grid">
                                    <div class="form-group">
                                        <label class="form-label" for="newUsername">
                                            New Username
                                        </label>
                                        <input type="text" id="newUsername" name="new_username" class="form-input" 
                                               placeholder="Enter new username (leave empty to keep current)">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label class="form-label" for="newEmail">
                                            New Email Address
                                        </label>
                                        <input type="email" id="newEmail" name="new_email" class="form-input" 
                                               placeholder="Enter new email (leave empty to keep current)">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label class="form-label" for="newFullName">
                                            New Full Name
                                        </label>
                                        <input type="text" id="newFullName" name="new_full_name" class="form-input" 
                                               placeholder="Enter new full name (leave empty to keep current)">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label class="form-label" for="newPassword">
                                            New Password
                                        </label>
                                        <div class="password-field">
                                            <input type="password" id="newPassword" name="new_password" class="form-input" 
                                                   placeholder="Enter new password (leave empty to keep current)">
                                            <button type="button" class="password-toggle" onclick="togglePassword('newPassword')">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label class="form-label" for="confirmPassword">
                                            Confirm New Password
                                        </label>
                                        <div class="password-field">
                                            <input type="password" id="confirmPassword" name="confirm_password" class="form-input" 
                                                   placeholder="Confirm your new password">
                                            <button type="button" class="password-toggle" onclick="togglePassword('confirmPassword')">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="action-buttons">
                            <button type="submit" class="btn btn-primary" id="updateBtn">
                                <div class="loading-spinner" id="loadingSpinner"></div>
                                <span id="updateBtnText">Update Profile</span>
                            </button>
                            <a href="admin.php" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Dropdown functionality
        function toggleDropdown() {
            const dropdown = document.getElementById('dropdownMenu');
            dropdown.classList.toggle('show');
        }

        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const dropdown = document.querySelector('.dropdown');
            const dropdownMenu = document.getElementById('dropdownMenu');
            
            if (!dropdown.contains(event.target)) {
                dropdownMenu.classList.remove('show');
            }
        });

        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const button = field.nextElementSibling;
            const icon = button.querySelector('i');
            
            if (field.type === 'password') {
                field.type = 'text';
                icon.className = 'fas fa-eye-slash';
            } else {
                field.type = 'password';
                icon.className = 'fas fa-eye';
            }
        }

        function handleLogout() {
            if (confirm('Are you sure you want to logout?')) {
                // Clear any cached data
                localStorage.removeItem('filibustero_admin_auth');
                sessionStorage.clear();
                
                // Set logout timestamp to prevent back navigation
                localStorage.setItem('filibustero_logout_time', Date.now().toString());
                
                // Use the same logout URL
                window.location.href = '?logout=1';
            }
        }

        // Prevent browser back/forward navigation after logout
        window.addEventListener('pageshow', function(event) {
            const logoutTime = localStorage.getItem('filibustero_logout_time');
            if (logoutTime && (Date.now() - parseInt(logoutTime)) < 300000) {
                // If user was recently logged out, prevent access
                localStorage.removeItem('filibustero_logout_time');
                window.location.replace('../index.html');
            }
        });


        // Form submission handling
        document.getElementById('adminProfileForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const currentEmail = document.getElementById('currentEmail').value;
            const newEmail = document.getElementById('newEmail').value;
            const newPassword = document.getElementById('newPassword').value;
            const confirmPassword = document.getElementById('confirmPassword').value;
            
            // Email validation
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(currentEmail)) {
                alert('Please enter a valid current email address');
                return;
            }
            
            if (newEmail && !emailRegex.test(newEmail)) {
                alert('Please enter a valid new email address');
                return;
            }
            
            // Password confirmation check
            if (newPassword && newPassword !== confirmPassword) {
                alert('New password and confirmation do not match');
                return;
            }
            
            // Minimum password length check
            if (newPassword && newPassword.length < 6) {
                alert('New password must be at least 6 characters long');
                return;
            }
            
            setLoading(true);
            this.submit();
        });

        function setLoading(loading) {
            const btn = document.getElementById('updateBtn');
            const spinner = document.getElementById('loadingSpinner');
            const btnText = document.getElementById('updateBtnText');
            
            btn.disabled = loading;
            spinner.style.display = loading ? 'inline-block' : 'none';
            btnText.textContent = loading ? 'Updating...' : 'Update Profile';
        }

        // Prevent browser back/forward navigation after logout
        window.addEventListener('pageshow', function(event) {
            const logoutTime = localStorage.getItem('filibustero_logout_time');
            if (logoutTime && (Date.now() - parseInt(logoutTime)) < 300000) {
                localStorage.removeItem('filibustero_logout_time');
                window.location.replace('../index.html');
            }
        });

        // Add page load animation
        document.addEventListener('DOMContentLoaded', function() {
            const profileContainer = document.querySelector('.profile-container');
            profileContainer.style.opacity = '0';
            profileContainer.style.transform = 'translateY(30px)';
            
            setTimeout(() => {
                profileContainer.style.opacity = '1';
                profileContainer.style.transform = 'translateY(0)';
            }, 100);
        });
    </script>
</body>
</html>