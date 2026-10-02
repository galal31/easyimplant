<?php

function loginDestination(PDO $pdo, array $user, mixed $requestId): string
{
    if ($user['role'] === 'admin') return 'admin/admin_dashboard.php';
    $id = filter_var($requestId, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
    if ($user['role'] === 'clinic' && $id) {
        $stmt = $pdo->prepare('SELECT id FROM requests WHERE id=? AND user_id=?');
        $stmt->execute([$id,$user['id']]);
        if ($stmt->fetchColumn()) return 'view_request.php?id=' . (int) $id;
    }
    return 'clinic_dashboard.php';
}
