<?php
// api/save_clinic_adjustment.php
require_once '../includes/db_connect.php';

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

$clinic_id = filter_input(INPUT_POST, 'clinic_id', FILTER_VALIDATE_INT);
$adjustment_type = trim($_POST['adjustment_type'] ?? '');
$amount_raw = filter_input(INPUT_POST, 'amount', FILTER_VALIDATE_FLOAT);
$reason = trim($_POST['reason'] ?? '');

if (!$clinic_id || !in_array($adjustment_type, ['credit', 'debit'], true) || $amount_raw === false || $amount_raw <= 0 || $reason === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Please enter a valid clinic, adjustment type, amount, and reason.']);
    exit;
}

$amount = $adjustment_type === 'credit' ? -abs((float) $amount_raw) : abs((float) $amount_raw);

try {
    $stmt_clinic = $pdo->prepare("SELECT id FROM users WHERE id = :id AND role = 'clinic'");
    $stmt_clinic->execute([':id' => $clinic_id]);
    if (!$stmt_clinic->fetch()) {
        http_response_code(404);
        echo json_encode(['error' => 'Clinic not found.']);
        exit;
    }

    $stmt = $pdo->prepare("INSERT INTO clinic_account_adjustments (clinic_id, admin_id, adjustment_type, amount, reason)
        VALUES (:clinic_id, :admin_id, :adjustment_type, :amount, :reason)");
    $stmt->execute([
        ':clinic_id' => $clinic_id,
        ':admin_id' => $_SESSION['user_id'],
        ':adjustment_type' => $adjustment_type,
        ':amount' => $amount,
        ':reason' => $reason,
    ]);

    http_response_code(200);
    echo json_encode(['success' => 'Balance adjustment saved successfully.']);
} catch (PDOException $e) {
    error_log('Save Clinic Adjustment Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error occurred.']);
}
?>
