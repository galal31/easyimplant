<?php
// api/verify_receipt.php
require_once '../includes/db_connect.php';
require_once '../includes/request_workflow.php';

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

$payment_id = filter_input(INPUT_POST, 'payment_id', FILTER_VALIDATE_INT);
$action = trim($_POST['action'] ?? '');
$reason = trim($_POST['reason'] ?? '');

if (!$payment_id || !in_array($action, ['approved', 'rejected'], true)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid parameters.']);
    exit;
}

try {
    $pdo->beginTransaction();

    if (!requestWorkflowCsrfIsValid(trim((string) ($_POST['csrf_token'] ?? '')))) {
        $pdo->rollBack();
        http_response_code(403);
        echo json_encode(['error' => 'Your session expired. Please refresh the page and try again.']);
        exit;
    }

    $stmt_payment = $pdo->prepare("SELECT p.request_id, p.status, p.payment_source,
            r.status AS request_status, r.service_type
        FROM payments p
        JOIN requests r ON r.id = p.request_id
        WHERE p.id = :id
        FOR UPDATE");
    $stmt_payment->execute([':id' => $payment_id]);
    $payment = $stmt_payment->fetch();
    if (!$payment) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['error' => 'Payment record not found.']);
        exit;
    }

    $pdo->rollBack();
    http_response_code(409);
    echo json_encode(['error' => 'Manual payment receipt review is disabled.']);

} catch (\PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log("Verify Receipt DB Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error occurred.']);
}
?>
