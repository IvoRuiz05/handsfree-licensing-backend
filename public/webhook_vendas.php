<?php
header('Content-Type: application/json');
require_once '../config.php';
require_once '../vendor/autoload.php';

function sendWelcomeEmail($user_email, $license_key, $expires_at, $account_number, $broker_server) {
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
        $mail->Subject = 'Bem-vindo ao Handsfree Licensing';
        $mail->Body = "
            <h2>Bem-vindo ao Handsfree Licensing!</h2>
            <p>Sua licença foi ativada com sucesso.</p>
            <p><strong>Email:</strong> $user_email</p>
            <p><strong>Chave de Licença:</strong> $license_key</p>
            <p><strong>Número da Conta:</strong> $account_number</p>
            <p><strong>Servidor do Broker:</strong> $broker_server</p>
            <p><strong>Expira em:</strong> " . date('d/m/Y', strtotime($expires_at)) . "</p>
            <br>
            <p>Use estas credenciais para fazer login no seu sistema MT5.</p>
            <p><strong>Mantenha estas informações em local seguro!</strong></p>
        ";
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Welcome email failed: " . $e->getMessage());
        return false;
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

// Verificar assinatura do webhook
$signature = $_SERVER['HTTP_X_WEBHOOK_SIGNATURE'] ?? '';
$payload = file_get_contents('php://input');

if (empty($signature) || !hash_equals($signature, hash_hmac('sha256', $payload, Config::$webhook_secret))) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Invalid webhook signature']);
    exit;
}

$data = json_decode($payload, true);
$customer_email = $data['customer_email'] ?? '';
$product_name = $data['product_name'] ?? '';
$license_duration_days = $data['license_duration_days'] ?? 30;
$license_key = $data['license_key'] ?? '';
$account_number = $data['account_number'] ?? '';
$broker_server = $data['broker_server'] ?? '';

if (empty($customer_email) || empty($license_key)) {
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
    $pdo->beginTransaction();
    
    // Calcular data de expiração
    $expires_at = date('Y-m-d H:i:s', strtotime("+$license_duration_days days"));
    
    // Gerar senha padrão
    $default_password = bin2hex(random_bytes(8));
    $password_hash = password_hash($default_password, PASSWORD_DEFAULT);
    
    // Verificar se license_key já existe
    $stmt = $pdo->prepare("SELECT id FROM users WHERE license_key = ?");
    $stmt->execute([$license_key]);
    
    if ($stmt->fetch()) {
        http_response_code(409);
        echo json_encode(['status' => 'error', 'message' => 'License key already exists']);
        $pdo->rollBack();
        exit;
    }
    
    // Criar usuário
    $stmt = $pdo->prepare("
        INSERT INTO users (email, password_hash, license_key, account_number, broker_server, expires_at) 
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$customer_email, $password_hash, $license_key, $account_number, $broker_server, $expires_at]);
    $user_id = $pdo->lastInsertId();
    
    // Registrar venda
    $stmt = $pdo->prepare("
        INSERT INTO sales (customer_email, product_name, license_duration_days, license_key, is_used, used_at) 
        VALUES (?, ?, ?, ?, true, NOW())
    ");
    $stmt->execute([$customer_email, $product_name, $license_duration_days, $license_key]);
    
    // Enviar email de boas-vindas
    sendWelcomeEmail($customer_email, $license_key, $expires_at, $account_number, $broker_server);
    
    $pdo->commit();
    
    echo json_encode([
        'status' => 'success',
        'message' => 'User created successfully',
        'user' => [
            'id' => $user_id,
            'email' => $customer_email,
            'license_key' => $license_key,
            'expires_at' => $expires_at,
            'default_password' => $default_password // Apenas para debug, remover em produção
        ]
    ]);
    
} catch (PDOException $e) {
    $pdo->rollBack();
    error_log("Webhook error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Internal server error']);
}
?>