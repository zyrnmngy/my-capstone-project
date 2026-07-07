<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Database configuration
$servername = "localhost";
$username = "u769346877_filibustero";
$password = "Filibustero_capstone08";
$dbname = "u769346877_filibustero_db";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["error" => "Database connection failed: " . $conn->connect_error]);
    exit;
}

// Get user_id from query parameter
$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

if ($user_id <= 0) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid user ID"]);
    exit;
}

try {
    // Get current user's section information
    $current_section_query = "
        SELECT 
            s.section,
            s.rizal_professor as teacher,
            COUNT(st.user_id) as student_count,
            AVG(gp.progress_percentage) as avg_progress,
            AVG(gp.score) as avg_score,
            AVG(gp.current_stage) as avg_stage,
            SUM(CASE WHEN gp.game_completed = 1 THEN 1 ELSE 0 END) as completed_count
        FROM students s
        LEFT JOIN students st ON s.section = st.section AND s.rizal_professor = st.rizal_professor
        LEFT JOIN users u ON st.user_id = u.id
        LEFT JOIN players p ON u.id = p.user_id
        LEFT JOIN game_progress gp ON p.id = gp.player_id
        WHERE s.user_id = ?
        GROUP BY s.section, s.rizal_professor
    ";

    $stmt = $conn->prepare($current_section_query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $current_section_result = $stmt->get_result();
    
    $current_section = null;
    if ($current_section_result->num_rows > 0) {
        $current_section = $current_section_result->fetch_assoc();
    }
    $stmt->close();

    // If no current section found, check if user exists and is a student
    if (!$current_section) {
        $check_user_query = "SELECT user_type FROM users WHERE id = ?";
        $stmt = $conn->prepare($check_user_query);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $user_result = $stmt->get_result();
        
        if ($user_result->num_rows === 0) {
            http_response_code(404);
            echo json_encode(["error" => "User not found"]);
            exit;
        }
        
        $user = $user_result->fetch_assoc();
        if ($user['user_type'] !== 'student') {
            http_response_code(400);
            echo json_encode(["error" => "User is not a student"]);
            exit;
        }
        $stmt->close();
    }

    // Get all other sections (excluding current user's section)
    $other_sections_query = "
        SELECT 
            s.section,
            s.rizal_professor as teacher,
            COUNT(DISTINCT st.user_id) as student_count,
            AVG(gp.progress_percentage) as avg_progress,
            AVG(gp.score) as avg_score,
            AVG(gp.current_stage) as avg_stage,
            SUM(CASE WHEN gp.game_completed = 1 THEN 1 ELSE 0 END) as completed_count
        FROM students s
        LEFT JOIN students st ON s.section = st.section AND s.rizal_professor = st.rizal_professor
        LEFT JOIN users u ON st.user_id = u.id
        LEFT JOIN players p ON u.id = p.user_id
        LEFT JOIN game_progress gp ON p.id = gp.player_id
        WHERE s.user_id != ? 
        GROUP BY s.section, s.rizal_professor
        HAVING COUNT(st.user_id) > 0
        ORDER BY avg_progress DESC
    ";

    $stmt = $conn->prepare($other_sections_query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $other_sections_result = $stmt->get_result();
    
    $other_sections = [];
    while ($row = $other_sections_result->fetch_assoc()) {
        // Ensure numeric values are properly formatted
        $row['avg_progress'] = floatval($row['avg_progress']);
        $row['avg_score'] = floatval($row['avg_score']);
        $row['avg_stage'] = floatval($row['avg_stage']);
        $row['student_count'] = intval($row['student_count']);
        $row['completed_count'] = intval($row['completed_count']);
        $other_sections[] = $row;
    }
    $stmt->close();

    // Prepare response
    $response = [
        'currentSection' => $current_section,
        'otherSections' => $other_sections,
        'success' => true
    ];

    echo json_encode($response);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => "Server error: " . $e->getMessage()]);
}

$conn->close();
?>