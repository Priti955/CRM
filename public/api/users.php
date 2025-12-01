<?php

declare(strict_types=1);

// Standard PHP setup
ob_start();
// Recommended: Keep display_errors set to 0 in production environment
ini_set('display_errors', 0); 
error_reporting(E_ALL);

// 1. Session and Helpers
if (session_status() === PHP_SESSION_NONE) session_start();

// Include database connection (defines $pdo)
require_once "../../config/db.php"; 
// Include the utility functions (MUST include json_response and require_role)
require_once "../../lib/util.php";

// Set necessary headers
header("Content-Type: application/json; charset=utf-8");
header('Access-Control-Allow-Origin: *'); // Adjust to your frontend URL
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');


try {
    // 2. CRITICAL AUTHORIZATION: Require Admin for User Management
    // If the user is not logged in (401) or is not 'admin' (403), 
    // this function handles the response and exits the script immediately.
    $currentUserId = require_role('admin'); 
    
    
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $action = $_GET['action'] ?? null;
    
    
    // --- READ (GET): List or Single User ---
    if ($method === 'GET') {
        if (!empty($_GET['id'])) {
            // ... [GET SINGLE USER logic] ...
            $id = (int)$_GET['id'];
            $stmt = $pdo->prepare("SELECT id, name, email, role, is_active, created_at FROM users WHERE id = ? AND deleted_at IS NULL LIMIT 1");
            $stmt->execute([$id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$user) json_response(['success'=>false, 'error'=>'User not found'], 404);
            json_response(['success'=>true, 'user'=>$user]);

        } else {
            // ... [GET LIST OF ALL USERS logic] ...
            $stmt = $pdo->query("SELECT id, name, email, role, is_active, created_at FROM users WHERE deleted_at IS NULL ORDER BY created_at DESC");
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            json_response(['success' => true, 'users' => $users]);
        }
    }


    // --- CREATE/UPDATE (POST/SAVE) ---
    if ($method === 'POST' && $action === 'save') {
        // ... [POST / SAVE logic from previous response] ...
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        
        $id = (int)($data['id'] ?? 0);
        $name = trim($data['name'] ?? '');
        $email = strtolower(trim($data['email'] ?? ''));
        $password = $data['password'] ?? '';
        $role = strtolower(trim($data['role'] ?? 'user'));
        $is_active = (int)($data['is_active'] ?? 1);

        if (!$name || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            json_response(['success'=>false, 'error'=>'Invalid name or email'], 400);
        }

        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ? AND deleted_at IS NULL LIMIT 1");
        $stmt->execute([$email, $id]);
        if ($stmt->fetch()) { json_response(['success'=>false, 'error'=>'Email already in use'], 409); }

        if ($id > 0) {
            // UPDATE EXISTING USER
            $sql = "UPDATE users SET name=?, email=?, role=?, is_active=?, updated_at=NOW() WHERE id=?";
            $params = [$name, $email, $role, $is_active, $id];

            if ($password !== '') {
                if (strlen($password) < 8) json_response(['success'=>false, 'error'=>'Password too short'], 400);
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $sql = "UPDATE users SET name=?, email=?, password=?, role=?, is_active=?, updated_at=NOW() WHERE id=?";
                $params = [$name, $email, $hash, $role, $is_active, $id];
            }

            $pdo->prepare($sql)->execute($params);
            json_response(['success'=>true, 'message'=>'User updated']);

        } else {
            // CREATE NEW USER
            if (strlen($password) < 8) json_response(['success'=>false, 'error'=>'Password required and must be 8+ chars'], 400);
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $sql = "INSERT INTO users (name, email, password, role, is_active, created_at) VALUES (?, ?, ?, ?, ?, NOW())";
            $pdo->prepare($sql)->execute([$name, $email, $hash, $role, $is_active]);
            $newId = $pdo->lastInsertId();
            json_response(['success'=>true, 'id'=>$newId, 'message'=>'User created']);
        }
    }


    // --- DEACTIVATE/REACTIVATE (POST/STATUS) ---
    if ($method === 'POST' && $action === 'status') {
        // ... [POST / STATUS logic from previous response] ...
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $id = (int)($data['id'] ?? 0);
        $is_active = (int)($data['is_active'] ?? 0); 
        
        if ($id <= 0) json_response(['success'=>false, 'error'=>'Missing User ID'], 400);
        
        // CRITICAL CHECK: Prevent Admin from deactivating their OWN account
        if ($id === $currentUserId) {
            json_response(['success'=>false, 'error'=>'Cannot change status of your own account'], 403);
        }

        $stmt = $pdo->prepare("UPDATE users SET is_active = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$is_active, $id]);
        json_response(['success'=>true, 'message'=>'User status updated']);
    }


    // --- DELETE (POST/DELETE) ---
    if ($method === 'POST' && $action === 'delete') {
        // ... [POST / DELETE logic from previous response] ...
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $id = (int)($data['id'] ?? 0);

        if ($id <= 0) json_response(['success'=>false, 'error'=>'Missing User ID'], 400);

        // CRITICAL CHECK: Prevent Admin from deleting their OWN account
        if ($id === $currentUserId) {
            json_response(['success'=>false, 'error'=>'Cannot delete your own account'], 403);
        }
        
        // Soft Delete
        $stmt = $pdo->prepare("UPDATE users SET deleted_at = NOW(), is_active = 0 WHERE id = ?");
        $stmt->execute([$id]);

        json_response(['success'=>true, 'message'=>'User deleted']);
    }

    
    // --- FALLBACK ---
    json_response(['success'=>false, 'error'=>'Invalid request or action'], 400);


} catch (PDOException $ex) {
    // Logging and 500 response
    error_log('Users API PDO Error: ' . $ex->getMessage() . PHP_EOL . $ex->getTraceAsString());
    json_response(['success'=>false, 'error'=>'Server error processing request'], 500);
} catch (Throwable $t) {
    // Logging and 500 response
    error_log('Users API Runtime Error: ' . $t->getMessage() . PHP_EOL . $t->getTraceAsString());
    json_response(['success'=>false, 'error'=>'Unexpected server error'], 500);
}