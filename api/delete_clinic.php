<?php
// api/delete_clinic.php
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

if (!$clinic_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid parameters.']);
    exit;
}

try {
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = :id AND role = 'clinic'");
    $stmt->execute([':id' => $clinic_id]);

    if ($stmt->rowCount() > 0) {
        http_response_code(200);
        echo json_encode(['success' => 'Clinic successfully deleted.']);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Clinic not found.']);
    }
} catch (\PDOException $e) {
    error_log("Delete Clinic DB Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error occurred.']);
}
?>