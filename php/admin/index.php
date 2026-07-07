<?php
require_once 'config.php';

// Handle login from the unified login form
if ($_POST && isset($_POST['username']) && isset($_POST['password']) && isset($_POST['role']) && $_POST['role'] === 'admin') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    
    if ($username === ADMIN_USERNAME && $password === ADMIN_PASSWORD) {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_username'] = $username;
        header('Location: index.php');
        exit;
    } else {
        $_SESSION['login_error'] = "Invalid admin credentials";
        header('Location: teacher_login.html');
        exit;
    }
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: teacher_login.html');
    exit;
}

// Check if admin is logged in
$is_logged_in = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'];

// If not logged in, redirect to unified login
if (!$is_logged_in) {
    header('Location: teacher_login.html');
    exit;
}

// Get dashboard statistics
$stats = [];
try {
    // Pending teacher requests
    $stmt = $pdo->query("SELECT COUNT(*) FROM teachers WHERE is_active = 0");
    $stats['pending_requests'] = $stmt->fetchColumn();
    
    // Active teachers
    $stmt = $pdo->query("SELECT COUNT(*) FROM teachers WHERE is_active = 1");
    $stats['active_teachers'] = $stmt->fetchColumn();
    
    // Total students
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE user_type = 'student'");
    $stats['total_students'] = $stmt->fetchColumn();
    
    // Total users
    $stmt = $pdo->query("SELECT COUNT(*) FROM users");
    $stats['total_users'] = $stmt->fetchColumn();
    
    // Recent activities
    $stmt = $pdo->prepare("
        SELECT u.username, u.full_name, u.user_type, u.created_at, t.is_active
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

        .logout-btn {
            background: #faf6ef;
            color: #5a5043;
            border: 1.5px solid #e8dfd0;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.2s ease;
            font-size: 0.9rem;
        }

        .logout-btn:hover {
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
        
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: #fffcf7;
            border: 1px solid #e8dfd0;
            border-radius: 12px;
            padding: 1.8rem;
            box-shadow: 0 4px 12px rgba(139, 107, 66, 0.08);
            display: flex;
            align-items: center;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(139, 107, 66, 0.15);
            border-color: #d4a259;
        }
        
        .stat-icon {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1.5rem;
            font-size: 1.8rem;
            border: 3px solid rgba(212, 162, 89, 0.3);
            position: relative;
            overflow: hidden;
        }

        .stat-icon::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(145deg, rgba(212, 162, 89, 0.1), rgba(184, 134, 58, 0.05));
            border-radius: 50%;
        }
        
        .stat-icon.pending {
            color: #d4a259;
            border-color: rgba(212, 162, 89, 0.3);
        }

        .stat-icon.pending::before {
            background: linear-gradient(145deg, rgba(212, 162, 89, 0.1), rgba(212, 162, 89, 0.05));
        }
        
        .stat-icon.success {
            color: #a38954;
            border-color: rgba(163, 137, 84, 0.3);
        }

        .stat-icon.success::before {
            background: linear-gradient(145deg, rgba(163, 137, 84, 0.1), rgba(163, 137, 84, 0.05));
        }
        
        .stat-icon.warning {
            color: #c9856d;
            border-color: rgba(201, 133, 109, 0.3);
        }

        .stat-icon.warning::before {
            background: linear-gradient(145deg, rgba(201, 133, 109, 0.1), rgba(201, 133, 109, 0.05));
        }
        
        .stat-icon.danger {
            color: #b36f59;
            border-color: rgba(179, 111, 89, 0.3);
        }

        .stat-icon.danger::before {
            background: linear-gradient(145deg, rgba(179, 111, 89, 0.1), rgba(179, 111, 89, 0.05));
        }
        
        .stat-info h4 {
            margin: 0;
            font-size: 2.2rem;
            font-weight: 700;
            color: #b8863a;
        }
        
        .stat-info p {
            margin: 0.5rem 0 0 0;
            color: #7a6f5d;
            font-weight: 600;
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
        
        .badge {
            padding: 0.4rem 0.8rem;
            border-radius: 6px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .badge-pending {
            background: #fef9f0;
            color: #9c7a4d;
        }
        
        .badge-active {
            background: #f0e8d8;
            color: #7a6f5d;
        }
        
        .badge-inactive {
            background: #f7f4ed;
            color: #8b764d;
        }

        .badge-info {
            background: #f5f0e6;
            color: #5a4d3a;
        }
        
        .alert {
            padding: 1rem 1.5rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            border: 1px solid;
            font-weight: 500;
        }

        .alert-danger {
            background: linear-gradient(135deg, rgba(201, 133, 109, 0.2), rgba(179, 111, 89, 0.1));
            color: #b36f59;
            border-color: rgba(201, 133, 109, 0.3);
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
            
            .stats-container {
                grid-template-columns: 1fr;
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
        }

        @media (max-width: 480px) {
            .stat-card {
                flex-direction: column;
                text-align: center;
            }

            .stat-icon {
                margin-right: 0;
                margin-bottom: 1rem;
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
                <li><a href="index.php" class="active"><i class="fas fa-tachometer-alt"></i> <span class="menu-text">Dashboard</span></a></li>
                <li><a href="teacher-requests.php"><i class="fas fa-user-clock"></i> <span class="menu-text">Teacher Requests</span></a></li>
                <li><a href="manage-teachers.php"><i class="fas fa-chalkboard-teacher"></i> <span class="menu-text">Manage Teachers</span></a></li>
                <li><a href="manage-students.php"><i class="fas fa-user-graduate"></i> <span class="menu-text">Manage Students</span></a></li>
                <li><a href="all-users.php"><i class="fas fa-users"></i> <span class="menu-text">All Users</span></a></li>
                <li><a href="?logout=1" class="logout-link"><i class="fas fa-sign-out-alt"></i> <span class="menu-text">Logout</span></a></li>
            </ul>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <div class="header">
                <h4>Administrator Dashboard</h4>
                <div class="header-actions">
                    <span class="welcome-text">Welcome, <?php echo htmlspecialchars($_SESSION['admin_username'] ?? 'Admin'); ?></span>
                    <a href="?logout=1" class="logout-btn">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>

            <?php if (isset($error)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
            <?php endif; ?>

            <!-- Dashboard Overview -->
            <div class="stats-container">
                <div class="stat-card">
                    <div class="stat-icon pending">
                        <i class="fas fa-user-clock"></i>
                    </div>
                    <div class="stat-info">
                        <h4><?php echo $stats['pending_requests'] ?? 0; ?></h4>
                        <p>Pending Teacher Requests</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon success">
                        <i class="fas fa-chalkboard-teacher"></i>
                    </div>
                    <div class="stat-info">
                        <h4><?php echo $stats['active_teachers'] ?? 0; ?></h4>
                        <p>Active Teachers</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon warning">
                        <i class="fas fa-user-graduate"></i>
                    </div>
                    <div class="stat-info">
                        <h4><?php echo $stats['total_students'] ?? 0; ?></h4>
                        <p>Registered Students</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon danger">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-info">
                        <h4><?php echo $stats['total_users'] ?? 0; ?></h4>
                        <p>Total System Users</p>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <i class="fas fa-history"></i> Recent User Activities
                </div>
                <div class="card-body">
                    <div class="table-container">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th><i class="fas fa-user"></i> Username</th>
                                    <th><i class="fas fa-id-card"></i> Full Name</th>
                                    <th><i class="fas fa-tag"></i> User Type</th>
                                    <th><i class="fas fa-calendar-plus"></i> Registration Date</th>
                                    <th><i class="fas fa-info-circle"></i> Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (isset($stats['recent_activities']) && !empty($stats['recent_activities'])): ?>
                                    <?php foreach ($stats['recent_activities'] as $activity): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($activity['username']); ?></td>
                                        <td><?php echo htmlspecialchars($activity['full_name']); ?></td>
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
                                        <td colspan="5" style="text-align: center; color: #c8b894; padding: 2rem;">
                                            <i class="fas fa-inbox" style="font-size: 2rem; margin-bottom: 1rem; display: block;"></i>
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
        // Add Font Awesome for icons
        if (!document.querySelector('link[href*="font-awesome"]')) {
            const link = document.createElement('link');
            link.rel = 'stylesheet';
            link.href = 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css';
            document.head.appendChild(link);
        }

        // Confirm logout
        document.querySelectorAll('.logout-link, .logout-btn').forEach(link => {
            link.addEventListener('click', function(e) {
                if (!confirm('Are you sure you want to logout?')) {
                    e.preventDefault();
                }
            });
        });

        // Add hover effects and interactions
        document.addEventListener('DOMContentLoaded', function() {
            // Animate stat cards on scroll
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

            document.querySelectorAll('.stat-card').forEach(card => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(30px)';
                card.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
                observer.observe(card);
            });
        });
    </script>
</body>
</html>