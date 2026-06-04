<?php
// app/models/Reports/PayableAgingReport.php

require_once __DIR__ . '/../ReportModel.php';

class PayableAgingReport extends ReportModel {

    public function getPayableAging($as_of_date, $branch_id = null) {
        
        $sql = "
            SELECT 
                s.supplier_code,
                s.supplier_name,
                s.mobile,
                b.branch_name,
                SUM(CASE WHEN DATEDIFF(:as_of_date, sl.transaction_date) <= 30 THEN 
                    (sl.debit - sl.credit) ELSE 0 END) as bucket_0_30,
                SUM(CASE WHEN DATEDIFF(:as_of_date, sl.transaction_date) BETWEEN 31 AND 60 THEN 
                    (sl.debit - sl.credit) ELSE 0 END) as bucket_31_60,
                SUM(CASE WHEN DATEDIFF(:as_of_date, sl.transaction_date) BETWEEN 61 AND 90 THEN 
                    (sl.debit - sl.credit) ELSE 0 END) as bucket_61_90,
                SUM(CASE WHEN DATEDIFF(:as_of_date, sl.transaction_date) > 90 THEN 
                    (sl.debit - sl.credit) ELSE 0 END) as bucket_90_plus,
                SUM(sl.debit - sl.credit) as total_payable
            FROM supplier_ledger sl
            JOIN suppliers s ON s.id = sl.supplier_id
            JOIN branches b ON b.id = s.id   -- Adjust if you have better branch link for suppliers
            WHERE sl.transaction_date <= :as_of_date
        ";

        $params = [':as_of_date' => $as_of_date];

        if ($branch_id) {
            $sql .= " AND b.id = :branch_id";
            $params[':branch_id'] = $branch_id;
        }

        $sql .= "
            GROUP BY s.id, s.supplier_code, s.supplier_name, s.mobile, b.branch_name
            HAVING total_payable > 0
            ORDER BY total_payable DESC
        ";

        $this->db->query($sql);

        foreach ($params as $key => $value) {
            $this->db->bind($key, $value);
        }

        return $this->db->resultSet();
    }

    public function exportPayableAging($data) {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="Payable_Aging_' . date('Ymd_His') . '.csv"');
        
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Supplier Code', 'Supplier Name', 'Mobile', 'Branch', '0-30 Days', 
                         '31-60 Days', '61-90 Days', '90+ Days', 'Total Payable']);

        foreach ($data as $row) {
            fputcsv($output, [
                $row['supplier_code'],
                $row['supplier_name'],
                $row['mobile'],
                $row['branch_name'],
                $row['bucket_0_30'],
                $row['bucket_31_60'],
                $row['bucket_61_90'],
                $row['bucket_90_plus'],
                $row['total_payable']
            ]);
        }
        fclose($output);
        exit;
    }
}