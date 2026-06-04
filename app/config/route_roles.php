<?php
/**
 * Phase 7 — role matrix for sales ecosystem routes (controller/action => allowed roles).
 * Admin always has full access via RouteAccess::allows().
 */
return [
    'SalesController' => [
        'create'              => ['admin', 'manager', 'salesman'],
        'final_sales'         => ['admin', 'manager', 'salesman'],
        'update'              => ['admin', 'manager', 'salesman'],
        'delete_invoice'      => ['admin', 'manager', 'salesman'],
        'save_payment'        => ['admin', 'manager', 'salesman', 'accountant'],
        'reverse_payment'     => ['admin', 'manager', 'accountant'],
        'reconcile'           => ['admin', 'manager', 'accountant'],
        'call_it_a_day'       => ['admin', 'manager', 'salesman'],
        'export'              => ['admin', 'manager', 'salesman', 'accountant'],
    ],
    'ReportController' => [
        'revenueOverview'     => ['admin', 'manager', 'salesman', 'accountant'],
        'salesFunnelPipeline' => ['admin', 'manager', 'salesman', 'accountant'],
        'customerPerformance' => ['admin', 'manager', 'salesman', 'accountant'],
    ],
    'ChallanController' => [
        'index'               => ['admin', 'manager', 'warehouse_manager', 'dispatcher'],
        'prepare_godown'      => ['admin', 'manager', 'warehouse_manager', 'dispatcher'],
        'create_final_challan'=> ['admin', 'manager', 'warehouse_manager', 'dispatcher'],
        'reverse_challan'     => ['admin', 'manager'],
        'export'              => ['admin', 'manager', 'warehouse_manager'],
    ],
    'SalesReturnController' => [
        'create'              => ['admin', 'manager', 'salesman'],
        'store'               => ['admin', 'manager', 'salesman'],
        'confirm'             => ['admin', 'manager', 'warehouse_manager', 'accountant'],
        'confirm_store'       => ['admin', 'manager', 'warehouse_manager', 'accountant'],
        'reverse'             => ['admin', 'manager', 'accountant'],
    ],
    'SalesAuditController' => [
        'checklist'           => ['admin', 'manager', 'accountant'],
        'run_checks'          => ['admin', 'manager', 'accountant'],
        'cancel_stale_drafts' => ['admin', 'manager'],
    ],
];