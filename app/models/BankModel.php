<?php
// app/models/BankModel.php

require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../helpers/Helper.php';


class BankModel extends Helper{

    protected  $db;

    public function __construct(?Database $db = null) {
        parent::__construct($db);
    }

    /**
     * Get all active banks for payment
     */
    public function getAllActiveBanks() {
       
        return $this->Get_All_Active_Bank();
    }

    /**
     * Get single bank by ID
     */
    public function getById($id) {
        return $this->Get_Bank_By_Id($id);
    }

    /**
     * Get all banks (for admin panel later)
     */
    public function getAllBanks() {
        return $this->Get_All_Bank();
    }

        public function createBank($data) {
        $this->db->query("
            INSERT INTO banks 
            (bank_name, account_number, branch_name, created_by)
            VALUES 
            (:bank_name, :account_number, :branch_name, :created_by)
        ");

        $this->db->bind(':bank_name', trim($data['bank_name']));
        $this->db->bind(':account_number', trim($data['account_number']));
        $this->db->bind(':branch_name', trim($data['branch_name'] ?? ''));
        $this->db->bind(':created_by', $_SESSION['user_id'] ?? 1);

        return $this->db->execute();
    }

    public function updateBank($id, $data) {
        $this->db->query("
            UPDATE banks SET 
                bank_name = :bank_name,
                account_number = :account_number,
                branch_name = :branch_name,
                is_active = :is_active,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
        ");

        $this->db->bind(':bank_name', trim($data['bank_name']));
        $this->db->bind(':account_number', trim($data['account_number']));
        $this->db->bind(':branch_name', trim($data['branch_name'] ?? ''));
        $this->db->bind(':is_active', $data['is_active'] ?? 1);
        $this->db->bind(':id', $id);

        return $this->db->execute();
    }

    public function toggleStatus($id) {
        $this->db->query("UPDATE banks SET is_active = NOT is_active WHERE id = :id");
        $this->db->bind(':id', $id);
        return $this->db->execute();
    }

    /**
     * Soft delete (deactivate) a bank
     */
    public function softDeleteBank($id) {
        $this->db->query("UPDATE banks SET is_active = 0 WHERE id = :id");
        $this->db->bind(':id', $id);
        return $this->db->execute();
    }

    /**
     * Restore a soft-deleted bank
     */
    public function restoreBank($id) {
        $this->db->query("UPDATE banks SET is_active = 1 WHERE id = :id");
        $this->db->bind(':id', $id);
        return $this->db->execute();
    }


        // Update bank balance when payment received
    public function updateBalance($bank_id, $amount, $type = 'credit') {
        $this->db->query("UPDATE banks SET balance = balance " .
                        ($type === 'credit' ? '+' : '-') . " :amount 
                         WHERE id = :bank_id");
        $this->db->bind(':amount', $amount);
        $this->db->bind(':bank_id', $bank_id);
        return $this->db->execute();
    }

    /**
     * Summary metrics for bank index hero.
     */
    public function getBankIndexStats(): array
    {
        $stats = [
            'active'         => 0,
            'inactive'       => 0,
            'total_balance'  => 0.0,
        ];

        $this->db->query('SELECT COUNT(*) AS c FROM banks WHERE is_active = 1');
        $stats['active'] = (int)($this->db->single()['c'] ?? 0);

        $this->db->query('SELECT COUNT(*) AS c FROM banks WHERE is_active = 0');
        $stats['inactive'] = (int)($this->db->single()['c'] ?? 0);

        $this->db->query('SELECT COALESCE(SUM(balance), 0) AS total FROM banks WHERE is_active = 1');
        $stats['total_balance'] = (float)($this->db->single()['total'] ?? 0);

        return $stats;
    }

    /**
     * Snapshot for edit sidebar.
     */
    public function getBankUsage(int $bankId): array
    {
        $this->db->query('SELECT * FROM banks WHERE id = :id');
        $this->db->bind(':id', $bankId);
        $bank = $this->db->single() ?: [];

        return [
            'balance'   => (float)($bank['balance'] ?? 0),
            'is_active' => !empty($bank['is_active']),
        ];
    }

    /**
     * Server-side DataTables for Banks
     */
    public function getBanksForDataTable(array $params): array
    {
        $start          = (int)($params['start'] ?? 0);
        $length         = (int)($params['length'] ?? 25);
        $searchValue    = trim($params['search']['value'] ?? '');
        $orderColumn    = (int)($params['order'][0]['column'] ?? 0);
        $orderDir       = $params['order'][0]['dir'] ?? 'asc';

        $filterStatus   = $params['filterStatus'] ?? '';
        $includeDeleted = !empty($params['includeDeleted']);

        $columns = ['bank_name', 'account_number', 'branch_name', 'balance', 'is_active'];

        $baseQuery = " FROM banks ";

        $where = [];
        $bindParams = [];

        if (!$includeDeleted) {
            $where[] = "is_active = 1";
        } else {
            $where[] = "is_active = 0";
        }

        // Global search
        if ($searchValue !== '') {
            $where[] = "(bank_name LIKE :search 
                      OR account_number LIKE :search 
                      OR branch_name LIKE :search)";
            $bindParams[':search'] = "%{$searchValue}%";
        }

        // Status filter
        if ($filterStatus === 'active' && !$includeDeleted) {
            $where[] = "is_active = 1";
        } elseif ($filterStatus === 'inactive') {
            $where[] = "is_active = 0";
        }

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        // Total records
        $totalQuery = "SELECT COUNT(id) as total {$baseQuery}";
        if (!$includeDeleted) {
            $totalQuery .= " WHERE is_active = 1";
        } else {
            $totalQuery .= " WHERE is_active = 0";
        }
        $this->db->query($totalQuery);
        $totalResult = $this->db->single();
        $recordsTotal = $totalResult['total'] ?? 0;

        // Filtered records
        $filteredQuery = "SELECT COUNT(id) as total {$baseQuery} {$whereSql}";
        $this->db->query($filteredQuery);
        foreach ($bindParams as $key => $val) {
            $this->db->bind($key, $val);
        }
        $filteredResult = $this->db->single();
        $recordsFiltered = $filteredResult['total'] ?? 0;

        // Data query
        $orderBy = $columns[$orderColumn] ?? 'bank_name';
        $dataQuery = "
            SELECT id, bank_name, account_number, branch_name, is_active,
                   COALESCE(balance, 0) AS balance
            {$baseQuery}
            {$whereSql}
            ORDER BY {$orderBy} {$orderDir}
            LIMIT {$start}, {$length}
        ";

        $this->db->query($dataQuery);
        foreach ($bindParams as $key => $val) {
            $this->db->bind($key, $val);
        }
        $data = $this->db->resultSet();

        return [
            'draw'            => (int)($params['draw'] ?? 1),
            'recordsTotal'    => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data'            => $data
        ];
    }
}