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
    // Verificar sessão e usuário
    $stmt = $pdo->prepare("
        SELECT s.*, u.email, u.license_key, u.is_active, u.expires_at
        FROM sessions s
        JOIN users u ON s.user_id = u.id
        WHERE s.session_token = ? AND EXTRACT(EPOCH FROM (NOW() - s.last_ping)) <= ?
    ");
    $stmt->execute([$session_token, Config::$heartbeat_timeout]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$session) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Invalid or expired session']);
        exit;
    }
    
    if (!$session['is_active']) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'User account inactive']);
        exit;
    }
    
    if ($session['expires_at'] && strtotime($session['expires_at']) < time()) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'License expired']);
        exit;
    }
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Session is valid',
        'user' => [
            'email' => $session['email'],
            'license_key' => $session['license_key'],
            'mt5_login' => $session['mt5_login']
        ],
        'session' => [
            'ip_address' => $session['ip_address'],
            'last_ping' => $session['last_ping']
        ]
    ]);
    
} catch (PDOException $e) {
    error_log("Check session error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Internal server error']);
}
?>