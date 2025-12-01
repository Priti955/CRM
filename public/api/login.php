<?php
declare(strict_types=1);

session_start();

// Display errors only for development, otherwise log them.
ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

// Best Practice: Restrict Access-Control-Allow-Origin in production 
// to only your frontend domain (e.g., http://localhost) instead of '*'.
header('Access-Control-Allow-Origin: http://localhost'); // Use your specific frontend URL
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
// Allow cookies/sessions to be sent across origins if needed (required for session/cookie auth)
header('Access-Control-Allow-Credentials: true');

require_once __DIR__ . '/../../config/db.php';

function json_response(array $data, int $status = 200): void {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// --------------------------------------------------------------------------------
// 1. ADD SECURITY CHECK: Block already logged-in users
// --------------------------------------------------------------------------------

if (isset($_SESSION['user_id'])) {
    json_response(['success' => false, 'error' => 'Already logged in'], 403);
}

// Allow OPTIONS preflight for security tools
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Only POST allowed
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'error' => 'Method not allowed'], 405);
}

// Sanitize inputs
// Use FILTER_SANITIZE_EMAIL for email, even though trim is used.
$email = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
$password = trim($_POST['password'] ?? '');

if ($email === '' || $password === '') {
    json_response(['success' => false, 'error' => 'Email and password are required'], 400);
}

try {
    $stmt = $pdo->prepare("
        SELECT id, password, is_active 
        FROM users 
        WHERE email = ? AND deleted_at IS NULL 
        LIMIT 1
    ");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // ------------------------------------------------------------------------
    // 2. USE GENERIC ERROR MESSAGE to prevent user enumeration
    // ------------------------------------------------------------------------
    if (!$user || !password_verify($password, $user['password'])) {
        // Delay response to mitigate brute-force attacks (optional, but good)
        usleep(500000); // 500ms delay
        json_response(['success' => false, 'error' => 'Invalid credentials'], 401);
    }
    
    // ------------------------------------------------------------------------
    // 3. CHECK FOR ACTIVE STATUS
    // ------------------------------------------------------------------------
    if ($user['is_active'] === 0) {
        json_response(['success' => false, 'error' => 'Account is inactive. Please contact support.'], 403);
    }

    // Start session
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$user['id'];

    json_response([
        'success' => true,
        'message' => 'Logged in successfully',
        'user_id' => $_SESSION['user_id']
    ]);

} catch (PDOException $e) {
    // Log error details privately instead of echoing them to the user
    error_log('Login PDO Error: ' . $e->getMessage()); 
    json_response([
        'success' => false,
        'error' => 'A server error occurred during login.'
    ], 500);
}