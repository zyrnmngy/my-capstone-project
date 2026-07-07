<?php
require_once 'config.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header('Location: index.php');
    exit;
}

$message = '';
$message_type = '';

// Handle actions
if ($_POST) {
    if (isset($_POST['action']) && isset($_POST['student_id'])) {
        $student_id = (int)$_POST['student_id'];
        $action = $_POST['action'];
        
        try {
            if ($action === 'delete') {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare("DELETE FROM students WHERE user_id = ?");
                $stmt->execute([$student_id]);
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$student_id]);
                $pdo->commit();
                $message = "Student account deleted successfully!";
                $message_type = "danger";
            } elseif ($action === 'reset_password') {
                $new_password = 'temp123'; // Default temporary password
                $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password_hash = ?, temp_password = 1 WHERE id = ?");
                $stmt->execute([$password_hash, $student_id]);
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

// Get search and filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$section_filter = isset($_GET['section']) ? trim($_GET['section']) : '';
$professor_filter = isset($_GET['professor']) ? trim($_GET['professor']) : '';

// Get students
try {
    $sql = "
        SELECT u.id, u.full_name, u.username, u.id_number, u.created_at,
               s.section, s.year_level, s.rizal_professor,
               gp.score, gp.current_stage, gp.progress_percentage, gp.last_played
        FROM users u 
        JOIN students s ON u.id = s.user_id 
        LEFT JOIN players p ON u.id = p.user_id
        LEFT JOIN game_progress gp ON p.id = gp.player_id
        WHERE u.user_type = 'student'
    ";
    
    $params = [];
    
    // Add search conditions
    if ($search) {
        $sql .= " AND (u.full_name LIKE ? OR u.username LIKE ? OR u.id_number LIKE ?)";
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
    }
    
    if ($section_filter) {
        $sql .= " AND s.section LIKE ?";
        $params[] = "%$section_filter%";
    }
    
    if ($professor_filter) {
        $sql .= " AND s.rizal_professor LIKE ?";
        $params[] = "%$professor_filter%";
    }
    
    $sql .= " ORDER BY u.full_name ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $students = $stmt->fetchAll();
    
    // Get unique sections and professors for filters
    $stmt = $pdo->query("SELECT DISTINCT section FROM students ORDER BY section");
    $sections = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $stmt = $pdo->query("SELECT DISTINCT rizal_professor FROM students ORDER BY rizal_professor");
    $professors = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Students - Filibustero Admin</title>
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

        .action-buttons .btn {
            padding: 0.4rem 0.8rem;
            margin-right: 0.5rem;
            font-size: 0.8rem;
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

        .text-center {
            text-align: center;
        }

        .text-muted {
            color: #a39582 !important;
        }

        .alert {
            padding: 1rem 1.5rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            border: 1px solid;
            font-weight: 500;
        }

        .alert-danger {
            background: #fdf5f3;
            color: #b36f59;
            border-color: #e8c9bf;
        }

        .alert-info {
            background: #f7f4ed;
            color: #8b764d;
            border-color: #ddd3bd;
        }

        .alert-dismissible .btn-close {
            background: none;
            border: none;
            color: #7a6f5d;
            opacity: 0.8;
            cursor: pointer;
            font-size: 1.2rem;
            padding: 0.25rem;
        }

        .alert-dismissible .btn-close:hover {
            opacity: 1;
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
            max-width: 600px;
            width: 90%;
        }

        .modal-content {
            background: #fffcf7;
            border: 1px solid #e8dfd0;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(139, 107, 66, 0.15);
            color: #4a4235;
        }

        .modal-header {
            background: #faf6ef;
            border-bottom: 1px solid #e8dfd0;
            padding: 1.5rem 2rem;
            border-radius: 11px 11px 0 0;
        }

        .modal-title {
            margin: 0;
            font-weight: 600;
            font-size: 1.2rem;
            color: #3d3529;
        }

        .btn-close {
            background: none;
            border: none;
            color: #7a6f5d;
            font-size: 1.5rem;
            cursor: pointer;
            padding: 0;
            opacity: 0.8;
            transition: opacity 0.2s ease;
        }

        .btn-close:hover {
            opacity: 1;
        }

        .modal-body {
            padding: 2rem;
            font-size: 1rem;
            line-height: 1.6;
        }

        .modal-footer {
            padding: 1.5rem 2rem;
            border-top: 1px solid #e8dfd0;
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

        .alert-warning {
            background: #fef9f0;
            color: #9c7a4d;
            border-color: #f0dfb8;
        }

        small {
            font-size: 0.85em;
            opacity: 0.9;
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

            .table-responsive {
                font-size: 0.8rem;
            }

            .table th, .table td {
                padding: 0.5rem;
            }
        }

        .fade {
            opacity: 0;
            transition: opacity 0.15s linear;
        }

        .fade.show {
            opacity: 1;
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
                <li><a href="manage-teachers.php"><i class="fas fa-chalkboard-teacher"></i> <span class="menu-text">Manage Teachers</span></a></li>
                <li><a href="manage-students.php" class="active"><i class="fas fa-user-graduate"></i> <span class="menu-text">Manage Students</span></a></li>
                <li><a href="all-users.php"><i class="fas fa-users"></i> <span class="menu-text">All Users</span></a></li>
                <li><a href="index.php?logout=1"><i class="fas fa-sign-out-alt"></i> <span class="menu-text">Logout</span></a></li>
            </ul>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <div class="header">
                <h4><i class="fas fa-user-graduate"></i> Manage Students</h4>
                <div class="header-actions">
                    <span class="welcome-text">Welcome, Admin</span>
                    <div class="dropdown">
                        <button type="button" class="dropdown-toggle">
                            <i class="fas fa-user-circle"></i>
                        </button>
                    </div>
                </div>
            </div>

            <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" onclick="this.parentElement.style.display='none'">&times;</button>
            </div>
            <?php endif; ?>

            <!-- Filter Section -->
            <div class="filter-section">
                <form method="GET" class="row">
                    <div class="col-md-4">
                        <input type="text" class="form-control" name="search" placeholder="Search students..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="col-md-3">
                        <select name="section" class="form-select">
                            <option value="">All Sections</option>
                            <?php foreach ($sections as $section): ?>
                                <option value="<?php echo htmlspecialchars($section); ?>" <?php echo $section_filter === $section ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($section); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select name="professor" class="form-select">
                            <option value="">All Professors</option>
                            <?php foreach ($professors as $professor): ?>
                                <option value="<?php echo htmlspecialchars($professor); ?>" <?php echo $professor_filter === $professor ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($professor); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-primary w-100" type="submit">
                            <i class="fas fa-search"></i> Filter
                        </button>
                    </div>
                    <?php if ($search || $section_filter || $professor_filter): ?>
                    <div class="col-12">
                        <a href="manage-students.php" class="btn btn-outline-secondary">
                            <i class="fas fa-times"></i> Clear All Filters
                        </a>
                    </div>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Students Table -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-user-graduate"></i> Students Registry (<?php echo count($students); ?>)
                </div>
                <div class="card-body">
                    <?php if (empty($students)): ?>
                        <div class="text-center text-muted">
                            <i class="fas fa-user-graduate fa-3x" style="margin-bottom: 1rem; display: block; opacity: 0.3;"></i>
                            <p>No students found<?php echo $search || $section_filter || $professor_filter ? ' matching your filters' : ''; ?>.</p>
                        </div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th><i class="fas fa-hashtag"></i> ID</th>
                                    <th><i class="fas fa-user"></i> Full Name</th>
                                    <th><i class="fas fa-at"></i> Username</th>
                                    <th><i class="fas fa-users"></i> Section</th>
                                    <th><i class="fas fa-chalkboard-teacher"></i> Professor</th>
                                    <th><i class="fas fa-chart-line"></i> Game Progress</th>
                                    <th><i class="fas fa-trophy"></i> Score</th>
                                    <th><i class="fas fa-clock"></i> Last Active</th>
                                    <th><i class="fas fa-cogs"></i> Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($students as $student): ?>
                                <tr>
                                    <td><?php echo $student['id']; ?></td>
                                    <td><?php echo htmlspecialchars($student['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($student['username']); ?></td>
                                    <td><small><?php echo htmlspecialchars($student['section']); ?></small></td>
                                    <td><small><?php echo htmlspecialchars($student['rizal_professor']); ?></small></td>
                                    <td>
                                        <?php if ($student['progress_percentage'] !== null): ?>
                                            <div class="progress-mini">
                                                <div class="progress-bar" style="width: <?php echo $student['progress_percentage']; ?>%"></div>
                                            </div>
                                            <small><?php echo number_format($student['progress_percentage'], 1); ?>%</small>
                                        <?php else: ?>
                                            <small class="text-muted">Not started</small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo $student['score'] ?? 0; ?></td>
                                    <td>
                                        <?php if ($student['last_played']): ?>
                                            <small><?php echo date('M d, Y', strtotime($student['last_played'])); ?></small>
                                        <?php else: ?>
                                            <small class="text-muted">Never</small>
                                        <?php endif; ?>
                                    </td>
                                    <td class="action-buttons">
                                        <button class="btn btn-sm btn-primary" onclick="viewStudent(<?php echo $student['id']; ?>)" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="btn btn-sm btn-danger" onclick="confirmDelete(<?php echo $student['id']; ?>, '<?php echo htmlspecialchars($student['full_name']); ?>')" title="Delete">
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
                        <input type="hidden" name="student_id" id="confirmStudentId">
                        <input type="hidden" name="action" id="confirmAction">
                        <button type="submit" class="btn" id="confirmBtn">Confirm</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        function confirmDelete(studentId, studentName) {
            document.getElementById('modalTitle').textContent = 'Delete Student';
            document.getElementById('modalBody').innerHTML = `Are you sure you want to delete <strong>${studentName}</strong>? This action cannot be undone.`;
            document.getElementById('confirmStudentId').value = studentId;
            document.getElementById('confirmAction').value = 'delete';
            document.getElementById('confirmBtn').textContent = 'Delete';
            document.getElementById('confirmBtn').className = 'btn btn-danger';
            
            showModal();
        }

        function resetPassword(studentId, studentName) {
            document.getElementById('modalTitle').textContent = 'Reset Password';
            document.getElementById('modalBody').innerHTML = `Are you sure you want to reset the password for <strong>${studentName}</strong>?`;
            document.getElementById('confirmStudentId').value = studentId;
            document.getElementById('confirmAction').value = 'reset_password';
            document.getElementById('confirmBtn').textContent = 'Reset';
            document.getElementById('confirmBtn').className = 'btn btn-info';
            
            showModal();
        }

        function viewStudent(studentId) {
            // Implement detailed view
            window.location.href = `view-student.php?id=${studentId}`;
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

        // Add hover effects and interactions
        document.addEventListener('DOMContentLoaded', function() {
            // Animate cards on scroll
            const observerOptions = {
                threshold: 0.1,
                rootMargin: '0px 0px -50px 0px'
            };

            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.opacity = '1';
                        entry.target.style.transform = 'translateY(0)';
                    }
                });
            }, observerOptions);

            document.querySelectorAll('.card, .filter-section').forEach(element => {
                element.style.opacity = '0';
                element.style.transform = 'translateY(30px)';
                element.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
                observer.observe(element);
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

            // Enhanced table row hover effects
            document.querySelectorAll('.table tr').forEach(row => {
                row.addEventListener('mouseenter', function() {
                    this.style.transform = 'scale(1.01)';
                    this.style.zIndex = '10';
                });
                
                row.addEventListener('mouseleave', function() {
                    this.style.transform = 'scale(1)';
                    this.style.zIndex = 'auto';
                });
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
    </script>
</body>
</html>