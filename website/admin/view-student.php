<?php
require_once 'config.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header('Location: admin');
    exit;
}

$message = '';
$message_type = '';

// Get student ID from URL
$student_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$student_id) {
    header('Location: manage-students');
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
        SELECT u.id, u.full_name, u.username, u.email, u.id_number, u.created_at, u.temp_password,
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
        header('Location: manage-students');
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
        
        .btn-back {
            background: linear-gradient(135deg, #faf6ef 0%, #f5f1eb 100%);
            color: #5a5043;
            border: 1.5px solid #e8dfd0;
            padding: 0.7rem 1.2rem; /* Reduced padding */
            border-radius: 0;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.85rem; /* Smaller font */
        }

        .btn-back:hover {
            background: linear-gradient(135deg, #d4a259 0%, #b8863a 100%);
            color: #fffcf7;
            border-color: #9c6d2e;
            box-shadow: 0 6px 15px rgba(212, 162, 89, 0.4);
            transform: translateY(-1px);
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

        .profile-section {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 1.5rem; /* Reduced gap */
            margin-bottom: 1.5rem; /* Reduced margin */
        }

        .profile-avatar {
            text-align: center;
            padding: 1.5rem; /* Reduced padding */
            background: linear-gradient(135deg, #faf6ef 0%, #f5f1eb 100%);
            border-radius: 0;
            border: 2px solid #d4a259;
        }

        .avatar-icon {
            font-size: 4rem; /* Smaller icon */
            color: #d4a259;
            margin-bottom: 1rem;
        }

        .student-name {
            font-size: 1.5rem; /* Smaller font */
            font-weight: 700;
            color: #3d3529;
            margin-bottom: 0.5rem;
        }

        .student-username {
            font-size: 1rem; /* Smaller font */
            color: #7a6f5d;
            margin-bottom: 1rem;
        }

        .status-badges {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            align-items: center;
            margin-bottom: 1rem;
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
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); /* Smaller cards */
            gap: 1rem; /* Reduced gap */
        }

        .detail-group {
            background: linear-gradient(135deg, #faf6ef 0%, #f5f1eb 100%);
            padding: 1.2rem; /* Reduced padding */
            border-radius: 0;
            border: 1px solid #e8dfd0;
        }

        .detail-label {
            font-weight: 600;
            color: #b8863a;
            font-size: 0.8rem; /* Smaller font */
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.5rem;
        }

        .detail-value {
            font-size: 1rem; /* Smaller font */
            color: #4a4235;
            font-weight: 500;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); /* Smaller cards */
            gap: 1rem; /* Reduced gap */
            margin-bottom: 1.5rem; /* Reduced margin */
        }

        .stat-card {
            background: linear-gradient(135deg, #fffcf7 0%, #f9f5ed 100%);
            padding: 1.2rem; /* Reduced padding */
            border-radius: 0;
            border: 1px solid #e8dfd0;
            text-align: center;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(139, 107, 66, 0.08);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(139, 107, 66, 0.15);
            border-color: #d4a259;
        }

        .stat-icon {
            font-size: 2rem; /* Smaller icon */
            color: #d4a259;
            margin-bottom: 0.5rem;
        }

        .stat-value {
            font-size: 1.8rem; /* Smaller font */
            font-weight: 700;
            color: #b8863a;
        }

        .stat-label {
            font-size: 0.8rem; /* Smaller font */
            color: #7a6f5d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .progress-ring {
            position: relative;
            width: 100px; /* Smaller ring */
            height: 100px;
            margin: 0 auto 0.5rem;
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
            font-size: 1rem; /* Smaller font */
            font-weight: 700;
            color: #b8863a;
        }

        .action-buttons {
            display: flex;
            gap: 0.8rem; /* Reduced gap */
            margin-top: 1rem;
        }

        .btn {
            padding: 0.7rem 1.2rem; /* Reduced padding */
            border: none;
            border-radius: 0;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
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

        .btn-info {
            background: linear-gradient(135deg, #6da8c9 0%, #598bb3 100%);
            color: #fffcf7;
            border: 1.5px solid transparent;
        }

        .btn-info:hover {
            background: linear-gradient(135deg, #598bb3 0%, #48759d 100%);
            transform: translateY(-1px);
            box-shadow: 0 6px 15px rgba(212, 162, 89, 0.4);
        }

        .btn-danger {
            background: linear-gradient(135deg, #c9856d 0%, #b36f59 100%);
            color: #fffcf7;
            border: 1.5px solid transparent;
        }

        .btn-danger:hover {
            background: linear-gradient(135deg, #b36f59 0%, #9d5c48 100%);
            transform: translateY(-1px);
            box-shadow: 0 6px 15px rgba(212, 162, 89, 0.4);
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

        .text-center {
            text-align: center;
        }

        .text-muted {
            color: #a39582 !important;
            opacity: 0.7;
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
                <li><a href="manage-teachers"><i class="fas fa-chalkboard-teacher"></i> Manage Teachers</a></li>
                <li><a href="manage-students" class="active"><i class="fas fa-user-graduate"></i> Manage Students</a></li>
                <li><a href="all-users"><i class="fas fa-users"></i> All Users</a></li>
                 <li><a href="admin-profile"><i class="fas fa-user-cog"></i> Admin Profile</a></li>
            </ul>
        </div>

        <!-- Main Content - Adjusted Width -->
        <div class="main-content">
            <nav class="navbar">
                <div class="nav-content">
                    <div class="logo">Student Profile</div>
                    <div class="header-actions">
                        <span class="welcome-text">Welcome, <?php echo htmlspecialchars($_SESSION['admin_username'] ?? 'Admin'); ?></span>
                        <a href="manage-students" class="btn-back">
                            <i class="fas fa-arrow-left"></i> Back to Students
                        </a>
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

                <!-- Student Profile -->
                <div class="content-section">
                    <div class="section-title">
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
                                    <div class="detail-label">Email</div>
                                    <div class="detail-value"><?php echo htmlspecialchars($student['email'] ?? 'N/A'); ?></div>
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
                <div class="content-section">
                    <div class="section-title">
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
                                    <svg width="100" height="100">
                                        <circle class="background" cx="50" cy="50" r="45"></circle>
                                        <circle class="progress" cx="50" cy="50" r="45" 
                                                style="stroke-dashoffset: <?php echo 283 - (283 * ($student['progress_percentage'] ?? 0) / 100); ?>"></circle>
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
                <div class="content-section">
                    <div class="section-title">
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
        
        function viewProfile() {
            window.location.href = 'admin-profile';
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
            const sections = document.querySelectorAll('.content-section');
            sections.forEach((section, index) => {
                section.style.opacity = '0';
                section.style.transform = 'translateY(30px)';
                section.style.transition = `opacity 0.6s ease ${index * 0.1}s, transform 0.6s ease ${index * 0.1}s`;
                
                setTimeout(() => {
                    section.style.opacity = '1';
                    section.style.transform = 'translateY(0)';
                }, 100);
            });

            // Animate progress ring
            const progressRing = document.querySelector('.progress-ring .progress');
            if (progressRing) {
                setTimeout(() => {
                    const progress = <?php echo $student['progress_percentage'] ?? 0; ?>;
                    const offset = 283 - (283 * progress / 100);
                    progressRing.style.strokeDashoffset = offset;
                }, 500);
            }

            // Add hover effects for stat cards
            document.querySelectorAll('.stat-card').forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-5px)';
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
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
    </script>
</body>
</html>