<?php

require_once '../includes/db_connect.php';
require_once '../includes/request_workflow.php';
require_once '../includes/surgeon_operations.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed.']);
    exit;
}
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized access.']);
    exit;
}

$requestId = filter_input(INPUT_POST, 'request_id', FILTER_VALIDATE_INT);
$providedPrice = trim((string) ($_POST['total_price'] ?? ''));
if (!$requestId || !requestWorkflowCsrfIsValid(trim((string) ($_POST['csrf_token'] ?? '')))) {
    http_response_code(403);
    echo json_encode(['error' => 'Your session expired. Please refresh the page and try again.']);
    exit;
}

try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("SELECT r.id, r.status, r.service_type, sr.requires_quote,
            sr.estimated_total, sr.total_price, sr.surgeon_id, sr.surgeon_name_snapshot, sr.confirmed_operation_at
        FROM requests r
        JOIN surgeon_requests sr ON sr.request_id = r.id
        WHERE r.id = :request_id
        FOR UPDATE");
    $stmt->execute([':request_id' => $requestId]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$request || $request['service_type'] !== 'surgeon_request') {
        throw new DomainException('Surgeon request not found.');
    }

    $isRevision=($_POST['action'] ?? '')==='revise';
    if ($isRevision && $request['status']!=='pending_payment') throw new DomainException('Only unpaid payment requests can return to coordination.');
    if (!$isRevision && $request['status'] !== 'pending_review') {
        throw new DomainException('This request is not waiting for price approval.');
    }
    if (!$isRevision && (!surgeonOperationIsReady($request) || new DateTimeImmutable($request['confirmed_operation_at'],new DateTimeZone('Africa/Cairo')) <= new DateTimeImmutable('now',new DateTimeZone('Africa/Cairo')))) {
        throw new DomainException('Assign a surgeon and confirm a future appointment before requesting payment.');
    }

    $approvedPayment = $pdo->prepare("SELECT id FROM payments WHERE request_id = :request_id AND status = 'approved' LIMIT 1 FOR UPDATE");
    $approvedPayment->execute([':request_id' => $requestId]);
    $checkout = $pdo->prepare('SELECT id FROM xpay_checkout_sessions WHERE request_id = :request_id LIMIT 1 FOR UPDATE');
    $checkout->execute([':request_id' => $requestId]);
    if ($approvedPayment->fetchColumn() || $checkout->fetchColumn()) {
        throw new DomainException('The final price is locked because payment activity already exists.');
    }
    if ($isRevision) {
        $pdo->prepare("UPDATE requests SET status='pending_review' WHERE id=?")->execute([$requestId]);
        $pdo->prepare('UPDATE surgeon_requests SET price_confirmed_at=NULL,price_confirmed_by=NULL WHERE request_id=?')->execute([$requestId]);
        logSurgeonOperation($pdo,(int)$requestId,(int)$_SESSION['user_id'],'coordination_reopened','pending_payment','pending_review','Details reopened before any payment session was created.');
        $pdo->commit(); echo json_encode(['success'=>'Returned to review and coordination.']); exit;
    }

    if ((int) $request['requires_quote'] === 1) {
        if (!preg_match('/^\d+(?:\.\d{1,2})?$/', $providedPrice)
            || (float) $providedPrice <= 0
            || (float) $providedPrice > 99999999.99) {
            throw new DomainException('Enter a valid final price greater than zero with no more than two decimal places.');
        }
        $finalPrice = number_format((float) $providedPrice, 2, '.', '');
    } else {
        $storedPrice = $request['total_price'] ?? $request['estimated_total'];
        if ($storedPrice === null || (float) $storedPrice <= 0) {
            throw new DomainException('This request has no valid stored price.');
        }
        $finalPrice = number_format((float) $storedPrice, 2, '.', '');
    }

    $pdo->prepare("UPDATE surgeon_requests
        SET total_price = :total_price, price_confirmed_at = NOW(), price_confirmed_by = :admin_id
        WHERE request_id = :request_id")
        ->execute([
            ':total_price' => $finalPrice,
            ':admin_id' => $_SESSION['user_id'],
            ':request_id' => $requestId,
        ]);

    if (!surgeonRequestTransitionIsAllowed('pending_review', 'pending_payment', 'price_confirmation')) {
        throw new DomainException('This request cannot move to payment from its current stage.');
    }
    $pdo->prepare("UPDATE requests SET status = 'pending_payment' WHERE id = :id AND status = 'pending_review'")
        ->execute([':id' => $requestId]);

    $pdo->prepare("INSERT INTO request_activity_logs
        (request_id, actor_id, actor_role, action, old_value, new_value, note)
        VALUES (:request_id, :actor_id, 'admin', 'payment_requested', :old_value, 'pending_payment', :note)")
        ->execute([
            ':request_id' => $requestId,
            ':actor_id' => $_SESSION['user_id'],
            ':old_value' => $request['status'],
            ':note' => 'Final price approved and online payment requested.',
        ]);

    $pdo->commit();
    echo json_encode(['success' => 'Final price approved and payment requested.']);
} catch (DomainException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(409);
    echo json_encode(['error' => $e->getMessage()]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Prepare surgeon payment error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'The payment request could not be prepared.']);
}
