<?php

session_start();
header('Content-Type: application/json');

// Get action from query string
$action = $_GET['action'] ?? '';


require_once "../../config/db.php"; 

require_once "../../lib/util.php";

// --- Utility function to read JSON input (required for fetch API) ---
function get_json_input() {
    $input = file_get_contents('php://input');
    return json_decode($input, true);
}


// ####################################################################
// 1. REGISTER
// ####################################################################

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'register') {
    
    $data = get_json_input();
    $name = trim($data['name'] ?? '');
    $email = strtolower(trim($data['email'] ?? ''));
    $password = $data['password'] ?? '';

    // Validation
    if (!$name || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
        http_response_code(400); 
        echo json_encode(['success' => false, 'error' => 'Invalid input: Name, valid email, and minimum 8 character password required.']); 
        exit;
    }

    // Check if email exists
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([$email]);
    if ($stmt->fetch()) { 
        http_response_code(409); 
        echo json_encode(['success' => false, 'error' => 'Email address already registered.']); 
        exit; 
    }

    // Set defaults (CRITICAL FIX)
    $default_role = 'user'; 
    $is_active = 1;

    // Insert user
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare('INSERT INTO users (name, email, password, role, is_active) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$name, $email, $hash, $default_role, $is_active]);

    echo json_encode(['success' => true, 'message' => 'Registration successful.']);
    exit;
}


// ####################################################################
// 2. LOGIN
// ####################################################################

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'login') {
    
    $data = get_json_input();
    $email = strtolower(trim($data['email'] ?? ''));
    $password = $data['password'] ?? '';

    // Fetch user including role, and check for active status (CRITICAL FIX)
    $stmt = $pdo->prepare('SELECT id, password, role FROM users WHERE email = ? AND is_active = 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password'])) {
        // Successful login
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_role'] = $user['role']; // CRITICAL FIX: Store the role
        
        echo json_encode(['success' => true, 'message' => 'Login successful.', 'redirect' => '../tickets.html']);
        exit;

    } else {
        // Login failed (invalid creds or inactive account)
        http_response_code(401); 
        echo json_encode(['success' => false, 'error' => 'Invalid credentials or inactive account.']); 
        exit;
    }
}


// ####################################################################
// 3. LOGOUT (Added for completeness)
// ####################################################################

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'logout') {
    
    session_unset();
    session_destroy();

    echo json_encode(['success' => true, 'message' => 'Logged out successfully.']);
    exit;
}


// Default response if no action matches
http_response_code(400);
echo json_encode(['success' => false, 'error' => 'Invalid action or request method.']);