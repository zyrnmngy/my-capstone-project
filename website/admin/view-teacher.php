<?php
require_once 'config.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header('Location: admin');
    exit;
}

// Get teacher ID
$teacher_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$teacher_id) {
    header('Location: manage-teachers');
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
        header('Location: manage-teachers');
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
    <title>View Teacher - <?php echo htmlspecialchars($teacher['full_name']); ?> - Filibustero Admin</title>
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
            font-size: 14px;
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
            margin-left: 220px;
            padding: 0;
            min-height: 100vh;
            width: calc(100% - 220px);
        }
        
        .navbar {
            background: linear-gradient(135deg, #fffcf7 0%, #f9f5ed 100%);
            padding: 1rem 2rem;
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
            font-size: 1.5rem;
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
        
        .btn-back {
            background: linear-gradient(135deg, #faf6ef 0%, #f5f1eb 100%);
            color: #5a5043;
            border: 1.5px solid #e8dfd0;
            padding: 0.7rem 1.2rem;
            border-radius: 0;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.85rem;
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
            padding: 1.5rem 2rem;
            max-width: none;
        }
        
        .content-section {
            background: linear-gradient(135deg, #fffcf7 0%, #f9f5ed 100%);
            border: 2px solid #d4a259;
            border-radius: 0;
            padding: 2rem;
            box-shadow: 
                0 8px 30px rgba(212, 162, 89, 0.2),
                0 0 40px rgba(212, 162, 89, 0.1),
                inset 0 0 20px rgba(212, 162, 89, 0.05);
            margin-bottom: 1.5rem;
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
            font-size: 1.4rem;
            font-weight: 900;
            color: #3d3529;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.8rem;
            font-family: 'Orbitron', monospace;
            letter-spacing: 1px;
            text-transform: uppercase;
            text-shadow: 2px 2px 0px rgba(212, 162, 89, 0.2);
        }

        .profile-section {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .profile-avatar {
            text-align: center;
            padding: 1.5rem;
            background: linear-gradient(135deg, #faf6ef 0%, #f5f1eb 100%);
            border-radius: 0;
            border: 2px solid #d4a259;
        }

        .avatar-icon {
            font-size: 4rem;
            color: #d4a259;
            margin-bottom: 1rem;
        }

        .teacher-name {
            font-size: 1.5rem;
            font-weight: 700;
            color: #3d3529;
            margin-bottom: 0.5rem;
        }

        .teacher-username {
            font-size: 1rem;
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
            padding: 0.4rem 0.8rem;
            border-radius: 0;
            font-size: 0.75rem;
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
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .detail-group {
            background: linear-gradient(135deg, #faf6ef 0%, #f5f1eb 100%);
            padding: 1.2rem;
            border-radius: 0;
            border: 1px solid #e8dfd0;
        }

        .detail-label {
            font-weight: 600;
            color: #b8863a;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.5rem;
        }

        .detail-value {
            font-size: 1rem;
            color: #4a4235;
            font-weight: 500;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .stat-card {
            background: linear-gradient(135deg, #fffcf7 0%, #f9f5ed 100%);
            padding: 1.2rem;
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
            font-size: 2rem;
            color: #d4a259;
            margin-bottom: 0.5rem;
        }

        .stat-value {
            font-size: 1.8rem;
            font-weight: 700;
            color: #b8863a;
        }

        .stat-label {
            font-size: 0.8rem;
            color: #7a6f5d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .progress-ring {
            position: relative;
            width: 100px;
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
            font-size: 1rem;
            font-weight: 700;
            color: #b8863a;
        }

        .action-buttons {
            display: flex;
            gap: 0.8rem;
            margin-top: 1rem;
        }

        .btn {
            padding: 0.7rem 1.2rem;
            border: none;
            border-radius: 0;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.85rem;
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
            padding: 1rem 1.2rem;
            border-radius: 0;
            margin-bottom: 1.5rem;
            border: 2px solid;
            font-weight: 500;
            font-size: 0.9rem;
        }

        .alert-success {
            background: linear-gradient(135deg, rgba(163, 137, 84, 0.1), rgba(139, 117, 72, 0.05));
            color: #8b7548;
            border-color: rgba(163, 137, 84, 0.3);
        }

        .alert-warning {
            background: linear-gradient(135deg, rgba(232, 176, 75, 0.1), rgba(209, 154, 58, 0.05));
            color: #d19a3a;
            border-color: rgba(232, 176, 75, 0.3);
        }

        .alert-danger {
            background: linear-gradient(135deg, rgba(201, 133, 109, 0.1), rgba(179, 111, 89, 0.05));
            color: #b36f59;
            border-color: rgba(201, 133, 109, 0.3);
        }

        .alert-dismissible .btn-close {
            background: none;
            border: none;
            color: #7a6f5d;
            opacity: 0.8;
            cursor: pointer;
            font-size: 1.1rem;
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

        .table-container {
            background: #fffcf7;
            border-radius: 0;
            overflow: hidden;
            border: 2px solid #d4a259;
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
            max-width: 500px;
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
            padding: 1.2rem 1.5rem;
        }

        .modal-title {
            margin: 0;
            font-weight: 600;
            font-size: 1.1rem;
            color: #3d3529;
            font-family: 'Orbitron', monospace;
        }

        .btn-close {
            background: none;
            border: none;
            color: #7a6f5d;
            font-size: 1.3rem;
            cursor: pointer;
            padding: 0;
            opacity: 0.8;
            transition: opacity 0.2s ease;
        }

        .btn-close:hover {
            opacity: 1;
        }

        .modal-body {
            padding: 1.5rem;
            font-size: 0.9rem;
            line-height: 1.6;
        }

        .modal-footer {
            padding: 1.2rem 1.5rem;
            border-top: 2px solid #d4a259;
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
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
            border-radius: 0;
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
                font-size: 1.2rem;
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
                    <div class="logo">Teacher Profile</div>
                    <div class="header-actions">
                        <span class="welcome-text">Welcome, <?php echo htmlspecialchars($_SESSION['admin_username'] ?? 'Admin'); ?></span>
                        <a href="manage-teachers" class="btn-back">
                            <i class="fas fa-arrow-left"></i> Back to Teachers
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

                <!-- Teacher Profile -->
                <div class="content-section">
                    <div class="section-title">
                        <i class="fas fa-user"></i> Teacher Information
                    </div>
                    <div class="card-body">
                        <div class="profile-section">
                            <div class="profile-avatar">
                                <div class="avatar-icon">
                                    <i class="fas fa-chalkboard-teacher"></i>
                                </div>
                                <div class="teacher-name"><?php echo htmlspecialchars($teacher['full_name'] ?? 'N/A'); ?></div>
                                <div class="teacher-username">@<?php echo htmlspecialchars($teacher['username'] ?? 'N/A'); ?></div>
                                <div class="status-badges">
                                    <?php if ($teacher['temp_password'] ?? false): ?>
                                        <span class="badge badge-warning">Temporary Password</span>
                                    <?php endif; ?>
                                    <?php if (($teacher['is_active'] ?? 0) == 1): ?>
                                        <span class="badge badge-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge badge-info">Inactive</span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="profile-details">
                                <div class="detail-group">
                                    <div class="detail-label">Teacher ID</div>
                                    <div class="detail-value"><?php echo htmlspecialchars($teacher['id_number'] ?? 'N/A'); ?></div>
                                </div>
                                <div class="detail-group">
                                    <div class="detail-label">Username</div>
                                    <div class="detail-value"><?php echo htmlspecialchars($teacher['username'] ?? 'N/A'); ?></div>
                                </div>
                                <div class="detail-group">
                                    <div class="detail-label">Full Name</div>
                                    <div class="detail-value"><?php echo htmlspecialchars($teacher['full_name'] ?? 'N/A'); ?></div>
                                </div>
                                <div class="detail-group">
                                    <div class="detail-label">Status</div>
                                    <div class="detail-value">
                                        <?php if (($teacher['is_active'] ?? 0) == 1): ?>
                                            <span style="color: #28a745;">✅ Active</span>
                                        <?php else: ?>
                                            <span style="color: #dc3545;">❌ Inactive</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="detail-group">
                                    <div class="detail-label">Account Created</div>
                                    <div class="detail-value"><?php echo date('F j, Y', strtotime($teacher['created_at'] ?? 'now')); ?></div>
                                </div>
                                <div class="detail-group">
                                    <div class="detail-label">Days Since Joined</div>
                                    <div class="detail-value"><?php echo (new DateTime())->diff(new DateTime($teacher['created_at'] ?? 'now'))->days; ?> days</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Assigned Sections -->
                <div class="content-section">
                    <div class="section-title">
                        <i class="fas fa-users"></i> Assigned Sections
                    </div>
                    <div class="card-body">
                        <div class="action-buttons">
                            <button class="btn btn-primary" onclick="showModal()">
                                <i class="fas fa-plus"></i> Assign Section
                            </button>
                        </div>
                        
                        <?php if (empty($assigned_sections)): ?>
                            <div class="text-center text-muted">
                                <i class="fas fa-user-slash fa-3x mb-3"></i>
                                <p>No sections assigned to this teacher.</p>
                            </div>
                        <?php else: ?>
                            <div class="stats-grid">
                                <?php foreach ($assigned_sections as $section): ?>
                                    <div class="stat-card">
                                        <div class="stat-icon"><i class="fas fa-users"></i></div>
                                        <div class="stat-value"><?php echo $section['student_count'] ?? 0; ?></div>
                                        <div class="stat-label"><?php echo htmlspecialchars($section['section'] ?? 'N/A'); ?></div>
                                        <form method="POST" style="margin-top: 0.5rem;">
                                            <input type="hidden" name="action" value="remove_section">
                                            <input type="hidden" name="section" value="<?php echo htmlspecialchars($section['section'] ?? ''); ?>">
                                            <button type="submit" class="btn btn-sm btn-danger" 
                                                    onclick="return confirm('Remove this section assignment?')">
                                                <i class="fas fa-times"></i> Remove
                                            </button>
                                        </form>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Students in Assigned Sections -->
                <div class="content-section">
                    <div class="section-title">
                        <i class="fas fa-user-graduate"></i> Students in Assigned Sections (<?php echo count($students); ?>)
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
                                                <td><span class="badge badge-info"><?php echo htmlspecialchars($student['section'] ?? 'N/A'); ?></span></td>
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
        // Modal functionality
        function showModal() {
            document.getElementById('assignSectionModal').classList.add('show');
            document.body.style.overflow = 'hidden';
        }
        function viewProfile() {
            window.location.href = 'admin-profile';
        }

        function closeModal() {
            document.getElementById('assignSectionModal').classList.remove('show');
            document.body.style.overflow = 'auto';
        }

        // Close modal when clicking outside
        document.getElementById('assignSectionModal').addEventListener('click', function(event) {
            if (event.target === this) {
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

            // Animate progress bars
            const progressBars = document.querySelectorAll('.progress-bar');
            progressBars.forEach((bar, index) => {
                const width = bar.style.width;
                bar.style.width = '0%';
                
                setTimeout(() => {
                    bar.style.width = width;
                }, (index * 100) + 1000);
            });

            // Add hover effects for stat cards
            document.querySelectorAll('.stat-card').forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-5px)';
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                });
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
            .content-section:hover {
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