<?php
declare(strict_types=1);

// Start the session to access $_SESSION variables
if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json; charset=utf-8');

// Function for JSON response
function json_response(array $data, int $status = 200): void {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

if (isset($_SESSION['user_id']) && isset($_SESSION['user_role'])) {
    
    json_response([
        'success' => true,
        'user_id' => $_SESSION['user_id'],
        'user_role' => $_SESSION['user_role']
    ], 200);

} else {
    
    json_response([
        'success' => false, 
        'error' => 'Not logged in'
    ], 401);
}
?>