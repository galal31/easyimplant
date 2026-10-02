<?php

class SurgeonOperationAgreementChanged extends DomainException {}

// Surgeon records are administrative resources, never login accounts.
function surgeonOperationIsReady(array $details): bool
{
    return (int) ($details['surgeon_id'] ?? 0) > 0
        && trim((string) ($details['surgeon_name_snapshot'] ?? '')) !== ''
        && !empty($details['confirmed_operation_at']);
}

function surgeonOperationFingerprint(array $details): string
{
    return hash('sha256',json_encode([
        (int)($details['surgeon_id'] ?? 0),
        (string)($details['surgeon_name_snapshot'] ?? ''),
        (string)($details['confirmed_operation_at'] ?? ''),
        (string)($details['total_price'] ?? ''),
        (string)($details['price_confirmed_at'] ?? ''),
    ],JSON_THROW_ON_ERROR));
}

function requireSurgeonOperationAgreement(array $details, string $fingerprint): void
{
    if (!surgeonOperationIsReady($details)) throw new DomainException('The administration must confirm the surgeon and appointment before payment.');
    if ($fingerprint==='' || !hash_equals(surgeonOperationFingerprint($details),$fingerprint)) throw new SurgeonOperationAgreementChanged('The operation details changed. Refresh the request and review the surgeon, appointment and final price before paying.');
}

function surgeonOperationDate(string $value, ?bool $future): string
{
    $value = str_replace('T', ' ', trim($value));
    $zone = new DateTimeZone('Africa/Cairo');
    $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $value, $zone);
    if (!$date || $date->format('Y-m-d H:i') !== $value) {
        throw new DomainException('Enter a valid operation date and time.');
    }
    $now = new DateTimeImmutable('now', $zone);
    if ($future && $date <= $now) throw new DomainException('The confirmed appointment must be in the future.');
    if ($future === false && $date > $now) throw new DomainException('The performed operation cannot be in the future.');
    return $date->format('Y-m-d H:i:s');
}

function logSurgeonOperation(PDO $pdo, int $requestId, int $adminId, string $action, ?string $old, ?string $new, string $note): void
{
    $pdo->prepare("INSERT INTO request_activity_logs (request_id,actor_id,actor_role,action,old_value,new_value,note)
        VALUES (?,?,'admin',?,?,?,?)")->execute([$requestId,$adminId,$action,$old,$new,$note]);
}

function saveSurgeonAppointment(PDO $pdo, int $requestId, int $surgeonId, string $appointment, int $adminId): void
{
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("SELECT r.status,r.service_type,sr.* FROM requests r JOIN surgeon_requests sr ON sr.request_id=r.id WHERE r.id=? FOR UPDATE");
        $stmt->execute([$requestId]);
        $request = $stmt->fetch();
        if (!$request || $request['service_type'] !== 'surgeon_request') throw new DomainException('Surgeon request not found.');
        $date = surgeonOperationDate($appointment, $request['status']==='in_progress' ? null : true);
        // After price confirmation, only fill missing legacy coordination data.
        if ($request['status'] !== 'pending_review' && (!in_array($request['status'], ['pending_payment','in_progress'], true) || surgeonOperationIsReady($request))) {
            throw new DomainException('The confirmed operation details are locked at this stage.');
        }
        if ($request['status'] !== 'pending_review' && !empty($request['surgeon_id']) && (int)$request['surgeon_id'] !== $surgeonId) {
            throw new DomainException('Keep the previously assigned surgeon when completing legacy details.');
        }
        $stmt = $pdo->prepare('SELECT * FROM surgeons WHERE id=? FOR UPDATE');
        $stmt->execute([$surgeonId]);
        $surgeon = $stmt->fetch();
        if (!$surgeon || !(int)$surgeon['is_available']) throw new DomainException('Select an available surgeon.');
        $snapshot = $request['status'] !== 'pending_review' && !empty($request['surgeon_name_snapshot'])
            ? $request['surgeon_name_snapshot'] : $surgeon['full_name'];
        $pdo->prepare('UPDATE surgeon_requests SET surgeon_id=?,surgeon_name_snapshot=?,confirmed_operation_at=? WHERE request_id=?')
            ->execute([$surgeonId,$snapshot,$date,$requestId]);
        $pdo->prepare('INSERT INTO surgeon_assignment_history (request_id,surgeon_id,surgeon_name_snapshot,operation_at,assigned_at,assigned_by) VALUES (?,?,?,?,NOW(),?)')
            ->execute([$requestId,$surgeonId,$snapshot,$date,$adminId]);
        logSurgeonOperation($pdo,$requestId,$adminId,'surgeon_appointment_confirmed',
            $request['surgeon_name_snapshot'], $snapshot,
            'Appointment: ' . ($request['confirmed_operation_at'] ?? 'not confirmed') . ' -> ' . $date);
        $pdo->commit();
    } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
}

function saveSurgeon(PDO $pdo, array $data): int
{
    $id = (int)($data['id'] ?? 0);
    $name = trim((string)($data['full_name'] ?? ''));
    $phone = trim((string)($data['phone'] ?? ''));
    $specialty = trim((string)($data['specialty'] ?? ''));
    $notes = trim((string)($data['notes'] ?? ''));
    if ($name === '' || $phone === '' || mb_strlen($name)>190 || mb_strlen($phone)>40 || mb_strlen($specialty)>190 || mb_strlen($notes)>4000) {
        throw new DomainException('Name and phone are required. Check the field lengths.');
    }
    $values = [$name,$phone,$specialty,$notes,empty($data['is_available']) ? 0 : 1];
    if ($id) {
        $pdo->beginTransaction();
        try {
            $stmt=$pdo->prepare('SELECT id FROM surgeons WHERE id=? FOR UPDATE'); $stmt->execute([$id]);
            if (!$stmt->fetchColumn()) throw new DomainException('Surgeon not found.');
            $pdo->prepare('UPDATE surgeons SET full_name=?,phone=?,specialty=?,notes=?,is_available=? WHERE id=?')->execute([...$values,$id]);
            $pdo->commit();
        } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
    } else {
        $pdo->prepare('INSERT INTO surgeons (full_name,phone,specialty,notes,is_available) VALUES (?,?,?,?,?)')->execute($values);
        $id=(int)$pdo->lastInsertId();
    }
    return $id;
}

function deleteOrDisableSurgeon(PDO $pdo, int $id): string
{
    $pdo->beginTransaction();
    try {
        $stmt=$pdo->prepare('SELECT id FROM surgeons WHERE id=? FOR UPDATE'); $stmt->execute([$id]);
        if (!$stmt->fetchColumn()) throw new DomainException('Surgeon not found.');
        // Activity logs preserve previous assignments even when the current surgeon changes.
        $stmt=$pdo->prepare('SELECT request_id FROM surgeon_requests WHERE surgeon_id=? LIMIT 1 FOR UPDATE'); $stmt->execute([$id]);
        // A surgeon ever assigned must remain, including assignments later changed.
        $history=$pdo->prepare("SELECT id FROM surgeon_assignment_history WHERE surgeon_id=? LIMIT 1"); $history->execute([$id]);
        if ($stmt->fetchColumn() || $history->fetchColumn()) {
            $pdo->prepare('UPDATE surgeons SET is_available=0 WHERE id=?')->execute([$id]);
            $message='Surgeon disabled; operation history preserved.';
        } else {
            $pdo->prepare('DELETE FROM surgeons WHERE id=?')->execute([$id]);
            $message='Unused surgeon deleted.';
        }
        $pdo->commit(); return $message;
    } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
}
