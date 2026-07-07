<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ob_start();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    require_once 'db_connect.php';
} catch (Exception $e) {
    ob_clean();
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit();
}

ob_clean();

$response = ['success' => false, 'error' => ''];

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $teacher_id = $_POST['teacher_id'] ?? '';
        $section = $_POST['section'] ?? '';
        
        if (empty($teacher_id) || empty($section)) {
            $response['error'] = 'Teacher ID and Section are required';
            echo json_encode($response);
            exit();
        }
        
        // First, check if teacher has permission to modify this section
        // Check teacher_sections table
        try {
            $checkStmt = $pdo->prepare("SELECT * FROM teacher_sections WHERE teacher_id = ? AND section = ?");
            $checkStmt->execute([$teacher_id, $section]);
            
            if ($checkStmt->rowCount() === 0) {
                // Also check if teacher has students in this section in leaderboard
                $checkLeaderboardStmt = $pdo->prepare("SELECT COUNT(*) FROM leaderboard WHERE teacher_id = ? AND section = ?");
                $checkLeaderboardStmt->execute([$teacher_id, $section]);
                $studentCount = $checkLeaderboardStmt->fetchColumn();
                
                if ($studentCount === 0) {
                    $response['error'] = 'You do not have permission to modify this section or section does not exist';
                    echo json_encode($response);
                    exit();
                }
            }
        } catch (Exception $e) {
            error_log("Permission check failed: " . $e->getMessage());
        }
        
        // Check if is_archived column exists in leaderboard
        $hasLeaderboardArchiveColumn = false;
        try {
            $checkColumnStmt = $pdo->prepare("SHOW COLUMNS FROM leaderboard LIKE 'is_archived'");
            $checkColumnStmt->execute();
            $hasLeaderboardArchiveColumn = $checkColumnStmt->rowCount() > 0;
        } catch (Exception $e) {
            error_log("Could not check leaderboard columns: " . $e->getMessage());
        }
        
        // Check if is_archived column exists in teacher_sections
        $hasTeacherSectionsArchiveColumn = false;
        try {
            $checkColumnStmt2 = $pdo->prepare("SHOW COLUMNS FROM teacher_sections LIKE 'is_archived'");
            $checkColumnStmt2->execute();
            $hasTeacherSectionsArchiveColumn = $checkColumnStmt2->rowCount() > 0;
        } catch (Exception $e) {
            error_log("Could not check teacher_sections columns: " . $e->getMessage());
        }
        
        $studentsArchived = 0;
        $sectionArchived = false;
        
        // 1. Archive students in leaderboard table (if any)
        try {
            if ($hasLeaderboardArchiveColumn) {
                $stmt = $pdo->prepare("UPDATE leaderboard SET is_archived = 1 WHERE teacher_id = ? AND section = ?");
                $stmt->execute([$teacher_id, $section]);
                $studentsArchived = $stmt->rowCount();
                error_log("Archived $studentsArchived students in leaderboard for section: $section");
            }
        } catch (Exception $e) {
            error_log("Error archiving students in leaderboard: " . $e->getMessage());
        }
        
        // 2. Archive the section in teacher_sections table (CRITICAL FOR SECTIONS WITH 0 STUDENTS)
        try {
            // First, ensure teacher_sections table has the record
            $checkTeacherSectionStmt = $pdo->prepare("SELECT COUNT(*) FROM teacher_sections WHERE teacher_id = ? AND section = ?");
            $checkTeacherSectionStmt->execute([$teacher_id, $section]);
            $teacherSectionExists = $checkTeacherSectionStmt->fetchColumn() > 0;
            
            if (!$teacherSectionExists) {
                // Insert the record if it doesn't exist
                $insertStmt = $pdo->prepare("INSERT INTO teacher_sections (teacher_id, section) VALUES (?, ?)");
                $insertStmt->execute([$teacher_id, $section]);
                error_log("Inserted missing teacher_sections record for section: $section");
            }
            
            // Now update the is_archived flag
            if ($hasTeacherSectionsArchiveColumn) {
                $stmt2 = $pdo->prepare("UPDATE teacher_sections SET is_archived = 1 WHERE teacher_id = ? AND section = ?");
                $stmt2->execute([$teacher_id, $section]);
                $sectionArchived = $stmt2->rowCount() > 0;
                error_log("Archived section in teacher_sections: $section");
            } else {
                // If column doesn't exist, add it
                try {
                    $pdo->exec("ALTER TABLE teacher_sections ADD COLUMN is_archived TINYINT(1) DEFAULT 0");
                    error_log("Added is_archived column to teacher_sections");
                    
                    $stmt2 = $pdo->prepare("UPDATE teacher_sections SET is_archived = 1 WHERE teacher_id = ? AND section = ?");
                    $stmt2->execute([$teacher_id, $section]);
                    $sectionArchived = $stmt2->rowCount() > 0;
                } catch (Exception $e) {
                    error_log("Could not add is_archived column to teacher_sections: " . $e->getMessage());
                }
            }
        } catch (Exception $e) {
            error_log("Error archiving section in teacher_sections: " . $e->getMessage());
        }
        
        // 3. Also use the section_archive table as a backup
        try {
            // Create archive table if it doesn't exist
            $pdo->exec("CREATE TABLE IF NOT EXISTS section_archive (
                id INT AUTO_INCREMENT PRIMARY KEY,
                teacher_id VARCHAR(50),
                section VARCHAR(100),
                archived_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY unique_section (teacher_id, section)
            )");
            
            $stmt3 = $pdo->prepare("INSERT INTO section_archive (teacher_id, section) VALUES (?, ?) 
                                   ON DUPLICATE KEY UPDATE archived_at = CURRENT_TIMESTAMP");
            $stmt3->execute([$teacher_id, $section]);
            error_log("Added/updated section in section_archive table: $section");
        } catch (Exception $e) {
            error_log("Error with section_archive table: " . $e->getMessage());
        }
        
        $response['success'] = true;
        $response['message'] = "Section '$section' has been archived successfully";
        $response['students_archived'] = $studentsArchived;
        $response['section_archived'] = $sectionArchived;
        
        error_log("Teacher $teacher_id archived section: $section ($studentsArchived students)");
        
    } else {
        $response['error'] = 'Invalid request method. POST required.';
    }
} catch (PDOException $e) {
    error_log("Database error in archive_section.php: " . $e->getMessage());
    $response['error'] = 'Database error occurred: ' . $e->getMessage();
} catch (Exception $e) {
    error_log("General error in archive_section.php: " . $e->getMessage());
    $response['error'] = 'An error occurred';
}

echo json_encode($response);
?>