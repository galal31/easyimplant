<?php

require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/request_review.php';

function assertHttpTest(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function writeHttpTestSession(string $sessionId, array $values): void
{
    if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
    session_id($sessionId);
    session_start();
    $_SESSION = $values;
    session_write_close();
}

function finalHttpStatus(array $headers): int
{
    $status = 0;
    foreach ($headers as $header) {
        if (preg_match('/^HTTP\/\S+\s+(\d{3})\b/i', $header, $matches)) $status = (int) $matches[1];
    }
    return $status;
}

function localHttp(string $method, string $path, string $sessionId, array $payload = []): array
{
    $headers = [
        'Accept: application/json',
        'Cookie: PHPSESSID=' . $sessionId,
    ];
    $options = [
        'method' => $method,
        'ignore_errors' => true,
        'timeout' => 15,
    ];
    if ($method === 'POST') {
        $headers[] = 'Content-Type: application/json';
        $options['content'] = json_encode($payload);
    }
    $options['header'] = implode("\r\n", $headers);
    $context = stream_context_create(['http' => $options]);
    $body = file_get_contents('http://127.0.0.1/easyimplant/' . ltrim($path, '/'), false, $context);
    $responseHeaders = $http_response_header ?? [];
    return [
        'status' => finalHttpStatus($responseHeaders),
        'json' => json_decode((string) $body, true),
        'body' => (string) $body,
    ];
}

function assertRenderedScriptsParse(string $html, string $label): void
{
    preg_match_all('/<script>(.*?)<\/script>/si', $html, $matches);
    $script = implode("\n;\n", $matches[1] ?? []);
    assertHttpTest($script !== '', "$label did not contain an inline script to validate.");
    $temporaryPath = tempnam(sys_get_temp_dir(), 'easyimplant-js-');
    $path = $temporaryPath . '.js';
    rename($temporaryPath, $path);
    file_put_contents($path, $script);
    exec('node --check ' . escapeshellarg($path) . ' 2>&1', $output, $exitCode);
    unlink($path);
    assertHttpTest($exitCode === 0, "$label JavaScript failed node --check: " . implode("\n", $output));
}

if (session_status() === PHP_SESSION_ACTIVE) session_write_close();

$suffix = bin2hex(random_bytes(5));
$adminSession = 'codexadm' . $suffix;
$clinicSession = 'codexcli' . $suffix;
$otherSession = 'codexoth' . $suffix;
$adminToken = bin2hex(random_bytes(24));
$clinicToken = bin2hex(random_bytes(24));
$otherToken = bin2hex(random_bytes(24));
$requestIds = [];
$otherClinicId = null;

try {
    $adminId = (int) $pdo->query("SELECT id FROM users WHERE role = 'admin' AND status = 'approved' ORDER BY id LIMIT 1")->fetchColumn();
    $clinicId = (int) $pdo->query("SELECT id FROM users WHERE role = 'clinic' AND status = 'approved' ORDER BY id LIMIT 1")->fetchColumn();
    assertHttpTest($adminId > 0 && $clinicId > 0, 'Local HTTP tests require one approved admin and clinic.');

    $userStmt = $pdo->prepare("INSERT INTO users
        (full_name, clinic_name, email, password, phone, country, role, status)
        VALUES ('Other Clinic Test', 'Other Clinic Test', :email, :password, '0000000000', 'egypt', 'clinic', 'approved')");
    $userStmt->execute([
        ':email' => "http-review-$suffix@example.test",
        ':password' => password_hash('test-only', PASSWORD_DEFAULT),
    ]);
    $otherClinicId = (int) $pdo->lastInsertId();

    writeHttpTestSession($adminSession, ['user_id' => $adminId, 'role' => 'admin', 'full_name' => 'Admin Test', 'clinic_name' => 'Easy Implant', 'request_workflow_csrf_token' => $adminToken]);
    writeHttpTestSession($clinicSession, ['user_id' => $clinicId, 'role' => 'clinic', 'full_name' => 'Clinic Test', 'clinic_name' => 'Clinic Test', 'request_workflow_csrf_token' => $clinicToken]);
    writeHttpTestSession($otherSession, ['user_id' => $otherClinicId, 'role' => 'clinic', 'full_name' => 'Other Clinic', 'clinic_name' => 'Other Clinic', 'request_workflow_csrf_token' => $otherToken]);

    $requestStmt = $pdo->prepare("INSERT INTO requests (user_id, service_type, status) VALUES (:user, :type, :status)");
    $requestStmt->execute([':user' => $clinicId, ':type' => 'surgical_guide', ':status' => 'pending_review']);
    $pendingRequestId = (int) $pdo->lastInsertId();
    $requestIds[] = $pendingRequestId;

    // update_request_status.php uses form input, so exercise it with a dedicated form request.
    $formContext = stream_context_create(['http' => [
        'method' => 'POST',
        'ignore_errors' => true,
        'header' => "Content-Type: application/x-www-form-urlencoded\r\nCookie: PHPSESSID=$adminSession",
        'content' => http_build_query(['request_id' => $pendingRequestId, 'status' => 'pending_payment', 'csrf_token' => $adminToken]),
    ]]);
    $directBody = file_get_contents('http://127.0.0.1/easyimplant/api/update_request_status.php', false, $formContext);
    $directStatus = finalHttpStatus($http_response_header ?? []);
    assertHttpTest($directStatus === 409, 'Admin direct pending_review to pending_payment must return HTTP 409.');
    assertHttpTest($pdo->query("SELECT status FROM requests WHERE id = $pendingRequestId")->fetchColumn() === 'pending_review', 'Rejected direct transition must not change the request.');
    $emptyReview = localHttp('POST', 'api/admin_send_review.php', $adminSession, [
        'request_id' => $pendingRequestId,
        'csrf_token' => $adminToken,
        'summary' => 'Explanation without a file',
        'files' => [],
    ]);
    assertHttpTest($emptyReview['status'] === 400, 'Admin review submission without a file must return HTTP 400.');
    $invalidReviewUpload = localHttp('POST', 'api/generate_review_upload_url.php', $adminSession, [
        'request_id' => $pendingRequestId,
        'csrf_token' => $adminToken,
        'filename' => 'unsafe.exe',
        'content_type' => 'application/octet-stream',
        'file_size' => 100,
    ]);
    assertHttpTest($invalidReviewUpload['status'] === 400, 'Unsupported review extensions must be rejected before generating an R2 URL.');
    $pdo->prepare("UPDATE requests SET status = 'completed' WHERE id = :id")->execute([':id' => $pendingRequestId]);
    $readOnlyMessage = localHttp('POST', 'api/send_request_message.php', $clinicSession, [
        'request_id' => $pendingRequestId,
        'csrf_token' => $clinicToken,
        'message_text' => 'This must not be saved.',
    ]);
    assertHttpTest($readOnlyMessage['status'] === 409, 'Completed-request conversations must be read-only.');

    $requestStmt->execute([':user' => $clinicId, ':type' => 'surgical_guide', ':status' => 'awaiting_clinic_approval']);
    $reviewRequestId = (int) $pdo->lastInsertId();
    $requestIds[] = $reviewRequestId;
    $detailsStmt = $pdo->prepare("INSERT INTO surgical_guide_details
        (request_id, operation_date, cbct_file_path, stl_file_path, implant_type, delivery_method)
        VALUES (:request, DATE_ADD(CURDATE(), INTERVAL 7 DAY), :cbct, :stl, 'HTTP Test Implant', 'clinic_print')");
    $detailsStmt->execute([
        ':request' => $reviewRequestId,
        ':cbct' => "tests/http-cbct-$suffix.zip",
        ':stl' => "tests/http-scan-$suffix.stl",
    ]);
    $packageStmt = $pdo->prepare("INSERT INTO request_review_packages (request_id, admin_id, summary) VALUES (:request, :admin, :summary)");
    $reviewFileStmt = $pdo->prepare("INSERT INTO request_review_files
        (package_id, file_path, original_name, content_type, file_size)
        VALUES (:package, :path, :name, 'application/pdf', 128)");
    $packageIds = [];
    foreach (['First round', 'Latest round'] as $index => $summary) {
        $packageStmt->execute([':request' => $reviewRequestId, ':admin' => $adminId, ':summary' => $summary]);
        $packageId = (int) $pdo->lastInsertId();
        $packageIds[] = $packageId;
        $reviewFileStmt->execute([
            ':package' => $packageId,
            ':path' => "tests/http-review-$suffix-$index.pdf",
            ':name' => "review-$index.pdf",
        ]);
    }
    $paymentStmt = $pdo->prepare("INSERT INTO payments (request_id, user_id, receipt_file_path, amount, status)
        VALUES (:request, :user, :path, 1.00, 'pending_verification')");
    $paymentStmt->execute([':request' => $reviewRequestId, ':user' => $clinicId, ':path' => "tests/http-receipt-$suffix.pdf"]);
    $paymentId = (int) $pdo->lastInsertId();

    $clinicPage = localHttp('GET', "view_request.php?id=$reviewRequestId", $clinicSession);
    assertHttpTest($clinicPage['status'] === 200 && str_contains($clinicPage['body'], 'Latest review') && str_contains($clinicPage['body'], 'approveReviewButton'), 'Clinic request page must render the latest review and approval action.');
    assertHttpTest(!str_contains($clinicPage['body'], '> Upload Receipt<'), 'Clinic Surgical Guide page must not render a manual receipt action.');
    assertRenderedScriptsParse($clinicPage['body'], 'Clinic request page');
    $adminPage = localHttp('GET', "admin/admin_view_request.php?id=$reviewRequestId", $adminSession);
    assertHttpTest($adminPage['status'] === 200 && str_contains($adminPage['body'], 'Send review to clinic') && str_contains($adminPage['body'], 'Request conversation'), 'Admin request page must render review-package and chat controls.');
    assertHttpTest(!str_contains($adminPage['body'], 'Approve &amp; Request Payment') && !str_contains($adminPage['body'], 'Approve & Request Payment'), 'Admin page must not render the old direct-payment action.');
    assertRenderedScriptsParse($adminPage['body'], 'Admin request page');

    $otherRead = localHttp('GET', "api/request_messages.php?request_id=$reviewRequestId&after_id=0&csrf_token=$otherToken", $otherSession);
    assertHttpTest($otherRead['status'] === 404, 'Another clinic must receive HTTP 404 for request messages. Got ' . $otherRead['status'] . ': ' . $otherRead['body']);
    $missingCsrfRead = localHttp('GET', "api/request_messages.php?request_id=$reviewRequestId&after_id=0", $clinicSession);
    assertHttpTest($missingCsrfRead['status'] === 403, 'Manual message refresh without CSRF must return HTTP 403.');
    $emptyMessage = localHttp('POST', 'api/send_request_message.php', $clinicSession, [
        'request_id' => $reviewRequestId,
        'csrf_token' => $clinicToken,
        'message_text' => '   ',
    ]);
    assertHttpTest($emptyMessage['status'] === 400, 'Empty messages must be rejected.');
    $longMessage = localHttp('POST', 'api/send_request_message.php', $clinicSession, [
        'request_id' => $reviewRequestId,
        'csrf_token' => $clinicToken,
        'message_text' => str_repeat('x', 4001),
    ]);
    assertHttpTest($longMessage['status'] === 400, 'Messages above the maximum length must be rejected.');

    $clinicMessage = localHttp('POST', 'api/send_request_message.php', $clinicSession, [
        'request_id' => $reviewRequestId,
        'csrf_token' => $clinicToken,
        'message_text' => 'Clinic HTTP test message',
    ]);
    $adminMessage = localHttp('POST', 'api/send_request_message.php', $adminSession, [
        'request_id' => $reviewRequestId,
        'csrf_token' => $adminToken,
        'message_text' => 'Admin HTTP test reply',
    ]);
    assertHttpTest($clinicMessage['status'] === 200 && $adminMessage['status'] === 200, 'Both roles must be able to send active-request messages.');
    $manualRefresh = localHttp('GET', "api/request_messages.php?request_id=$reviewRequestId&after_id=0&csrf_token=$clinicToken", $clinicSession);
    assertHttpTest($manualRefresh['status'] === 200 && count($manualRefresh['json']['messages'] ?? []) === 2, 'Manual refresh must return both chronological messages.');
    $limitedRefresh = localHttp('GET', "api/request_messages.php?request_id=$reviewRequestId&after_id=0&csrf_token=$clinicToken", $clinicSession);
    assertHttpTest($limitedRefresh['status'] === 429, 'A repeated clinic refresh inside the cooldown must return HTTP 429.');
    assertHttpTest(
        ($limitedRefresh['json']['code'] ?? '') === 'chat_refresh_rate_limited'
        && (int) ($limitedRefresh['json']['retry_after'] ?? 0) >= 1
        && (int) ($limitedRefresh['json']['retry_after'] ?? 0) <= REQUEST_MESSAGE_REFRESH_COOLDOWN_SECONDS,
        'The rate-limit response must include a bounded retry_after value.'
    );
    $adminRefresh = localHttp('GET', "api/request_messages.php?request_id=$reviewRequestId&after_id=0&csrf_token=$adminToken", $adminSession);
    assertHttpTest($adminRefresh['status'] === 200, 'The refresh cooldown must be isolated between the clinic and admin sessions.');

    $oldApproval = localHttp('POST', 'api/approve_review.php', $clinicSession, [
        'request_id' => $reviewRequestId,
        'package_id' => $packageIds[0],
        'csrf_token' => $clinicToken,
    ]);
    assertHttpTest($oldApproval['status'] === 409, 'Approving an old review round must return HTTP 409.');
    $paymentsBefore = (int) $pdo->query("SELECT COUNT(*) FROM payments WHERE request_id = $reviewRequestId")->fetchColumn();
    $latestApproval = localHttp('POST', 'api/approve_review.php', $clinicSession, [
        'request_id' => $reviewRequestId,
        'package_id' => $packageIds[1],
        'csrf_token' => $clinicToken,
    ]);
    assertHttpTest($latestApproval['status'] === 200, 'The owning clinic must be able to approve the latest review round. Response: ' . $latestApproval['body']);
    $paymentsAfter = (int) $pdo->query("SELECT COUNT(*) FROM payments WHERE request_id = $reviewRequestId")->fetchColumn();
    assertHttpTest($paymentsBefore === $paymentsAfter, 'Review approval must not create a payment.');
    assertHttpTest($pdo->query("SELECT status FROM requests WHERE id = $reviewRequestId")->fetchColumn() === 'pending_payment', 'HTTP approval must move the request to pending_payment only.');
    $approvedPage = localHttp('GET', "view_request.php?id=$reviewRequestId", $clinicSession);
    assertHttpTest(
        $approvedPage['status'] === 200
            && !str_contains($approvedPage['body'], 'id="approveReviewButton"')
            && !str_contains($approvedPage['body'], 'Upload Receipt')
            && str_contains($approvedPage['body'], 'Pay the approved request total securely through XPay')
            && str_contains($approvedPage['body'], 'Production starts only after XPay confirms the payment'),
        'Approved page must remove approval/manual receipt actions and explain the XPay confirmation step.'
    );

    $activeCheckoutStmt = $pdo->prepare("INSERT INTO xpay_checkout_sessions
        (request_id, user_id, idempotency_key, return_token, xpay_session_id, checkout_url,
         status, payment_status, amount_minor, currency, livemode, expires_at)
        VALUES (:request_id, :user_id, :idempotency_key, :return_token, :session_id,
                :checkout_url, 'open', 'unpaid', 10000, 'EGP', 0,
                DATE_ADD(UTC_TIMESTAMP(), INTERVAL 30 MINUTE))");
    $activeCheckoutStmt->execute([
        ':request_id' => $reviewRequestId,
        ':user_id' => $clinicId,
        ':idempotency_key' => 'http-test-' . $suffix,
        ':return_token' => hash('sha256', 'http-return-' . $suffix),
        ':session_id' => 'cs_test_http_' . $suffix,
        ':checkout_url' => 'https://checkout.xpay.app/c/cs_test_http_' . $suffix,
    ]);
    $rejectDuringCheckoutContext = stream_context_create(['http' => [
        'method' => 'POST',
        'ignore_errors' => true,
        'header' => "Content-Type: application/x-www-form-urlencoded\r\nCookie: PHPSESSID=$adminSession",
        'content' => http_build_query(['request_id' => $reviewRequestId, 'status' => 'rejected', 'csrf_token' => $adminToken]),
    ]]);
    $rejectDuringCheckoutBody = file_get_contents('http://127.0.0.1/easyimplant/api/update_request_status.php', false, $rejectDuringCheckoutContext);
    $rejectDuringCheckoutStatus = finalHttpStatus($http_response_header ?? []);
    assertHttpTest($rejectDuringCheckoutStatus === 409 && str_contains((string) $rejectDuringCheckoutBody, 'active XPay checkout'), 'An active XPay checkout must block request rejection.');
    assertHttpTest($pdo->query("SELECT status FROM requests WHERE id = $reviewRequestId")->fetchColumn() === 'pending_payment', 'Blocked rejection must leave the request in pending_payment.');
    $pdo->prepare('DELETE FROM xpay_checkout_sessions WHERE request_id = :request_id')->execute([':request_id' => $reviewRequestId]);

    $doubleApproval = localHttp('POST', 'api/approve_review.php', $clinicSession, [
        'request_id' => $reviewRequestId,
        'package_id' => $packageIds[1],
        'csrf_token' => $clinicToken,
    ]);
    assertHttpTest($doubleApproval['status'] === 409, 'A duplicate approval must return HTTP 409.');

    $uploadContext = stream_context_create(['http' => [
        'method' => 'POST', 'ignore_errors' => true,
        'header' => "Content-Type: application/x-www-form-urlencoded\r\nCookie: PHPSESSID=$clinicSession",
        'content' => http_build_query(['request_id' => $reviewRequestId, 'amount' => '1.00']),
    ]]);
    $uploadBody = file_get_contents('http://127.0.0.1/easyimplant/api/upload_receipt.php', false, $uploadContext);
    $uploadStatus = finalHttpStatus($http_response_header ?? []);
    assertHttpTest($uploadStatus === 409 && str_contains((string) $uploadBody, 'Manual payment receipts are disabled'), 'Surgical Guide receipt uploads must be blocked server-side.');

    $verifyContext = stream_context_create(['http' => [
        'method' => 'POST', 'ignore_errors' => true,
        'header' => "Content-Type: application/x-www-form-urlencoded\r\nCookie: PHPSESSID=$adminSession",
        'content' => http_build_query(['payment_id' => $paymentId, 'action' => 'approved', 'csrf_token' => $adminToken]),
    ]]);
    $verifyBody = file_get_contents('http://127.0.0.1/easyimplant/api/verify_receipt.php', false, $verifyContext);
    $verifyStatus = finalHttpStatus($http_response_header ?? []);
    assertHttpTest($verifyStatus === 409 && str_contains((string) $verifyBody, 'Historical receipts are read-only'), 'Surgical Guide receipt approval must be blocked server-side.');

    $requestStmt->execute([':user' => $clinicId, ':type' => 'surgeon_request', ':status' => 'pending_review']);
    $surgeonRequestId = (int) $pdo->lastInsertId();
    $requestIds[] = $surgeonRequestId;
    $surgeonContext = stream_context_create(['http' => [
        'method' => 'POST', 'ignore_errors' => true,
        'header' => "Content-Type: application/x-www-form-urlencoded\r\nCookie: PHPSESSID=$adminSession",
        'content' => http_build_query(['request_id' => $surgeonRequestId, 'status' => 'pending_payment']),
    ]]);
    file_get_contents('http://127.0.0.1/easyimplant/api/update_request_status.php', false, $surgeonContext);
    $surgeonStatus = finalHttpStatus($http_response_header ?? []);
    assertHttpTest($surgeonStatus === 200, 'The existing surgeon-request status API behavior must remain unchanged.');

    echo "Surgical Guide review HTTP tests passed.\n";
} finally {
    if ($requestIds) {
        $delete = $pdo->prepare('DELETE FROM requests WHERE id IN (' . implode(',', array_fill(0, count($requestIds), '?')) . ')');
        $delete->execute($requestIds);
    }
    if ($otherClinicId) $pdo->prepare('DELETE FROM users WHERE id = :id')->execute([':id' => $otherClinicId]);
    foreach ([$adminSession, $clinicSession, $otherSession] as $sessionId) {
        $sessionFile = rtrim((string) ini_get('session.save_path'), '/\\') . DIRECTORY_SEPARATOR . 'sess_' . $sessionId;
        if (is_file($sessionFile)) unlink($sessionFile);
    }
}
