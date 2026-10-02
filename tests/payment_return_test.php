<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
define('EASYIMPLANT_SKIP_SESSION', true);
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/payment_return.php';
require_once __DIR__ . '/../includes/login_destination.php';
if (APP_ENVIRONMENT !== 'local') throw new RuntimeException('Local test database only.');
function checkReturn(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
$pdo->beginTransaction();
try {
    $tag = bin2hex(random_bytes(5));
    $pdo->prepare("INSERT INTO users (full_name,clinic_name,email,password,phone,country,role,status) VALUES ('Return Test','Return Test',?,'unused','000','egypt','clinic','approved')")->execute(['return-'.$tag.'@example.invalid']);
    $user = ['id'=>(int)$pdo->lastInsertId(),'role'=>'clinic'];
    $pdo->prepare("INSERT INTO requests (user_id,service_type,status) VALUES (?,'surgeon_request','pending_payment')")->execute([$user['id']]);
    $id = (int)$pdo->lastInsertId();
    $token = bin2hex(random_bytes(32));
    $session = 'cs_return_' . $tag;
    $pdo->prepare("INSERT INTO xpay_checkout_sessions (request_id,user_id,idempotency_key,return_token,xpay_session_id,status,payment_status,amount_minor,currency,expires_at) VALUES (?,?,?, ?,?,'open','unpaid',150000,'EGP',DATE_ADD(UTC_TIMESTAMP(),INTERVAL 30 MINUTE))")->execute([$id,$user['id'],$tag,$token,$session]);
    checkReturn(paymentReturnStatus($pdo,'invalid')['state']==='missing','Invalid token accepted.');
    checkReturn(paymentReturnStatus($pdo,bin2hex(random_bytes(32)))['state']==='missing','Unknown token leaked a request.');
    checkReturn(paymentReturnStatus($pdo,$token)['state']==='pending','Open payment not pending.');
    checkReturn(paymentReturnStatus($pdo,$token,'paid')['state']==='pending','URL trusted as payment confirmation.');
    checkReturn(paymentReturnStatus($pdo,$token,'failed')['state']==='cancelled','Cancel return not shown.');
    checkReturn(!paymentReturnStatus($pdo,$token,'failed')['terminal'],'Cancelled return stopped checking for delayed confirmation.');
    $pdo->prepare("UPDATE xpay_checkout_sessions SET expires_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 MINUTE) WHERE return_token=?")->execute([$token]);
    checkReturn(paymentReturnStatus($pdo,$token)['state']==='expired','Expired payment not recognized.');
    $pdo->prepare("UPDATE xpay_checkout_sessions SET status='complete',payment_status='paid' WHERE return_token=?")->execute([$token]);
    checkReturn(paymentReturnStatus($pdo,$token)['state']==='pending','Unverified session status treated as paid.');
    $pdo->prepare("INSERT INTO xpay_webhook_events (event_id,event_type,xpay_session_id,payload_sha256,processing_status) VALUES (?,'checkout.session.async_payment_failed',?,?,'processed')")->execute(['evt_return_'.$tag,$session,str_repeat('a',64)]);
    checkReturn(paymentReturnStatus($pdo,$token)['state']==='failed','Async failure not recognized.');
    $pdo->prepare("UPDATE xpay_webhook_events SET processing_status='rejected' WHERE xpay_session_id=?")->execute([$session]);
    checkReturn(paymentReturnStatus($pdo,$token)['state']==='review','Rejected confirmation not shown.');
    $pdo->prepare("INSERT INTO payments (request_id,user_id,amount,status,payment_source,currency,provider_session_id,provider_payment_intent_id,approved_at) VALUES (?,?,1500,'approved','xpay','EGP',?,?,NOW())")->execute([$id,$user['id'],$session,'pi_return_'.$tag]);
    $status = paymentReturnStatus($pdo,$token,'failed');
    checkReturn($status['state']==='paid' && $status['terminal'],'Confirmed payment lost to cancelled return.');
    checkReturn(!str_contains($status['message'],'in progress'),'Incorrect request state asserted.');
    foreach (['in_progress'=>'awaiting the operation','completed'=>'completed','rejected'=>'current status','cancelled'=>'Financial review'] as $requestState=>$text) {
        $pdo->prepare('UPDATE requests SET status=? WHERE id=?')->execute([$requestState,$id]);
        checkReturn(str_contains(paymentReturnStatus($pdo,$token)['message'],$text),'Incorrect paid request message for '.$requestState.'.');
    }
    $pdo->prepare("UPDATE requests SET service_type='surgical_guide',status='in_progress' WHERE id=?")->execute([$id]);
    checkReturn(str_contains(paymentReturnStatus($pdo,$token)['message'],'in progress'),'Surgical Guide payment return message changed.');
    $pdo->prepare("UPDATE requests SET service_type='surgeon_request' WHERE id=?")->execute([$id]);
    $pdo->prepare('UPDATE payments SET amount=1 WHERE provider_session_id=?')->execute([$session]);
    checkReturn(paymentReturnStatus($pdo,$token)['state']!=='paid','Mismatched payment accepted.');
    checkReturn(loginDestination($pdo,$user,$id)==='view_request.php?id='.$id,'Login lost request destination.');
    checkReturn(loginDestination($pdo,['id'=>$user['id']+999999,'role'=>'clinic'],$id)==='clinic_dashboard.php','Another clinic request accepted.');
    checkReturn(loginDestination($pdo,$user,'https://example.com')==='clinic_dashboard.php','External redirect accepted.');
    checkReturn(loginDestination($pdo,$user,[$id])==='clinic_dashboard.php','Array redirect accepted.');
    checkReturn(loginDestination($pdo,['id'=>$user['id'],'role'=>'admin'],$id)==='admin/admin_dashboard.php','Admin redirect changed.');
    echo "Payment return states, delayed confirmation, paid request messages, token validation, and owned login destinations passed.\n";
} finally { if ($pdo->inTransaction()) $pdo->rollBack(); }
