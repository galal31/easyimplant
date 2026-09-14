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
$role = (string) ($_SESSION['role'] ?? '');
if (!isset($_SESSION['user_id']) || !in_array($role, ['admin', 'clinic'], true)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$requestId = filter_var($data['request_id'] ?? null, FILTER_VALIDATE_INT);
$messageText = trim((string) ($data['message_text'] ?? ''));
if (!$requestId || $messageText === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Write a message before sending.']);
    exit;
}
if (mb_strlen($messageText) > REQUEST_MESSAGE_MAX_LENGTH) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Messages cannot exceed ' . REQUEST_MESSAGE_MAX_LENGTH . ' characters.']);
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
        $role,
        true
    );
    if (!surgicalGuideChatIsWritable($request['status'])) {
        throw new DomainException('This conversation is read-only because the request is completed or rejected.');
    }

    $stmt = $pdo->prepare("INSERT INTO request_messages (request_id, sender_id, sender_role, message_text)
        VALUES (:request_id, :sender_id, :sender_role, :message_text)");
    $stmt->execute([
        ':request_id' => $requestId,
        ':sender_id' => $_SESSION['user_id'],
        ':sender_role' => $role,
        ':message_text' => $messageText,
    ]);
    $messageId = (int) $pdo->lastInsertId();
    $messageStmt = $pdo->prepare("SELECT m.id, m.sender_id, m.sender_role, m.message_text, m.created_at,
            COALESCE(u.full_name, IF(m.sender_role = 'admin', 'Easy Implant Admin', 'Clinic')) AS sender_name
        FROM request_messages m LEFT JOIN users u ON u.id = m.sender_id WHERE m.id = :id");
    $messageStmt->execute([':id' => $messageId]);
    $message = $messageStmt->fetch();
    $pdo->commit();

    echo json_encode(['success' => true, 'message' => requestMessageJson($message)]);
} catch (DomainException|RuntimeException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(409);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Send request message error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'The message could not be sent.']);
}
