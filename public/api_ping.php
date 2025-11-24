<?php
header('Content-Type: application/json');
require_once '../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$session_token = $input['session_token'] ?? '';

if (empty($session_token)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Session token required']);
    exit;
}

$pdo = Config::getDB();
if (!$pdo) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed']);
    exit;
}

try {
    // Atualizar last_ping
    $stmt = $pdo->prepare("
        UPDATE sessions 
        SET last_ping = NOW() 
        WHERE session_token = ? AND EXTRACT(EPOCH FROM (NOW() - last_ping)) <= ?
        RETURNING user_id
    ");
    $stmt->execute([$session_token, Config::$heartbeat_timeout]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$session) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Invalid or expired session']);
        exit;
    }
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Ping successful',
        'timestamp' => date('c')
    ]);
    
} catch (PDOException $e) {
    error_log("Ping error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Internal server error']);
}
?>