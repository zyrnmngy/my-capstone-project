<?php
require_once 'config.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header('Location: index.php');
    exit;
}

$message = '';
$message_type = '';

// Get student ID from URL
$student_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$student_id) {
    header('Location: manage-students.php');
    exit;
}

// Handle actions
if ($_POST) {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        
        try {
            if ($action === 'reset_password') {
                $new_password = 'temp123'; // Default temporary password
                $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password_hash = ?, temp_password = 1 WHERE id = ?");
                $stmt->execute([$password_hash, $student_id]);
                $message = "Password reset successfully! New password: temp123";
                $message_type = "info";
            } elseif ($action === 'reset_progress') {
                $pdo->beginTransaction();
                // Get player ID
                $stmt = $pdo->prepare("SELECT id FROM players WHERE user_id = ?");
                $stmt->execute([$student_id]);
                $player = $stmt->fetch();
                
                if ($player) {
                    // Reset game progress
                    $stmt = $pdo->prepare("UPDATE game_progress SET score = 0, current_stage = 1, progress_percentage = 0, last_played = NULL WHERE player_id = ?");
                    $stmt->execute([$player['id']]);
                    
                    // Delete game sessions
                    $stmt = $pdo->prepare("DELETE FROM game_sessions WHERE player_id = ?");
                    $stmt->execute([$player['id']]);
                }
                $pdo->commit();
                $message = "Game progress reset successfully!";
                $message_type = "info";
            }
        } catch (PDOException $e) {
            $pdo->rollBack();
            $message = "Error: " . $e->getMessage();
            $message_type = "danger";
        }
    }
}

// Get student details
try {
    $sql = "
        SELECT u.id, u.full_name, u.username, u.id_number, u.created_at, u.temp_password,
               s.section, s.year_level, s.rizal_professor,
               p.id as player_id, gp.score, gp.current_stage, gp.progress_percentage, 
               gp.last_played, gp.created_at as first_played
        FROM users u 
        JOIN students s ON u.id = s.user_id 
        LEFT JOIN players p ON u.id = p.user_id
        LEFT JOIN game_progress gp ON p.id = gp.player_id
        WHERE u.id = ? AND u.user_type = 'student'
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$student_id]);
    $student = $stmt->fetch();
    
    if (!$student) {
        header('Location: manage-students.php');
        exit;
    }
    
    // Get game sessions history
    if ($student['player_id']) {
        $stmt = $pdo->prepare("
            SELECT * FROM game_sessions 
            WHERE player_id = ? 
            ORDER BY created_at DESC 
            LIMIT 10
        ");
        $stmt->execute([$student['player_id']]);
        $sessions = $stmt->fetchAll();
    } else {
        $sessions = [];
    }
    
    // Get login history (if you have a login_logs table)
    // This is optional - uncomment if you track login history
    /*
    $stmt = $pdo->prepare("
        SELECT * FROM login_logs 
        WHERE user_id = ? 
        ORDER BY login_time DESC 
        LIMIT 10
    ");
    $stmt->execute([$student_id]);
    $login_history = $stmt->fetchAll();
    */
    
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}

// Calculate statistics
$total_time_played = 0;
$average_score = 0;

if (!empty($sessions)) {
    foreach ($sessions as $session) {
        if ($session['duration']) {
            $total_time_played += $session['duration'];
        }
    }
    $average_score = $student['score'] ?? 0;
}

$days_since_joined = $student['created_at'] ? (new DateTime())->diff(new DateTime($student['created_at']))->days : 0;
$days_since_last_played = $student['last_played'] ? (new DateTime())->diff(new DateTime($student['last_played']))->days : null;

function formatDuration($seconds) {
    if ($seconds < 60) {
        return $seconds . 's';
    } elseif ($seconds < 3600) {
        return floor($seconds / 60) . 'm ' . ($seconds % 60) . 's';
    } else {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        return $hours . 'h ' . $minutes . 'm';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Student - <?php echo htmlspecialchars($student['full_name']); ?> - Filibustero Admin</title>
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

        .btn-back {
            background: #faf6ef;
            color: #5a5043;
            border: 1.5px solid #e8dfd0;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-back:hover {
            background: #d4a259;
            color: #fffcf7;
            border-color: #d4a259;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(212, 162, 89, 0.3);
        }
        
        .card {
            background: #fffcf7;
            border: 1px solid #e8dfd0;
            border-radius: 15px;
            box-shadow: 0 8px 32px rgba(139, 107, 66, 0.15);
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
        }
        
        .card-body {
            padding: 2rem;
        }

        .profile-section {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .profile-avatar {
            text-align: center;
            padding: 2rem;
            background: #faf6ef;
            border-radius: 15px;
            border: 1px solid #e8dfd0;
        }

        .avatar-icon {
            font-size: 5rem;
            color: #d4a259;
            margin-bottom: 1rem;
        }

        .student-name {
            font-size: 1.8rem;
            font-weight: 700;
            color: #3d3529;
            margin-bottom: 0.5rem;
        }

        .student-username {
            font-size: 1.1rem;
            color: #7a6f5d;
            margin-bottom: 1rem;
        }

        .status-badges {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            align-items: center;
        }

        .badge {
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .badge-success {
            background: #f0e8d8;
            color: #7a6f5d;
        }

        .badge-warning {
            background: #fef9f0;
            color: #9c7a4d;
        }

        .badge-info {
            background: #f5f0e6;
            color: #5a4d3a;
        }

        .profile-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
        }

        .detail-group {
            background: #faf6ef;
            padding: 1.5rem;
            border-radius: 10px;
            border: 1px solid #e8dfd0;
        }

        .detail-label {
            font-weight: 600;
            color: #b8863a;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 0.5rem;
        }

        .detail-value {
            font-size: 1.1rem;
            color: #4a4235;
            font-weight: 500;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: #fffcf7;
            padding: 1.5rem;
            border-radius: 12px;
            border: 1px solid #e8dfd0;
            text-align: center;
            transition: transform 0.3s ease;
            box-shadow: 0 4px 12px rgba(139, 107, 66, 0.08);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(139, 107, 66, 0.15);
            border-color: #d4a259;
        }

        .stat-icon {
            font-size: 2.5rem;
            color: #d4a259;
            margin-bottom: 0.5rem;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: #b8863a;
        }

        .stat-label {
            font-size: 0.9rem;
            color: #7a6f5d;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .progress-ring {
            position: relative;
            width: 120px;
            height: 120px;
            margin: 0 auto 1rem;
        }

        .progress-ring svg {
            transform: rotate(-90deg);
            width: 100%;
            height: 100%;
        }

        .progress-ring circle {
            fill: none;
            stroke-width: 8;
        }

        .progress-ring .background {
            stroke: rgba(212, 162, 89, 0.2);
        }

        .progress-ring .progress {
            stroke: #d4a259;
            stroke-linecap: round;
            stroke-dasharray: 314;
            stroke-dashoffset: 314;
            transition: stroke-dashoffset 1s ease-in-out;
        }

        .progress-text {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 1.2rem;
            font-weight: 700;
            color: #b8863a;
        }

        .action-buttons {
            display: flex;
            gap: 1rem;
            margin-top: 1.5rem;
        }

        .btn {
            padding: 0.8rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
        }

        .btn-primary {
            background: linear-gradient(145deg, #d4a259, #b8863a);
            color: #fffcf7;
        }

        .btn-primary:hover {
            background: linear-gradient(145deg, #b8863a, #d4a259);
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(212, 162, 89, 0.3);
        }

        .btn-info {
            background: linear-gradient(145deg, #a38954, #8b7548);
            color: #fffcf7;
        }

        .btn-info:hover {
            background: linear-gradient(145deg, #8b7548, #a38954);
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(163, 137, 84, 0.3);
        }

        .btn-danger {
            background: linear-gradient(145deg, #c9856d, #b36f59);
            color: #fffcf7;
        }

        .btn-danger:hover {
            background: linear-gradient(145deg, #b36f59, #c9856d);
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(201, 133, 109, 0.3);
        }

        .btn-secondary {
            background: linear-gradient(145deg, #8b8071, #6f6759);
            color: #fffcf7;
        }

        .btn-secondary:hover {
            background: linear-gradient(145deg, #6f6759, #8b8071);
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(139, 128, 113, 0.3);
        }

        .table-responsive {
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid #e8dfd0;
            margin-top: 1rem;
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
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
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

        .alert {
            padding: 1rem 1.5rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            border: 2px solid;
            font-weight: 600;
        }

        .alert-danger {
            background: linear-gradient(135deg, rgba(201, 133, 109, 0.2), rgba(179, 111, 89, 0.1));
            color: #b36f59;
            border-color: rgba(201, 133, 109, 0.3);
        }

        .alert-info {
            background: linear-gradient(135deg, rgba(163, 137, 84, 0.2), rgba(139, 117, 72, 0.1));
            color: #8b7548;
            border-color: rgba(163, 137, 84, 0.3);
        }

        .alert-dismissible .btn-close {
            background: none;
            border: none;
            color: #5a5043;
            opacity: 0.8;
            cursor: pointer;
            font-size: 1.2rem;
            padding: 0.25rem;
        }

        .text-center {
            text-align: center;
        }

        .text-muted {
            color: #7a6f5d !important;
            opacity: 0.7;
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
        }

        .modal-content {
            background: #fffcf7;
            border: 1px solid #e8dfd0;
            border-radius: 15px;
            box-shadow: 0 20px 60px rgba(139, 107, 66, 0.15);
            color: #4a4235;
        }

        .modal-header {
            background: #faf6ef;
            border-bottom: 1px solid #e8dfd0;
            padding: 1.5rem 2rem;
            border-radius: 15px 15px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-title {
            margin: 0;
            font-weight: 700;
            font-size: 1.3rem;
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
            transition: opacity 0.3s ease;
        }

        .btn-close:hover {
            opacity: 1;
            color: #d4a259;
        }

        .modal-body {
            padding: 2rem;
            font-size: 1.1rem;
            line-height: 1.6;
        }

        .modal-footer {
            padding: 1.5rem 2rem;
            border-top: 1px solid #e8dfd0;
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
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

            .profile-section {
                grid-template-columns: 1fr;
                gap: 1rem;
            }

            .profile-details {
                grid-template-columns: 1fr;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .action-buttons {
                flex-direction: column;
            }
        }

        small {
            font-size: 0.85em;
            opacity: 0.9;
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
                <h4><i class="fas fa-user-graduate"></i> Student Profile</h4>
                <div class="header-actions">
                    <a href="manage-students.php" class="btn-back">
                        <i class="fas fa-arrow-left"></i> Back to Students
                    </a>
                </div>
            </div>

            <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" onclick="this.parentElement.style.display='none'">&times;</button>
            </div>
            <?php endif; ?>

            <!-- Student Profile -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-user"></i> Student Information
                </div>
                <div class="card-body">
                    <div class="profile-section">
                        <div class="profile-avatar">
                            <div class="avatar-icon">
                                <i class="fas fa-user-graduate"></i>
                            </div>
                            <div class="student-name"><?php echo htmlspecialchars($student['full_name']); ?></div>
                            <div class="student-username">@<?php echo htmlspecialchars($student['username']); ?></div>
                            <div class="status-badges">
                                <?php if ($student['temp_password']): ?>
                                    <span class="badge badge-warning">Temporary Password</span>
                                <?php endif; ?>
                                <?php if ($student['last_played']): ?>
                                    <span class="badge badge-success">Active Player</span>
                                <?php else: ?>
                                    <span class="badge badge-info">Not Started</span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="action-buttons">
                                <?php if ($student['progress_percentage'] > 0): ?>
                                <button class="btn btn-danger" onclick="resetProgress(<?php echo $student['id']; ?>, '<?php echo htmlspecialchars($student['full_name']); ?>')">
                                    <i class="fas fa-undo"></i> Reset Progress
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="profile-details">
                            <div class="detail-group">
                                <div class="detail-label">Student ID</div>
                                <div class="detail-value"><?php echo htmlspecialchars($student['id_number'] ?? 'N/A'); ?></div>
                            </div>
                            <div class="detail-group">
                                <div class="detail-label">Section</div>
                                <div class="detail-value"><?php echo htmlspecialchars($student['section']); ?></div>
                            </div>
                            <div class="detail-group">
                                <div class="detail-label">Year Level</div>
                                <div class="detail-value"><?php echo htmlspecialchars($student['year_level']); ?></div>
                            </div>
                            <div class="detail-group">
                                <div class="detail-label">Rizal Professor</div>
                                <div class="detail-value"><?php echo htmlspecialchars($student['rizal_professor']); ?></div>
                            </div>
                            <div class="detail-group">
                                <div class="detail-label">Account Created</div>
                                <div class="detail-value"><?php echo date('F j, Y', strtotime($student['created_at'])); ?></div>
                            </div>
                            <div class="detail-group">
                                <div class="detail-label">Days Since Joined</div>
                                <div class="detail-value"><?php echo $days_since_joined; ?> days</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Game Statistics -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-chart-line"></i> Game Statistics & Progress
                </div>
                <div class="card-body">
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-icon"><i class="fas fa-trophy"></i></div>
                            <div class="stat-value"><?php echo $student['score'] ?? 0; ?></div>
                            <div class="stat-label">Current Score</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon"><i class="fas fa-layer-group"></i></div>
                            <div class="stat-value"><?php echo $student['current_stage'] ?? 1; ?></div>
                            <div class="stat-label">Current Stage</div>
                        </div>
                        <div class="stat-card">
                            <div class="progress-ring">
                                <svg width="120" height="120">
                                    <circle class="background" cx="60" cy="60" r="50"></circle>
                                    <circle class="progress" cx="60" cy="60" r="50" 
                                            style="stroke-dashoffset: <?php echo 314 - (314 * ($student['progress_percentage'] ?? 0) / 100); ?>"></circle>
                                </svg>
                                <div class="progress-text"><?php echo number_format($student['progress_percentage'] ?? 0, 1); ?>%</div>
                            </div>
                            <div class="stat-label">Progress</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon"><i class="fas fa-calendar-alt"></i></div>
                            <div class="stat-value">
                                <?php 
                                if ($days_since_last_played === null) {
                                    echo 'Never';
                                } elseif ($days_since_last_played === 0) {
                                    echo 'Today';
                                } elseif ($days_since_last_played === 1) {
                                    echo '1 day ago';
                                } else {
                                    echo $days_since_last_played . ' days ago';
                                }
                                ?>
                            </div>
                            <div class="stat-label">Last Played</div>
                        </div>
                    </div>

                    <?php if ($student['first_played']): ?>
                    <div class="detail-group">
                        <div class="detail-label">First Game Session</div>
                        <div class="detail-value"><?php echo date('F j, Y g:i A', strtotime($student['first_played'])); ?></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Account Activity -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-user-clock"></i> Account Activity Summary
                </div>
                <div class="card-body">
                    <div class="profile-details">
                        <div class="detail-group">
                            <div class="detail-label">Account Status</div>
                            <div class="detail-value">
                                <?php if ($student['temp_password']): ?>
                                    <span style="color: #ffc107;">⚠️ Needs Password Change</span>
                                <?php else: ?>
                                    <span style="color: #28a745;">✅ Active</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="detail-group">
                            <div class="detail-label">Learning Progress</div>
                            <div class="detail-value">
                                <?php 
                                $progress = $student['progress_percentage'] ?? 0;
                                if ($progress >= 80): ?>
                                    <span style="color: #28a745;">🏆 Excellent Progress</span>
                                <?php elseif ($progress >= 50): ?>
                                    <span style="color: #ffc107;">📈 Good Progress</span>
                                <?php elseif ($progress > 0): ?>
                                    <span style="color: #17a2b8;">🚀 Getting Started</span>
                                <?php else: ?>
                                    <span style="color: #6c757d;">⏳ Not Started</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="detail-group">
                            <div class="detail-label">Engagement Level</div>
                            <div class="detail-value">
                                <?php 
                                if ($days_since_last_played === null): ?>
                                    <span style="color: #6c757d;">❌ No Engagement</span>
                                <?php elseif ($days_since_last_played <= 1): ?>
                                    <span style="color: #28a745;">🔥 Very Active</span>
                                <?php elseif ($days_since_last_played <= 7): ?>
                                    <span style="color: #ffc107;">⚡ Active</span>
                                <?php elseif ($days_since_last_played <= 30): ?>
                                    <span style="color: #17a2b8;">💤 Moderate</span>
                                <?php else: ?>
                                    <span style="color: #dc3545;">😴 Inactive</span>
                                <?php endif; ?>
                            </div>
                        </div>
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
                        <input type="hidden" name="action" id="confirmAction">
                        <button type="submit" class="btn" id="confirmBtn">Confirm</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>

        function resetProgress(studentId, studentName) {
            document.getElementById('modalTitle').textContent = 'Reset Game Progress';
            document.getElementById('modalBody').innerHTML = `Are you sure you want to reset the game progress for <strong>${studentName}</strong>?<br><br>This will:<br>• Reset score to 0<br>• Reset stage to 1<br>• Reset progress to 0%<br>• Delete all game sessions<br><br><strong>This action cannot be undone!</strong>`;
            document.getElementById('confirmAction').value = 'reset_progress';
            document.getElementById('confirmBtn').textContent = 'Reset Progress';
            document.getElementById('confirmBtn').className = 'btn btn-danger';
            
            showModal();
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

        // Add smooth animations and interactions
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

            document.querySelectorAll('.card, .stat-card').forEach(element => {
                element.style.opacity = '0';
                element.style.transform = 'translateY(30px)';
                element.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
                observer.observe(element);
            });

            // Animate progress ring
            const progressRing = document.querySelector('.progress-ring .progress');
            if (progressRing) {
                setTimeout(() => {
                    const progress = <?php echo $student['progress_percentage'] ?? 0; ?>;
                    const offset = 314 - (314 * progress / 100);
                    progressRing.style.strokeDashoffset = offset;
                }, 500);
            }

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

            // Enhanced stat card hover effects
            document.querySelectorAll('.stat-card').forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-10px) scale(1.02)';
                    this.style.boxShadow = '0 20px 40px rgba(212, 175, 55, 0.2)';
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(-5px) scale(1)';
                    this.style.boxShadow = 'none';
                });
            });

            // Table row hover effects
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