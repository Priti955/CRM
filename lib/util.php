<?php
// lib/util.php
// Utility helpers — do NOT start session or output headers here unconditionally.

// --- CORE RESPONSE UTILITIES ---

function json_response(array $data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function require_json_body(): array {
    $raw = file_get_contents('php://input');
    $body = json_decode($raw, true);
    if (!is_array($body)) {
        // fallback to form POST if present (e.g., if application/x-www-form-urlencoded is used)
        if (!empty($_POST)) return $_POST;
        json_response(['success'=>false,'error' => 'Invalid JSON body'], 400);
    }
    return $body;
}

function sanitize_str($s): string {
    return trim(strip_tags((string)$s));
}

// --- AUTHENTICATION & AUTHORIZATION ---

function require_auth(): int {
    if (session_status() === PHP_SESSION_NONE) {
        // session must already be started by the API entrypoint
        json_response(['success' => false, 'error' => 'Unauthorized'], 401);
    }
    if (empty($_SESSION['user_id'])) json_response(['success' => false, 'error' => 'Unauthorized'], 401);
    return (int)$_SESSION['user_id'];
}

// --- START OF NEW ROLE-BASED FUNCTIONS (CRITICAL ADDITIONS) ---

/**
 * Gets the current user's role from the database.
 * NOTE: Assumes $pdo is available globally (db.php must be included).
 * @param int $userId The ID of the user.
 * @return string|null The role ('user', 'staff', 'admin') or null if not found/error.
 */
function get_user_role(int $userId): ?string {
    global $pdo; // <--- CRUCIAL: Access the PDO connection established in db.php

    if (!$pdo) {
        error_log("PDO object not defined in get_user_role. Check db.php inclusion.");
        return null;
    }

    try {
        $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $result = $stmt->fetchColumn();
        return is_string($result) ? $result : null;
    } catch (PDOException $e) {
        error_log("DB Error fetching user role for ID $userId: " . $e->getMessage());
        return null;
    }
}

/**
 * Enforces minimum role access for an API endpoint.
 * This is the primary function for role-based security.
 * @param string $minRole The minimum required role ('user', 'staff', or 'admin').
 * @param array $rolesMap Defines role hierarchy for comparison.
 * @return int The ID of the authenticated user.
 */
function require_role(string $minRole, array $rolesMap = ['user' => 1, 'staff' => 2, 'admin' => 3]): int {
    // 1. Check if the user is authenticated (401 handled inside)
    $userId = require_auth();

    // 2. Fetch the user's role
    $userRole = get_user_role($userId);

    // 3. Check role validity
    if (!$userRole || !isset($rolesMap[$userRole])) {
        json_response(['success' => false, 'error' => 'Forbidden access or invalid user state.'], 403);
    }
    
    // 4. Compare role levels
    if ($rolesMap[$userRole] < $rolesMap[$minRole]) {
        json_response(['success' => false, 'error' => 'Permission denied. Requires ' . ucfirst($minRole) . ' access.'], 403);
    }

    // Optional: Store role in session for faster future lookups (avoiding repeated DB lookups)
    if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== $userRole) {
        $_SESSION['user_role'] = $userRole;
    }

    return $userId;
}

// --- CSRF PROTECTION (Optional but good practice) ---

function generate_csrf(): string {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf_token'];
}

function verify_csrf(string $token): bool {
    if (session_status() === PHP_SESSION_NONE) session_start();
    // hash_equals prevents timing attacks
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}