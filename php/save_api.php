<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
session_start();

// Database connection
$db = new mysqli('localhost', 'u769346877_filibustero', 'Filibustero_capstone08', 'u769346877_filibustero_db');

if ($db->connect_error) {
    die(json_encode(['success' => false, 'error' => 'Database connection failed']));
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch($action) {
    case 'save':
        saveGame($db);
        break;
    case 'load':
        loadGame($db);
        break;
    case 'list':
        listSaves($db);
        break;
    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
}

function saveGame($db) {
    $userId = $_SESSION['user_id'] ?? null;
    $saveSlot = $_POST['slot'] ?? 1;
    $saveData = $_POST['data'] ?? '';
    
    if (!$userId) {
        echo json_encode(['success' => false, 'error' => 'Not logged in']);
        return;
    }
    
    $stmt = $db->prepare("INSERT INTO game_saves (user_id, save_slot, save_data) VALUES (?, ?, ?) 
                          ON DUPLICATE KEY UPDATE save_data = ?, updated_at = CURRENT_TIMESTAMP");
    $stmt->bind_param('iiss', $userId, $saveSlot, $saveData, $saveData);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Save failed']);
    }
}

function loadGame($db) {
    $userId = $_SESSION['user_id'] ?? null;
    $saveSlot = $_GET['slot'] ?? 1;
    
    if (!$userId) {
        echo json_encode(['success' => false, 'error' => 'Not logged in']);
        return;
    }
    
    $stmt = $db->prepare("SELECT save_data FROM game_saves WHERE user_id = ? AND save_slot = ?");
    $stmt->bind_param('ii', $userId, $saveSlot);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        echo json_encode(['success' => true, 'data' => $row['save_data']]);
    } else {
        echo json_encode(['success' => false, 'error' => 'No save found']);
    }
}

function listSaves($db) {
    $userId = $_SESSION['user_id'] ?? null;
    
    if (!$userId) {
        echo json_encode(['success' => false, 'error' => 'Not logged in']);
        return;
    }
    
    $stmt = $db->prepare("SELECT save_slot, updated_at FROM game_saves WHERE user_id = ?");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $saves = [];
    while ($row = $result->fetch_assoc()) {
        $saves[] = $row;
    }
    
    echo json_encode(['success' => true, 'saves' => $saves]);
}
?>