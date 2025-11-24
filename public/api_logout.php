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
    // Buscar sessão para log
    $stmt = $pdo->prepare("SELECT user_id FROM sessions WHERE session_token = ?");
    $stmt->execute([$session_token]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($session) {
        // Log do logout
        $stmt = $pdo->prepare("
            INSERT INTO access_logs (user_id, action, ip_address, user_agent) 
            VALUES (?, 'logout', ?, ?)
        ");
        $stmt->execute([$session['user_id'], $_SERVER['REMOTE_ADDR'] ?? 'unknown', $_SERVER['HTTP_USER_AGENT'] ?? 'unknown']);
    }
    
    // Remover sessão
    $stmt = $pdo->prepare("DELETE FROM sessions WHERE session_token = ?");
    $stmt->execute([$session_token]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode([
            'status' => 'success',
            'message' => 'Logout successful'
        ]);
    } else {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Session not found']);
    }
    
} catch (PDOException $e) {
    error_log("Logout error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Internal server error']);
}
?>