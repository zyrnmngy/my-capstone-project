<?php
require_once 'config.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header('Location: index.php');
    exit;
}

$message = '';
$message_type = '';

// Handle teacher approval/rejection
if ($_POST) {
    if (isset($_POST['action']) && isset($_POST['teacher_id'])) {
        $teacher_id = (int)$_POST['teacher_id'];
        $action = $_POST['action'];
        
        try {
            if ($action === 'approve') {
                $stmt = $pdo->prepare("UPDATE teachers SET is_active = 1 WHERE user_id = ?");
                $stmt->execute([$teacher_id]);
                $message = "Teacher approved successfully!";
                $message_type = "success";
            } elseif ($action === 'reject') {
                // Delete from teachers table and users table
                $pdo->beginTransaction();
                $stmt = $pdo->prepare("DELETE FROM teachers WHERE user_id = ?");
                $stmt->execute([$teacher_id]);
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$teacher_id]);
                $pdo->commit();
                $message = "Teacher request rejected and account deleted!";
                $message_type = "warning";
            }
        } catch (PDOException $e) {
            $pdo->rollBack();
            $message = "Error: " . $e->getMessage();
            $message_type = "danger";
        }
    }
}

// Get pending teacher requests
try {
    $stmt = $pdo->prepare("
        SELECT u.id, u.full_name, u.username, u.id_number, u.created_at, t.is_active
        FROM users u 
        JOIN teachers t ON u.id = t.user_id 
        WHERE t.is_active = 0 AND u.user_type = 'teacher'
        ORDER BY u.created_at DESC
    ");
    $stmt->execute();
    $pending_teachers = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Requests - Filibustero Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #faf8f5 0%, #f5f1eb 100%);
            color: #4a4235;
            min-height: 100vh;
        }

        .background-decoration {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: 
                radial-gradient(circle at 10% 20%, rgba(218, 165, 85, 0.04) 0%, transparent 50%),
                radial-gradient(circle at 90% 80%, rgba(184, 134, 58, 0.03) 0%, transparent 50%);
            pointer-events: none;
            z-index: -1;
        }

        .dashboard-container {
            display: flex;
            min-height: 100vh;
        }
        
        .sidebar {
            width: 280px;
            background: #fffcf7;
            border-right: 1px solid #e8dfd0;
            color: #5a5043;
            padding: 0;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            transition: all 0.3s;
            z-index: 1000;
            box-shadow: 0 0 20px rgba(139, 107, 66, 0.08);
        }
        
        .sidebar-header {
            padding: 2rem 1.5rem;
            border-bottom: 1px solid #e8dfd0;
            text-align: center;
            background: #faf6ef;
        }
        
        .sidebar-header .logo {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #d4a259 0%, #b8863a 100%);
            border-radius: 50%;
            margin: 0 auto 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fffcf7;
            font-size: 1.5rem;
            font-weight: 700;
            box-shadow: 0 4px 12px rgba(212, 162, 89, 0.3);
        }
        
        .sidebar-header h3 {
            margin: 0;
            font-weight: 700;
            font-size: 1.3rem;
            color: #3d3529;
            letter-spacing: -0.5px;
        }
        
        .sidebar-header .subtitle {
            color: #7a6f5d;
            font-size: 0.9rem;
            margin-top: 0.3rem;
        }
        
        .sidebar-menu {
            list-style: none;
            padding: 1rem 0;
            margin: 0;
        }
        
        .sidebar-menu li {
            margin: 0.3rem 0;
        }
        
        .sidebar-menu a {
            color: #5a5043;
            padding: 0.9rem 1.5rem;
            display: block;
            text-decoration: none;
            transition: all 0.2s ease;
            border-left: 4px solid transparent;
            position: relative;
            font-weight: 500;
            font-size: 0.95rem;
        }
        
        .sidebar-menu a:hover, .sidebar-menu a.active {
            background: #fef9f0;
            border-left: 4px solid #d4a259;
            color: #b8863a;
        }
        
        .sidebar-menu i {
            margin-right: 12px;
            width: 20px;
            text-align: center;
            font-size: 1.1rem;
        }

        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 2rem;
            min-height: 100vh;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.5rem 2rem;
            background: #fffcf7;
            border: 1px solid #e8dfd0;
            box-shadow: 0 4px 12px rgba(139, 107, 66, 0.08);
            margin-bottom: 2rem;
            border-radius: 12px;
        }

        .header h4 {
            font-size: 1.5rem;
            font-weight: 700;
            color: #3d3529;
            margin: 0;
            letter-spacing: -0.5px;
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .welcome-text {
            color: #7a6f5d;
            font-weight: 500;
        }

        .dropdown {
            position: relative;
            display: inline-block;
        }

        .dropdown-toggle {
            background: #faf6ef;
            color: #5a5043;
            border: 1.5px solid #e8dfd0;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.2s ease;
            text-decoration: none;
        }

        .dropdown-toggle:hover {
            background: #d4a259;
            color: #fffcf7;
            border-color: #d4a259;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(212, 162, 89, 0.3);
        }
        
        .card {
            background: #fffcf7;
            border: 1px solid #e8dfd0;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(139, 107, 66, 0.08);
            margin-bottom: 2rem;
            overflow: hidden;
        }
        
        .card-header {
            background: #faf6ef;
            border-bottom: 1px solid #e8dfd0;
            font-weight: 600;
            font-size: 1.1rem;
            color: #3d3529;
            padding: 1.5rem 2rem;
        }
        
        .card-body {
            padding: 2rem;
        }
        
        .filter-section {
            background: #fffcf7;
            border: 1px solid #e8dfd0;
            border-radius: 12px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 12px rgba(139, 107, 66, 0.08);
        }

        .row {
            display: flex;
            flex-wrap: wrap;
            margin: -0.75rem;
        }

        .col-md-6, .col-md-4, .col-md-2, .col-12 {
            padding: 0.75rem;
        }

        .col-md-6 { flex: 0 0 50%; max-width: 50%; }
        .col-md-4 { flex: 0 0 33.33%; max-width: 33.33%; }
        .col-md-2 { flex: 0 0 16.67%; max-width: 16.67%; }
        .col-12 { flex: 0 0 100%; max-width: 100%; }

        .form-control, .form-select {
            width: 100%;
            padding: 0.8rem 1rem;
            background: #fffcf7;
            border: 1.5px solid #e8dfd0;
            border-radius: 8px;
            color: #4a4235;
            font-family: 'Inter', sans-serif;
            font-size: 1rem;
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
            padding: 0.8rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            font-size: 0.9rem;
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
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid #e8dfd0;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
            margin: 0;
            background: #fffcf7;
        }

        .table th {
            background: #f5f0e6;
            color: #5a5043;
            border: none;
            padding: 1rem;
            font-weight: 600;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .table td {
            padding: 1rem;
            border-bottom: 1px solid #e8dfd0;
            color: #5a5043;
            vertical-align: middle;
        }

        .table tr:hover {
            background: #faf6ef;
        }

        .table tr:last-child td {
            border-bottom: none;
        }

        .action-buttons {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .action-buttons .btn {
            padding: 0.5rem 1rem;
            font-size: 0.9rem;
        }

        .btn-success {
            background: linear-gradient(145deg, #a38954, #8b7548);
            color: #fffcf7;
            border-color: rgba(163, 137, 84, 0.3);
        }

        .btn-success:hover {
            background: linear-gradient(145deg, #b89a5e, #a38954);
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(163, 137, 84, 0.3);
        }

        .btn-danger {
            background: linear-gradient(145deg, #c9856d, #b36f59);
            color: #fffcf7;
            border-color: rgba(201, 133, 109, 0.3);
        }

        .btn-danger:hover {
            background: linear-gradient(145deg, #d89580, #c9856d);
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(201, 133, 109, 0.3);
        }

        .btn-info {
            background: linear-gradient(145deg, #8b9da8, #748690);
            color: #fffcf7;
            border-color: rgba(139, 157, 168, 0.3);
        }

        .btn-info:hover {
            background: linear-gradient(145deg, #9badb8, #8b9da8);
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(139, 157, 168, 0.3);
        }

        .btn-secondary {
            background: linear-gradient(145deg, #8b8071, #6f6759);
            color: #fffcf7;
            border-color: rgba(139, 128, 113, 0.3);
        }

        .btn-secondary:hover {
            background: linear-gradient(145deg, #9d9183, #8b8071);
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(139, 128, 113, 0.3);
        }

        .btn-warning {
            background: linear-gradient(135deg, #e8b04b 0%, #d19a3a 100%);
            color: #fffcf7;
        }

        .btn-warning:hover {
            background: linear-gradient(135deg, #d19a3a 0%, #b8863a 100%);
            transform: translateY(-1px);
        }

        .text-center {
            text-align: center;
        }

        .text-muted {
            color: #a39582 !important;
        }

        .alert {
            padding: 1rem 1.5rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            border: 2px solid;
            position: relative;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .alert-success {
            background: linear-gradient(135deg, rgba(163, 137, 84, 0.2), rgba(139, 117, 72, 0.1));
            color: #8b7548;
            border-color: rgba(163, 137, 84, 0.3);
        }

        .alert-warning {
            background: linear-gradient(135deg, rgba(232, 176, 75, 0.2), rgba(209, 154, 58, 0.1));
            color: #d19a3a;
            border-color: rgba(232, 176, 75, 0.3);
        }

        .alert-danger {
            background: linear-gradient(135deg, rgba(201, 133, 109, 0.2), rgba(179, 111, 89, 0.1));
            color: #b36f59;
            border-color: rgba(201, 133, 109, 0.3);
        }

        .alert-info {
            background: #f7f4ed;
            color: #8b764d;
            border-color: #ddd3bd;
        }

        .alert-dismissible {
            padding-right: 4rem;
        }

        .btn-close {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: inherit;
            font-size: 1.2rem;
            cursor: pointer;
            opacity: 0.7;
            transition: opacity 0.3s;
        }

        .btn-close:hover {
            opacity: 1;
        }

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: #a39582;
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1.5rem;
            opacity: 0.5;
            display: block;
        }

        .empty-state p {
            font-size: 1.2rem;
            margin: 0;
        }

        .badge {
            padding: 0.35em 0.65em;
            border-radius: 6px;
            font-size: 0.75em;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .badge-student {
            background: #e8dfd0;
            color: #5a4d3a;
        }

        .badge-teacher {
            background: #f0e8d8;
            color: #7a6f5d;
        }

         .badge-admin {
            background: #fdf5f3;
            color: #9d5c48;
        }

        .badge-active {
            background: #f0e8d8;
            color: #7a6f5d;
        }

        .badge-pending {
            background: #fef9f0;
            color: #9c7a4d;
        }

        .badge-temp {
            background: #f7f4ed;
            color: #8b764d;
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
            background: #fffcf7;
            border: 1px solid #e8dfd0;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(139, 107, 66, 0.15);
            max-width: 500px;
            width: 90%;
            max-height: 90vh;
            overflow: hidden;
        }

        .modal-header {
            background: #faf6ef;
            border-bottom: 1px solid #e8dfd0;
            padding: 1.5rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-radius: 11px 11px 0 0;
        }

        .modal-title {
            margin: 0;
            font-weight: 600;
            font-size: 1.2rem;
            color: #3d3529;
        }

        .modal-body {
            padding: 2rem;
            color: #4a4235;
            line-height: 1.6;
        }

        .modal-footer {
            padding: 1.5rem 2rem;
            border-top: 1px solid #e8dfd0;
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
        }

        .password-hash {
            font-family: 'Courier New', monospace;
            background: #faf6ef;
            border: 1px solid #e8dfd0;
            padding: 1rem;
            border-radius: 8px;
            word-break: break-all;
            color: #b8863a;
            font-weight: 600;
            margin-top: 0.5rem;
        }

        small {
            font-size: 0.85em;
            opacity: 0.9;
        }

        .spinner-border {
            width: 2rem;
            height: 2rem;
            border: 3px solid rgba(212, 162, 89, 0.3);
            border-top-color: #d4a259;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .visually-hidden {
            position: absolute !important;
            width: 1px !important;
            height: 1px !important;
            padding: 0 !important;
            margin: -1px !important;
            overflow: hidden !important;
            clip: rect(0, 0, 0, 0) !important;
            white-space: nowrap !important;
            border: 0 !important;
        }

        .fade {
            opacity: 0;
            transition: opacity 0.15s linear;
        }

        .fade.show {
            opacity: 1;
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 80px;
            }
            
            .sidebar .menu-text {
                display: none;
            }
            
            .sidebar-header h3,
            .sidebar-header .subtitle {
                font-size: 0;
            }

            .sidebar-header .logo {
                font-size: 1.2rem;
                width: 40px;
                height: 40px;
            }
            
            .sidebar-menu i {
                margin-right: 0;
                font-size: 1.2rem;
            }
            
            .main-content {
                margin-left: 80px;
                padding: 1rem;
            }

            .header {
                padding: 1rem;
                flex-direction: column;
                gap: 1rem;
            }

            .header-actions {
                width: 100%;
                justify-content: space-between;
            }

            .row {
                flex-direction: column;
            }

            .col-md-6, .col-md-4, .col-md-2 {
                flex: none;
                max-width: 100%;
                margin-bottom: 1rem;
            }

            .action-buttons {
                flex-direction: column;
            }

            .table-responsive {
                overflow-x: auto;
                font-size: 0.8rem;
            }

            .table th, .table td {
                padding: 0.5rem;
            }
        }

        @media (max-width: 480px) {
            .card-body {
                padding: 1rem;
            }

            .table th,
            .table td {
                padding: 0.5rem;
                font-size: 0.9rem;
            }

            .btn {
                padding: 0.4rem 0.8rem;
                font-size: 0.8rem;
            }
        }
    </style>
</head>
<body>
    <div class="background-texture"></div>
    
    <div class="dashboard-container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header">
                <div class="logo">🏛️</div>
                <h3><span class="menu-text">Filibustero Admin</span></h3>
                <div class="subtitle"><span class="menu-text">Management Portal</span></div>
            </div>
            <ul class="sidebar-menu">
                <li><a href="index.php"><i class="fas fa-tachometer-alt"></i> <span class="menu-text">Dashboard</span></a></li>
                <li><a href="teacher-requests.php" class="active"><i class="fas fa-user-clock"></i> <span class="menu-text">Teacher Requests</span></a></li>
                <li><a href="manage-teachers.php"><i class="fas fa-chalkboard-teacher"></i> <span class="menu-text">Manage Teachers</span></a></li>
                <li><a href="manage-students.php"><i class="fas fa-user-graduate"></i> <span class="menu-text">Manage Students</span></a></li>
                <li><a href="all-users.php"><i class="fas fa-users"></i> <span class="menu-text">All Users</span></a></li>
                <li><a href="index.php?logout=1"><i class="fas fa-sign-out-alt"></i> <span class="menu-text">Logout</span></a></li>
            </ul>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <div class="header">
                <h4>Teacher Requests</h4>
                <div class="header-actions">
                    <span class="welcome-text">Welcome, Admin</span>
                    <div class="dropdown">
                        <button class="dropdown-toggle" onclick="toggleDropdown()">
                            <i class="fas fa-user-circle"></i>
                        </button>
                        <div class="dropdown-menu" id="dropdownMenu">
                            <div class="dropdown-divider"></div>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible" role="alert">
                <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : ($message_type === 'warning' ? 'exclamation-triangle' : 'exclamation-circle'); ?>"></i>
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" onclick="this.parentElement.style.display='none'">&times;</button>
            </div>
            <?php endif; ?>

            <!-- Teacher Requests Section -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-user-clock"></i> Pending Teacher Requests
                </div>
                <div class="card-body">
                    <?php if (empty($pending_teachers)): ?>
                        <div class="empty-state">
                            <i class="fas fa-inbox"></i>
                            <p>No pending teacher requests at the moment.</p>
                        </div>
                    <?php else: ?>
                    <div class="table-container">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th><i class="fas fa-hashtag"></i> ID</th>
                                    <th><i class="fas fa-user"></i> Full Name</th>
                                    <th><i class="fas fa-at"></i> Username</th>
                                    <th><i class="fas fa-id-card"></i> ID Number</th>
                                    <th><i class="fas fa-calendar"></i> Date Requested</th>
                                    <th><i class="fas fa-cogs"></i> Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pending_teachers as $teacher): ?>
                                <tr>
                                    <td><?php echo $teacher['id']; ?></td>
                                    <td><?php echo htmlspecialchars($teacher['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($teacher['username']); ?></td>
                                    <td><?php echo htmlspecialchars($teacher['id_number']); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($teacher['created_at'])); ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="btn btn-success" onclick="approveTeacher(<?php echo $teacher['id']; ?>, '<?php echo htmlspecialchars($teacher['full_name']); ?>')" title="Approve Teacher">
                                                <i class="fas fa-check"></i> Approve
                                            </button>
                                            <button class="btn btn-danger" onclick="rejectTeacher(<?php echo $teacher['id']; ?>, '<?php echo htmlspecialchars($teacher['full_name']); ?>')" title="Reject Teacher">
                                                <i class="fas fa-times"></i> Reject
                                            </button>
                                            <button class="btn btn-info" onclick="viewTeacher(<?php echo $teacher['id']; ?>)" title="View Details">
                                                <i class="fas fa-eye"></i> View
                                            </button>
                                        </div>
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

    <!-- Confirmation Modal -->
    <div class="modal" id="confirmationModal">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Confirm Action</h5>
                    <button type="button" class="btn-close" onclick="closeModal('confirmationModal')">&times;</button>
                </div>
                <div class="modal-body" id="modalBody">
                    Are you sure you want to perform this action?
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('confirmationModal')">Cancel</button>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="teacher_id" id="confirmTeacherId">
                        <input type="hidden" name="action" id="confirmAction">
                        <button type="submit" class="btn" id="confirmBtn">Confirm</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- View Teacher Modal -->
    <div class="modal" id="viewTeacherModal">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Teacher Details</h5>
                    <button type="button" class="btn-close" onclick="closeModal('viewTeacherModal')">&times;</button>
                </div>
                <div class="modal-body" id="teacherDetails">
                    <div style="text-align: center;">
                        <div class="spinner-border" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('viewTeacherModal')">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function showModal(modalId) {
            document.getElementById(modalId).classList.add('show');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('show');
        }

        function approveTeacher(teacherId, teacherName) {
            document.getElementById('modalTitle').textContent = 'Approve Teacher';
            document.getElementById('modalBody').innerHTML = `Are you sure you want to approve <strong>${teacherName}</strong> as a teacher?`;
            document.getElementById('confirmTeacherId').value = teacherId;
            document.getElementById('confirmAction').value = 'approve';
            document.getElementById('confirmBtn').textContent = 'Approve';
            document.getElementById('confirmBtn').className = 'btn btn-danger';
            
            showModal('confirmationModal');
        }

        function viewTeacher(teacherId) {
            showModal('viewTeacherModal');
            
            // Fetch teacher details via AJAX
            fetch('get-teacher-details.php?id=' + teacherId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('teacherDetails').innerHTML = `
                            <div style="margin-bottom: 1rem;"><strong>Full Name:</strong> ${data.teacher.full_name}</div>
                            <div style="margin-bottom: 1rem;"><strong>Username:</strong> ${data.teacher.username}</div>
                            <div style="margin-bottom: 1rem;"><strong>ID Number:</strong> ${data.teacher.id_number}</div>
                            <div style="margin-bottom: 1rem;"><strong>User Type:</strong> ${data.teacher.user_type}</div>
                            <div style="margin-bottom: 1rem;"><strong>Date Registered:</strong> ${new Date(data.teacher.created_at).toLocaleDateString()}</div>
                            <div style="margin-bottom: 1rem;"><strong>Status:</strong> <span style="background: linear-gradient(145deg, #ffc107, #e6ac00); color: #1a1a1a; padding: 0.3rem 0.6rem; border-radius: 6px; font-size: 0.8rem; font-weight: 600; text-transform: uppercase;">Pending Approval</span></div>
                        `;
                    } else {
                        document.getElementById('teacherDetails').innerHTML = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> Error loading teacher details.</div>';
                    }
                })
                .catch(error => {
                    document.getElementById('teacherDetails').innerHTML = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> Error loading teacher details.</div>';
                });
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                if (event.target === modal) {
                    modal.classList.remove('show');
                }
            });
        }

        // Add ripple effect to buttons
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.btn').forEach(button => {
                button.addEventListener('click', function(e) {
                    const ripple = document.createElement('span');
                    const rect = this.getBoundingClientRect();
                    const size = Math.max(rect.width, rect.height);
                    const x = e.clientX - rect.left - size / 2;
                    const y = e.clientY - rect.top - size / 2;
                    
                    ripple.style.cssText = `
                        position: absolute;
                        border-radius: 50%;
                        background: rgba(255, 255, 255, 0.6);
                        transform: scale(0);
                        animation: ripple 0.6s linear;
                        width: ${size}px;
                        height: ${size}px;
                        left: ${x}px;
                        top: ${y}px;
                        pointer-events: none;
                    `;
                    
                    this.style.position = 'relative';
                    this.style.overflow = 'hidden';
                    this.appendChild(ripple);
                    
                    setTimeout(() => {
                        ripple.remove();
                    }, 600);
                });
            });

            // Add CSS for ripple animation
            const style = document.createElement('style');
            style.textContent = `
                @keyframes ripple {
                    to {
                        transform: scale(4);
                        opacity: 0;
                    }
                }
            `;
            document.head.appendChild(style);

            // Auto-dismiss alerts after 5 seconds
            const alerts = document.querySelectorAll('.alert-dismissible');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.opacity = '0';
                    alert.style.transform = 'translateY(-20px)';
                    setTimeout(() => {
                        alert.style.display = 'none';
                    }, 300);
                }, 5000);
            });
        });
    </script>
</body>
</html>