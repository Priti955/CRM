<?php
declare(strict_types=1);

session_start();

// Display errors only for development
ini_set('display_errors', 1); 
error_reporting(E_ALL);

// --- UTIL FUNCTION (Define if not in util.php) ---
function json_response(array $data, int $status = 200): void {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
// -------------------------------------------------

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: http://localhost'); // Adjust to your frontend domain
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');

require_once "../../config/db.php"; // Adjust path if needed

// Block already logged-in users from registering
if (isset($_SESSION['user_id'])) {
    json_response(['success' => false, 'error' => 'Logout first to register a new account.'], 403);
}

// Only POST allowed
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'error' => 'Method not allowed'], 405);
}

// Sanitize inputs
$name = trim($_POST['name'] ?? '');
$email = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
$password = $_POST['password'] ?? '';
// --- NEW: Capture password confirmation ---
$passwordConfirmation = $_POST['password_confirmation'] ?? '';

$errors = [];

// Basic validation
if (strlen($name) < 2) {
    $errors['name'] = "Name must be at least 2 characters";
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = "Invalid email address";
}
if (strlen($password) < 8) {
    $errors['password'] = "Password must be at least 8 characters";
}
// --- CRITICAL ADDITION: Password confirmation check ---
if ($password !== $passwordConfirmation) {
    $errors['password_confirmation'] = "Passwords do not match";
}

if (!empty($errors)) {
    // 422 Unprocessable Entity is standard for validation errors
    json_response(['success' => false, 'errors' => $errors], 422);
}

// Hash password
$hashed = password_hash($password, PASSWORD_BCRYPT);
// Define default role and status
$defaultRole = 'user';
$isActive = 1;

try {
    $stmt = $pdo->prepare("
        INSERT INTO users (name, email, password, role, is_active) 
        VALUES (?, ?, ?, ?, ?)
    ");
    
    // Execute with the cleaned data, default role, and active status
    $stmt->execute([$name, $email, $hashed, $defaultRole, $isActive]);

    // Optional: Log the user in immediately after successful registration
    /*
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$pdo->lastInsertId();
    // Update the message if auto-logging in
    $successMessage = "Registration successful. You are now logged in."; 
    */
    $successMessage = "User registered successfully.";

    json_response([
        "success" => true,
        "message" => $successMessage
    ]);

} catch (PDOException $e) {
    if ($e->getCode() == '23000') { // SQL state for integrity constraint violation (e.g., duplicate unique key on email)
        // 409 Conflict is standard for resource conflict (duplicate email)
        json_response(["success" => false, "error" => "Email already registered."], 409);
    } else {
        // Log the error internally and return a generic message to the client
        error_log('Registration PDO Error: ' . $e->getMessage()); 
        json_response([
            "success" => false,
            "error" => "A server error occurred during registration."
        ], 500);
    }
}