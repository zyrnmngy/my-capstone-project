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

$response = ['success' => false, 'sections' => [], 'error' => ''];

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $teacher_id = $_POST['teacher_id'] ?? '';
        $include_archived = isset($_POST['include_archived']) ? filter_var($_POST['include_archived'], FILTER_VALIDATE_BOOLEAN) : false;

        // Modify your query based on this
        if ($include_archived) {
            // Show all sections including archived
            $query = "SELECT ... FROM ... WHERE teacher_id = ?";
        } else {
            // Show only non-archived sections
            $query = "SELECT ... FROM ... WHERE teacher_id = ? AND is_archived = 0";
        }
        
        if (empty($teacher_id)) {
            $response['error'] = 'Teacher ID is required';
            echo json_encode($response);
            exit();
        }
        
        // Check multiple sources for sections
        $allSections = [];
        
        // 1. First try leaderboard table (your primary data source)
        try {
            // Check if is_archived column exists in leaderboard
            $checkColumnStmt = $pdo->prepare("SHOW COLUMNS FROM leaderboard LIKE 'is_archived'");
            $checkColumnStmt->execute();
            $hasArchiveColumn = $checkColumnStmt->rowCount() > 0;
            
            if ($hasArchiveColumn) {
                if ($include_archived) {
                    $query = "
                        SELECT 
                            section,
                            COUNT(DISTINCT player_id) as student_count,
                            MAX(is_archived) as is_archived
                        FROM leaderboard 
                        WHERE teacher_id = ?
                        GROUP BY section
                        ORDER BY is_archived ASC, section ASC
                    ";
                } else {
                    $query = "
                        SELECT 
                            section,
                            COUNT(DISTINCT player_id) as student_count,
                            0 as is_archived
                        FROM leaderboard 
                        WHERE teacher_id = ? AND (is_archived = 0 OR is_archived IS NULL)
                        GROUP BY section
                        ORDER BY section ASC
                    ";
                }
            } else {
                // No archive column, check archive table
                $query = "
                    SELECT 
                        section,
                        COUNT(DISTINCT player_id) as student_count,
                        0 as is_archived
                    FROM leaderboard 
                    WHERE teacher_id = ?
                    GROUP BY section
                    ORDER BY section ASC
                ";
            }
            
            $stmt = $pdo->prepare($query);
            $stmt->execute([$teacher_id]);
            $leaderboardSections = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Check archive table for archived sections
            if ($pdo->query("SHOW TABLES LIKE 'section_archive'")->rowCount() > 0) {
                $archiveStmt = $pdo->prepare("SELECT section FROM section_archive WHERE teacher_id = ?");
                $archiveStmt->execute([$teacher_id]);
                $archivedSections = $archiveStmt->fetchAll(PDO::FETCH_COLUMN);
                
                // Mark sections as archived if they're in the archive table
                foreach ($leaderboardSections as &$section) {
                    if (in_array($section['section'], $archivedSections)) {
                        $section['is_archived'] = 1;
                    } elseif (!isset($section['is_archived'])) {
                        $section['is_archived'] = 0;
                    }
                }
            } else {
                // Ensure is_archived is set
                foreach ($leaderboardSections as &$section) {
                    if (!isset($section['is_archived'])) {
                        $section['is_archived'] = 0;
                    }
                }
            }
            
            $allSections = array_merge($allSections, $leaderboardSections);
            
        } catch (Exception $e) {
            error_log("Leaderboard sections query failed: " . $e->getMessage());
        }
        
        // 2. Also check teacher_sections table if it exists
        try {
            if ($pdo->query("SHOW TABLES LIKE 'teacher_sections'")->rowCount() > 0) {
                $checkColumnStmt = $pdo->prepare("SHOW COLUMNS FROM teacher_sections LIKE 'is_archived'");
                $checkColumnStmt->execute();
                $hasArchiveColumn = $checkColumnStmt->rowCount() > 0;
                
                if ($hasArchiveColumn) {
                    if ($include_archived) {
                        $query = "
                            SELECT 
                                ts.section,
                                COUNT(DISTINCT l.player_id) as student_count,
                                COALESCE(ts.is_archived, 0) as is_archived
                            FROM teacher_sections ts
                            LEFT JOIN leaderboard l ON ts.section = l.section
                            WHERE ts.teacher_id = ?
                            GROUP BY ts.section, ts.is_archived
                            ORDER BY ts.is_archived ASC, ts.section ASC
                        ";
                    } else {
                        $query = "
                            SELECT 
                                ts.section,
                                COUNT(DISTINCT l.player_id) as student_count,
                                0 as is_archived
                            FROM teacher_sections ts
                            LEFT JOIN leaderboard l ON ts.section = l.section
                            WHERE ts.teacher_id = ? AND (ts.is_archived = 0 OR ts.is_archived IS NULL)
                            GROUP BY ts.section
                            ORDER BY ts.section ASC
                        ";
                    }
                } else {
                    $query = "
                        SELECT 
                            ts.section,
                            COUNT(DISTINCT l.player_id) as student_count,
                            0 as is_archived
                        FROM teacher_sections ts
                        LEFT JOIN leaderboard l ON ts.section = l.section
                        WHERE ts.teacher_id = ?
                        GROUP BY ts.section
                        ORDER BY ts.section ASC
                    ";
                }
                
                $stmt = $pdo->prepare($query);
                $stmt->execute([$teacher_id]);
                $teacherSections = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Merge sections, avoiding duplicates
                foreach ($teacherSections as $ts) {
                    $found = false;
                    foreach ($allSections as &$as) {
                        if ($as['section'] === $ts['section']) {
                            $found = true;
                            // Update student count if higher
                            if ($ts['student_count'] > $as['student_count']) {
                                $as['student_count'] = $ts['student_count'];
                            }
                            // If either source says archived, mark as archived
                            if ($ts['is_archived'] == 1) {
                                $as['is_archived'] = 1;
                            }
                            break;
                        }
                    }
                    if (!$found) {
                        $allSections[] = $ts;
                    }
                }
            }
        } catch (Exception $e) {
            error_log("Teacher sections query failed: " . $e->getMessage());
        }
        
        // Filter out archived sections if not requested
        if (!$include_archived) {
            $allSections = array_filter($allSections, function($section) {
                return !isset($section['is_archived']) || $section['is_archived'] == 0;
            });
            $allSections = array_values($allSections); // Reindex
        }
        
        // Ensure all sections have required fields
        foreach ($allSections as &$section) {
            if (!isset($section['student_count'])) {
                $section['student_count'] = 0;
            }
            if (!isset($section['is_archived'])) {
                $section['is_archived'] = 0;
            }
            // Convert to proper types
            $section['student_count'] = (int)$section['student_count'];
            $section['is_archived'] = (int)$section['is_archived'];
        }
        
        $response['success'] = true;
        $response['sections'] = $allSections;
        
        error_log("Teacher sections query successful for teacher_id: $teacher_id, found " . count($allSections) . " sections");
        
    } else {
        $response['error'] = 'Invalid request method. POST required.';
    }
} catch (PDOException $e) {
    error_log("Database error in get_teacher_sections.php: " . $e->getMessage());
    $response['error'] = 'Database error occurred: ' . $e->getMessage();
} catch (Exception $e) {
    error_log("General error in get_teacher_sections.php: " . $e->getMessage());
    $response['error'] = 'An error occurred: ' . $e->getMessage();
}

echo json_encode($response);
?>