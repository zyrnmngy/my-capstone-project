<?php
// =============================================
// DEBUG TEACHER SECTIONS - TEST SCRIPT
// =============================================
header('Content-Type: application/json');
require_once 'config.php';

$conn = getDBConnection();

// Get teacher ID from request
$teacherId = $_GET['teacher_id'] ?? $_POST['teacher_id'] ?? 1; // Default to 1 for testing

echo "<h2>Debug Teacher Sections</h2>";
echo "<p>Testing teacher ID: $teacherId</p>";

try {
    // 1. Check if teacher exists in users table
    echo "<h3>1. Check Teacher in Users Table:</h3>";
    $stmt = $conn->prepare("SELECT id, full_name, user_type FROM users WHERE id = ? AND user_type = 'teacher'");
    $stmt->bind_param("i", $teacherId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        echo "<p>✓ Teacher found: " . $row['full_name'] . " (ID: " . $row['id'] . ")</p>";
    } else {
        echo "<p>✗ Teacher not found with ID: $teacherId</p>";
        echo "<p>Available teachers:</p>";
        
        // Show all teachers
        $stmt2 = $conn->prepare("SELECT id, full_name FROM users WHERE user_type = 'teacher'");
        $stmt2->execute();
        $result2 = $stmt2->get_result();
        
        while ($teacher = $result2->fetch_assoc()) {
            echo "<p>- ID: {$teacher['id']}, Name: {$teacher['full_name']}</p>";
        }
    }
    
    // 2. Check teacher_sections table
    echo "<h3>2. Check Teacher Sections Table:</h3>";
    $stmt = $conn->prepare("SELECT section FROM teacher_sections WHERE teacher_id = ?");
    $stmt->bind_param("i", $teacherId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $sections = [];
    while ($row = $result->fetch_assoc()) {
        $sections[] = $row['section'];
    }
    
    if (!empty($sections)) {
        echo "<p>✓ Sections found: " . implode(', ', $sections) . "</p>";
    } else {
        echo "<p>✗ No sections found for teacher ID: $teacherId</p>";
    }
    
    // 3. Check all entries in teacher_sections table
    echo "<h3>3. All Teacher-Section Mappings:</h3>";
    $stmt = $conn->prepare("
        SELECT ts.teacher_id, ts.section, u.full_name 
        FROM teacher_sections ts 
        LEFT JOIN users u ON ts.teacher_id = u.id 
        ORDER BY ts.teacher_id
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        echo "<table border='1'>";
        echo "<tr><th>Teacher ID</th><th>Teacher Name</th><th>Section</th></tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>{$row['teacher_id']}</td>";
            echo "<td>{$row['full_name']}</td>";
            echo "<td>{$row['section']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No teacher-section mappings found!</p>";
    }
    
    // 4. Test the API call
    echo "<h3>4. Test API Response:</h3>";
    
    // Simulate the actual API call
    $stmt = $conn->prepare("SELECT section FROM teacher_sections WHERE teacher_id = ?");
    $stmt->bind_param("i", $teacherId);
    $stmt->execute();
    $result = $stmt->get_result();
    $sections = [];
    
    while ($row = $result->fetch_assoc()) {
        $sections[] = $row['section'];
    }
    
    $apiResponse = [
        'success' => true, 
        'sections' => $sections,
        'teacher_id' => $teacherId
    ];
    
    echo "<pre>" . json_encode($apiResponse, JSON_PRETTY_PRINT) . "</pre>";
    
    // 5. Check table structure
    echo "<h3>5. Table Structure Check:</h3>";
    $result = $conn->query("DESCRIBE teacher_sections");
    if ($result) {
        echo "<table border='1'>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>{$row['Field']}</td>";
            echo "<td>{$row['Type']}</td>";
            echo "<td>{$row['Null']}</td>";
            echo "<td>{$row['Key']}</td>";
            echo "<td>{$row['Default']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
} catch (Exception $e) {
    echo "<p>Error: " . $e->getMessage() . "</p>";
}

$conn->close();

// Add links for easy testing
echo "<hr>";
echo "<h3>Test Links:</h3>";
for ($i = 1; $i <= 3; $i++) {
    echo "<a href='?teacher_id=$i'>Test Teacher ID $i</a> | ";
}
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
table { border-collapse: collapse; margin: 10px 0; }
th, td { padding: 8px; text-align: left; }
th { background-color: #f2f2f2; }
pre { background-color: #f5f5f5; padding: 10px; border-radius: 4px; }
</style>