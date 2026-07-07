<?php
require_once 'config.php';

/// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_SESSION['admin_logged_in'])) {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    
    if (!empty($username) && !empty($password)) {
        try {
            // Check admin table
            $stmt = $pdo->prepare("SELECT * FROM admin WHERE username = ?");
            $stmt->execute([$username]);
            $admin = $stmt->fetch();
            
            if ($admin) {
                // Verify password (handle both hashed and plain text)
                $passwordValid = false;
                
                if (password_get_info($admin['password_hash'])['algo']) {
                    // It's hashed
                    $passwordValid = password_verify($password, $admin['password_hash']);
                } else {
                    // It's plain text (backward compatibility)
                    $passwordValid = ($password === $admin['password_hash']);
                    
                    // Rehash it for security
                    if ($passwordValid) {
                        $newHash = password_hash($password, PASSWORD_DEFAULT);
                        $updateStmt = $pdo->prepare("UPDATE admin SET password_hash = ? WHERE id = ?");
                        $updateStmt->execute([$newHash, $admin['id']]);
                    }
                }
                
                if ($passwordValid) {
                    // Update last login
                    $updateStmt->execute([$admin['id']]);
                    
                    // Set session
                    $_SESSION['admin_logged_in'] = true;
                    $_SESSION['admin_id'] = $admin['id'];
                    $_SESSION['admin_username'] = $admin['username'];
                    $_SESSION['admin_email'] = $admin['email'] ?? '';
                    $_SESSION['admin_full_name'] = $admin['full_name'];
                    
                    header('Location: admin.php');
                    exit;
                } else {
                    $login_error = 'Invalid username or password';
                }
            } else {
                $login_error = 'Invalid username or password';
            }
        } catch (PDOException $e) {
            error_log('Admin login error: ' . $e->getMessage());
            $login_error = 'Login failed. Please try again.';
        }
    } else {
        $login_error = 'Please enter both username and password';
    }
}

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

// Get dashboard statistics with email data
$stats = [];
try {
    // Pending teacher requests with email
    $stmt = $pdo->query("SELECT COUNT(*) FROM teachers WHERE is_active = 0");
    $stats['pending_requests'] = $stmt->fetchColumn();
    
    // Active teachers with email
    $stmt = $pdo->query("SELECT COUNT(*) FROM teachers WHERE is_active = 1");
    $stats['active_teachers'] = $stmt->fetchColumn();
    
    // Total students with email
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE user_type = 'student'");
    $stats['total_students'] = $stmt->fetchColumn();
    
    // Total users
    $stmt = $pdo->query("SELECT COUNT(*) FROM users");
    $stats['total_users'] = $stmt->fetchColumn();
    
    // Recent activities with email - updated query to include email
    $stmt = $pdo->prepare("
        SELECT u.username, u.full_name, u.email, u.user_type, u.created_at, t.is_active
        FROM users u 
        LEFT JOIN teachers t ON u.id = t.user_id
        ORDER BY u.created_at DESC 
        LIMIT 5
    ");
    $stmt->execute();
    $stats['recent_activities'] = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Filibustero Admin Dashboard</title>
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
            font-size: 14px; /* Smaller base font size */
        }
        
        /* Lighter animated background with scanlines effect */
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
        
        /* Dashboard Layout - More Compact */
        .dashboard-container {
            display: flex;
            min-height: 100vh;
            width: 100%;
        }
        
        /* Sidebar Styles - More Compact */
        .sidebar {
            width: 220px; /* Reduced from 280px */
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
            padding: 1.5rem 1rem; /* Reduced padding */
            border-bottom: 2px solid #d4a259;
            text-align: center;
            background: linear-gradient(135deg, #faf6ef 0%, #f5f1eb 100%);
        }
        
        .sidebar-header .logo {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #d4a259 0%, #b8863a 100%);
            border-radius: 50%; /* Changed to circular */
            margin: 0 auto 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fffcf7;
            font-size: 1.2rem;
            font-weight: 700;
            box-shadow: 
                0 6px 20px rgba(212, 162, 89, 0.4),
                inset 0 1px 0px rgba(255, 252, 247, 0.3);
            border: 2px solid #fffcf7;
            overflow: hidden; /* Important for containing the image */
        }
        
        /* If using an <img> tag */
        .sidebar-header .logo img {
            width: 150%; /* Adjust as needed */
            height: 1500%;
            object-fit: contain; /* Ensures image maintains aspect ratio */
            border-radius: 50%; /* Makes the image itself circular */
        }
        
        /* If using background-image */
        .sidebar-header .logo {
            background-image: url('path/to/your-logo.png');
            background-size: 60%; /* Adjust as needed */
            background-repeat: no-repeat;
            background-position: center;
        }
        
        .sidebar-header h3 {
            margin: 0;
            font-weight: 700;
            font-size: 1.1rem; /* Smaller font */
            color: #3d3529;
            letter-spacing: -0.5px;
            font-family: 'Orbitron', monospace;
        }
        
        .sidebar-header .subtitle {
            color: #7a6f5d;
            font-size: 0.8rem; /* Smaller font */
            margin-top: 0.3rem;
            font-family: 'Orbitron', monospace;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .sidebar-menu {
            list-style: none;
            padding: 1rem 0; /* Reduced padding */
            margin: 0;
        }
        
        .sidebar-menu li {
            margin: 0.2rem 0; /* Reduced margin */
        }
        
        .sidebar-menu a {
            color: #5a5043;
            padding: 0.8rem 1rem; /* Reduced padding */
            display: block;
            text-decoration: none;
            transition: all 0.3s ease;
            border-left: 4px solid transparent;
            position: relative;
            font-weight: 600;
            font-size: 0.85rem; /* Smaller font */
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
            margin-right: 10px; /* Reduced margin */
            width: 16px; /* Smaller icon container */
            text-align: center;
            font-size: 1rem; /* Smaller icons */
            color: #b8863a;
        }

        /* Main Content - Adjusted Width */
        .main-content {
            flex: 1;
            margin-left: 220px; /* Adjusted to match sidebar width */
            padding: 0;
            min-height: 100vh;
            width: calc(100% - 220px); /* Adjusted to match sidebar width */
        }
        
        .navbar {
            background: linear-gradient(135deg, #fffcf7 0%, #f9f5ed 100%);
            padding: 1rem 2rem; /* Reduced padding */
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
            font-size: 1.5rem; /* Smaller font */
            font-weight: 700;
            color: #b8863a;
            letter-spacing: -0.5px;
            font-family: 'Orbitron', monospace;
        }
        
        .header-actions {
            display: flex;
            align-items: center;
            gap: 1rem; /* Reduced gap */
        }
        
        .welcome-text {
            font-weight: 600;
            color: #5a5043;
            font-family: 'Orbitron', monospace;
            text-transform: uppercase;
            font-size: 0.8rem; /* Smaller font */
            letter-spacing: 0.5px;
        }
        
        .dropdown {
            position: relative;
        }
        
        .dropdown-toggle {
            background: linear-gradient(135deg, #d4a259 0%, #b8863a 100%);
            border: 2px solid #9c6d2e;
            color: #fffcf7;
            padding: 0.5rem; /* Reduced padding */
            border-radius: 0;
            cursor: pointer;
            font-size: 1rem; /* Smaller font */
            width: 40px; /* Smaller button */
            height: 40px;
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
            min-width: 200px; /* Slightly smaller */
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
            padding: 0.7rem 1rem; /* Reduced padding */
            color: #5a5043;
            text-decoration: none;
            transition: all 0.2s ease;
            border-bottom: 1px solid #f0dfb8;
            font-weight: 600;
            font-family: 'Orbitron', monospace;
            font-size: 0.8rem; /* Smaller font */
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
            width: 14px; /* Smaller icon container */
        }
        
        .dropdown-divider {
            height: 1px;
            background: #d4a259;
            margin: 0.5rem 0;
        }
        
        .container {
            width: 100%;
            padding: 1.5rem 2rem; /* Reduced padding */
            max-width: none;
        }
        
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); /* Smaller cards */
            gap: 1.5rem; /* Reduced gap */
            margin-bottom: 2rem; /* Reduced margin */
        }
        
        .stats-card {
            background: linear-gradient(135deg, #fffcf7 0%, #f9f5ed 100%);
            border: 2px solid #d4a259;
            border-radius: 0;
            padding: 2rem 1.5rem; /* Reduced padding */
            box-shadow: 
                0 8px 30px rgba(212, 162, 89, 0.2),
                0 0 40px rgba(212, 162, 89, 0.1),
                inset 0 0 20px rgba(212, 162, 89, 0.05);
            transition: all 0.3s ease;
            position: relative;
        }
        
        .stats-card::before {
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
        
        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 
                0 15px 40px rgba(212, 162, 89, 0.3),
                0 0 60px rgba(212, 162, 89, 0.15),
                inset 0 0 20px rgba(212, 162, 89, 0.08);
        }
        
        .card-header {
            display: flex;
            align-items: center;
            gap: 1rem; /* Reduced gap */
            margin-bottom: 1rem; /* Reduced margin */
        }
        
        .card-icon {
            width: 50px; /* Smaller icon */
            height: 50px;
            border-radius: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem; /* Smaller font */
            color: #fffcf7;
            background: linear-gradient(135deg, #d4a259 0%, #b8863a 100%);
            box-shadow: 
                0 8px 25px rgba(212, 162, 89, 0.4),
                inset 0 1px 0px rgba(255, 252, 247, 0.3);
            border: 2px solid #fffcf7;
        }
        
        .card-title {
            font-size: 1.2rem; /* Smaller font */
            font-weight: 700;
            color: #3d3529;
            font-family: 'Orbitron', monospace;
            letter-spacing: 1px;
            text-transform: uppercase;
        }
        
        .stat-number {
            font-size: 2.5rem; /* Smaller font */
            font-weight: 900;
            color: #b8863a;
            margin-bottom: 0.5rem;
            font-family: 'Orbitron', monospace;
            text-shadow: 2px 2px 0px rgba(212, 162, 89, 0.3);
        }
        
        .stat-label {
            color: #7a6f5d;
            font-size: 1rem; /* Smaller font */
            font-weight: 600;
        }
        
        .content-section {
            background: linear-gradient(135deg, #fffcf7 0%, #f9f5ed 100%);
            border: 2px solid #d4a259;
            border-radius: 0;
            padding: 2rem; /* Reduced padding */
            box-shadow: 
                0 8px 30px rgba(212, 162, 89, 0.2),
                0 0 40px rgba(212, 162, 89, 0.1),
                inset 0 0 20px rgba(212, 162, 89, 0.05);
            margin-bottom: 1.5rem; /* Reduced margin */
            position: relative;
        }
        
        .content-section::before {
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
        
        .section-title {
            font-size: 1.4rem; /* Smaller font */
            font-weight: 900;
            color: #3d3529;
            margin-bottom: 1.5rem; /* Reduced margin */
            display: flex;
            align-items: center;
            gap: 0.8rem; /* Reduced gap */
            font-family: 'Orbitron', monospace;
            letter-spacing: 1px;
            text-transform: uppercase;
            text-shadow: 2px 2px 0px rgba(212, 162, 89, 0.2);
        }
        
        .leaderboard-container {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        
        .leaderboard-table {
            width: 100%;
            border-collapse: collapse;
            background: linear-gradient(135deg, #fffcf7 0%, #f9f5ed 100%);
            border: 2px solid #d4a259;
            border-radius: 0;
            overflow: hidden;
            box-shadow: 0 0 40px rgba(212, 162, 89, 0.2);
            font-size: 0.85rem; /* Smaller table font */
        }
        
        .leaderboard-table th {
            background: linear-gradient(135deg, #d4a259 0%, #b8863a 100%);
            color: #fffcf7;
            padding: 1rem 0.8rem; /* Reduced padding */
            text-align: left;
            font-weight: 700;
            font-family: 'Orbitron', monospace;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-size: 0.8rem; /* Smaller font */
            border-bottom: 2px solid #9c6d2e;
            box-shadow: 0 4px 12px rgba(212, 162, 89, 0.2);
        }
        
        .leaderboard-table td {
            padding: 0.9rem 0.8rem; /* Reduced padding */
            border-bottom: 2px solid #d4a259;
            transition: all 0.2s ease;
            color: #4a4235;
            font-weight: 600;
            border: 1px solid rgba(212, 162, 89, 0.3);
            border-bottom: 1px solid rgba(212, 162, 89, 0.5);
        }
        
        .leaderboard-table tr:hover td {
            background: #faf6ef;
            box-shadow: inset 0 0 10px rgba(212, 162, 89, 0.1);
            transform: translateX(5px);
        }
        
        .badge {
            padding: 0.4rem 0.8rem; /* Reduced padding */
            border-radius: 0;
            font-size: 0.75rem; /* Smaller font */
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .badge-pending {
            background: #fef9f0;
            color: #9c7a4d;
            border: 1px solid #d4a259;
        }
        
        .badge-active {
            background: #f0e8d8;
            color: #7a6f5d;
            border: 1px solid #b8863a;
        }
        
        .badge-inactive {
            background: #f7f4ed;
            color: #8b764d;
            border: 1px solid #9c7a4d;
        }

        .badge-info {
            background: #f5f0e6;
            color: #5a4d3a;
            border: 1px solid #d4a259;
        }
        
        .alert {
            padding: 1rem 1.2rem; /* Reduced padding */
            border-radius: 0;
            margin-bottom: 1.5rem; /* Reduced margin */
            border: 2px solid;
            font-weight: 500;
            font-size: 0.9rem; /* Smaller font */
        }

        .alert-danger {
            background: linear-gradient(135deg, rgba(201, 133, 109, 0.1), rgba(179, 111, 89, 0.05));
            color: #b36f59;
            border-color: rgba(201, 133, 109, 0.3);
        }

        .refresh-btn {
            background: linear-gradient(135deg, #faf6ef 0%, #f5f1eb 100%);
            border: 2px solid #d4a259;
            color: #5a5043;
            padding: 0.7rem 1.2rem; /* Reduced padding */
            border-radius: 0;
            cursor: pointer;
            font-weight: 700;
            transition: all 0.3s ease;
            margin-left: 1rem;
            font-family: 'Orbitron', monospace;
            font-size: 0.8rem; /* Smaller font */
            text-transform: uppercase;
            letter-spacing: 0.5px;
            box-shadow: 0 4px 12px rgba(212, 162, 89, 0.1);
        }
        
        .refresh-btn:hover {
            background: linear-gradient(135deg, #d4a259 0%, #b8863a 100%);
            color: #fffcf7;
            border-color: #9c6d2e;
            box-shadow: 
                0 6px 20px rgba(212, 162, 89, 0.4),
                inset 0 1px 0px rgba(255, 252, 247, 0.3);
            transform: translateY(-2px);
        }

        /* Mobile Responsive Styles */
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
            
            .logo {
                font-size: 1.2rem; /* Smaller font */
                text-align: center;
            }
            
            .header-actions {
                width: 100%;
                justify-content: space-between;
            }
            
            .welcome-text {
                font-size: 0.75rem; /* Smaller font */
            }
            
            .dashboard-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            
            .stats-card {
                padding: 1.5rem;
            }
            
            .card-header {
                flex-direction: column;
                text-align: center;
                gap: 0.5rem;
            }
            
            .stat-number {
                font-size: 2rem; /* Smaller font */
                text-align: center;
            }
            
            .stat-label {
                text-align: center;
                font-size: 0.9rem; /* Smaller font */
            }
            
            .content-section {
                padding: 1.5rem;
            }
            
            .section-title {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
                font-size: 1.2rem; /* Smaller font */
            }
            
            .leaderboard-container {
                margin: 0 -1rem;
                padding: 0 1rem;
                width: calc(100% + 2rem);
            }
            
            .leaderboard-table {
                min-width: 800px;
            }
            
            .leaderboard-table th,
            .leaderboard-table td {
                padding: 0.8rem 0.5rem;
                font-size: 0.8rem; /* Smaller font */
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
                <li><a href="admin" class="active"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li><a href="teacher-requests"><i class="fas fa-user-clock"></i> Teacher Requests</a></li>
                <li><a href="manage-teachers"><i class="fas fa-chalkboard-teacher"></i> Manage Teachers</a></li>
                <li><a href="manage-students"><i class="fas fa-user-graduate"></i> Manage Students</a></li>
                <li><a href="all-users"><i class="fas fa-users"></i> All Users</a></li>
                <li><a href="admin-profile"><i class="fas fa-user-cog"></i> Admin Profile</a></li>
            </ul>
        </div>

        <!-- Main Content - Adjusted Width -->
        <div class="main-content">
            <nav class="navbar">
                <div class="nav-content">
                    <div class="logo">Admin Dashboard</div>
                    <div class="header-actions">
                        <span class="welcome-text">Welcome, <?php echo htmlspecialchars($_SESSION['admin_username'] ?? 'Admin'); ?></span>
                        <div class="dropdown">
                            <button class="dropdown-toggle" onclick="toggleDropdown()">
                                <i class="fas fa-user-circle"></i>
                            </button>
                            <div class="dropdown-menu" id="dropdownMenu">
                                <a href="admin-profile" class="dropdown-item">
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
                <?php if (isset($error)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
                </div>
                <?php endif; ?>

                <!-- Dashboard Overview -->
                <div class="dashboard-grid">
                    <div class="stats-card">
                        <div class="card-header">
                            <div class="card-icon"><i class="fas fa-user-clock"></i></div>
                            <div class="card-title">Pending Requests</div>
                        </div>
                        <div class="stat-number"><?php echo $stats['pending_requests'] ?? 0; ?></div>
                        <div class="stat-label">Teacher Approvals Needed</div>
                    </div>

                    <div class="stats-card">
                        <div class="card-header">
                            <div class="card-icon"><i class="fas fa-chalkboard-teacher"></i></div>
                            <div class="card-title">Active Teachers</div>
                        </div>
                        <div class="stat-number"><?php echo $stats['active_teachers'] ?? 0; ?></div>
                        <div class="stat-label">Currently Teaching</div>
                    </div>

                    <div class="stats-card">
                        <div class="card-header">
                            <div class="card-icon"><i class="fas fa-user-graduate"></i></div>
                            <div class="card-title">Total Students</div>
                        </div>
                        <div class="stat-number"><?php echo $stats['total_students'] ?? 0; ?></div>
                        <div class="stat-label">Registered Students</div>
                    </div>

                    <div class="stats-card">
                        <div class="card-header">
                            <div class="card-icon"><i class="fas fa-users"></i></div>
                            <div class="card-title">System Users</div>
                        </div>
                        <div class="stat-number"><?php echo $stats['total_users'] ?? 0; ?></div>
                        <div class="stat-label">Total Users</div>
                    </div>
                </div>

                <!-- Recent Activities Section -->
                <div class="content-section">
                    <div class="section-title">
                        <i class="fas fa-history"></i> Recent User Activities
                        <button class="refresh-btn" onclick="location.reload()">
                            <i class="fas fa-sync-alt"></i> Refresh
                        </button>
                    </div>
                    <div class="leaderboard-container">
                        <table class="leaderboard-table">
                            <thead>
                                <tr>
                                    <th>Username</th>
                                    <th>Full Name</th>
                                    <th>Email</th>
                                    <th>User Type</th>
                                    <th>Registration Date</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (isset($stats['recent_activities']) && !empty($stats['recent_activities'])): ?>
                                    <?php foreach ($stats['recent_activities'] as $activity): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($activity['username']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($activity['full_name']); ?></td>
                                        <td><?php echo htmlspecialchars($activity['email'] ?? 'N/A'); ?></td>
                                        <td><span class="badge badge-info"><?php echo ucfirst($activity['user_type']); ?></span></td>
                                        <td><?php echo date('M d, Y H:i', strtotime($activity['created_at'])); ?></td>
                                        <td>
                                            <?php if ($activity['user_type'] === 'teacher'): ?>
                                                <?php if ($activity['is_active'] == 1): ?>
                                                    <span class="badge badge-active">Active</span>
                                                <?php else: ?>
                                                    <span class="badge badge-pending">Pending</span>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="badge badge-active">Active</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" style="text-align: center; color: #7a6f5d; padding: 3rem;">
                                            <i class="fas fa-inbox" style="font-size: 3rem; margin-bottom: 1rem; display: block; opacity: 0.5;"></i>
                                            No recent activities found
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
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

        function viewProfile() {
            window.location.href = 'admin-profile';
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

        // Add page load animation
        document.addEventListener('DOMContentLoaded', function() {
            // Animate stats cards
            const statsCards = document.querySelectorAll('.stats-card');
            statsCards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(30px)';
                card.style.transition = `opacity 0.6s ease ${index * 0.1}s, transform 0.6s ease ${index * 0.1}s`;
                
                setTimeout(() => {
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, 100);
            });
        });

        // Add hover effects for table rows
        document.querySelectorAll('.leaderboard-table tr').forEach(row => {
            row.addEventListener('mouseenter', function() {
                this.style.transform = 'translateX(5px)';
                this.style.transition = 'transform 0.2s ease';
            });
            
            row.addEventListener('mouseleave', function() {
                this.style.transform = 'translateX(0)';
            });
        });

        // Confirm logout for sidebar logout link
        document.querySelector('.logout-link').addEventListener('click', function(e) {
            if (!confirm('Are you sure you want to logout?')) {
                e.preventDefault();
            }
        });
    </script>
</body>
</html>