<?php
/**
 * Phase 5 — scheduled GL reconciliation (AR, inventory, COGS tie-out).
 * Usage: php database/scripts/run_gl_reconciliation.php [--branch=ID] [--from=YYYY-MM-DD] [--to=YYYY-MM-DD]
 * Cron (daily): php database/scripts/run_gl_reconciliation.php
 * Exit code 1 when any branch report has_issues.
 */
declare(strict_types=1);

$root = dirname(__DIR__, 2);
if (!defined('APP_ROOT')) {
    define('APP_ROOT', $root);
}
require_once $root . '/config/config.php';
require_once $root . '/app/services/Accounting/ReconciliationService.php';

$opts = getopt('', ['branch::', 'from::', 'to::']);
$branchFilter = isset($opts['branch']) ? max(0, (int)$opts['branch']) : null;
$fromDate = $opts['from'] ?? date('Y-m-01');
$toDate = $opts['to'] ?? date('Y-m-d');

$service = new ReconciliationService();
$reports = $service->runScheduledReconciliation($branchFilter, $fromDate, $toDate);

$anyIssues = false;
foreach ($reports as $report) {
    $label = $report['branch_name'] ?? ('Branch ' . ($report['branch_id'] ?? 'all'));
    $status = !empty($report['has_issues']) ? 'ISSUES' : 'OK';
    if (!empty($report['has_issues'])) {
        $anyIssues = true;
    }

    echo "[{$status}] {$label}\n";
    if (!empty($report['issues'])) {
        foreach ($report['issues'] as $issue) {
            echo "  - {$issue}\n";
        }
    }
    $ar = $report['ar'] ?? [];
    echo sprintf(
        "  AR: ledger=%.2f GL=%.2f diff=%.2f\n",
        (float)($ar['customer_ledger_net'] ?? 0),
        (float)($ar['gl_ar_net'] ?? 0),
        (float)($ar['difference'] ?? 0)
    );
    $inv = $report['inventory'] ?? [];
    echo sprintf(
        "  Inventory: stock=%.2f GL=%.2f diff=%.2f\n",
        (float)($inv['warehouse_stock_value'] ?? 0),
        (float)($inv['gl_inventory_net'] ?? 0),
        (float)($inv['difference'] ?? 0)
    );
    $cogs = $report['cogs'] ?? [];
    echo sprintf(
        "  COGS (%s..%s): stock=%.2f GL=%.2f diff=%.2f\n",
        $cogs['from_date'] ?? $fromDate,
        $cogs['to_date'] ?? $toDate,
        (float)($cogs['stock_cogs_amount'] ?? 0),
        (float)($cogs['gl_cogs_amount'] ?? 0),
        (float)($cogs['difference'] ?? 0)
    );
    echo "\n";
}

exit($anyIssues ? 1 : 0);