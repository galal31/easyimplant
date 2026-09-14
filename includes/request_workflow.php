<?php

function requestWorkflowCsrfIsValid(string $providedToken): bool
{
    $sessionToken = $_SESSION['request_workflow_csrf_token'] ?? '';

    return is_string($sessionToken)
        && $sessionToken !== ''
        && $providedToken !== ''
        && hash_equals($sessionToken, $providedToken);
}

function surgicalGuideTransitionIsAllowed(string $currentStatus, string $newStatus, string $source): bool
{
    $transitions = [
        'admin' => [
            'pending_review' => ['pending_payment', 'rejected'],
            'pending_payment' => ['rejected'],
            'in_progress' => ['rejected'],
        ],
        'payment_approval' => [
            'pending_payment' => ['in_progress'],
        ],
        'deliverables' => [
            'in_progress' => ['completed'],
        ],
    ];

    return in_array($newStatus, $transitions[$source][$currentStatus] ?? [], true);
}

