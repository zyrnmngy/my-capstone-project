<?php
// create_progress_table.php - Run this once to create the progress table
header('Content-Type: application/json');

// Database configuration
$host = 'localhost';
$dbname = 'u769346877_filibustero_db';
$username = 'u769346877_filibustero';
$port= '3306';
$password = 'Filibustero_capstone08';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create user_progress table
    $sql = "
        CREATE TABLE IF NOT EXISTS user_progress (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            overall_progress DECIMAL(5,2) DEFAULT 0.00,
            story_progress DECIMAL(5,2) DEFAULT 0.00,
            chapter_progress DECIMAL(5,2) DEFAULT 0.00,
            quest_progress DECIMAL(5,2) DEFAULT 0.00,
            item_progress DECIMAL(5,2) DEFAULT 0.00,
            achievement_progress DECIMAL(5,2) DEFAULT 0.00,
            playtime_hours INT DEFAULT 0,
            last_save_date DATETIME DEFAULT CURRENT_TIMESTAMP,
            completed_switches INT DEFAULT 0,
            completed_chapters INT DEFAULT 0,
            completed_quests INT DEFAULT 0,
            collected_items INT DEFAULT 0,
            unlocked_achievements INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_user_progress (user_id),
            INDEX idx_user_id (user_id),
            INDEX idx_updated_at (updated_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    
    $pdo->exec($sql);
    
    echo json_encode([
        'success' => true, 
        'message' => 'User progress table created successfully'
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false, 
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
?>