<?php
require_once '../includes/db_connect.php';
require_once '../includes/request_workflow.php';
require_once '../includes/surgeon_operations.php';
header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['error'=>'Method not allowed.']); exit; }
if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') { http_response_code(401); echo json_encode(['error'=>'Unauthorized access.']); exit; }
if (!requestWorkflowCsrfIsValid((string)($_POST['csrf_token'] ?? ''))) { http_response_code(403); echo json_encode(['error'=>'Your session expired. Refresh the page.']); exit; }
try {
    $requestId=filter_input(INPUT_POST,'request_id',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
    $surgeonId=filter_input(INPUT_POST,'surgeon_id',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
    if (!$requestId || !$surgeonId) throw new DomainException('Select a valid request and surgeon.');
    saveSurgeonAppointment($pdo,$requestId,$surgeonId,(string)($_POST['confirmed_operation_at'] ?? ''),(int)$_SESSION['user_id']);
    echo json_encode(['success'=>'Surgeon and appointment confirmed.']);
} catch (DomainException $e) { http_response_code(409); echo json_encode(['error'=>$e->getMessage()]); }
catch (Throwable $e) { error_log('Surgeon appointment: '.$e->getMessage()); http_response_code(500); echo json_encode(['error'=>'Could not save operation details.']); }
