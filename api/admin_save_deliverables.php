<?php
// api/admin_save_deliverables.php
session_start();
require_once '../includes/db_connect.php';
require_once '../includes/request_workflow.php';
require_once '../includes/r2_config.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

// Decode the JSON payload from the request body
$json_data = file_get_contents('php://input');
$data = json_decode($json_data, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON payload.']);
    exit;
}

$request_id = filter_var($data['request_id'] ?? null, FILTER_VALIDATE_INT);
$files_by_type = $data['files'] ?? [];
$csrf_token = trim((string) ($data['csrf_token'] ?? ''));

if (!$request_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid Request ID.']);
    exit;
}

if (!requestWorkflowCsrfIsValid($csrf_token)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Your session expired. Please refresh the page and try again.']);
    exit;
}

$allowed_file_types = ['video', 'instruction', 'guide', 'optional'];
$normalized_files = [];
$admin_upload_prefix = 'uploads/admin_' . (int) $_SESSION['user_id'] . '/';

foreach ($files_by_type as $file_type => $file_paths) {
    if (!in_array($file_type, $allowed_file_types, true) || !is_array($file_paths)) {
        continue;
    }

    foreach ($file_paths as $file) {
        $path = trim((string) (is_array($file) ? ($file['path'] ?? '') : $file));
        if ($path === '') {
            continue;
        }

        $pending = $_SESSION['pending_upload_keys'][$path] ?? null;
        if (
            !str_starts_with($path, $admin_upload_prefix)
            || !is_array($pending)
            || (int) ($pending['created_at'] ?? 0) < time() - 3600
        ) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'One or more uploaded files are invalid or no longer belong to this session.']);
            exit;
        }

        $originalName = trim((string) ($pending['original_name'] ?? ''));
        if ($file_type === 'guide' && strtolower(pathinfo($originalName ?: $path, PATHINFO_EXTENSION)) !== 'stl') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Final guide files must use the STL format.']);
            exit;
        }

        try {
            $head = $s3Client->headObject(['Bucket' => $bucketName, 'Key' => $path]);
            $actualSize = (int) ($head['ContentLength'] ?? 0);
            $actualType = strtolower((string) ($head['ContentType'] ?? 'application/octet-stream'));
            if ($actualSize < 1 || $actualSize !== (int) ($pending['file_size'] ?? 0)) {
                throw new RuntimeException('Uploaded file size mismatch.');
            }
        } catch (Throwable $uploadError) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'One or more delivery files could not be verified. Please upload them again.']);
            exit;
        }

        $normalized_files[$file_type][] = [
            'path' => $path,
            'original_name' => $originalName,
            'content_type' => $actualType,
            'file_size' => $actualSize,
        ];
    }
}

if (!$normalized_files) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Please upload at least one delivery file.']);
    exit;
}

try {
    $pdo->beginTransaction();

    // Fetch request details to validate against
    $stmt_check = $pdo->prepare("SELECT r.service_type, r.status, d.delivery_method
        FROM requests r
        JOIN surgical_guide_details d ON r.id = d.request_id
        WHERE r.id = :id
        FOR UPDATE");
    $stmt_check->execute([':id' => $request_id]);
    $req = $stmt_check->fetch();

    if (!$req || $req['service_type'] !== 'surgical_guide') {
        $pdo->rollBack();
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Deliverables can only be uploaded for Surgical Guides.']);
        exit;
    }

    if (!surgicalGuideTransitionIsAllowed($req['status'], 'completed', 'deliverables')) {
        $pdo->rollBack();
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Delivery files can only be finalized while the request is in progress.']);
        exit;
    }

    $stmt_payment = $pdo->prepare("SELECT id FROM payments WHERE request_id = :request_id AND status = 'approved' LIMIT 1");
    $stmt_payment->execute([':request_id' => $request_id]);
    if (!$stmt_payment->fetchColumn()) {
        $pdo->rollBack();
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'The request cannot be completed before its payment is approved.']);
        exit;
    }

    // Validate that "Guide" files are present if delivery method is 'clinic_print'
    if ($req['delivery_method'] === 'clinic_print' && empty($normalized_files['guide'])) {
        $pdo->rollBack();
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'The Final Guide (STL) is required because the clinic requested to print it locally.']);
        exit;
    }

    // Prepare the statement to insert into the new table
    $stmt_insert = $pdo->prepare(
        "INSERT INTO request_deliverables (request_id, file_type, file_path, original_name, content_type, file_size)
         VALUES (:request_id, :file_type, :file_path, :original_name, :content_type, :file_size)"
    );

    // Loop through the file categories and their paths, and insert them
    foreach ($normalized_files as $file_type => $file_paths) {
        // Ensure we have an array of paths to process
        if (!is_array($file_paths) || empty($file_paths)) {
            continue;
        }

        foreach ($file_paths as $file) {
            if (!empty($file['path'])) {
                $stmt_insert->execute([
                    ':request_id' => $request_id,
                    ':file_type'  => $file_type,
                    ':file_path'  => $file['path'],
                    ':original_name' => $file['original_name'],
                    ':content_type' => $file['content_type'],
                    ':file_size' => $file['file_size'],
                ]);
            }
        }
    }

    // Update the main request status to 'completed'
    $stmt_status = $pdo->prepare("UPDATE requests SET status = 'completed' WHERE id = :id");
    $stmt_status->execute([':id' => $request_id]);

    $stmt_log = $pdo->prepare("INSERT INTO request_activity_logs (request_id, actor_id, actor_role, action, new_value, note)
        VALUES (:request_id, :actor_id, 'admin', 'deliverables_uploaded', 'completed', :note)");
    $stmt_log->execute([
        ':request_id' => $request_id,
        ':actor_id' => $_SESSION['user_id'],
        ':note' => 'Deliverables uploaded and request marked as completed.'
    ]);

    $pdo->commit();
    foreach ($normalized_files as $file_paths) {
        foreach ($file_paths as $file) {
            unset($_SESSION['pending_upload_keys'][$file['path']]);
        }
    }
    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'Deliverables successfully saved and the case is marked as completed.']);
} catch (\PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Upload Deliverables DB Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error occurred while saving the deliverables.']);
}
?>
