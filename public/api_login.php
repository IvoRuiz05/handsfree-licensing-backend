<?php
header('Content-Type: application/json');
require_once '../config.php';
require_once '../vendor/autoload.php';

function cleanSessions($pdo, $user_id) {
    $timeout = Config::$heartbeat_timeout;
    $stmt = $pdo->prepare("DELETE FROM sessions WHERE user_id = ? AND EXTRACT(EPOCH FROM (NOW() - last_ping)) > ?");
    $stmt->execute([$user_id, $timeout]);
}

function sendLoginEmail($user_email, $ip_address, $user_agent) {
    try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        
        $mail->isSMTP();
        $mail->Host = Config::$smtp_host;
        $mail->SMTPAuth = true;
        $mail->Username = Config::$smtp_user;
        $mail->Password = Config::$smtp_pass;
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = Config::$smtp_port;
        
        $mail->setFrom(Config::$from_email, 'Handsfree Licensing');
        $mail->addAddress($user_email);
        
        $mail->isHTML(true);
        $mail->Subject = 'Novo Login Detectado - Handsfree Licensing';
        $mail->Body = "
            <h2>Novo Login Detectado</h2>
            <p>Um novo login foi realizado na sua conta Handsfree Licensing.</p>
            <p><strong>IP:</strong> $ip_address</p>
            <p><strong>User Agent:</strong> $user_agent</p>
            <p><strong>Data/Hora:</strong> " . date('d/m/Y H:i:s') . "</p>
            <br>
            <p>Se não foi você, entre em contato imediatamente com o suporte.</p>
        ";
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email sending failed: " . $e->getMessage());
        return false;
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$license_key = $input['license_key'] ?? '';
$password = $input['password'] ?? '';
$mt5_login = $input['mt5_login'] ?? '';
$ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

if (empty($license_key) || empty($password) || empty($mt5_login)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Missing required fields']);
    exit;
}

$pdo = Config::getDB();
if (!$pdo) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed']);
    exit;
}

try {
    // Buscar usuário
    $stmt = $pdo->prepare("
        SELECT id, email, password_hash, license_key, is_active, expires_at, max_sessions 
        FROM users 
        WHERE license_key = ? AND is_active = true
    ");
    $stmt->execute([$license_key]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user || !password_verify($password, $user['password_hash'])) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Invalid credentials']);
        exit;
    }
    
    // Verificar se a licença expirou
    if ($user['expires_at'] && strtotime($user['expires_at']) < time()) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'License expired']);
        exit;
    }
    
    // Limpar sessões expiradas
    cleanSessions($pdo, $user['id']);
    
    // Verificar número máximo de sessões
    $stmt = $pdo->prepare("SELECT COUNT(*) as active_sessions FROM sessions WHERE user_id = ?");
    $stmt->execute([$user['id']]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result['active_sessions'] >= $user['max_sessions']) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Maximum sessions reached']);
        exit;
    }
    
    // Gerar token de sessão
    $session_token = bin2hex(random_bytes(32));
    
    // Criar nova sessão
    $stmt = $pdo->prepare("
        INSERT INTO sessions (user_id, session_token, mt5_login, ip_address, user_agent, last_ping) 
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([$user['id'], $session_token, $mt5_login, $ip_address, $user_agent]);
    
    // Log do acesso
    $stmt = $pdo->prepare("
        INSERT INTO access_logs (user_id, action, ip_address, user_agent) 
        VALUES (?, 'login', ?, ?)
    ");
    $stmt->execute([$user['id'], $ip_address, $user_agent]);
    
    // Enviar email de notificação
    sendLoginEmail($user['email'], $ip_address, $user_agent);
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Login successful',
        'session_token' => $session_token,
        'user' => [
            'email' => $user['email'],
            'license_key' => $user['license_key'],
            'max_sessions' => $user['max_sessions']
        ]
    ]);
    
} catch (PDOException $e) {
    error_log("Login error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Internal server error']);
}
?>