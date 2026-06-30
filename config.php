<?php
// Start secure session if not already started
if (session_status() === PHP_SESSION_NONE) {
    // Set session cookie parameters for security
    session_set_cookie_params([
        'lifetime' => 86400, // 1 day
        'path' => '/',
        'secure' => isset($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
    session_start();
}

// Database Credentials
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'stock_portfolio');

// Establish PDO connection
try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (PDOException $e) {
    // In production, log error and show generic message. For XAMPP environment, output connection issue.
    die("Database connection failed. Please ensure MySQL is running in XAMPP and the database 'stock_portfolio' is imported. Error: " . $e->getMessage());
}

// Ensure uploads directory exists for stock logos
$upload_dir = __DIR__ . '/uploads';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// Helpers/Utility Functions

/**
 * Sanitizes user input to prevent XSS attacks.
 */
function sanitize($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * Checks if the current user is logged in.
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['username']);
}

/**
 * Enforces authentication on private pages.
 */
function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: login.php");
        exit;
    }
}

/**
 * Sets or retrieves flash messages.
 */
function flash($name = '', $message = '', $class = 'success') {
    if (!empty($name)) {
        if (!empty($message)) {
            $_SESSION['flash'][$name] = [
                'message' => $message,
                'class' => $class
            ];
        } else {
            if (isset($_SESSION['flash'][$name])) {
                $flash = $_SESSION['flash'][$name];
                unset($_SESSION['flash'][$name]);
                return $flash;
            }
        }
    }
    return null;
}
?>
