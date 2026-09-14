<?php
require_once '../includes/db_connect.php';
require_once '../includes/request_workflow.php';
require_once '../includes/request_review.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'clinic') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$requestId = filter_var($data['request_id'] ?? null, FILTER_VALIDATE_INT);
$packageId = filter_var($data['package_id'] ?? null, FILTER_VALIDATE_INT);
if (!$requestId || !$packageId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request or review package.']);
    exit;
}
if (!requestWorkflowCsrfIsValid(trim((string) ($data['csrf_token'] ?? '')))) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Your session expired. Please refresh the page and try again.']);
    exit;
}

try {
    $pdo->beginTransaction();
    $request = requireSurgicalGuideConversationAccess(
        $pdo,
        (int) $requestId,
        (int) $_SESSION['user_id'],
        'clinic',
        true
    );
    if ($request['status'] !== 'awaiting_clinic_approval') {
        throw new DomainException('This request is no longer waiting for clinic approval. Refresh the page.');
    }
    if (!surgicalGuideTransitionIsAllowed($request['status'], 'pending_payment', 'clinic_approval')) {
        throw new DomainException('This request cannot move to payment from its current stage.');
    }

    $latestStmt = $pdo->prepare("SELECT id, approved_at FROM request_review_packages
        WHERE request_id = :request_id ORDER BY id DESC LIMIT 1 FOR UPDATE");
    $latestStmt->execute([':request_id' => $requestId]);
    $latest = $latestStmt->fetch();
    if (!$latest || (int) $latest['id'] !== (int) $packageId) {
        throw new DomainException('Only the latest review package can be approved. Refresh the page and review the newest files.');
    }
    if (!empty($latest['approved_at'])) {
        throw new DomainException('This review package has already been approved.');
    }

    $updatePackage = $pdo->prepare("UPDATE request_review_packages
        SET approved_at = NOW(), approved_by = :approved_by WHERE id = :id AND approved_at IS NULL");
    $updatePackage->execute([':approved_by' => $_SESSION['user_id'], ':id' => $packageId]);
    if ($updatePackage->rowCount() !== 1) {
        throw new DomainException('This review package has already been approved.');
    }

    $pdo->prepare("UPDATE requests SET status = 'pending_payment' WHERE id = :id AND status = 'awaiting_clinic_approval'")
        ->execute([':id' => $requestId]);
    $logStmt = $pdo->prepare("INSERT INTO request_activity_logs
        (request_id, actor_id, actor_role, action, old_value, new_value, note)
        VALUES (:request_id, :actor_id, 'clinic', 'review_package_approved', 'awaiting_clinic_approval', 'pending_payment', :note)");
    $logStmt->execute([
        ':request_id' => $requestId,
        ':actor_id' => $_SESSION['user_id'],
        ':note' => 'Clinic approved review package #' . $packageId . '. No payment was recorded.',
    ]);
    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'The plan was approved. Online payment will become available after the payment gateway is connected.',
        'status' => 'pending_payment',
    ]);
} catch (DomainException|RuntimeException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(409);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Approve review package error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'The plan approval could not be saved. Try again.']);
}
