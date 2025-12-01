<?php
// public/api/profile_upload.php
declare(strict_types=1);
require_once __DIR__ . '/../../config/db.php';   // ensures $pdo
require_once __DIR__ . '/../../lib/util.php';    // if you have require_auth() there

if (session_status() === PHP_SESSION_NONE) session_start();
$uid = require_auth(); // returns user id or exits with 401 JSON

// simple file handling
if (empty($_FILES['file']) || empty($_FILES['file']['name'])) {
    http_response_code(400);
    echo json_encode(['success'=>false,'error'=>'No file uploaded']);
    exit;
}

$uploaddir = __DIR__ . '/../../storage/uploads';
if (!is_dir($uploaddir)) mkdir($uploaddir, 0755, true);

$orig = basename($_FILES['file']['name']);
$fname = uniqid('avatar_') . '_' . preg_replace('/[^a-zA-Z0-9_\.-]/', '_', $orig);
$dest = $uploaddir . '/' . $fname;

if (!is_uploaded_file($_FILES['file']['tmp_name']) || !move_uploaded_file($_FILES['file']['tmp_name'], $dest)) {
    http_response_code(500);
    echo json_encode(['success'=>false,'error'=>'Failed to save uploaded file']);
    exit;
}

// Save file path in users table (ensure users table has avatar column or similar)
$path = 'storage/uploads/' . $fname;
$stmt = $pdo->prepare("UPDATE users SET avatar = ?, updated_at = NOW() WHERE id = ?");
$stmt->execute([$path, $uid]);

echo json_encode(['success'=>true, 'avatar' => $path]);
exit;
