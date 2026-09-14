<?php
// api/assign_surgeon.php
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

$request_id = filter_input(INPUT_POST, 'request_id', FILTER_VALIDATE_INT);
$surgeon_id = filter_input(INPUT_POST, 'surgeon_id', FILTER_VALIDATE_INT);

if (!$request_id || !$surgeon_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid parameters. Please select a valid surgeon.']);
    exit;
}

try {
    // Make sure request is of type surgeon_request
    $stmt_check = $pdo->prepare("SELECT service_type FROM requests WHERE id = :id");
    $stmt_check->execute([':id' => $request_id]);
    $req = $stmt_check->fetch();

    if (!$req || $req['service_type'] !== 'surgeon_request') {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid request type.']);
        exit;
    }

    $stmt = $pdo->prepare("UPDATE surgeon_requests SET assigned_surgeon_id = :surgeon_id WHERE request_id = :request_id");
    $stmt->execute([
        ':surgeon_id' => $surgeon_id,
        ':request_id' => $request_id
    ]);

    http_response_code(200);
    echo json_encode(['success' => 'Surgeon assigned successfully.']);

} catch (\PDOException $e) {
    error_log("Assign Surgeon DB Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error occurred.']);
}
?>