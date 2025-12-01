<?php
if (session_status() === PHP_SESSION_NONE) session_start();
ini_set('display_errors',1); error_reporting(E_ALL);
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json; charset=utf-8');
$out = ['session_id'=>session_id()?:null,'session_user_id'=>$_SESSION['user_id']??null];
try { $stmt = $pdo->query("SELECT 1 as ok"); $out['db']=$stmt->fetch(); } catch(Exception $e) { $out['db_error']=$e->getMessage(); }
echo json_encode($out, JSON_PRETTY_PRINT);
