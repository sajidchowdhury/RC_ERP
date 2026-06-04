<?php
// app/models/Reports/TrialBalanceReport.php

class TrialBalanceReport
{
    protected $db;

    public function __construct()
    {
        require_once '../core/Database.php';
        $this->db = new Database();
    }

    /**
     * Generate Trial Balance for a date range.
     * This is the key report to verify the accounting system is working correctly.
     */
    public function getTrialBalance($fromDate, $toDate, $accountType = null)
    {
        $fromDate = $fromDate ?: date('Y-m-01');
        $toDate   = $toDate   ?: date('Y-m-d');

        $sql = "
            SELECT 
                l.id as ledger_id,
                l.ledger_code,
                l.ledger_name,
                l.account_type,
                l.normal_balance,
                l.ledger_nature,
                COALESCE(SUM(jl.debit), 0) as total_debit,
                COALESCE(SUM(jl.credit), 0) as total_credit,
                COUNT(jl.id) as line_count
            FROM ledgers l
            LEFT JOIN journal_lines jl 
                INNER JOIN journal_entries je 
                    ON je.id = jl.journal_entry_id 
                   AND je.entry_date BETWEEN :from_date AND :to_date
                   AND COALESCE(je.is_reversed, 0) = 0
            ON jl.ledger_id = l.id
            WHERE l.is_active = 1
        ";

        if ($accountType) {
            $sql .= " AND l.account_type = :account_type";
        }

        $sql .= "
            GROUP BY l.id, l.ledger_code, l.ledger_name, l.account_type, l.normal_balance, l.ledger_nature
            ORDER BY FIELD(l.account_type, 'Asset', 'Liability', 'Equity', 'Income', 'Expense'), 
                     l.ledger_name ASC
        ";

        $this->db->query($sql);
        $this->db->bind(':from_date', $fromDate);
        $this->db->bind(':to_date', $toDate);

        if ($accountType) {
            $this->db->bind(':account_type', $accountType);
        }

        $rows = $this->db->resultSet();

        $data = [];
        $grandDebit  = 0;
        $grandCredit = 0;

        foreach ($rows as $row) {
            $debit  = (float)$row['total_debit'];
            $credit = (float)$row['total_credit'];

            // Only include accounts that had activity in the period
            if ($debit == 0 && $credit == 0) {
                continue;
            }

            $net = $debit - $credit;

            // Determine balance side based on normal balance
            $balance = 0;
            $balance_side = '';

            if ($row['normal_balance'] === 'debit') {
                $balance = $net; // positive = debit balance
                $balance_side = $net >= 0 ? 'Dr' : 'Cr';
            } else {
                $balance = -$net; // positive = credit balance
                $balance_side = $net <= 0 ? 'Cr' : 'Dr';
            }

            $data[] = [
                'ledger_id'     => $row['ledger_id'],
                'ledger_code'   => $row['ledger_code'],
                'ledger_name'   => $row['ledger_name'],
                'account_type'  => $row['account_type'],
                'normal_balance'=> $row['normal_balance'],
                'ledger_nature' => $row['ledger_nature'],
                'debit'         => $debit,
                'credit'        => $credit,
                'net'           => $net,
                'balance'       => abs($balance),
                'balance_side'  => $balance_side,
                'has_activity'  => true
            ];

            $grandDebit  += $debit;
            $grandCredit += $credit;
        }

        return [
            'from_date'    => $fromDate,
            'to_date'      => $toDate,
            'data'         => $data,
            'grand_debit'  => $grandDebit,
            'grand_credit' => $grandCredit,
            'is_balanced'  => round($grandDebit, 2) === round($grandCredit, 2),
            'difference'   => round($grandDebit - $grandCredit, 2)
        ];
    }

    /**
     * Export to CSV (simple but useful)
     */
    public function exportToCsv($reportData, $filename = 'Trial_Balance')
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '_' . date('Y-m-d') . '.csv"');

        $output = fopen('php://output', 'w');

        // Header
        fputcsv($output, ['Trial Balance Report']);
        fputcsv($output, ['Period: ' . $reportData['from_date'] . ' to ' . $reportData['to_date']]);
        fputcsv($output, ['Generated: ' . date('Y-m-d H:i:s')]);
        fputcsv($output, []);

        fputcsv($output, ['Code', 'Ledger Name', 'Type', 'Debit', 'Credit', 'Balance', 'Side']);

        foreach ($reportData['data'] as $row) {
            fputcsv($output, [
                $row['ledger_code'],
                $row['ledger_name'],
                $row['account_type'],
                number_format($row['debit'], 2),
                number_format($row['credit'], 2),
                number_format($row['balance'], 2),
                $row['balance_side']
            ]);
        }

        fputcsv($output, []);
        fputcsv($output, ['TOTALS', '', '', 
            number_format($reportData['grand_debit'], 2), 
            number_format($reportData['grand_credit'], 2), 
            '', 
            $reportData['is_balanced'] ? 'BALANCED' : 'NOT BALANCED'
        ]);

        fclose($output);
        exit;
    }
}