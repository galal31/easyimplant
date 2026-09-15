<?php

require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/request_workflow.php';
require_once __DIR__ . '/../includes/request_review.php';

function assertReviewTest(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function tableExists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table");
    $stmt->execute([':table' => $table]);
    return (bool) $stmt->fetchColumn();
}

foreach (['request_review_packages', 'request_review_files', 'request_messages'] as $table) {
    assertReviewTest(tableExists($pdo, $table), "Missing migrated table: $table");
}

assertReviewTest(!surgicalGuideTransitionIsAllowed('pending_review', 'pending_payment', 'admin'), 'Admin must not skip clinic approval.');
assertReviewTest(surgicalGuideTransitionIsAllowed('pending_review', 'awaiting_clinic_approval', 'review_package'), 'First review package must start clinic approval.');
assertReviewTest(surgicalGuideTransitionIsAllowed('awaiting_clinic_approval', 'awaiting_clinic_approval', 'review_package'), 'A revised review package must keep the same status.');
assertReviewTest(surgicalGuideTransitionIsAllowed('awaiting_clinic_approval', 'pending_payment', 'clinic_approval'), 'Clinic approval must lead to pending payment.');
assertReviewTest(!surgicalGuideTransitionIsAllowed('pending_payment', 'in_progress', 'admin'), 'Admin must not manually start production.');
assertReviewTest(!surgicalGuideTransitionIsAllowed('pending_payment', 'in_progress', 'payment_approval'), 'Manual receipt approval must not start production.');
assertReviewTest(surgicalGuideTransitionIsAllowed('pending_payment', 'in_progress', 'gateway_payment_confirmation'), 'The future gateway transition remains explicitly isolated.');
assertReviewTest(surgicalGuideTransitionIsAllowed('in_progress', 'completed', 'deliverables'), 'The existing final-deliverables completion path must remain available.');

assertReviewTest(detectRequestReviewFileType("\x89PNG\x0D\x0A\x1A\x0Arest") === 'image/png', 'PNG signature detection failed.');
assertReviewTest(detectRequestReviewFileType('%PDF-1.7') === 'application/pdf', 'PDF signature detection failed.');
assertReviewTest(requestReviewDetectedTypeMatches('docx', 'application/zip'), 'OOXML document signature mapping failed.');

$_SESSION['request_message_refresh_limits'] = [];
assertReviewTest(consumeRequestMessageRefreshLimit(10, 20, 'clinic', 1000.0) === 0, 'The first message refresh must be allowed.');
assertReviewTest(consumeRequestMessageRefreshLimit(10, 20, 'clinic', 1001.0) === 4, 'A rapid repeat refresh must return the remaining cooldown.');
assertReviewTest(consumeRequestMessageRefreshLimit(11, 20, 'clinic', 1001.0) === 0, 'The cooldown must be isolated per request.');
assertReviewTest(consumeRequestMessageRefreshLimit(10, 21, 'clinic', 1001.0) === 0, 'The cooldown must be isolated per user.');
assertReviewTest(consumeRequestMessageRefreshLimit(10, 20, 'clinic', 1005.0) === 0, 'Refresh must be allowed after the cooldown expires.');
unset($_SESSION['request_message_refresh_limits']);

$suffix = bin2hex(random_bytes(5));
$insertUser = $pdo->prepare("INSERT INTO users
    (full_name, clinic_name, email, password, phone, country, role, status)
    VALUES (:name, :clinic, :email, :password, :phone, 'egypt', :role, 'approved')");

$pdo->beginTransaction();
try {
    $userIds = [];
    foreach ([['Admin Test', 'Admin', 'admin'], ['Clinic One', 'Clinic One', 'clinic'], ['Clinic Two', 'Clinic Two', 'clinic']] as $index => $user) {
        $insertUser->execute([
            ':name' => $user[0],
            ':clinic' => $user[1],
            ':email' => "review-test-$index-$suffix@example.test",
            ':password' => password_hash('test-only', PASSWORD_DEFAULT),
            ':phone' => '0000000000',
            ':role' => $user[2],
        ]);
        $userIds[] = (int) $pdo->lastInsertId();
    }
    [$adminId, $clinicOneId, $clinicTwoId] = $userIds;

    $requestStmt = $pdo->prepare("INSERT INTO requests (user_id, service_type, status)
        VALUES (:user_id, 'surgical_guide', 'pending_review')");
    $requestStmt->execute([':user_id' => $clinicOneId]);
    $requestId = (int) $pdo->lastInsertId();
    assertReviewTest($pdo->query("SELECT status FROM requests WHERE id = $requestId")->fetchColumn() === 'pending_review', 'New Surgical Guide request must start pending review.');

    $owned = requireSurgicalGuideConversationAccess($pdo, $requestId, $clinicOneId, 'clinic');
    assertReviewTest((int) $owned['id'] === $requestId, 'The owning clinic must access its request.');
    $otherClinicBlocked = false;
    try {
        requireSurgicalGuideConversationAccess($pdo, $requestId, $clinicTwoId, 'clinic');
    } catch (RuntimeException) {
        $otherClinicBlocked = true;
    }
    assertReviewTest($otherClinicBlocked, 'Another clinic must not access review messages or files.');

    $packageStmt = $pdo->prepare("INSERT INTO request_review_packages (request_id, admin_id, summary)
        VALUES (:request_id, :admin_id, :summary)");
    $fileStmt = $pdo->prepare("INSERT INTO request_review_files
        (package_id, file_path, original_name, content_type, file_size)
        VALUES (:package_id, :path, :name, :type, :size)");
    $packageIds = [];
    foreach ([1, 2] as $round) {
        $packageStmt->execute([':request_id' => $requestId, ':admin_id' => $adminId, ':summary' => "Review round $round"]);
        $packageId = (int) $pdo->lastInsertId();
        $packageIds[] = $packageId;
        $fileStmt->execute([
            ':package_id' => $packageId,
            ':path' => "tests/review-$suffix-$round.pdf",
            ':name' => "review-$round.pdf",
            ':type' => 'application/pdf',
            ':size' => 128 + $round,
        ]);
        $pdo->prepare("UPDATE requests SET status = 'awaiting_clinic_approval' WHERE id = :id")
            ->execute([':id' => $requestId]);
    }

    $packages = fetchRequestReviewPackages($pdo, $requestId);
    assertReviewTest(count($packages) === 2, 'A second package must preserve the first review round.');
    assertReviewTest((int) $packages[0]['id'] === $packageIds[1], 'The newest review round must be returned first.');
    assertReviewTest((int) $packages[1]['id'] === $packageIds[0], 'The older review round must remain available.');

    $paymentsBefore = (int) $pdo->query("SELECT COUNT(*) FROM payments WHERE request_id = $requestId")->fetchColumn();
    $approveStmt = $pdo->prepare("UPDATE request_review_packages SET approved_at = NOW(), approved_by = :clinic WHERE id = :id");
    $approveStmt->execute([':clinic' => $clinicOneId, ':id' => $packageIds[1]]);
    $pdo->prepare("UPDATE requests SET status = 'pending_payment' WHERE id = :id")
        ->execute([':id' => $requestId]);
    $paymentsAfter = (int) $pdo->query("SELECT COUNT(*) FROM payments WHERE request_id = $requestId")->fetchColumn();
    assertReviewTest($paymentsBefore === 0 && $paymentsAfter === 0, 'Clinic approval must not create a payment record.');
    assertReviewTest($pdo->query("SELECT status FROM requests WHERE id = $requestId")->fetchColumn() === 'pending_payment', 'Latest-round approval must lead to pending payment.');
    assertReviewTest($pdo->query("SELECT approved_at IS NULL FROM request_review_packages WHERE id = {$packageIds[0]}")->fetchColumn() === 1, 'Approving the latest round must not alter an older round.');

    $messageStmt = $pdo->prepare("INSERT INTO request_messages (request_id, sender_id, sender_role, message_text)
        VALUES (:request_id, :sender_id, :sender_role, :text)");
    $messageStmt->execute([':request_id' => $requestId, ':sender_id' => $clinicOneId, ':sender_role' => 'clinic', ':text' => 'Please confirm the sleeve size.']);
    $firstMessageId = (int) $pdo->lastInsertId();
    $messageStmt->execute([':request_id' => $requestId, ':sender_id' => $adminId, ':sender_role' => 'admin', ':text' => 'Confirmed in the latest package.']);
    $messages = fetchRequestMessages($pdo, $requestId);
    assertReviewTest(count($messages) === 2 && $messages[0]['sender_role'] === 'clinic' && $messages[1]['sender_role'] === 'admin', 'Messages must preserve chronological order and sender roles.');
    assertReviewTest(count(fetchRequestMessages($pdo, $requestId, $firstMessageId)) === 1, 'Manual after_id refresh must return only newer messages.');

    $pdo->prepare("UPDATE requests SET status = 'completed' WHERE id = :id")->execute([':id' => $requestId]);
    assertReviewTest(!surgicalGuideChatIsWritable('completed') && !surgicalGuideChatIsWritable('rejected'), 'Completed and rejected conversations must be read-only.');

    $pdo->prepare("DELETE FROM requests WHERE id = :id")->execute([':id' => $requestId]);
    foreach (['request_review_packages', 'request_review_files', 'request_messages'] as $table) {
        $column = $table === 'request_review_files' ? 'package_id' : 'request_id';
        if ($table === 'request_review_files') {
            $remaining = $pdo->query("SELECT COUNT(*) FROM request_review_files WHERE package_id IN ({$packageIds[0]}, {$packageIds[1]})")->fetchColumn();
        } else {
            $remaining = $pdo->query("SELECT COUNT(*) FROM `$table` WHERE `$column` = $requestId")->fetchColumn();
        }
        assertReviewTest((int) $remaining === 0, "$table rows must cascade when a request is deleted.");
    }

    $pdo->rollBack();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    throw $e;
}

foreach (['admin/admin_view_request.php', 'view_request.php'] as $page) {
    $source = file_get_contents(__DIR__ . '/../' . $page);
    assertReviewTest(!preg_match('/setInterval|WebSocket|EventSource|long\s*poll/i', $source), "$page must not contain automatic chat polling.");
    assertReviewTest(substr_count($source, 'request_messages.php?request_id=') === 1, "$page must make one manual message-refresh request per click handler.");
    assertReviewTest(str_contains($source, 'startChatRefreshCooldown'), "$page must show the manual refresh cooldown in its button.");
}

$uploadSource = file_get_contents(__DIR__ . '/../api/upload_receipt.php');
$verifySource = file_get_contents(__DIR__ . '/../api/verify_receipt.php');
assertReviewTest(str_contains($uploadSource, 'Manual payment receipts are disabled for Surgical Guide requests.'), 'Receipt upload API must block Surgical Guides.');
assertReviewTest(str_contains($verifySource, 'Historical receipts are read-only.'), 'Receipt review API must keep Surgical Guide receipts read-only.');

echo "Surgical Guide review workflow tests passed.\n";
