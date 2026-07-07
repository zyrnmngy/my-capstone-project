<?php
require_once 'config.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header('Location: index.php');
    exit;
}

// Get teacher ID
$teacher_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$teacher_id) {
    header('Location: manage-teachers.php');
    exit;
}

$message = '';
$message_type = '';

// Handle section assignment
if ($_POST && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    try {
        if ($action === 'assign_section') {
            $section = trim($_POST['section']);
            if (!empty($section)) {
                // Check if already assigned
                $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM teacher_sections WHERE teacher_id = ? AND section = ?");
                $check_stmt->execute([$teacher_id, $section]);
                
                if ($check_stmt->fetchColumn() == 0) {
                    $stmt = $pdo->prepare("INSERT INTO teacher_sections (teacher_id, section) VALUES (?, ?)");
                    $stmt->execute([$teacher_id, $section]);
                    $message = "Section assigned successfully!";
                    $message_type = "success";
                } else {
                    $message = "Section already assigned to this teacher!";
                    $message_type = "warning";
                }
            }
        } elseif ($action === 'remove_section') {
            $section = $_POST['section'];
            $stmt = $pdo->prepare("DELETE FROM teacher_sections WHERE teacher_id = ? AND section = ?");
            $stmt->execute([$teacher_id, $section]);
            $message = "Section removed successfully!";
            $message_type = "success";
        }
    } catch (PDOException $e) {
        $message = "Error: " . $e->getMessage();
        $message_type = "danger";
    }
}

// Get teacher details - Fixed query to include email field
try {
    $stmt = $pdo->prepare("
        SELECT u.id, u.full_name, u.username, u.id_number, u.created_at, 
               t.is_active, u.temp_password
        FROM users u 
        JOIN teachers t ON u.id = t.user_id 
        WHERE u.id = ? AND u.user_type = 'teacher'
    ");
    $stmt->execute([$teacher_id]);
    $teacher = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$teacher) {
        header('Location: manage-teachers.php');
        exit;
    }
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
    // Set default values to prevent undefined index errors
    $teacher = [
        'id' => $teacher_id,
        'full_name' => 'N/A',
        'username' => 'N/A',
        'id_number' => 'N/A',
        'created_at' => date('Y-m-d H:i:s'),
        'is_active' => 0,
        'temp_password' => 0
    ];
}

// Get assigned sections
try {
    $stmt = $pdo->prepare("
        SELECT ts.section, COUNT(s.user_id) as student_count
        FROM teacher_sections ts
        LEFT JOIN students s ON ts.section = s.section
        WHERE ts.teacher_id = ?
        GROUP BY ts.section
        ORDER BY ts.section
    ");
    $stmt->execute([$teacher_id]);
    $assigned_sections = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $assigned_sections = [];
}

// Get students in assigned sections
try {
    $stmt = $pdo->prepare("
        SELECT u.id, u.full_name, u.id_number, s.section, s.year_level,
               COALESCE(gp.score, 0) as score,
               COALESCE(gp.current_stage, 1) as current_stage,
               COALESCE(gp.progress_percentage, 0) as progress_percentage,
               gp.last_played
        FROM users u
        JOIN students s ON u.id = s.user_id
        LEFT JOIN players p ON u.id = p.user_id
        LEFT JOIN game_progress gp ON p.id = gp.player_id
        WHERE s.section IN (
            SELECT section FROM teacher_sections WHERE teacher_id = ?
        )
        ORDER BY s.section, u.full_name
    ");
    $stmt->execute([$teacher_id]);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $students = [];
}

// Get available sections (sections not assigned to this teacher)
try {
    $stmt = $pdo->prepare("
        SELECT DISTINCT section 
        FROM students 
        WHERE section NOT IN (
            SELECT section FROM teacher_sections WHERE teacher_id = ?
        )
        ORDER BY section
    ");
    $stmt->execute([$teacher_id]);
    $available_sections = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $available_sections = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Teacher - <?php echo htmlspecialchars($teacher['full_name']); ?></title>
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

        .background-texture {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: 
                radial-gradient(circle at 10% 20%, rgba(218, 165, 85, 0.05) 0%, transparent 50%),
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
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
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
            font-size: 1.5rem;
            color: #3d3529;
            letter-spacing: -0.5px;
        }
        
        .sidebar-header .subtitle {
            color: #7a6f5d;
            font-size: 0.9rem;
            margin-top: 0.5rem;
        }
        
        .sidebar-menu {
            list-style: none;
            padding: 1rem 0;
            margin: 0;
        }
        
        .sidebar-menu li {
            margin: 0.5rem 0;
        }
        
        .sidebar-menu a {
            color: #5a5043;
            padding: 1rem 1.5rem;
            display: block;
            text-decoration: none;
            transition: all 0.3s ease;
            border-left: 4px solid transparent;
            position: relative;
            font-weight: 500;
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

        .header-left {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }

        .header h4 {
            font-size: 1.8rem;
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
            font-weight: 600;
        }

        .dropdown {
            position: relative;
            display: inline-block;
        }

        .dropdown-toggle {
            background: #faf6ef;
            border: 1.5px solid #e8dfd0;
            color: #5a5043;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .dropdown-toggle:hover {
            background: #d4a259;
            color: #fffcf7;
            border-color: #d4a259;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(212, 162, 89, 0.3);
        }

        .dropdown-menu {
            display: none;
            position: absolute;
            right: 0;
            top: 100%;
            background: #fffcf7;
            border: 1px solid #e8dfd0;
            border-radius: 8px;
            min-width: 150px;
            box-shadow: 0 8px 32px rgba(139, 107, 66, 0.15);
            z-index: 1000;
        }

        .dropdown-menu.show {
            display: block;
        }

        .dropdown-item {
            color: #5a5043;
            padding: 0.75rem 1rem;
            text-decoration: none;
            display: block;
            transition: all 0.3s ease;
        }

        .dropdown-item:hover {
            background: #fef9f0;
            color: #b8863a;
        }

        .dropdown-divider {
            border-top: 1px solid #e8dfd0;
            margin: 0.5rem 0;
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
            font-weight: 700;
            font-size: 1.2rem;
            color: #3d3529;
            padding: 1.5rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .card-body {
            padding: 2rem;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            font-size: 0.9rem;
            text-align: center;
        }

        .btn-primary {
            background: linear-gradient(145deg, #d4a259, #b8863a);
            color: #fffcf7;
            border: 1px solid #d4a259;
        }

        .btn-primary:hover {
            background: linear-gradient(145deg, #b8863a, #d4a259);
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(212, 162, 89, 0.3);
        }

        .btn-outline-secondary {
            background: transparent;
            color: #7a6f5d;
            border: 1.5px solid #e8dfd0;
        }

        .btn-outline-secondary:hover {
            background: #faf6ef;
            color: #5a5043;
            transform: translateY(-1px);
        }

        .btn-sm {
            padding: 0.4rem 0.8rem;
            font-size: 0.8rem;
        }

        .btn-success {
            background: linear-gradient(145deg, #a38954, #8b7548);
            color: #fffcf7;
            border: 1px solid #a38954;
        }

        .btn-success:hover {
            background: linear-gradient(145deg, #8b7548, #a38954);
            transform: translateY(-1px);
            box-shadow: 0 4px 15px rgba(163, 137, 84, 0.3);
        }

        .btn-outline-danger {
            background: transparent;
            color: #c9856d;
            border: 1.5px solid rgba(201, 133, 109, 0.3);
        }

        .btn-outline-danger:hover {
            background: rgba(201, 133, 109, 0.1);
            color: #b36f59;
            transform: translateY(-1px);
        }

        .btn-secondary {
            background: linear-gradient(145deg, #8b8071, #6f6759);
            color: #fffcf7;
            border: 1px solid #8b8071;
        }

        .btn-secondary:hover {
            background: linear-gradient(145deg, #6f6759, #8b8071);
            transform: translateY(-1px);
            box-shadow: 0 4px 15px rgba(139, 128, 113, 0.3);
        }

        .d-flex {
            display: flex;
        }

        .justify-content-between {
            justify-content: space-between;
        }

        .align-items-center {
            align-items: center;
        }

        .me-2 {
            margin-right: 0.5rem;
        }

        .me-3 {
            margin-right: 1rem;
        }

        .mb-2 {
            margin-bottom: 0.5rem;
        }

        .mb-3 {
            margin-bottom: 1rem;
        }

        .d-inline {
            display: inline;
        }

        .row {
            display: flex;
            flex-wrap: wrap;
            margin: -0.75rem;
        }

        .col-md-8 {
            flex: 0 0 66.666667%;
            max-width: 66.666667%;
            padding: 0.75rem;
        }

        .col-md-4 {
            flex: 0 0 33.333333%;
            max-width: 33.333333%;
            padding: 0.75rem;
        }

        .info-row {
            margin-bottom: 1rem;
            padding: 1rem;
            background: #faf6ef;
            border-radius: 8px;
            border: 1px solid #e8dfd0;
        }

        .info-label {
            font-weight: 600;
            color: #b8863a;
        }

        .badge {
            padding: 0.4rem 0.8rem;
            border-radius: 6px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: inline-block;
            margin: 0.2rem;
        }

        .bg-primary {
            background: #d4a259;
            color: #fffcf7;
        }

        .bg-success {
            background: #a38954;
            color: #fffcf7;
        }

        .bg-secondary {
            background: #8b8071;
            color: #fffcf7;
        }

        .table-container {
            background: #fffcf7;
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid #e8dfd0;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
            margin: 0;
        }

        .table th {
            background: #f5f0e6;
            color: #5a5043;
            border: none;
            padding: 1rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-size: 0.9rem;
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

        .progress-bar-container {
            width: 100px;
        }

        .progress {
            height: 20px;
            background: #f5f0e6;
            border-radius: 10px;
            overflow: hidden;
            border: 1px solid #e8dfd0;
        }

        .progress-bar {
            height: 100%;
            background: linear-gradient(145deg, #d4a259, #b8863a);
            color: #fffcf7;
            font-size: 0.8rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: width 0.3s ease;
        }

        .text-muted {
            color: #7a6f5d !important;
        }

        .text-center {
            text-align: center;
        }

        .fa-3x {
            font-size: 3rem;
        }

        .alert {
            padding: 1rem 1.5rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            border: 1px solid;
            display: flex;
            align-items: center;
            gap: 0.5rem;
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

        .alert-dismissible {
            position: relative;
        }

        .btn-close {
            background: none;
            border: none;
            color: inherit;
            font-size: 1.2rem;
            cursor: pointer;
            margin-left: auto;
            padding: 0;
            opacity: 0.7;
            transition: opacity 0.3s ease;
        }

        .btn-close:hover {
            opacity: 1;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1050;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(58, 47, 35, 0.5);
            backdrop-filter: blur(5px);
        }

        .modal.show {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-dialog {
            max-width: 500px;
            width: 90%;
            margin: 2rem;
        }

        .modal-content {
            background: #fffcf7;
            border: 1px solid #e8dfd0;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(139, 107, 66, 0.15);
            overflow: hidden;
        }

        .modal-header {
            background: #faf6ef;
            border-bottom: 1px solid #e8dfd0;
            padding: 1.5rem 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .modal-title {
            font-weight: 700;
            color: #3d3529;
            margin: 0;
            font-size: 1.3rem;
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

        .form-label {
            color: #3d3529;
            font-weight: 600;
            margin-bottom: 0.5rem;
            display: block;
        }

        .form-select {
            background: #fffcf7;
            border: 1.5px solid #e8dfd0;
            border-radius: 8px;
            color: #4a4235;
            padding: 0.75rem 1rem;
            font-size: 1rem;
            transition: all 0.3s ease;
            width: 100%;
        }

        .form-select:focus {
            outline: none;
            border-color: #d4a259;
            box-shadow: 0 0 0 3px rgba(212, 162, 89, 0.1);
        }

        .form-select option {
            background: #fffcf7;
            color: #4a4235;
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

            .col-md-8, .col-md-4 {
                flex: 0 0 100%;
                max-width: 100%;
            }
        }

        @media (max-width: 480px) {
            .card-body {
                padding: 1rem;
            }

            .table-responsive {
                overflow-x: auto;
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
                <li><a href="teacher-requests.php"><i class="fas fa-user-clock"></i> <span class="menu-text">Teacher Requests</span></a></li>
                <li><a href="manage-teachers.php" class="active"><i class="fas fa-chalkboard-teacher"></i> <span class="menu-text">Manage Teachers</span></a></li>
                <li><a href="manage-students.php"><i class="fas fa-user-graduate"></i> <span class="menu-text">Manage Students</span></a></li>
                <li><a href="all-users.php"><i class="fas fa-users"></i> <span class="menu-text">All Users</span></a></li>
                <li><a href="index.php?logout=1"><i class="fas fa-sign-out-alt"></i> <span class="menu-text">Logout</span></a></li>
            </ul>
        </div>

<!-- Main Content -->
        <div class="main-content">
            <div class="header">
                <div class="header-left">
                    <a href="manage-teachers.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Back to Teachers
                    </a>
                    <h4 class="d-inline">Teacher Details</h4>
                </div>
                <div class="header-actions">
                    <span class="welcome-text">Welcome, Admin</span>
                    <div class="dropdown">
                        <button class="dropdown-toggle" onclick="toggleDropdown()">
                            <i class="fas fa-user-circle"></i>
                        </button>
                        <div class="dropdown-menu" id="dropdownMenu">
                            <a href="#" class="dropdown-item"><i class="fas fa-user me-2"></i>Profile</a>
                            <div class="dropdown-divider"></div>
                            <a href="index.php?logout=1" class="dropdown-item"><i class="fas fa-sign-out-alt me-2"></i>Logout</a>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                <i class="fas fa-info-circle"></i>
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" onclick="this.parentElement.style.display='none'">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <?php endif; ?>

            <div class="row">
                <!-- Teacher Information - Fixed to handle missing array keys -->
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <i class="fas fa-user me-2"></i>Teacher Information
                        </div>
                        <div class="card-body">
                            <div class="info-row">
                                <div class="info-label">Full Name</div>
                                <div><?php echo htmlspecialchars($teacher['full_name'] ?? 'N/A'); ?></div>
                            </div>
                            <div class="info-row">
                                <div class="info-label">Username</div>
                                <div><?php echo htmlspecialchars($teacher['username'] ?? 'N/A'); ?></div>
                            </div>
                            <div class="info-row">
                                <div class="info-label">ID Number</div>
                                <div><?php echo htmlspecialchars($teacher['id_number'] ?? 'N/A'); ?></div>
                            </div>
                            <div class="info-row">
                                <div class="info-label">Status</div>
                                <div>
                                    <?php if (($teacher['is_active'] ?? 0) == 1): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Inactive</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="info-row">
                                <div class="info-label">Created</div>
                                <div><?php echo date('F j, Y', strtotime($teacher['created_at'] ?? 'now')); ?></div>
                            </div>
                            <?php if ($teacher['temp_password'] ?? false): ?>
                            <div class="info-row">
                                <div class="info-label">Password Status</div>
                                <div><span class="badge bg-warning">Temporary Password</span></div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Assigned Sections -->
                    <div class="card">
                        <div class="card-header">
                            <span><i class="fas fa-users me-2"></i>Assigned Sections</span>
                            <button class="btn btn-sm btn-primary" onclick="showModal()">
                                <i class="fas fa-plus"></i> Assign
                            </button>
                        </div>
                        <div class="card-body">
                            <?php if (empty($assigned_sections)): ?>
                                <p class="text-muted">No sections assigned</p>
                            <?php else: ?>
                                <?php foreach ($assigned_sections as $section): ?>
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span class="badge bg-primary">
                                            <?php echo htmlspecialchars($section['section'] ?? 'N/A'); ?>
                                            <small>(<?php echo $section['student_count'] ?? 0; ?> students)</small>
                                        </span>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="remove_section">
                                            <input type="hidden" name="section" value="<?php echo htmlspecialchars($section['section'] ?? ''); ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" 
                                                    onclick="return confirm('Remove this section assignment?')">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </form>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Students List -->
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <i class="fas fa-user-graduate me-2"></i>Students in Assigned Sections (<?php echo count($students); ?>)
                        </div>
                        <div class="card-body">
                            <?php if (empty($students)): ?>
                                <div class="text-center text-muted">
                                    <i class="fas fa-user-slash fa-3x mb-3"></i>
                                    <p>No students found in assigned sections.</p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <div class="table-container">
                                        <table class="table">
                                            <thead>
                                                <tr>
                                                    <th>Name</th>
                                                    <th>ID Number</th>
                                                    <th>Section</th>
                                                    <th>Year Level</th>
                                                    <th>Score</th>
                                                    <th>Stage</th>
                                                    <th>Progress</th>
                                                    <th>Last Played</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($students as $student): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($student['full_name'] ?? 'N/A'); ?></td>
                                                    <td><?php echo htmlspecialchars($student['id_number'] ?? 'N/A'); ?></td>
                                                    <td><span class="badge bg-secondary"><?php echo htmlspecialchars($student['section'] ?? 'N/A'); ?></span></td>
                                                    <td><?php echo htmlspecialchars($student['year_level'] ?? 'N/A'); ?></td>
                                                    <td><strong><?php echo number_format($student['score'] ?? 0); ?></strong></td>
                                                    <td>Stage <?php echo $student['current_stage'] ?? 1; ?></td>
                                                    <td>
                                                        <div class="progress-bar-container">
                                                            <div class="progress">
                                                                <div class="progress-bar" role="progressbar" 
                                                                     style="width: <?php echo $student['progress_percentage'] ?? 0; ?>%"
                                                                     aria-valuenow="<?php echo $student['progress_percentage'] ?? 0; ?>" 
                                                                     aria-valuemin="0" aria-valuemax="100">
                                                                    <?php echo round($student['progress_percentage'] ?? 0, 1); ?>%
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <?php if ($student['last_played'] ?? false): ?>
                                                            <?php echo date('M d, Y', strtotime($student['last_played'])); ?>
                                                        <?php else: ?>
                                                            <em class="text-muted">Never</em>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Assign Section Modal -->
    <div class="modal" id="assignSectionModal">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Assign Section</h5>
                    <button type="button" class="btn-close" onclick="closeModal()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="assign_section">
                        <div class="mb-3">
                            <label for="section" class="form-label">Select Section</label>
                            <?php if (empty($available_sections)): ?>
                                <p class="text-muted">All available sections are already assigned to this teacher.</p>
                            <?php else: ?>
                                <select class="form-select" id="section" name="section" required>
                                    <option value="">Choose a section...</option>
                                    <?php foreach ($available_sections as $section): ?>
                                        <option value="<?php echo htmlspecialchars($section); ?>">
                                            <?php echo htmlspecialchars($section); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                        <?php if (!empty($available_sections)): ?>
                            <button type="submit" class="btn btn-primary">Assign Section</button>
                        <?php endif; ?>
                    </div>
                </form>
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
        window.addEventListener('click', function(event) {
            if (!event.target.matches('.dropdown-toggle')) {
                const dropdowns = document.getElementsByClassName('dropdown-menu');
                for (let i = 0; i < dropdowns.length; i++) {
                    const openDropdown = dropdowns[i];
                    if (openDropdown.classList.contains('show')) {
                        openDropdown.classList.remove('show');
                    }
                }
            }
        });

        // Modal functionality
        function showModal() {
            document.getElementById('assignSectionModal').classList.add('show');
            document.body.style.overflow = 'hidden';
        }

        function closeModal() {
            document.getElementById('assignSectionModal').classList.remove('show');
            document.body.style.overflow = 'auto';
        }

        // Close modal when clicking outside of it
        document.getElementById('assignSectionModal').addEventListener('click', function(event) {
            if (event.target === this) {
                closeModal();
            }
        });

        // Add animations and effects on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Animate info rows
            const infoRows = document.querySelectorAll('.info-row');
            infoRows.forEach((row, index) => {
                row.style.opacity = '0';
                row.style.transform = 'translateX(-20px)';
                row.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                
                setTimeout(() => {
                    row.style.opacity = '1';
                    row.style.transform = 'translateX(0)';
                }, index * 100);
            });

            // Animate table rows
            const tableRows = document.querySelectorAll('tbody tr');
            tableRows.forEach((row, index) => {
                row.style.opacity = '0';
                row.style.transform = 'translateY(20px)';
                row.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                
                setTimeout(() => {
                    row.style.opacity = '1';
                    row.style.transform = 'translateY(0)';
                }, (index * 100) + 500);
            });

            // Animate progress bars
            const progressBars = document.querySelectorAll('.progress-bar');
            progressBars.forEach((bar, index) => {
                const width = bar.style.width;
                bar.style.width = '0%';
                
                setTimeout(() => {
                    bar.style.width = width;
                }, (index * 100) + 1000);
            });

            // Add ripple effect to buttons
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
        });

        // Add CSS for animations
        const style = document.createElement('style');
        style.textContent = `
            @keyframes ripple {
                to {
                    transform: scale(4);
                    opacity: 0;
                }
            }
            
            .fade {
                opacity: 0;
                transition: opacity 0.3s ease;
            }
            
            .fade.show {
                opacity: 1;
            }
            
            .table-responsive {
                overflow-x: auto;
            }

            /* Hover effects for cards */
            .card:hover {
                transform: translateY(-2px);
                box-shadow: 0 12px 40px rgba(0, 0, 0, 0.4);
            }

            /* Animated background for badges */
            .badge {
                background-size: 200% 200%;
                animation: gradientShift 3s ease infinite;
            }

            @keyframes gradientShift {
                0% { background-position: 0% 50%; }
                50% { background-position: 100% 50%; }
                100% { background-position: 0% 50%; }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>