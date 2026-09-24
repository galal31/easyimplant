<?php
// api/delete_request.php
session_start();
require_once '../includes/db_connect.php';
require_once '../includes/r2_config.php';
require_once '../includes/surgical_guide_kits.php';
require_once '../includes/surgical_guide_pricing.php';

use Aws\Exception\AwsException;

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

$request_id = filter_input(INPUT_POST, 'request_id', FILTER_VALIDATE_INT);

if (!$request_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid Request ID.']);
    exit;
}

try {
    ensureSurgicalGuideKitsSchema($pdo);
    // 1. تجميع مسارات الملفات المرتبطة بالطلب لمسحها من السحابة
    $filesToDelete = [];

    // جلب ملفات Surgical Guide
    $stmt_guide = $pdo->prepare("SELECT cbct_file_path, stl_file_path FROM surgical_guide_details WHERE request_id = :id");
    $stmt_guide->execute([':id' => $request_id]);
    if ($guide = $stmt_guide->fetch()) {
        if (!empty($guide['cbct_file_path'])) $filesToDelete[] = ['Key' => $guide['cbct_file_path']];
        if (!empty($guide['stl_file_path'])) $filesToDelete[] = ['Key' => $guide['stl_file_path']];
    }

    $stmt_guide_kit_files = $pdo->prepare("SELECT file_path FROM surgical_guide_kit_files WHERE request_id = :id");
    $stmt_guide_kit_files->execute([':id' => $request_id]);
    while ($guideKitFile = $stmt_guide_kit_files->fetch()) {
        if (!empty($guideKitFile['file_path'])) $filesToDelete[] = ['Key' => $guideKitFile['file_path']];
    }

    // جلب ملفات طلب الجراح المرفوعة مباشرة إلى R2
    $stmt_surgeon_files = $pdo->prepare("SELECT file_path FROM surgeon_request_files WHERE request_id = :id");
    $stmt_surgeon_files->execute([':id' => $request_id]);
    while ($surgeon_file = $stmt_surgeon_files->fetch()) {
        if (!empty($surgeon_file['file_path'])) $filesToDelete[] = ['Key' => $surgeon_file['file_path']];
    }

    // جلب ملفات التسليم (Deliverables)
    $stmt_deliv = $pdo->prepare("SELECT file_path FROM request_deliverables WHERE request_id = :id");
    $stmt_deliv->execute([':id' => $request_id]);
    while ($deliv = $stmt_deliv->fetch()) {
        if (!empty($deliv['file_path'])) $filesToDelete[] = ['Key' => $deliv['file_path']];
    }

    // Review-package files are separate from final deliverables.
    $stmt_review_files = $pdo->prepare("SELECT rf.file_path
        FROM request_review_files rf
        JOIN request_review_packages rp ON rp.id = rf.package_id
        WHERE rp.request_id = :id");
    $stmt_review_files->execute([':id' => $request_id]);
    while ($reviewFile = $stmt_review_files->fetch()) {
        if (!empty($reviewFile['file_path'])) $filesToDelete[] = ['Key' => $reviewFile['file_path']];
    }

    // جلب ملفات إيصالات الدفع
    $stmt_pay = $pdo->prepare("SELECT receipt_file_path FROM payments WHERE request_id = :id");
    $stmt_pay->execute([':id' => $request_id]);
    while ($pay = $stmt_pay->fetch()) {
        if (!empty($pay['receipt_file_path'])) $filesToDelete[] = ['Key' => $pay['receipt_file_path']];
    }

    // 2. مسح الملفات من Cloudflare R2
    if (!empty($filesToDelete)) {
        try {
            $s3Client->deleteObjects([
                'Bucket' => $bucketName,
                'Delete' => [
                    'Objects' => $filesToDelete,
                    'Quiet' => false
                ]
            ]);
        } catch (AwsException $e) {
            error_log("R2 Delete Error: " . $e->getMessage());
            // هنكمل مسح من الداتابيز حتى لو حصل مشكلة في السحابة عشان الداتابيز متتعلقش
        }
    }

    // 3. Release any open reward reservation, then delete atomically.
    $pdo->beginTransaction();
    $requestLock = $pdo->prepare("SELECT service_type, status FROM requests WHERE id = :id FOR UPDATE");
    $requestLock->execute([':id' => $request_id]);
    $requestRow = $requestLock->fetch(PDO::FETCH_ASSOC);
    if ($requestRow && $requestRow['service_type'] === 'surgical_guide' && $requestRow['status'] !== 'completed') {
        releaseClinicFreeImplantReservation($pdo, (int) $request_id);
    }

    $stmt_delete = $pdo->prepare("DELETE FROM requests WHERE id = :id");
    $stmt_delete->execute([':id' => $request_id]);

    if ($stmt_delete->rowCount() > 0) {
        $pdo->commit();
        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Request and all associated files deleted successfully.']);
    } else {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Request not found.']);
    }

} catch (\Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log("Delete Request DB Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
