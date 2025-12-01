<?php
// logout.php
session_start();

// 1. Clear session variables
$_SESSION = [];
session_unset();
// 2. Destroy session file on server
session_destroy(); 

// 3. FORCE deletion of the session cookie from the browser (CRUCIAL)
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode(['success' => true, 'message' => 'Logged out successfully. Cookie destroyed.']);
exit;
?>