<?php
// api/upload_receipt.php
session_start();
require_once '../includes/db_connect.php';
require_once '../includes/r2_config.php';

use Aws\Exception\AwsException;

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'clinic') {
    echo json_encode(['error' => 'Unauthorized access.']);
    exit;
}

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $request_id = filter_input(INPUT_POST, 'request_id', FILTER_VALIDATE_INT);
    $amount     = filter_input(INPUT_POST, 'amount', FILTER_VALIDATE_FLOAT); // اختياري
    
    if (!$request_id) {
        echo json_encode(['error' => 'Invalid request ID.']);
        exit;
    }

    // التأكد إن الطلب يخص المستخدم ده ومستني دفع
    $stmtCheck = $pdo->prepare("
        SELECT r.id, sgd.total_price
        FROM requests r
        LEFT JOIN surgical_guide_details sgd ON sgd.request_id = r.id
        WHERE r.id = :id AND r.user_id = :user_id AND r.status = 'pending_payment'
    ");
    $stmtCheck->execute([':id' => $request_id, ':user_id' => $user_id]);
    $request = $stmtCheck->fetch();
    if (!$request) {
        echo json_encode(['error' => 'Unauthorized or request is not pending payment.']);
        exit;
    }

    if (!$amount && $request['total_price'] !== null) {
        $amount = (float) $request['total_price'];
    }

    if (!isset($_FILES['receipt_file']) || $_FILES['receipt_file']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['error' => 'Please upload a valid receipt image or PDF.']);
        exit;
    }

    $fileTmpPath = $_FILES['receipt_file']['tmp_name'];
    $originalFileName = basename($_FILES['receipt_file']['name']);
    $fileSize = (int) ($_FILES['receipt_file']['size'] ?? 0);
    $detectedContentType = function_exists('mime_content_type') ? mime_content_type($fileTmpPath) : false;
    $contentType = is_string($detectedContentType) && $detectedContentType !== ''
        ? $detectedContentType
        : (string) ($_FILES['receipt_file']['type'] ?? 'application/octet-stream');
    
    // مسار ملف الإيصال على R2
    $uniqueFileName = 'uploads/receipts/req_' . $request_id . '_' . time() . '_' . preg_replace("/[^a-zA-Z0-9.]/", "_", $originalFileName);

    try {
        // رفع الإيصال لـ R2
        $s3Client->putObject([
            'Bucket'     => $bucketName,
            'Key'        => $uniqueFileName,
            'SourceFile' => $fileTmpPath,
            'ContentType'=> $contentType,
        ]);

        // تسجيل بيانات الدفع في الداتا بيز (بحالة pending_verification)
        $stmtPay = $pdo->prepare("INSERT INTO payments
            (request_id, user_id, receipt_file_path, receipt_original_name, receipt_content_type, receipt_file_size, amount, status)
            VALUES (:request_id, :user_id, :receipt_file_path, :receipt_original_name, :receipt_content_type, :receipt_file_size, :amount, 'pending_verification')");
        $stmtPay->execute([
            ':request_id'        => $request_id,
            ':user_id'           => $user_id,
            ':receipt_file_path' => $uniqueFileName,
            ':receipt_original_name' => $originalFileName,
            ':receipt_content_type' => $contentType,
            ':receipt_file_size' => $fileSize,
            ':amount'            => $amount ? $amount : null
        ]);

        $stmtLog = $pdo->prepare("INSERT INTO request_activity_logs (request_id, actor_id, actor_role, action, new_value, note)
            VALUES (:request_id, :actor_id, 'clinic', 'receipt_uploaded', 'pending_verification', :note)");
        $stmtLog->execute([
            ':request_id' => $request_id,
            ':actor_id' => $user_id,
            ':note' => 'Payment receipt uploaded for verification.'
        ]);

        echo json_encode(['success' => 'Receipt uploaded successfully for verification!']);

    } catch (AwsException $e) {
        error_log("R2 Receipt Upload Error: " . $e->getMessage());
        echo json_encode(['error' => 'Failed to upload receipt to the cloud.']);
    } catch (\PDOException $e) {
        error_log("DB Error in upload_receipt: " . $e->getMessage());
        echo json_encode(['error' => 'A database error occurred.']);
    }
} else {
    echo json_encode(['error' => 'Invalid request method.']);
}
?>
