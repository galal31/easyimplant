<?php
// api/update_request_status.php
require_once '../includes/db_connect.php';
require_once '../includes/request_workflow.php';
require_once '../includes/surgical_guide_pricing.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed.']);
    exit;
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized access.']);
    exit;
}

$request_id = filter_input(INPUT_POST, 'request_id', FILTER_VALIDATE_INT);
$status = trim($_POST['status'] ?? '');
$reason = trim($_POST['reason'] ?? '');

$allowed_statuses = ['pending_review', 'pending_payment', 'in_progress', 'completed', 'rejected'];

if (!$request_id || !in_array($status, $allowed_statuses, true)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid parameters.']);
    exit;
}

try {
    $pdo->beginTransaction();

    $stmt_old = $pdo->prepare("SELECT status, service_type FROM requests WHERE id = :id FOR UPDATE");
    $stmt_old->execute([':id' => $request_id]);
    $request = $stmt_old->fetch();

    if (!$request) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['error' => 'Request not found.']);
        exit;
    }

    $old_status = $request['status'];
    $is_surgical_guide = $request['service_type'] === 'surgical_guide';

    if ($is_surgical_guide) {
        $csrf_token = trim($_POST['csrf_token'] ?? '');
        if (!requestWorkflowCsrfIsValid($csrf_token)) {
            $pdo->rollBack();
            http_response_code(403);
            echo json_encode(['error' => 'Your session expired. Please refresh the page and try again.']);
            exit;
        }

        if (!surgicalGuideTransitionIsAllowed($old_status, $status, 'admin')) {
            $pdo->rollBack();
            http_response_code(409);
            echo json_encode(['error' => 'This status change is not allowed from the current request step. Please refresh the page.']);
            exit;
        }

        if ($old_status === 'pending_payment' && $status === 'rejected') {
            $activeCheckoutStmt = $pdo->prepare("SELECT id FROM xpay_checkout_sessions
                WHERE request_id = :request_id
                  AND payment_status <> 'paid'
                  AND status IN ('creating', 'open')
                  AND (
                    (expires_at IS NOT NULL AND expires_at > UTC_TIMESTAMP())
                    OR (expires_at IS NULL AND created_at > UTC_TIMESTAMP() - INTERVAL 30 MINUTE)
                  )
                LIMIT 1");
            $activeCheckoutStmt->execute([':request_id' => $request_id]);
            if ($activeCheckoutStmt->fetchColumn()) {
                $pdo->rollBack();
                http_response_code(409);
                echo json_encode(['error' => 'This request has an active XPay checkout. Wait for it to expire before rejecting the request.']);
                exit;
            }
        }
    } elseif ($status === 'rejected' && $reason === '') {
        // Keep the existing surgeon-request behavior unchanged.
        $pdo->rollBack();
        http_response_code(400);
        echo json_encode(['error' => 'Rejection reason is required.']);
        exit;
    }

    if ($status === 'rejected') {
        if ($is_surgical_guide) {
            releaseClinicFreeImplantReservation($pdo, (int) $request_id);
            $stmt_pending_payments = $pdo->prepare("UPDATE payments
                SET status = 'rejected'
                WHERE request_id = :request_id AND status = 'pending_verification'");
            $stmt_pending_payments->execute([':request_id' => $request_id]);
        }

        $stmt = $pdo->prepare("UPDATE requests
            SET status = :status,
                rejection_reason = :reason,
                rejected_at = NOW(),
                rejected_by = :admin_id
            WHERE id = :id");
        $stmt->execute([
            ':status' => $status,
            ':reason' => $reason !== '' ? $reason : null,
            ':admin_id' => $_SESSION['user_id'],
            ':id' => $request_id,
        ]);
    } else {
        $stmt = $pdo->prepare("UPDATE requests
            SET status = :status,
                rejection_reason = NULL,
                rejected_at = NULL,
                rejected_by = NULL
            WHERE id = :id");
        $stmt->execute([
            ':status' => $status,
            ':id' => $request_id,
        ]);
    }

    $stmt_log = $pdo->prepare("INSERT INTO request_activity_logs (request_id, actor_id, actor_role, action, old_value, new_value, note)
        VALUES (:request_id, :actor_id, 'admin', 'status_changed', :old_value, :new_value, :note)");
    $stmt_log->execute([
        ':request_id' => $request_id,
        ':actor_id' => $_SESSION['user_id'],
        ':old_value' => $old_status,
        ':new_value' => $status,
        ':note' => $status === 'rejected' ? $reason : null,
    ]);

    $pdo->commit();
    http_response_code(200);
    echo json_encode(['success' => 'Request status updated successfully.']);

} catch (\Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log("Update Request Status DB Error: " . $e->getMessage());
    $isConflict = $e instanceof DomainException;
    http_response_code($isConflict ? 409 : 500);
    echo json_encode(['error' => $isConflict ? $e->getMessage() : 'Database error occurred.']);
}
?>
