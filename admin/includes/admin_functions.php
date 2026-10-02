<?php
// admin_functions.php

function getStatusBadge($status, $serviceType = null) {
    if ($status === 'cancelled') return '<span class="rounded-full bg-red-50 px-3 py-1 text-sm font-semibold text-red-700">Cancelled — financial review</span>';
    if ($serviceType === 'surgeon_request') {
        $label = ['pending_review'=>'Review & coordination','in_progress'=>'Paid — awaiting operation','completed'=>'Operation performed'][$status] ?? null;
        if ($label) return '<span class="rounded-full bg-blue-50 px-3 py-1 text-sm font-semibold text-blue-700">'.$label.'</span>';
    }
    $badges = [
        'pending_review' => '<span class="px-2.5 py-1 text-xs font-semibold rounded-full bg-amber-50 text-amber-600 border border-amber-200">Pending Review</span>',
        'awaiting_clinic_approval' => '<span class="px-2.5 py-1 text-xs font-semibold rounded-full bg-cyan-50 text-cyan-700 border border-cyan-200">Awaiting Clinic Approval</span>',
        'rejected'       => '<span class="px-2.5 py-1 text-xs font-semibold rounded-full bg-red-50 text-red-600 border border-red-200">Rejected</span>',
        'pending_payment'=> '<span class="px-2.5 py-1 text-xs font-semibold rounded-full bg-orange-50 text-orange-600 border border-orange-200">Pending Payment</span>',
        'in_progress'    => '<span class="px-2.5 py-1 text-xs font-semibold rounded-full bg-indigo-50 text-indigo-600 border border-indigo-200">In Progress</span>',
        'completed'      => '<span class="px-2.5 py-1 text-xs font-semibold rounded-full bg-emerald-50 text-emerald-600 border border-emerald-200">Completed</span>'
    ];
    return $badges[$status] ?? '<span class="px-2.5 py-1 text-xs font-semibold rounded-full bg-slate-100 text-slate-600">Unknown</span>';
}

function getServiceType($type) {
    return $type === 'surgical_guide' ? 'Surgical Guide' : 'Surgeon Request';
}
