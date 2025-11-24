<?php
header('Content-Type: application/json');
require_once '../config.php';

$response = [
    'status' => 'success',
    'message' => 'Handsfree MT5 Licensing API',
    'version' => '1.0.0',
    'endpoints' => [
        'POST /api_login.php' => 'User login',
        'POST /api_ping.php' => 'Keep session alive',
        'POST /api_check.php' => 'Check session',
        'POST /api_logout.php' => 'User logout',
        'POST /webhook_vendas.php' => 'Webhook for user creation'
    ]
];

echo json_encode($response);
?>