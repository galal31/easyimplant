<?php
// CLI integration test against the local Apache site; all fixtures are removed.
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
require_once __DIR__.'/../includes/db_connect.php';
require_once __DIR__.'/../includes/surgeon_operations.php';
require_once __DIR__.'/../includes/request_review.php';
require_once __DIR__.'/../includes/xpay.php';
if (APP_ENVIRONMENT!=='local') throw new RuntimeException('Local tests only.');
$base='http://localhost/easyimplant/';
$tag=bin2hex(random_bytes(6)); $password=bin2hex(random_bytes(18));
$users=$requests=$surgeons=$events=$cookies=$services=[];
$checks=0;
function checkOperation(bool $ok,string $message): void { global $checks; if(!$ok) throw new RuntimeException($message); $checks++; }
function operationHttp(string $path,?array $data=null,?string $cookie=null,bool $json=false): array {
    global $base;
    if ($data===null) $path=preg_replace('/\.php(?=\?|$)/','',$path);
    $curl=curl_init($base.$path);
    $location='';
    curl_setopt($curl,CURLOPT_HEADERFUNCTION,static function($curl,$header) use (&$location) { if(str_starts_with(strtolower($header),'location:')) $location=trim(substr($header,9)); return strlen($header); });
    curl_setopt_array($curl,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>15,CURLOPT_FOLLOWLOCATION=>false]);
    if ($cookie) { curl_setopt($curl,CURLOPT_COOKIEJAR,$cookie); curl_setopt($curl,CURLOPT_COOKIEFILE,$cookie); }
    if ($data!==null) { curl_setopt($curl,CURLOPT_POST,true); curl_setopt($curl,CURLOPT_POSTFIELDS,$json?json_encode($data):http_build_query($data)); if($json) curl_setopt($curl,CURLOPT_HTTPHEADER,['Content-Type: application/json']); }
    $body=curl_exec($curl); if($body===false) throw new RuntimeException(curl_error($curl));
    $code=curl_getinfo($curl,CURLINFO_RESPONSE_CODE); curl_close($curl);
    checkOperation(!preg_match('/(?:Fatal error|Warning:|Notice:|Database error occurred)/',$body),'PHP/DB error on '.$path);
    return [$code,$body,json_decode($body,true),$location];
}
function operationToken(string $page,string $cookie): string {
    [$code,$html]=operationHttp($page,null,$cookie); checkOperation($code===200,'Page unavailable: '.$page);
    checkOperation((bool)preg_match('/const requestWorkflowCsrfToken\s*=\s*"([a-f0-9]+)"/',$html,$match),'Missing workflow token');
    return $match[1];
}
function operationJavascript(string $html): void {
    preg_match_all('/<script\b[^>]*>(.*?)<\/script>/si',$html,$matches);
    $temporary=tempnam(sys_get_temp_dir(),'operation-js-'); $file=$temporary.'.js'; rename($temporary,$file);
    try {
        file_put_contents($file,implode("\n",$matches[1]));
        $process=proc_open(['node','--check',$file],[1=>['pipe','w'],2=>['pipe','w']],$pipes);
        if(!is_resource($process)) throw new RuntimeException('Node syntax checker unavailable.');
        $output=stream_get_contents($pipes[1]).stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
        checkOperation(proc_close($process)===0,'Rendered JavaScript syntax: '.$output);
    } finally { if(is_file($file)) unlink($file); }
}
function operationRequest(PDO $pdo,int $clinic,bool $quote=false): int {
    global $requests;
    $pdo->prepare("INSERT INTO requests (user_id,service_type,status) VALUES (?,'surgeon_request','pending_review')")->execute([$clinic]); $id=(int)$pdo->lastInsertId(); $requests[]=$id;
    $pdo->prepare("INSERT INTO surgeon_requests (request_id,patient_name,patient_age,medical_history,proposed_date,service_name_snapshot,requires_quote,estimated_total,total_price,currency) VALUES (?,'Operation Test',35,'Test only',DATE_ADD(CURDATE(),INTERVAL 7 DAY),'Test surgery',?,?,?,'EGP')")->execute([$id,$quote?1:0,$quote?null:1500,$quote?null:1500]);
    return $id;
}
try {
    foreach(['admin','clinic','clinic'] as $index=>$role) {
        $email='operation-test-'.$tag.'-'.$index.'@example.test';
        $pdo->prepare("INSERT INTO users (full_name,clinic_name,email,password,phone,country,governorate,role,status) VALUES ('Operation Test','Operation Test',?,?, '0000000000','egypt','cairo',?,'approved')")->execute([$email,password_hash($password,PASSWORD_DEFAULT),$role]); $users[]=(int)$pdo->lastInsertId();
        $cookie=tempnam(sys_get_temp_dir(),'surgeon-cookie-'); $cookies[]=$cookie;
        [$code,,$login]=operationHttp('api/login.php',['email'=>$email,'password'=>$password],$cookie); checkOperation($code===200 && isset($login['redirect']),'Test login failed');
    }
    [$admin,$clinic,$other]=$users; [$adminCookie,$clinicCookie,$otherCookie]=$cookies;
    // Check the rendered date limit and reject direct POSTs bypassing the browser.
    $today=new DateTimeImmutable('today',new DateTimeZone('Africa/Cairo'));
    [$code,$html]=operationHttp('request_surgeon.php',null,$clinicCookie);
    checkOperation($code===200 && str_contains($html,'min="'.$today->modify('+3 days')->format('Y-m-d').'"'),'Incorrect proposed-date minimum');
    preg_match('/id="csrfToken" value="([a-f0-9]+)"/',$html,$dateMatch);
    $pdo->prepare('INSERT INTO surgeon_services (name,is_active) VALUES (?,1)')->execute(['Date Test '.$tag]);
    $service=(int)$pdo->lastInsertId(); $services[]=$service;
    $submission=['csrf_token'=>$dateMatch[1] ?? '', 'patient_name'=>'Date Test','patient_age'=>35,'medical_history'=>'Test only','surgical_service'=>'custom:'.$service];
    foreach([-1,0,1,2] as $days) {
        [$code,,$response]=operationHttp('api/submit_surgeon.php',$submission+['proposed_date'=>$today->modify($days.' days')->format('Y-m-d')],$clinicCookie);
        checkOperation($code===400 && str_contains($response['error'] ?? '', '3 calendar days'),'Too-early proposed date accepted: '.$days);
    }
    [$code,,$response]=operationHttp('api/submit_surgeon.php',$submission+['proposed_date'=>$today->modify('+3 days')->format('Y-m-d')],$clinicCookie);
    if(!empty($response['request_id'])) $requests[]=(int)$response['request_id'];
    checkOperation($code===201 && !empty($response['request_id']),'First allowed proposed date rejected');
    [$code,$html]=operationHttp('admin/admin_surgeons.php',null,$adminCookie); checkOperation($code===200,'Surgeon CRUD page failed: HTTP '.$code.'; cookie bytes '.filesize($adminCookie));
    preg_match('/name="csrf_token" value="([a-f0-9]+)"/',$html,$match); $crudToken=$match[1] ?? ''; checkOperation($crudToken!=='','CRUD token missing');
    $name='Operation Surgeon '.$tag;
    [$code,$html]=operationHttp('admin/admin_surgeons.php',['action'=>'save','full_name'=>$name,'phone'=>'0000000000','specialty'=>'Implant surgery','notes'=>'Private note','is_available'=>1,'csrf_token'=>$crudToken],$adminCookie);
    checkOperation($code===200 && str_contains($html,'Surgeon saved.'),'CRUD create failed');
    $stmt=$pdo->prepare('SELECT id FROM surgeons WHERE full_name=?'); $stmt->execute([$name]); $surgeon=(int)$stmt->fetchColumn(); $surgeons[]=$surgeon; checkOperation($surgeon>0,'Surgeon not persisted');
    $unused=saveSurgeon($pdo,['full_name'=>$name.' unused','phone'=>'0000000000','is_available'=>1]); $surgeons[]=$unused;
    checkOperation(deleteOrDisableSurgeon($pdo,$unused)==='Unused surgeon deleted.','Unused deletion failed');
    [$code]=operationHttp('admin/admin_surgeons.php',['action'=>'save','full_name'=>'Forbidden','phone'=>'0','csrf_token'=>'invalid'],$adminCookie);
    checkOperation(!$pdo->query("SELECT id FROM surgeons WHERE full_name='Forbidden'")->fetchColumn(),'CRUD CSRF bypass');
    [$code]=operationHttp('admin/admin_surgeons.php',null,$clinicCookie); checkOperation($code===302,'Clinic accessed surgeon CRUD');
    $request=operationRequest($pdo,$clinic); $quote=operationRequest($pdo,$clinic,true);
    $token=operationToken('admin/admin_view_request.php?id='.$request,$adminCookie);
    [, $adminHtml]=operationHttp('admin/admin_view_request.php?id='.$request,null,$adminCookie); operationJavascript($adminHtml);
    $clinicToken=operationToken('view_request.php?id='.$request,$clinicCookie);
    $otherRequest=operationRequest($pdo,$other); $otherToken=operationToken('view_request.php?id='.$otherRequest,$otherCookie);
    [$code]=operationHttp('api/assign_surgeon.php',['request_id'=>$request,'surgeon_id'=>$surgeon,'csrf_token'=>'bad'],$adminCookie); checkOperation($code===403,'Assignment CSRF bypass');
    [$code]=operationHttp('api/assign_surgeon.php',['request_id'=>$request,'surgeon_id'=>$surgeon,'csrf_token'=>$clinicToken],$clinicCookie); checkOperation($code===401,'Clinic assigned surgeon');
    [$code]=operationHttp('api/prepare_surgeon_payment.php',['request_id'=>$request,'csrf_token'=>$token],$adminCookie); checkOperation($code===409,'Uncoordinated request became payable');
    $appointment=(new DateTimeImmutable('+7 days',new DateTimeZone('Africa/Cairo')))->format('Y-m-d\TH:i');
    [$code]=operationHttp('api/assign_surgeon.php',['request_id'=>$request,'surgeon_id'=>$surgeon,'confirmed_operation_at'=>$appointment,'csrf_token'=>$token],$adminCookie); checkOperation($code===200,'Assignment failed');
    [$code]=operationHttp('api/prepare_surgeon_payment.php',['request_id'=>$request,'total_price'=>'1','csrf_token'=>$token],$adminCookie); checkOperation($code===200,'Calculated price approval failed');
    checkOperation((float)$pdo->query('SELECT total_price FROM surgeon_requests WHERE request_id='.$request)->fetchColumn()===1500.0,'Browser changed calculated price');
    [$code]=operationHttp('api/update_request_status.php',['request_id'=>$request,'status'=>'in_progress','csrf_token'=>$token],$adminCookie); checkOperation($code===409,'Manual payment bypass');
    [$code]=operationHttp('api/assign_surgeon.php',['request_id'=>$request,'surgeon_id'=>$surgeon,'confirmed_operation_at'=>$appointment,'csrf_token'=>$token],$adminCookie); checkOperation($code===409,'Locked details changed');
    [$code]=operationHttp('api/prepare_surgeon_payment.php',['request_id'=>$request,'action'=>'revise','csrf_token'=>$token],$adminCookie); checkOperation($code===200,'Reopen before payment failed');
    [$code]=operationHttp('api/prepare_surgeon_payment.php',['request_id'=>$request,'csrf_token'=>$token],$adminCookie); checkOperation($code===200,'Reapproval failed');
    $agreement=$pdo->query('SELECT * FROM surgeon_requests WHERE request_id='.$request)->fetch();
    $fingerprint=surgeonOperationFingerprint($agreement); requireSurgeonOperationAgreement($agreement,$fingerprint); $checks++;
    foreach(['surgeon_id','surgeon_name_snapshot','confirmed_operation_at','total_price','price_confirmed_at'] as $field) {
        $changed=$agreement; $changed[$field]=$field==='surgeon_id'?999999:'changed';
        try { requireSurgeonOperationAgreement($changed,$fingerprint); checkOperation(false,'Stale booking agreement accepted: '.$field); } catch(DomainException $e) { $checks++; }
    }
    saveSurgeonAppointment($pdo,$quote,$surgeon,$appointment,$admin);
    foreach(['0','-1','12.345','garbage'] as $price) { [$code]=operationHttp('api/prepare_surgeon_payment.php',['request_id'=>$quote,'total_price'=>$price,'csrf_token'=>$token],$adminCookie); checkOperation($code===409,'Invalid quote accepted'); }
    [$code]=operationHttp('api/prepare_surgeon_payment.php',['request_id'=>$quote,'total_price'=>'2500.50','csrf_token'=>$token],$adminCookie); checkOperation($code===200,'Quote approval failed');
    [$code,$html]=operationHttp('view_request.php?id='.$request,null,$clinicCookie); checkOperation(str_contains($html,'requestChatPanel') && str_contains($html,$name) && str_contains($html,'Pay now') && !str_contains($html,'Private note'),'Clinic details/chat/payment/privacy incorrect');
    operationJavascript($html);
    [$code,,,$location]=operationHttp('api/create_xpay_checkout.php',['request_id'=>$request,'csrf_token'=>$clinicToken,'operation_fingerprint'=>'stale'],$clinicCookie);
    checkOperation($code===303 && str_contains($location,'operation_changed'),'Stale payment page not rejected before checkout');
    checkOperation(!(int)$pdo->query('SELECT COUNT(*) FROM xpay_checkout_sessions WHERE request_id='.$request)->fetchColumn(),'Stale payment created a checkout session');
    [$code]=operationHttp('api/send_request_message.php',['request_id'=>$request,'message_text'=>'Discuss appointment','csrf_token'=>$clinicToken],$clinicCookie,true); checkOperation($code===200,'Clinic chat send failed');
    [$code]=operationHttp('api/send_request_message.php',['request_id'=>$request,'message_text'=>'Confirmed','csrf_token'=>$token],$adminCookie,true); checkOperation($code===200,'Admin chat send failed');
    [$code]=operationHttp('api/send_request_message.php',['request_id'=>$request,'message_text'=>'Unauthorized','csrf_token'=>$otherToken],$otherCookie,true); checkOperation($code===409,'Other clinic sent message');
    [$code]=operationHttp('api/request_messages.php?request_id='.$request.'&csrf_token='.$otherToken,null,$otherCookie); checkOperation($code===404,'Other clinic read messages');
    [$code,,$messages]=operationHttp('api/request_messages.php?request_id='.$request.'&csrf_token='.$clinicToken,null,$clinicCookie); checkOperation($code===200 && count($messages['messages'])===2,'Chat read failed');
    [$code]=operationHttp('api/request_messages.php?request_id='.$request.'&csrf_token='.$clinicToken,null,$clinicCookie); checkOperation($code===429,'Manual refresh cooldown bypass');
    $session='cs_operation_'.$tag; $event='evt_operation_'.$tag; $events[]=$event;
    $pdo->prepare("INSERT INTO xpay_checkout_sessions (request_id,user_id,idempotency_key,return_token,xpay_session_id,status,payment_status,amount_minor,currency,livemode,expires_at) VALUES (?,?,?,?,?,'open','unpaid',150000,'EGP',0,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 30 MINUTE))")->execute([$request,$clinic,xpayUuidV4(),bin2hex(random_bytes(32)),$session]);
    [$code]=operationHttp('api/prepare_surgeon_payment.php',['request_id'=>$request,'action'=>'revise','csrf_token'=>$token],$adminCookie); checkOperation($code===409,'Payment session details changed');
    [$code]=operationHttp('api/update_request_status.php',['request_id'=>$request,'status'=>'rejected','reason'=>'Cannot proceed','csrf_token'=>$token],$adminCookie); checkOperation($code===409,'Active checkout rejection allowed');
    $result=xpayProcessWebhookEvent($pdo,['id'=>$event,'type'=>'checkout.session.completed','livemode'=>false,'data'=>['object'=>['id'=>$session,'paymentStatus'=>'paid','amountTotal'=>150000,'currency'=>'EGP','paymentIntent'=>'pi_operation_'.$tag,'metadata'=>['request_id'=>(string)$request,'clinic_id'=>(string)$clinic,'service_type'=>'surgeon_request']]]]);
    checkOperation($result['status']==='paid','Verified local payment processing failed');
    checkOperation($pdo->query('SELECT status FROM requests WHERE id='.$request)->fetchColumn()==='in_progress','Paid booking did not advance');
    [$code]=operationHttp('api/update_request_status.php',['request_id'=>$request,'status'=>'completed','csrf_token'=>$token],$adminCookie); checkOperation($code===409,'Empty completion accepted');
    $actual=(new DateTimeImmutable('-1 hour',new DateTimeZone('Africa/Cairo')))->format('Y-m-d\TH:i');
    [$code]=operationHttp('api/update_request_status.php',['request_id'=>$request,'status'=>'completed','performed_at'=>$appointment,'completion_note'=>'Done','csrf_token'=>$token],$adminCookie); checkOperation($code===409,'Future completion accepted');
    [$code]=operationHttp('api/update_request_status.php',['request_id'=>$request,'status'=>'completed','performed_at'=>$actual,'completion_note'=>'Operation performed successfully.','csrf_token'=>$token],$adminCookie); checkOperation($code===200,'Completion failed');
    [$code]=operationHttp('api/send_request_message.php',['request_id'=>$request,'message_text'=>'After closure','csrf_token'=>$clinicToken],$clinicCookie,true); checkOperation($code===409,'Closed chat writable');
    [$code]=operationHttp('api/delete_request.php',['request_id'=>$request],$adminCookie); checkOperation($code===409,'Operation history deleted');
    $legacy=operationRequest($pdo,$clinic);
    $pdo->prepare("UPDATE requests SET status='in_progress' WHERE id=?")->execute([$legacy]);
    [$code]=operationHttp('api/update_request_status.php',['request_id'=>$legacy,'status'=>'completed','performed_at'=>$actual,'completion_note'=>'Legacy operation','csrf_token'=>$token],$adminCookie); checkOperation($code===409,'Incomplete unpaid legacy operation completed');
    $pdo->prepare("INSERT INTO payments (request_id,user_id,amount,status,payment_source,currency,approved_at) VALUES (?, ?,1500,'approved','xpay','EGP',NOW())")->execute([$legacy,$clinic]);
    saveSurgeonAppointment($pdo,$legacy,$surgeon,$actual,$admin);
    [$code]=operationHttp('api/update_request_status.php',['request_id'=>$legacy,'status'=>'completed','performed_at'=>$actual,'completion_note'=>'Legacy details completed by administration','csrf_token'=>$token],$adminCookie); checkOperation($code===200,'Legacy operation details could not be completed');
    checkOperation((float)$pdo->query('SELECT total_price FROM surgeon_requests WHERE request_id='.$legacy)->fetchColumn()===1500.0,'Legacy price changed');
    // Separate paid booking exercises cancellation without touching the approved payment.
    $cancel=operationRequest($pdo,$clinic); saveSurgeonAppointment($pdo,$cancel,$surgeon,$appointment,$admin);
    $pdo->prepare("UPDATE requests SET status='in_progress' WHERE id=?")->execute([$cancel]);
    $pdo->prepare("INSERT INTO payments (request_id,user_id,amount,status,payment_source,currency,approved_at) VALUES (?, ?,1500,'approved','xpay','EGP',NOW())")->execute([$cancel,$clinic]);
    [$code]=operationHttp('api/update_request_status.php',['request_id'=>$cancel,'status'=>'cancelled','reason'=>'Clinic cancelled after payment','csrf_token'=>$token],$adminCookie); checkOperation($code===200,'Paid cancellation failed');
    checkOperation((int)$pdo->query('SELECT financial_review_required FROM surgeon_requests WHERE request_id='.$cancel)->fetchColumn()===1,'Cancellation not flagged');
    checkOperation($pdo->query('SELECT status FROM payments WHERE request_id='.$cancel)->fetchColumn()==='approved','Cancellation altered approved payment');
    [$code,$html]=operationHttp('view_request.php?id='.$cancel,null,$clinicCookie); checkOperation(str_contains($html,'No automatic refund') && str_contains($html,'read-only'),'Cancellation message/chat incorrect');
    // Names are snapshots; disabling a surgeon preserves all previous assignments.
    saveSurgeon($pdo,['id'=>$surgeon,'full_name'=>$name.' renamed','phone'=>'0000000000','is_available'=>1]);
    checkOperation($pdo->query('SELECT surgeon_name_snapshot FROM surgeon_requests WHERE request_id='.$request)->fetchColumn()===$name,'Historical surgeon name changed');
    checkOperation(deleteOrDisableSurgeon($pdo,$surgeon)==='Surgeon disabled; operation history preserved.','Assigned surgeon deleted');
    [$code]=operationHttp('api/assign_surgeon.php',['request_id'=>$otherRequest,'surgeon_id'=>$surgeon,'confirmed_operation_at'=>$appointment,'csrf_token'=>$token],$adminCookie); checkOperation($code===409,'Unavailable surgeon assigned');
    [$code,$html]=operationHttp('admin/admin_surgeon_history.php?id='.$surgeon,null,$adminCookie); checkOperation($code===200 && str_contains($html,'surgeon_history') && str_contains($html,'financial review required'),'History page failed');
    [$code]=operationHttp('api/delete_clinic.php',['clinic_id'=>$clinic],$adminCookie); checkOperation($code===409,'Clinic operation history deleted');
    foreach(['2026-02-30T10:00','bad date'] as $date) { try { surgeonOperationDate($date,null); checkOperation(false,'Invalid date accepted'); } catch(DomainException $e) { $checks++; } }
    checkOperation(!surgeonRequestTransitionIsAllowed('in_progress','rejected','admin'),'Paid request can be rejected instead of cancelled');
    echo 'Surgeon operations: '.$checks." HTTP, persistence, payment, history and security checks passed.\n";
} finally {
    if($pdo->inTransaction()) $pdo->rollBack();
    foreach($events as $event) $pdo->prepare('DELETE FROM xpay_webhook_events WHERE event_id=?')->execute([$event]);
    foreach($requests as $id) { $pdo->prepare('DELETE FROM surgeon_assignment_history WHERE request_id=?')->execute([$id]); $pdo->prepare('DELETE FROM requests WHERE id=?')->execute([$id]); }
    foreach($surgeons as $id) $pdo->prepare('DELETE FROM surgeons WHERE id=?')->execute([$id]);
    foreach($services as $id) $pdo->prepare('DELETE FROM surgeon_services WHERE id=?')->execute([$id]);
    foreach($users as $id) $pdo->prepare('DELETE FROM users WHERE id=?')->execute([$id]);
    foreach($cookies as $cookie) if(is_file($cookie)) unlink($cookie);
}
