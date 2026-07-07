<?php
require_once 'config.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header('Location: admin');
    exit;
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: ../index');
    exit;
}

$message = '';
$message_type = '';

// Handle actions
if ($_POST) {
    if (isset($_POST['action']) && isset($_POST['teacher_id'])) {
        $teacher_id = (int)$_POST['teacher_id'];
        $action = $_POST['action'];
        
        try {
            if ($action === 'deactivate') {
                $stmt = $pdo->prepare("UPDATE teachers SET is_active = 0 WHERE user_id = ?");
                $stmt->execute([$teacher_id]);
                $message = "Teacher deactivated successfully!";
                $message_type = "warning";
            } elseif ($action === 'activate') {
                $stmt = $pdo->prepare("UPDATE teachers SET is_active = 1 WHERE user_id = ?");
                $stmt->execute([$teacher_id]);
                $message = "Teacher activated successfully!";
                $message_type = "success";
            } elseif ($action === 'delete') {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare("DELETE FROM teachers WHERE user_id = ?");
                $stmt->execute([$teacher_id]);
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$teacher_id]);
                $pdo->commit();
                $message = "Teacher account deleted successfully!";
                $message_type = "danger";
            } elseif ($action === 'reset_password') {
                $new_password = 'temp123'; // Default temporary password
                $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password_hash = ?, temp_password = 1 WHERE id = ?");
                $stmt->execute([$password_hash, $teacher_id]);
                $message = "Password reset successfully! New password: temp123";
                $message_type = "info";
            }
        } catch (PDOException $e) {
            $pdo->rollBack();
            $message = "Error: " . $e->getMessage();
            $message_type = "danger";
        }
    }
}

// Get search parameter
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Get active teachers
try {
    $sql = "
        SELECT u.id, u.full_name, u.username, u.email, u.id_number, u.created_at, t.is_active,
               GROUP_CONCAT(ts.section SEPARATOR ', ') as sections
        FROM users u 
        JOIN teachers t ON u.id = t.user_id 
        LEFT JOIN teacher_sections ts ON u.id = ts.teacher_id
        WHERE u.user_type = 'teacher'
    ";
    
    $params = [];
    if ($search) {
        $sql .= " AND (u.full_name LIKE ? OR u.username LIKE ? OR u.id_number LIKE ?)";
        $search_param = "%$search%";
        $params = [$search_param, $search_param, $search_param];
    }
    
    $sql .= " GROUP BY u.id ORDER BY t.is_active DESC, u.full_name ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $teachers = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Teachers - Filibustero Admin</title>
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

        .filter-section {
            background: linear-gradient(135deg, #fffcf7 0%, #f9f5ed 100%);
            border: 2px solid #d4a259;
            border-radius: 0;
            padding: 1.5rem; /* Reduced padding */
            margin-bottom: 1.5rem; /* Reduced margin */
            box-shadow: 
                0 8px 30px rgba(212, 162, 89, 0.2),
                0 0 40px rgba(212, 162, 89, 0.1),
                inset 0 0 20px rgba(212, 162, 89, 0.05);
        }

        .row {
            display: flex;
            flex-wrap: wrap;
            margin: -0.5rem; /* Reduced margin */
        }

        .col-md-6, .col-md-4, .col-md-3, .col-md-2, .col-12 {
            padding: 0.5rem; /* Reduced padding */
        }

        .col-md-6 { flex: 0 0 50%; max-width: 50%; }
        .col-md-4 { flex: 0 0 33.33%; max-width: 33.33%; }
        .col-md-3 { flex: 0 0 25%; max-width: 25%; }
        .col-md-2 { flex: 0 0 16.67%; max-width: 16.67%; }
        .col-12 { flex: 0 0 100%; max-width: 100%; }

        .form-control, .form-select {
            width: 100%;
            padding: 0.7rem 0.8rem; /* Reduced padding */
            background: #fffcf7;
            border: 1.5px solid #e8dfd0;
            border-radius: 0;
            color: #4a4235;
            font-family: 'Inter', sans-serif;
            font-size: 0.9rem; /* Smaller font */
            transition: all 0.2s ease;
        }

        .form-control:focus, .form-select:focus {
            outline: none;
            border-color: #d4a259;
            box-shadow: 0 0 0 3px rgba(212, 162, 89, 0.15);
            background: #fffcf7;
        }

        .form-control::placeholder {
            color: #a39582;
            opacity: 0.7;
        }

        .btn {
            padding: 0.7rem 1.2rem; /* Reduced padding */
            border: none;
            border-radius: 0;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            font-size: 0.85rem; /* Smaller font */
            font-family: 'Inter', sans-serif;
        }

        .btn-primary {
            background: linear-gradient(135deg, #d4a259 0%, #b8863a 100%);
            color: #fffcf7;
            border: 1.5px solid transparent;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #b8863a 0%, #9c6d2e 100%);
            transform: translateY(-1px);
            box-shadow: 0 6px 15px rgba(212, 162, 89, 0.4);
        }

        .btn-outline-secondary {
            background: transparent;
            color: #7a6f5d;
            border: 1.5px solid #e8dfd0;
        }

        .btn-outline-secondary:hover {
            background: #faf6ef;
            color: #d4a259;
            border-color: #d4a259;
        }

        .w-100 {
            width: 100% !important;
        }
        
        .table-responsive {
            display: block !important;
            width: 100% !important;
            overflow-x: auto !important;
        }
        
        .table {
            display: table !important;
            width: 100% !important;
            border-collapse: collapse;
            background: linear-gradient(135deg, #fffcf7 0%, #f9f5ed 100%);
            border: 2px solid #d4a259;
            border-radius: 0;
            overflow: hidden;
            box-shadow: 0 0 40px rgba(212, 162, 89, 0.2);
            font-size: 0.85rem; /* Smaller table font */
        }
        
        .table th {
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
        
        .table td {
            padding: 0.9rem 0.8rem; /* Reduced padding */
            border-bottom: 2px solid #d4a259;
            transition: all 0.2s ease;
            color: #4a4235;
            font-weight: 600;
            border: 1px solid rgba(212, 162, 89, 0.3);
            border-bottom: 1px solid rgba(212, 162, 89, 0.5);
        }
        
        .table tr:hover td {
            background: #faf6ef;
            box-shadow: inset 0 0 10px rgba(212, 162, 89, 0.1);
            transform: translateX(5px);
        }
        
        .table tbody {
            display: table-row-group !important;
        }
        
        .table tr {
            display: table-row !important;
        }
        
        .table td {
            display: table-cell !important;
        }

        .table tr:hover {
            background: #faf6ef;
        }

        .table tr:last-child td {
            border-bottom: none;
        }

        .action-buttons .btn {
            padding: 0.3rem 0.5rem; /* Reduced padding */
            margin-right: 0.2rem;
            font-size: 0.75rem; /* Smaller font */
        }

        .btn-warning {
            background: linear-gradient(135deg, #e8b04b 0%, #d19a3a 100%);
            color: #fffcf7;
        }

        .btn-warning:hover {
            background: linear-gradient(135deg, #d19a3a 0%, #b8863a 100%);
            transform: translateY(-1px);
        }

        .btn-success {
            background: linear-gradient(135deg, #d4a259 0%, #b8863a 100%);
            color: #fffcf7;
        }

        .btn-success:hover {
            background: linear-gradient(135deg, #b8863a 0%, #9c6d2e 100%);
            transform: translateY(-1px);
        }

        .btn-danger {
            background: linear-gradient(135deg, #c9856d 0%, #b36f59 100%);
            color: #fffcf7;
        }

        .btn-danger:hover {
            background: linear-gradient(135deg, #b36f59 0%, #9d5c48 100%);
            transform: translateY(-1px);
        }

        .btn-info {
            background: linear-gradient(135deg, #6da8c9 0%, #598bb3 100%);
            color: #fffcf7;
        }

        .btn-info:hover {
            background: linear-gradient(135deg, #598bb3 0%, #48759d 100%);
            transform: translateY(-1px);
        }

        .text-center {
            text-align: center;
        }

        .text-muted {
            color: #a39582 !important;
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

        .alert-info {
            background: linear-gradient(135deg, rgba(212, 162, 89, 0.1), rgba(184, 134, 58, 0.05));
            color: #8b764d;
            border-color: rgba(212, 162, 89, 0.3);
        }

        .alert-warning {
            background: linear-gradient(135deg, rgba(232, 176, 75, 0.1), rgba(209, 154, 58, 0.05));
            color: #9c7a4d;
            border-color: rgba(232, 176, 75, 0.3);
        }

        .alert-success {
            background: linear-gradient(135deg, rgba(212, 162, 89, 0.1), rgba(184, 134, 58, 0.05));
            color: #8b764d;
            border-color: rgba(212, 162, 89, 0.3);
        }

        .alert-dismissible .btn-close {
            background: none;
            border: none;
            color: #7a6f5d;
            opacity: 0.8;
            cursor: pointer;
            font-size: 1.1rem; /* Slightly smaller */
            padding: 0.25rem;
        }

        .alert-dismissible .btn-close:hover {
            opacity: 1;
        }

        .badge {
            padding: 0.4rem 0.8rem; /* Reduced padding */
            border-radius: 0;
            font-size: 0.75rem; /* Smaller font */
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .badge-success {
            background: linear-gradient(135deg, #d4a259 0%, #b8863a 100%);
            color: #fffcf7;
        }

        .badge-secondary {
            background: #e8dfd0;
            color: #5a4d3a;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1050;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(58, 47, 35, 0.5);
            backdrop-filter: blur(3px);
        }

        .modal.show {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-dialog {
            max-width: 500px; /* Smaller modal */
            width: 90%;
        }

        .modal-content {
            background: #fffcf7;
            border: 2px solid #d4a259;
            border-radius: 0;
            box-shadow: 0 20px 60px rgba(139, 107, 66, 0.15);
            color: #4a4235;
        }

        .modal-header {
            background: linear-gradient(135deg, #faf6ef 0%, #f5f1eb 100%);
            border-bottom: 2px solid #d4a259;
            padding: 1.2rem 1.5rem; /* Reduced padding */
        }

        .modal-title {
            margin: 0;
            font-weight: 600;
            font-size: 1.1rem; /* Smaller font */
            color: #3d3529;
            font-family: 'Orbitron', monospace;
        }

        .btn-close {
            background: none;
            border: none;
            color: #7a6f5d;
            font-size: 1.3rem; /* Slightly smaller */
            cursor: pointer;
            padding: 0;
            opacity: 0.8;
            transition: opacity 0.2s ease;
        }

        .btn-close:hover {
            opacity: 1;
        }

        .modal-body {
            padding: 1.5rem; /* Reduced padding */
            font-size: 0.9rem; /* Smaller font */
            line-height: 1.6;
        }

        .modal-footer {
            padding: 1.2rem 1.5rem; /* Reduced padding */
            border-top: 2px solid #d4a259;
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
        }

        .btn-secondary {
            background: #f5f0e6;
            color: #5a5043;
            border: 1.5px solid #e8dfd0;
        }

        .btn-secondary:hover {
            background: #e8dfd0;
            color: #4a4235;
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
            
            .row {
                flex-direction: column;
            }

            .col-md-6, .col-md-4, .col-md-3, .col-md-2 {
                flex: none;
                max-width: 100%;
                margin-bottom: 1rem;
            }

            .table-responsive {
                font-size: 0.75rem; /* Smaller font */
            }

            .table th, .table td {
                padding: 0.6rem 0.4rem; /* Reduced padding */
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
                <li><a href="admin"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li><a href="teacher-requests"><i class="fas fa-user-clock"></i> Teacher Requests</a></li>
                <li><a href="manage-teachers" class="active"><i class="fas fa-chalkboard-teacher"></i> Manage Teachers</a></li>
                <li><a href="manage-students"><i class="fas fa-user-graduate"></i> Manage Students</a></li>
                <li><a href="all-users"><i class="fas fa-users"></i> All Users</a></li>
                <li><a href="admin-profile"><i class="fas fa-user-cog"></i> Admin Profile</a></li>
            </ul>
        </div>

        <!-- Main Content - Adjusted Width -->
        <div class="main-content">
            <nav class="navbar">
                <div class="nav-content">
                    <div class="logo">Manage Teachers</div>
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
                <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" onclick="this.parentElement.style.display='none'">&times;</button>
                </div>
                <?php endif; ?>

                <!-- Filter Section -->
                <div class="filter-section">
                    <form method="GET" class="row">
                        <div class="col-md-8">
                            <input type="text" class="form-control" name="search" placeholder="Search teachers..." value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="col-md-4">
                            <div class="d-flex gap-2">
                                <button class="btn btn-primary w-100" type="submit">
                                    <i class="fas fa-search"></i> Search
                                </button>
                                <?php if ($search): ?>
                                <a href="manage-teachers" class="btn btn-outline-secondary">
                                    <i class="fas fa-times"></i> Clear
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Teachers Table -->
                <div class="content-section">
                    <div class="section-title">
                        <i class="fas fa-chalkboard-teacher"></i> Teachers Registry (<?php echo count($teachers); ?>)
                    </div>
                    <div class="card-body">
                        <?php if (empty($teachers)): ?>
                            <div class="text-center text-muted">
                                <i class="fas fa-user-slash fa-3x" style="margin-bottom: 1rem; display: block; opacity: 0.3;"></i>
                                <p>No teachers found<?php echo $search ? ' matching your search' : ''; ?>.</p>
                            </div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th><i class="fas fa-hashtag"></i> ID</th>
                                        <th><i class="fas fa-user"></i> Full Name</th>
                                        <th><i class="fas fa-at"></i> Username</th>
                                        <th><i class="fas fa-envelope"></i> Email</th> <!-- Add this line -->
                                        <th><i class="fas fa-id-card"></i> ID Number</th>
                                        <th><i class="fas fa-users"></i> Assigned Sections</th>
                                        <th><i class="fas fa-circle"></i> Status</th>
                                        <th><i class="fas fa-cogs"></i> Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($teachers as $teacher): ?>
                                    <tr>
                                        <td><?php echo $teacher['id']; ?></td>
                                        <td><?php echo htmlspecialchars($teacher['full_name']); ?></td>
                                        <td><?php echo htmlspecialchars($teacher['username']); ?></td>
                                        <td><?php echo htmlspecialchars($teacher['email'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($teacher['id_number']); ?></td>
                                        <td>
                                            <?php if ($teacher['sections']): ?>
                                                <small><?php echo htmlspecialchars($teacher['sections']); ?></small>
                                            <?php else: ?>
                                                <small class="text-muted">No sections assigned</small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($teacher['is_active'] == 1): ?>
                                                <span class="badge badge-success">Active</span>
                                            <?php else: ?>
                                                <span class="badge badge-secondary">Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="action-buttons">
                                            <?php if ($teacher['is_active'] == 1): ?>
                                                <button class="btn btn-sm btn-warning" onclick="confirmAction(<?php echo $teacher['id']; ?>, 'deactivate', '<?php echo htmlspecialchars($teacher['full_name']); ?>')" title="Deactivate">
                                                    <i class="fas fa-user-slash"></i>
                                                </button>
                                            <?php else: ?>
                                                <button class="btn btn-sm btn-success" onclick="confirmAction(<?php echo $teacher['id']; ?>, 'activate', '<?php echo htmlspecialchars($teacher['full_name']); ?>')" title="Activate">
                                                    <i class="fas fa-user-check"></i>
                                                </button>
                                            <?php endif; ?>
                                            <button class="btn btn-sm btn-info" onclick="resetPassword(<?php echo $teacher['id']; ?>, '<?php echo htmlspecialchars($teacher['full_name']); ?>')" title="Reset Password">
                                                <i class="fas fa-key"></i>
                                            </button>
                                            <button class="btn btn-sm btn-primary" onclick="viewTeacher(<?php echo $teacher['id']; ?>)" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="btn btn-sm btn-danger" onclick="confirmAction(<?php echo $teacher['id']; ?>, 'delete', '<?php echo htmlspecialchars($teacher['full_name']); ?>')" title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Confirmation Modal -->
    <div class="modal fade" id="confirmationModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Confirm Action</h5>
                    <button type="button" class="btn-close" onclick="closeModal()" aria-label="Close">&times;</button>
                </div>
                <div class="modal-body" id="modalBody">
                    Are you sure you want to perform this action?
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="teacher_id" id="confirmTeacherId">
                        <input type="hidden" name="action" id="confirmAction">
                        <button type="submit" class="btn" id="confirmBtn">Confirm</button>
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

        function confirmAction(teacherId, action, teacherName) {
            let title, message, btnClass, btnText;
            
            switch(action) {
                case 'activate':
                    title = 'Activate Teacher';
                    message = `Are you sure you want to activate <strong>${teacherName}</strong>?`;
                    btnClass = 'btn-success';
                    btnText = 'Activate';
                    break;
                case 'deactivate':
                    title = 'Deactivate Teacher';
                    message = `Are you sure you want to deactivate <strong>${teacherName}</strong>?`;
                    btnClass = 'btn-warning';
                    btnText = 'Deactivate';
                    break;
                case 'delete':
                    title = 'Delete Teacher';
                    message = `Are you sure you want to delete <strong>${teacherName}</strong>? This action cannot be undone.`;
                    btnClass = 'btn-danger';
                    btnText = 'Delete';
                    break;
                case 'reset_password':
                    title = 'Reset Password';
                    message = `Are you sure you want to reset the password for <strong>${teacherName}</strong>? The new password will be "temp123".`;
                    btnClass = 'btn-info';
                    btnText = 'Reset';
                    break;
            }
            
            document.getElementById('modalTitle').textContent = title;
            document.getElementById('modalBody').innerHTML = message;
            document.getElementById('confirmTeacherId').value = teacherId;
            document.getElementById('confirmAction').value = action;
            document.getElementById('confirmBtn').textContent = btnText;
            document.getElementById('confirmBtn').className = 'btn ' + btnClass;
            
            showModal();
        }

        function resetPassword(teacherId, teacherName) {
            confirmAction(teacherId, 'reset_password', teacherName);
        }

        function viewTeacher(teacherId) {
            // Implement detailed view
            window.location.href = `view-teacher?id=${teacherId}`;
        }

        function showModal() {
            const modal = document.getElementById('confirmationModal');
            modal.classList.add('show');
            modal.style.display = 'flex';
        }

        function closeModal() {
            const modal = document.getElementById('confirmationModal');
            modal.classList.remove('show');
            modal.style.display = 'none';
        }

        // Close modal when clicking outside
        document.getElementById('confirmationModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });

        // Add page load animation
        document.addEventListener('DOMContentLoaded', function() {
            // Animate content sections
            const sections = document.querySelectorAll('.content-section, .filter-section');
            sections.forEach((section, index) => {
                section.style.opacity = '0';
                section.style.transform = 'translateY(30px)';
                section.style.transition = `opacity 0.6s ease ${index * 0.1}s, transform 0.6s ease ${index * 0.1}s`;
                
                setTimeout(() => {
                    section.style.opacity = '1';
                    section.style.transform = 'translateY(0)';
                }, 100);
            });

            // Add hover effects for table rows
            document.querySelectorAll('.table tr').forEach(row => {
                row.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateX(5px)';
                    this.style.transition = 'transform 0.2s ease';
                });
                
                row.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateX(0)';
                });
            });
        });

        // Confirm logout for sidebar logout link
        document.querySelector('.logout-link').addEventListener('click', function(e) {
            if (!confirm('Are you sure you want to logout?')) {
                e.preventDefault();
            }
        });

        // Prevent browser back/forward navigation after logout
        window.addEventListener('pageshow', function(event) {
            const logoutTime = localStorage.getItem('filibustero_logout_time');
            if (logoutTime && (Date.now() - parseInt(logoutTime)) < 300000) {
                // If user was recently logged out, prevent access
                localStorage.removeItem('filibustero_logout_time');
                window.location.replace('../index.html');
            }
        });

        function exportData() {
            const table = document.querySelector('.table');
            const rows = Array.from(table.querySelectorAll('tr'));
            
            let csv = [];
            rows.forEach(row => {
                const cols = Array.from(row.querySelectorAll('td, th'));
                const rowData = cols.slice(0, -1).map(col => {
                    return '"' + col.textContent.replace(/"/g, '""').trim() + '"';
                });
                if (rowData.length > 0) {
                    csv.push(rowData.join(','));
                }
            });
            
            const csvContent = csv.join('\n');
            const blob = new Blob([csvContent], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'teachers_' + new Date().toISOString().split('T')[0] + '.csv';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
        }
    </script>
</body>
</html>