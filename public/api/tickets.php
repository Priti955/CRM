<?php

declare(strict_types=1);

ob_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../config/db.php'; 

function json_response(array $data, int $status = 200): void {
    if (ob_get_length()) ob_clean();
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}


function sanitize_str_local($s): string {
    return is_string($s) ? trim($s) : '';
}


if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    json_response(['success'=>false, 'error'=>'Not logged in'], 401);
}
$uid = (int)$_SESSION['user_id'];

function ticket_visible_to(PDO $pdo, int $ticketId, int $userId): bool {
    $stmt = $pdo->prepare("
        SELECT 1 FROM tickets 
        WHERE id = ? AND deleted_at IS NULL 
        AND (created_by = ? OR id IN (
            SELECT ticket_id FROM ticket_assignments 
            WHERE ticket_id = ? AND assigned_to = ? AND unassigned_at IS NULL
        )) LIMIT 1
    ");
    $stmt->execute([$ticketId, $userId, $ticketId, $userId]);
    return (bool)$stmt->fetchColumn();
}

function is_author(PDO $pdo, int $ticketId, int $userId): bool {
    $stmt = $pdo->prepare("SELECT created_by FROM tickets WHERE id = ? LIMIT 1");
    $stmt->execute([$ticketId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row && intval($row['created_by']) === $userId;
}

function is_assignee(PDO $pdo, int $ticketId, int $userId): bool {
    $stmt = $pdo->prepare("SELECT 1 FROM ticket_assignments WHERE ticket_id = ? AND assigned_to = ? AND unassigned_at IS NULL LIMIT 1");
    $stmt->execute([$ticketId, $userId]);
    return (bool)$stmt->fetchColumn();
}

/**
 * Gets the current file path for a ticket.
 */
function get_current_file_path(PDO $pdo, int $ticketId): ?string {
    $stmt = $pdo->prepare("SELECT file_path FROM tickets WHERE id = ? LIMIT 1");
    $stmt->execute([$ticketId]);
    $path = $stmt->fetchColumn();
    return is_string($path) ? $path : null;
}


$ALLOWED_STATUSES = ['pending','inprogress','completed','onhold'];
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? null;

try {


    if ($method === 'GET') {
        if (!empty($_GET['id'])) {
            $id = intval($_GET['id']);
            if (!ticket_visible_to($pdo, $id, $uid)) {
                json_response(['success'=>false,'error'=>'Not found or forbidden'],404);
            }

            $stmt = $pdo->prepare("
                SELECT t.*, u.name AS author_name 
                FROM tickets t 
                JOIN users u ON u.id = t.created_by 
                WHERE t.id = ? LIMIT 1
            ");
            $stmt->execute([$id]);
            $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$ticket) json_response(['success'=>false,'error'=>'Not found'],404);

            
            $a = $pdo->prepare("
                SELECT u.id,u.name 
                FROM ticket_assignments ta 
                JOIN users u ON u.id = ta.assigned_to 
                WHERE ta.ticket_id = ? AND ta.unassigned_at IS NULL LIMIT 1
            ");
            $a->execute([$id]);
            $ass = $a->fetch(PDO::FETCH_ASSOC);
            $ticket['assignee'] = $ass ?: null;

            json_response(['success'=>true,'ticket'=>$ticket]);
        }

        $q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
        $params = [$uid, $uid];
        $sql = "
            SELECT t.id, t.name AS title, t.description, COALESCE(t.status,'pending') AS status, 
                    t.file_path, t.created_at, u.name AS author_name,
                    (SELECT u2.name 
                     FROM ticket_assignments ta 
                     JOIN users u2 ON u2.id = ta.assigned_to 
                     WHERE ta.ticket_id = t.id AND ta.unassigned_at IS NULL LIMIT 1) AS assignee_name
            FROM tickets t
            JOIN users u ON u.id = t.created_by
            WHERE t.deleted_at IS NULL
              AND (t.created_by = ? OR t.id IN (SELECT ticket_id FROM ticket_assignments WHERE assigned_to = ? AND unassigned_at IS NULL))
        ";

        if ($q !== '') {
            $sql .= " AND (t.name LIKE ? OR t.description LIKE ? OR u.name LIKE ?)";
            $like = "%{$q}%";
            $params[] = $like; $params[] = $like; $params[] = $like;
        }
        $sql .= " ORDER BY t.created_at DESC LIMIT 500";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($tickets as &$t) {
            if (!isset($t['title'])) $t['title'] = $t['name'] ?? '';
            if (!isset($t['assignee_name']) || $t['assignee_name'] === null) $t['assignee_name'] = '-';
        }
        unset($t);

        json_response(['success'=>true,'tickets'=>$tickets]);
    }

 
    if ($method === 'DELETE' || ($method === 'POST' && $action === 'delete')) {
        $id = intval($_REQUEST['id'] ?? 0);
        if ($id <= 0) json_response(['success'=>false,'error'=>'Missing id'],400);
        if (!is_author($pdo,$id,$uid)) json_response(['success'=>false,'error'=>'Forbidden'],403);

        $stmt = $pdo->prepare("UPDATE tickets SET deleted_at = NOW() WHERE id = ? AND created_by = ?");
        $stmt->execute([$id,$uid]);
        json_response(['success'=>true,'message'=>'Ticket deleted']);
    }


    if ($method === 'POST' && ($action === null || $action === 'save')) {
        $isForm = !empty($_POST) || !empty($_FILES);
        if ($isForm) {
            $name = sanitize_str_local($_POST['title'] ?? $_POST['name'] ?? '');
            $description = sanitize_str_local($_POST['description'] ?? '');
        } else {
            $body = json_decode(file_get_contents('php://input'), true) ?: [];
            $name = sanitize_str_local($body['title'] ?? $body['name'] ?? '');
            $description = sanitize_str_local($body['description'] ?? '');
        }

        $id = intval($_REQUEST['id'] ?? 0);
        
      
        $file_path = null;
        if ($id > 0) {
            // If updating an existing ticket, get the current file_path to preserve it 
            $file_path = get_current_file_path($pdo, $id);
        }
        
        if (!empty($_FILES['file']['name'])) {
            $uploaddir = __DIR__ . '/../../storage/uploads';
            if (!is_dir($uploaddir) && !mkdir($uploaddir,0755,true)) {
                json_response(['success'=>false,'error'=>'Failed to create uploads dir'],500);
            }
            $orig = basename((string)$_FILES['file']['name']);
            $fname = uniqid('f_') . '_' . preg_replace('/[^a-zA-Z0-9_\.-]/', '_', $orig);
            $dest = $uploaddir . '/' . $fname;
            if (!is_uploaded_file($_FILES['file']['tmp_name']) || !move_uploaded_file($_FILES['file']['tmp_name'],$dest)) {
                json_response(['success'=>false,'error'=>'Upload failed'],500);
            }
           
            $file_path = 'storage/uploads/' . $fname;
        }
        
        if ($name === '') json_response(['success'=>false,'error'=>'Name required'],400);

        
        if ($id > 0) {

            if (!is_author($pdo,$id,$uid)) json_response(['success'=>false,'error'=>'Forbidden'],403);
            $stmt = $pdo->prepare("UPDATE tickets SET name=?, description=?, file_path=?, updated_at=NOW() WHERE id=? AND created_by=?");
            $stmt->execute([$name,$description,$file_path,$id,$uid]);
            json_response(['success'=>true,'message'=>'Ticket updated']);
        } else {
            
            $stmt = $pdo->prepare("INSERT INTO tickets (name,description,file_path,created_by,created_at) VALUES (?, ?, ?, ?, NOW())");
            $stmt->execute([$name,$description,$file_path,$uid]);
            $newId = intval($pdo->lastInsertId());
            json_response(['success'=>true,'ticket_id'=>$newId]);
        }
    }

    if ($method === 'PATCH' || ($method === 'POST' && $action === 'status')) {
        $data = $_POST ?: json_decode(file_get_contents('php://input'), true) ?: [];
        $id = intval($data['id'] ?? $_REQUEST['id'] ?? 0);
        $status = sanitize_str_local($data['status'] ?? $_REQUEST['status'] ?? '');
        if ($id <= 0 || $status === '') json_response(['success'=>false,'error'=>'Missing id or status'],400);
        $status = strtolower($status);
        if (!in_array($status, $ALLOWED_STATUSES, true)) json_response(['success'=>false,'error'=>'Invalid status'],400);

        if (!is_assignee($pdo,$id,$uid)) json_response(['success'=>false,'error'=>'Forbidden'],403);
        
        if ($status === 'completed') {
            $stmt = $pdo->prepare("UPDATE tickets SET status=?, completed_at=NOW(), updated_at=NOW() WHERE id=?");
            $stmt->execute([$status,$id]);
        } else {
            
            $stmt = $pdo->prepare("UPDATE tickets SET status=?, completed_at=NULL, updated_at=NOW() WHERE id=?"); 
            $stmt->execute([$status,$id]);
        }

        json_response(['success'=>true,'message'=>'Status updated','status'=>$status]);
    }


    if ($method === 'POST' && $action === 'assign') {
        $data = $_POST ?: json_decode(file_get_contents('php://input'), true) ?: [];
        $ticket_id = intval($data['ticket_id'] ?? $_REQUEST['ticket_id'] ?? 0);
        $assigned_to = intval($data['assigned_to'] ?? $_REQUEST['assigned_to'] ?? 0);
        if ($ticket_id <= 0 || $assigned_to <= 0) json_response(['success'=>false,'error'=>'Missing ticket_id or assigned_to'],400);
        if (!is_author($pdo,$ticket_id,$uid)) json_response(['success'=>false,'error'=>'Forbidden'],403);

        $ucheck = $pdo->prepare("SELECT id FROM users WHERE id=? AND deleted_at IS NULL LIMIT 1");
        $ucheck->execute([$assigned_to]);
        if (!$ucheck->fetchColumn()) json_response(['success'=>false,'error'=>'Assigned user not found'],404);

        $pdo->prepare("UPDATE ticket_assignments SET unassigned_at=NOW() WHERE ticket_id=? AND unassigned_at IS NULL")->execute([$ticket_id]);
        $ins = $pdo->prepare("INSERT INTO ticket_assignments (ticket_id,assigned_to,assigned_at) VALUES (?, ?, NOW())");
        $ins->execute([$ticket_id,$assigned_to]);

        json_response(['success'=>true,'message'=>'Assigned']);
    }
    
    // === POST unassign ticket ===
    if ($method === 'POST' && $action === 'unassign') {
        $data = $_POST ?: json_decode(file_get_contents('php://input'), true) ?: [];
        $ticket_id = intval($data['ticket_id'] ?? $_REQUEST['ticket_id'] ?? 0);
        if ($ticket_id <= 0) json_response(['success'=>false,'error'=>'Missing ticket_id'],400);
        
        if (!is_author($pdo,$ticket_id,$uid)) json_response(['success'=>false,'error'=>'Forbidden'],403);

        $stmt = $pdo->prepare("UPDATE ticket_assignments SET unassigned_at=NOW() WHERE ticket_id=? AND unassigned_at IS NULL");
        $stmt->execute([$ticket_id]);

        json_response(['success'=>true,'message'=>'Unassigned']);
    }

    // fallback
    json_response(['success'=>false,'error'=>'Method not allowed'],405);

} catch (PDOException $ex) {
    @file_put_contents(__DIR__.'/../../storage/logs/api_errors.log', date('c').' tickets.php PDO: '.$ex->getMessage().PHP_EOL.$ex->getTraceAsString().PHP_EOL, FILE_APPEND);
    json_response(['success'=>false,'error'=>'Server error'],500);
} catch (Throwable $t) {
    @file_put_contents(__DIR__.'/../../storage/logs/api_errors.log', date('c').' tickets.php ERR: '.$t->getMessage().PHP_EOL.$t->getTraceAsString().PHP_EOL, FILE_APPEND);
    json_response(['success'=>false,'error'=>'Server error'],500);
}