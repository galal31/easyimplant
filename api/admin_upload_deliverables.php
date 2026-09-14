<?php
// api/admin_upload_deliverables.php
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
$admin_plan_video_path = trim($_POST['admin_plan_video_path'] ?? '');
$admin_instruction_file_path = trim($_POST['admin_instruction_file_path'] ?? '');
$admin_guide_file_path = trim($_POST['admin_guide_file_path'] ?? '');

if (!$request_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid Request ID.']);
    exit;
}

try {
    // Check if the request is a surgical guide type
    $stmt_check = $pdo->prepare("SELECT service_type FROM requests WHERE id = :id");
    $stmt_check->execute([':id' => $request_id]);
    $req = $stmt_check->fetch();

    if (!$req || $req['service_type'] !== 'surgical_guide') {
        http_response_code(400);
        echo json_encode(['error' => 'Deliverables can only be uploaded for Surgical Guides.']);
        exit;
    }

    $stmt = $pdo->prepare("UPDATE surgical_guide_details SET admin_plan_video_path = :video, admin_instruction_file_path = :instruction, admin_guide_file_path = :guide WHERE request_id = :request_id");
    $stmt->execute([
        ':video' => !empty($admin_plan_video_path) ? $admin_plan_video_path : null,
        ':instruction' => !empty($admin_instruction_file_path) ? $admin_instruction_file_path : null,
        ':guide' => !empty($admin_guide_file_path) ? $admin_guide_file_path : null,
        ':request_id' => $request_id
    ]);

    http_response_code(200);
    echo json_encode(['success' => 'Deliverables successfully saved to the case.']);

} catch (\PDOException $e) {
    error_log("Upload Deliverables DB Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error occurred.']);
}
?>