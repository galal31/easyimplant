<?php
require_once '../includes/db_connect.php';
require_once '../includes/request_workflow.php';
require_once '../includes/request_review.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$role = (string) ($_SESSION['role'] ?? '');
if (!isset($_SESSION['user_id']) || !in_array($role, ['admin', 'clinic'], true)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

$requestId = filter_input(INPUT_GET, 'request_id', FILTER_VALIDATE_INT);
$afterId = filter_input(INPUT_GET, 'after_id', FILTER_VALIDATE_INT);
if (!$requestId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request ID.']);
    exit;
}
if (!requestWorkflowCsrfIsValid(trim((string) ($_GET['csrf_token'] ?? '')))) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Your session expired. Please refresh the page and try again.']);
    exit;
}

try {
    $request = requireSurgicalGuideConversationAccess(
        $pdo,
        (int) $requestId,
        (int) $_SESSION['user_id'],
        $role
    );
    $messages = array_map(
        'requestMessageJson',
        fetchRequestMessages($pdo, (int) $requestId, max(0, (int) $afterId))
    );
    echo json_encode([
        'success' => true,
        'messages' => $messages,
        'writable' => surgicalGuideChatIsWritable($request['status']),
    ]);
} catch (RuntimeException $e) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('Request messages fetch error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Messages could not be loaded.']);
}
