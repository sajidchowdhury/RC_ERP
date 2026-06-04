<?php
// app/services/Accounting/ReconciliationService.php — Phase 2/5 GL vs sub-ledger reconciliation

require_once __DIR__ . '/../../helpers/Helper.php';

class ReconciliationService extends Helper
{
    private float $tolerance;

    public function __construct(?Database $db = null, ?float $tolerance = null)
    {
        parent::__construct($db);
        $this->tolerance = $tolerance ?? $this->defaultTolerance();
    }

    private function defaultTolerance(): float
    {
        if (defined('GL_RECONCILIATION_TOLERANCE')) {
            return max(0.0001, (float)GL_RECONCILIATION_TOLERANCE);
        }
        return 0.02;
    }

    /**
     * Phase 2 AR snapshot (backward compatible).
     */
    public function getSalesArReport(?int $branchId = null): array
    {
        $full = $this->runFullReport($branchId);
        $ar = $full['ar'];

        return [
            'branch_id'               => $ar['branch_id'],
            'branch_name'             => $ar['branch_name'],
            'customer_ledger_net'     => $ar['customer_ledger_net'],
            'gl_ar_net'               => $ar['gl_ar_net'],
            'ar_difference'           => $ar['difference'],
            'ar_within_tolerance'     => $ar['within_tolerance'],
            'ledger_mismatch_count'   => $ar['ledger_mismatch_count'],
            'ledger_mismatches'       => $ar['ledger_mismatches'],
            'null_branch_ledger_rows' => $ar['null_branch_ledger_rows'],
            'ar_ledger_ids'           => $ar['ar_ledger_ids'],
            'tolerance'               => $this->tolerance,
            'ran_at'                  => $full['ran_at'],
        ];
    }

    /**
     * Full Phase 5 reconciliation: AR, inventory, COGS tie-out.
     */
    public function runFullReport(?int $branchId = null, ?string $fromDate = null, ?string $toDate = null): array
    {
        $branchId = $branchId ?? self::sessionBranchId();
        $branchFilter = ($branchId > 0) ? $branchId : null;
        $fromDate = $fromDate ?: date('Y-m-01');
        $toDate = $toDate ?: date('Y-m-d');

        $ar = $this->buildArSection($branchFilter);
        $inventory = $this->buildInventorySection($branchFilter);
        $cogs = $this->buildCogsSection($branchFilter, $fromDate, $toDate);

        $issues = [];
        if (!$ar['within_tolerance']) {
            $issues[] = 'AR sub-ledger vs GL difference ' . number_format($ar['difference'], 2);
        }
        if ($ar['ledger_mismatch_count'] > 0) {
            $issues[] = $ar['ledger_mismatch_count'] . ' customer ledger balance mismatch(es)';
        }
        if (!$inventory['within_tolerance']) {
            $issues[] = 'Inventory GL vs stock valuation difference ' . number_format($inventory['difference'], 2);
        }
        if (!$cogs['within_tolerance']) {
            $issues[] = 'COGS GL vs stock OUT difference ' . number_format($cogs['difference'], 2) . ' (period)';
        }

        return [
            'branch_id'   => $branchFilter,
            'branch_name' => $this->resolveBranchName($branchFilter),
            'from_date'   => $fromDate,
            'to_date'     => $toDate,
            'tolerance'   => $this->tolerance,
            'ran_at'      => date('Y-m-d H:i:s'),
            'ar'          => $ar,
            'inventory'   => $inventory,
            'cogs'        => $cogs,
            'has_issues'  => $issues !== [],
            'issues'      => $issues,
        ];
    }

    private function buildArSection(?int $branchId): array
    {
        $customerNet = $this->sumCustomerLedgerNetBalances($branchId);
        $glArNet = $this->sumGlArControlBalance($branchId);
        $diff = round($customerNet - $glArNet, 2);
        $mismatches = $this->getCustomerLedgerBalanceMismatches($this->tolerance, $branchId);

        return [
            'branch_id'               => $branchId,
            'branch_name'             => $this->resolveBranchName($branchId),
            'customer_ledger_net'     => round($customerNet, 2),
            'gl_ar_net'               => round($glArNet, 2),
            'difference'              => $diff,
            'within_tolerance'        => abs($diff) <= $this->tolerance,
            'ledger_mismatch_count'   => count($mismatches),
            'ledger_mismatches'       => $mismatches,
            'null_branch_ledger_rows' => $this->countNullBranchLedgerRows($branchId),
            'ar_ledger_ids'           => $this->getLedgerIdsByNature('customer_receivable'),
        ];
    }

    private function buildInventorySection(?int $branchId): array
    {
        $stockValue = $this->sumWarehouseStockValue($branchId);
        $glInventory = $this->sumGlLedgerNetByNature('inventory', $branchId);
        $diff = round($stockValue - $glInventory, 2);
        $ledgerIds = $this->getLedgerIdsByNature('inventory');

        return [
            'warehouse_stock_value' => round($stockValue, 2),
            'gl_inventory_net'      => round($glInventory, 2),
            'difference'            => $diff,
            'within_tolerance'      => abs($diff) <= $this->tolerance,
            'inventory_ledger_ids'  => $ledgerIds,
            'note'                  => 'GL inventory is cumulative balance; stock is current on-hand at avg cost.',
        ];
    }

    private function buildCogsSection(?int $branchId, string $fromDate, string $toDate): array
    {
        $stockCogs = $this->sumChallanStockCogs($branchId, $fromDate, $toDate);
        $glCogs = $this->sumChallanGlCogs($branchId, $fromDate, $toDate);
        $diff = round($stockCogs - $glCogs, 2);

        return [
            'from_date'          => $fromDate,
            'to_date'            => $toDate,
            'stock_cogs_amount'  => round($stockCogs, 2),
            'gl_cogs_amount'     => round($glCogs, 2),
            'difference'         => $diff,
            'within_tolerance'   => abs($diff) <= $this->tolerance,
            'timing_note'        => 'Stock uses challan_date; GL uses journal entry_date. Small drift is normal across month-end.',
        ];
    }

    private function sumWarehouseStockValue(?int $branchId): float
    {
        $branchSql = '';
        if ($branchId !== null && $branchId > 0) {
            $branchSql = ' AND w.branch_id = ' . (int)$branchId;
        }

        $this->db->query("
            SELECT COALESCE(SUM(ws.qty * ws.avg_cost), 0) AS val
            FROM warehouse_stock ws
            INNER JOIN warehouses w ON w.id = ws.warehouse_id
            WHERE w.is_active = 1 {$branchSql}
        ");

        return (float)($this->db->single()['val'] ?? 0);
    }

    private function sumGlLedgerNetByNature(string $nature, ?int $branchId): float
    {
        $ledgerIds = $this->getLedgerIdsByNature($nature);
        if ($ledgerIds === []) {
            return 0.0;
        }

        $idList = implode(',', array_map('intval', $ledgerIds));
        $branchSql = '';
        if ($branchId !== null && $branchId > 0) {
            $branchSql = ' AND je.branch_id = ' . (int)$branchId;
        }

        $this->db->query("
            SELECT COALESCE(SUM(jl.debit), 0) - COALESCE(SUM(jl.credit), 0) AS net_bal
            FROM journal_lines jl
            INNER JOIN journal_entries je ON je.id = jl.journal_entry_id
            WHERE jl.ledger_id IN ({$idList})
              AND COALESCE(je.is_reversed, 0) = 0
              {$branchSql}
        ");

        return (float)($this->db->single()['net_bal'] ?? 0);
    }

    private function sumChallanStockCogs(?int $branchId, string $fromDate, string $toDate): float
    {
        $branchSql = '';
        if ($branchId !== null && $branchId > 0) {
            $branchSql = ' AND si.branch_id = ' . (int)$branchId;
        }

        $this->db->query("
            SELECT COALESCE(SUM(ABS(st.qty) * st.rate), 0) AS amt
            FROM stock_transactions st
            INNER JOIN sales_challans sc ON st.reference_type = 'sales_challan'
                AND st.reference_id = sc.id
            INNER JOIN sales_invoices si ON si.id = sc.sales_invoice_id
            WHERE st.qty < -0.0001
              AND COALESCE(sc.is_reversed, 0) = 0
              AND sc.challan_date BETWEEN :from_d AND :to_d
              {$branchSql}
        ");
        $this->db->bind(':from_d', $fromDate);
        $this->db->bind(':to_d', $toDate);

        return (float)($this->db->single()['amt'] ?? 0);
    }

    private function sumChallanGlCogs(?int $branchId, string $fromDate, string $toDate): float
    {
        $cogsIds = $this->getLedgerIdsByNature('cogs');
        if ($cogsIds === []) {
            return 0.0;
        }

        $idList = implode(',', array_map('intval', $cogsIds));
        $branchSql = '';
        if ($branchId !== null && $branchId > 0) {
            $branchSql = ' AND je.branch_id = ' . (int)$branchId;
        }

        $this->db->query("
            SELECT COALESCE(SUM(jl.debit), 0) AS cogs_debit
            FROM journal_lines jl
            INNER JOIN journal_entries je ON je.id = jl.journal_entry_id
            WHERE jl.ledger_id IN ({$idList})
              AND je.reference_type = 'sales_challan'
              AND COALESCE(je.is_reversed, 0) = 0
              AND je.entry_date BETWEEN :from_d AND :to_d
              {$branchSql}
        ");
        $this->db->bind(':from_d', $fromDate);
        $this->db->bind(':to_d', $toDate);

        return (float)($this->db->single()['cogs_debit'] ?? 0);
    }

    private function resolveBranchName(?int $branchId): string
    {
        if ($branchId === null || $branchId <= 0) {
            return 'All branches';
        }

        $this->db->query('SELECT branch_name FROM branches WHERE id = :id LIMIT 1');
        $this->db->bind(':id', $branchId);
        $row = $this->db->single();

        return $row['branch_name'] ?? ('Branch #' . $branchId);
    }

    private function sumCustomerLedgerNetBalances(?int $branchId): float
    {
        $branchSql = '';
        if ($branchId !== null && $branchId > 0) {
            $branchSql = ' AND (cl.branch_id = ' . (int)$branchId . ' OR cl.branch_id IS NULL)';
        }

        $this->db->query("
            SELECT COALESCE(SUM(lb.last_balance), 0) AS total
            FROM (
                SELECT cl1.customer_id, cl1.running_balance AS last_balance
                FROM customer_ledger cl1
                INNER JOIN (
                    SELECT customer_id, MAX(id) AS max_id
                    FROM customer_ledger cl
                    WHERE COALESCE(cl.is_reversed, 0) = 0 {$branchSql}
                    GROUP BY customer_id
                ) latest ON cl1.id = latest.max_id
                WHERE COALESCE(cl1.is_reversed, 0) = 0
            ) lb
        ");

        return (float)($this->db->single()['total'] ?? 0);
    }

    private function sumGlArControlBalance(?int $branchId): float
    {
        return $this->sumGlLedgerNetByNature('customer_receivable', $branchId);
    }

    /** @return int[] */
    private function getLedgerIdsByNature(string $nature): array
    {
        $this->db->query("
            SELECT id FROM ledgers
            WHERE ledger_nature = :nature AND is_active = 1
        ");
        $this->db->bind(':nature', $nature);
        $rows = $this->db->resultSet();
        $ids = [];
        foreach ($rows as $row) {
            $ids[] = (int)$row['id'];
        }
        return $ids;
    }

    /** @return int[] @deprecated use getLedgerIdsByNature */
    private function getArLedgerIds(): array
    {
        return $this->getLedgerIdsByNature('customer_receivable');
    }

    private function countNullBranchLedgerRows(?int $branchId): int
    {
        $branchSql = '';
        if ($branchId !== null && $branchId > 0) {
            $branchSql = ' AND (branch_id = ' . (int)$branchId . ' OR branch_id IS NULL)';
        }

        $this->db->query("
            SELECT COUNT(*) AS c FROM customer_ledger
            WHERE branch_id IS NULL {$branchSql}
        ");

        return (int)($this->db->single()['c'] ?? 0);
    }

    /**
     * Run reconciliation for all active branches (or one branch / all combined).
     *
     * @return array<int, array>
     */
    public function runScheduledReconciliation(?int $branchFilter = null, ?string $fromDate = null, ?string $toDate = null): array
    {
        $fromDate = $fromDate ?: date('Y-m-01');
        $toDate = $toDate ?: date('Y-m-d');
        $reports = [];

        if ($branchFilter !== null && $branchFilter > 0) {
            $reports[] = $this->runFullReport($branchFilter, $fromDate, $toDate);
            $this->emitAlertsForReport($reports[0]);
            return $reports;
        }

        $reports[] = $this->runFullReport(null, $fromDate, $toDate);

        $this->db->query('SELECT id FROM branches WHERE COALESCE(is_active, 1) = 1 ORDER BY id ASC');
        foreach ($this->db->resultSet() as $row) {
            $bid = (int)$row['id'];
            if ($bid <= 0) {
                continue;
            }
            $reports[] = $this->runFullReport($bid, $fromDate, $toDate);
        }

        foreach ($reports as $report) {
            $this->emitAlertsForReport($report);
        }

        return $reports;
    }

    private function emitAlertsForReport(array $report): void
    {
        if (empty($report['has_issues'])) {
            return;
        }

        self::writeAlert('GL reconciliation issues', [
            'branch_id'   => $report['branch_id'] ?? null,
            'branch_name' => $report['branch_name'] ?? null,
            'issues'      => $report['issues'] ?? [],
            'from_date'   => $report['from_date'] ?? null,
            'to_date'     => $report['to_date'] ?? null,
        ]);
    }

    /**
     * Phase 5.4 — log + optional email when sales audit has hard failures.
     */
    public static function notifyAuditFailures(int $failCount, int $warnCount, array $context = []): void
    {
        if ($failCount <= 0) {
            return;
        }

        $payload = array_merge([
            'fail' => $failCount,
            'warn' => $warnCount,
        ], $context);

        self::writeAlert("Sales audit checklist: {$failCount} failure(s)", $payload);

        if (defined('RECON_ALERT_EMAIL') && RECON_ALERT_EMAIL !== '') {
            $to = RECON_ALERT_EMAIL;
            $subject = APP_NAME . ' — Sales audit failures (' . $failCount . ')';
            $body = "Sales audit reported {$failCount} failure(s) and {$warnCount} warning(s).\n\n"
                . json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            @mail($to, $subject, $body, 'Content-Type: text/plain; charset=UTF-8');
        }
    }

    /**
     * Write alert when audit/reconciliation finds failures (Phase 5.4).
     */
    public static function writeAlert(string $message, array $context = []): void
    {
        $logDir = defined('APP_ROOT') ? APP_ROOT . '/logs' : dirname(__DIR__, 3) . '/logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }

        $line = json_encode([
            'timestamp' => date('Y-m-d H:i:s'),
            'message'   => $message,
            'context'   => $context,
        ], JSON_UNESCAPED_UNICODE);

        @file_put_contents($logDir . '/reconciliation_alerts.log', $line . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}