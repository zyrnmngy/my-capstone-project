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
        
        // Try to unarchive from leaderboard table first
        try {
            // Check if is_archived column exists
            $checkColumnStmt = $pdo->prepare("SHOW COLUMNS FROM leaderboard LIKE 'is_archived'");
            $checkColumnStmt->execute();
            $hasArchiveColumn = $checkColumnStmt->rowCount() > 0;
            
            if ($hasArchiveColumn) {
                // Update all students in this section
                $stmt = $pdo->prepare("UPDATE leaderboard SET is_archived = 0 WHERE section = ?");
                $stmt->execute([$section]);
                
                $updatedCount = $stmt->rowCount();
            }
            
            // Also update teacher_sections if it exists
            try {
                if ($pdo->query("SHOW TABLES LIKE 'teacher_sections'")->rowCount() > 0) {
                    $stmt2 = $pdo->prepare("UPDATE teacher_sections SET is_archived = 0 WHERE teacher_id = ? AND section = ?");
                    $stmt2->execute([$teacher_id, $section]);
                }
            } catch (Exception $e) {
                error_log("Note: teacher_sections update skipped: " . $e->getMessage());
            }
            
            // Remove from archive table if it exists
            try {
                if ($pdo->query("SHOW TABLES LIKE 'section_archive'")->rowCount() > 0) {
                    $stmt3 = $pdo->prepare("DELETE FROM section_archive WHERE teacher_id = ? AND section = ?");
                    $stmt3->execute([$teacher_id, $section]);
                }
            } catch (Exception $e) {
                error_log("Note: section_archive cleanup skipped: " . $e->getMessage());
            }
            
            $response['success'] = true;
            $response['message'] = "Section '$section' has been restored successfully";
            if (isset($updatedCount)) {
                $response['students_restored'] = $updatedCount;
            }
            
            error_log("Teacher $teacher_id restored section: $section");
            
        } catch (PDOException $e) {
            // If leaderboard update fails, try to remove from archive table
            try {
                if ($pdo->query("SHOW TABLES LIKE 'section_archive'")->rowCount() > 0) {
                    $stmt = $pdo->prepare("DELETE FROM section_archive WHERE teacher_id = ? AND section = ?");
                    $stmt->execute([$teacher_id, $section]);
                    
                    if ($stmt->rowCount() > 0) {
                        $response['success'] = true;
                        $response['message'] = "Section '$section' has been restored successfully (from archive table)";
                    } else {
                        $response['error'] = 'Section was not found in archive';
                    }
                } else {
                    $response['error'] = 'Archive system not properly configured';
                }
            } catch (PDOException $e2) {
                error_log("Failed to restore from archive table: " . $e2->getMessage());
                throw $e; // Re-throw original error
            }
        }
        
    } else {
        $response['error'] = 'Invalid request method. POST required.';
    }
} catch (PDOException $e) {
    error_log("Database error in restore_section.php: " . $e->getMessage());
    $response['error'] = 'Database error occurred: ' . $e->getMessage();
} catch (Exception $e) {
    error_log("General error in restore_section.php: " . $e->getMessage());
    $response['error'] = 'An error occurred';
}

echo json_encode($response);
?>