<?php
class Config {
    // Database
    public static $db_host;
    public static $db_user;
    public static $db_pass;
    public static $db_name;
    public static $db_port;
    
    // Security
    public static $webhook_secret;
    public static $max_sessions;
    public static $heartbeat_timeout;
    
    // Email
    public static $smtp_host;
    public static $smtp_port;
    public static $smtp_user;
    public static $smtp_pass;
    public static $from_email;
    
    public static function init() {
        // Database
        self::$db_host = getenv('DB_HOST') ?: 'localhost';
        self::$db_user = getenv('DB_USER') ?: 'postgres';
        self::$db_pass = getenv('DB_PASS') ?: '';
        self::$db_name = getenv('DB_NAME') ?: 'mt5_licensing';
        self::$db_port = getenv('DB_PORT') ?: '5432';
        
        // Security
        self::$webhook_secret = getenv('WEBHOOK_SECRET') ?: 'your-webhook-secret';
        self::$max_sessions = getenv('MAX_SESSIONS') ?: 3;
        self::$heartbeat_timeout = getenv('HEARTBEAT_TIMEOUT') ?: 300;
        
        // Email
        self::$smtp_host = getenv('SMTP_HOST') ?: 'smtp.gmail.com';
        self::$smtp_port = getenv('SMTP_PORT') ?: '587';
        self::$smtp_user = getenv('SMTP_USER') ?: '';
        self::$smtp_pass = getenv('SMTP_PASS') ?: '';
        self::$from_email = getenv('FROM_EMAIL') ?: 'noreply@handsfree.com';
    }
    
    public static function getDB() {
        try {
            $dsn = "pgsql:host=" . self::$db_host . ";port=" . self::$db_port . ";dbname=" . self::$db_name;
            $pdo = new PDO($dsn, self::$db_user, self::$db_pass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            return $pdo;
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            return null;
        }
    }
}

Config::init();
?>