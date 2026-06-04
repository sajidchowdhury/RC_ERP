<?php
// app/models/LedgerModel.php

require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../helpers/Helper.php';


class LedgerModel extends Helper{


    protected $db;

    public function __construct(?Database $db = null) {
        parent::__construct($db);
    }

    public function getAllLedgers() {
        return $this->Get_All_Ledger();
    }

    public function getLedgerById($id) {
        return $this->Get_All_Ledger_By_Id($id);
    }

    /**
     * Get all active ledgers (improved version)
     */
    public function getActiveLedgers() {
        $this->db->query("
            SELECT * FROM ledgers 
            WHERE is_active = 1 
            ORDER BY sort_order ASC, ledger_name ASC
        ");
        return $this->db->resultSet();
    }

    /**
     * Get ledgers by nature (e.g. 'cash_bank', 'customer_receivable', 'sales_revenue', 'cogs', 'inventory')
     * Used by JournalPostingService and automated posting logic.
     */
    public function getLedgersByNature($nature) {
        $this->db->query("
            SELECT * FROM ledgers 
            WHERE ledger_nature = :nature AND is_active = 1
            ORDER BY sort_order ASC, ledger_name ASC
        ");
        $this->db->bind(':nature', $nature);
        return $this->db->resultSet();
    }

    /**
     * Get control accounts (e.g. Accounts Receivable, Accounts Payable)
     */
    public function getControlAccounts() {
        $this->db->query("
            SELECT * FROM ledgers 
            WHERE is_control_account = 1 AND is_active = 1
            ORDER BY ledger_name ASC
        ");
        return $this->db->resultSet();
    }

    /**
     * Get ledgers for dropdown (with indentation for parents)
     */
    public function getLedgersForDropdown() {
        $this->db->query("
            SELECT id, ledger_code, ledger_name, parent_id, account_type 
            FROM ledgers 
            WHERE is_active = 1 
            ORDER BY sort_order ASC, ledger_name ASC
        ");
        return $this->db->resultSet();
    }

    private function generateLedgerCode() {
        $this->db->query("SELECT COUNT(*) as total FROM ledgers");
        $row = $this->db->single();
        $next = str_pad($row['total'] + 1, 4, '0', STR_PAD_LEFT);
        return "L-" . $next;
    }

    public function createLedger($data) {
        $ledger_code = $this->generateLedgerCode();

        $this->db->query("
            INSERT INTO ledgers 
            (ledger_code, ledger_name, parent_id, account_type, ledger_nature, 
             normal_balance, is_system, is_control_account, control_account_type,
             sort_order, created_by)
            VALUES 
            (:ledger_code, :ledger_name, :parent_id, :account_type, :ledger_nature, 
             :normal_balance, :is_system, :is_control_account, :control_account_type,
             :sort_order, :created_by)
        ");

        $this->db->bind(':ledger_code', $ledger_code);
        $this->db->bind(':ledger_name', trim($data['ledger_name']));
        $this->db->bind(':parent_id', $data['parent_id'] ?? 0);
        $this->db->bind(':account_type', $data['account_type'] ?? 'Expense');
        $this->db->bind(':ledger_nature', $data['ledger_nature'] ?? null);
        $this->db->bind(':normal_balance', $data['normal_balance'] ?? 'debit');
        $this->db->bind(':is_system', $data['is_system'] ?? 0);
        $this->db->bind(':is_control_account', $data['is_control_account'] ?? 0);
        $this->db->bind(':control_account_type', $data['control_account_type'] ?? null);
        $this->db->bind(':sort_order', $data['sort_order'] ?? 0);
        $this->db->bind(':created_by', $_SESSION['user_id'] ?? 1);

        return $this->db->execute();
    }

    public function updateLedger($id, $data) {
        if ($this->isSystemLedger($id)) {
            return false; // Block updates to system ledgers
        }

        $this->db->query("
            UPDATE ledgers SET 
                ledger_name = :ledger_name,
                parent_id = :parent_id,
                account_type = :account_type,
                ledger_nature = :ledger_nature,
                normal_balance = :normal_balance,
                is_control_account = :is_control_account,
                control_account_type = :control_account_type,
                sort_order = :sort_order,
                is_active = :is_active,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
        ");

        $this->db->bind(':ledger_name', trim($data['ledger_name']));
        $this->db->bind(':parent_id', $data['parent_id'] ?? 0);
        $this->db->bind(':account_type', $data['account_type'] ?? 'Expense');
        $this->db->bind(':ledger_nature', $data['ledger_nature'] ?? null);
        $this->db->bind(':normal_balance', $data['normal_balance'] ?? 'debit');
        $this->db->bind(':is_control_account', $data['is_control_account'] ?? 0);
        $this->db->bind(':control_account_type', $data['control_account_type'] ?? null);
        $this->db->bind(':sort_order', $data['sort_order'] ?? 0);
        $this->db->bind(':is_active', $data['is_active'] ?? 1);
        $this->db->bind(':id', $id);

        return $this->db->execute();
    }

    public function toggleStatus($id) {
        if ($this->isSystemLedger($id)) {
            return false; // Protect system ledgers from deactivation
        }

        $this->db->query("UPDATE ledgers SET is_active = NOT is_active WHERE id = :id");
        $this->db->bind(':id', $id);
        return $this->db->execute();
    }

    /**
     * Soft delete (deactivate) a ledger. System ledgers are blocked.
     * Used by controller delete() for non-system ledgers.
     */
    public function softDeleteLedger($id) {
        if ($this->isSystemLedger($id)) {
            return false;
        }
        $this->db->query("UPDATE ledgers SET is_active = 0, updated_at = CURRENT_TIMESTAMP WHERE id = :id");
        $this->db->bind(':id', $id);
        return $this->db->execute();
    }

    /**
     * Check if a ledger is a system ledger (protected)
     */
    public function isSystemLedger($id): bool {
        $this->db->query("SELECT is_system FROM ledgers WHERE id = :id LIMIT 1");
        $this->db->bind(':id', $id);
        $row = $this->db->single();
        return !empty($row['is_system']);
    }

    /**
     * Server-side DataTables for Ledgers (supports new accounting columns)
     */
    public function getLedgersForDataTable(array $params): array
    {
        $start       = (int)($params['start'] ?? 0);
        $length      = (int)($params['length'] ?? 25);
        $searchValue = trim($params['search']['value'] ?? '');
        $orderColumn = (int)($params['order'][0]['column'] ?? 0);
        $orderDir    = $params['order'][0]['dir'] ?? 'asc';

        $filterAccountType   = $params['filterAccountType'] ?? '';
        $filterLedgerNature  = $params['filterLedgerNature'] ?? '';
        $filterIsControl     = $params['filterIsControl'] ?? '';
        $filterIsSystem      = $params['filterIsSystem'] ?? '';
        $filterStatus        = $params['filterStatus'] ?? ''; // 'active', 'inactive'

        $showInactive = !empty($params['showInactive']);

        $columns = [
            'l.ledger_code',
            'l.ledger_name',
            'p.ledger_name',
            'l.account_type',
            'l.ledger_nature',
            'l.normal_balance',
            'l.is_control_account',
            'l.is_system',
            'l.is_active'
        ];

        $baseQuery = "
            FROM ledgers l
            LEFT JOIN ledgers p ON l.parent_id = p.id
        ";

        $where = [];
        $bindParams = [];

        // Account Type filter
        if ($filterAccountType) {
            $where[] = "l.account_type = :account_type";
            $bindParams[':account_type'] = $filterAccountType;
        }

        // Ledger Nature filter
        if ($filterLedgerNature) {
            $where[] = "l.ledger_nature = :ledger_nature";
            $bindParams[':ledger_nature'] = $filterLedgerNature;
        }

        // Control Account filter
        if ($filterIsControl === '1') {
            $where[] = "l.is_control_account = 1";
        } elseif ($filterIsControl === '0') {
            $where[] = "l.is_control_account = 0";
        }

        // System filter
        if ($filterIsSystem === '1') {
            $where[] = "l.is_system = 1";
        } elseif ($filterIsSystem === '0') {
            $where[] = "l.is_system = 0";
        }

        // Status filter
        if ($filterStatus === 'active') {
            $where[] = "l.is_active = 1";
        } elseif ($filterStatus === 'inactive') {
            $where[] = "l.is_active = 0";
        } elseif ($showInactive) {
            $where[] = "l.is_active = 0";
        } else {
            $where[] = "l.is_active = 1";
        }

        // Global search
        if ($searchValue !== '') {
            $where[] = "(l.ledger_code LIKE :search OR l.ledger_name LIKE :search OR p.ledger_name LIKE :search)";
            $bindParams[':search'] = "%{$searchValue}%";
        }

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        // Total records
        $totalQuery = "SELECT COUNT(l.id) as total {$baseQuery} {$whereSql}";
        $this->db->query($totalQuery);
        foreach ($bindParams as $key => $val) {
            $this->db->bind($key, $val);
        }
        $totalResult = $this->db->single();
        $recordsTotal = $totalResult['total'] ?? 0;

        // Filtered count
        $filteredQuery = "SELECT COUNT(l.id) as total {$baseQuery} {$whereSql}";
        $this->db->query($filteredQuery);
        foreach ($bindParams as $key => $val) {
            $this->db->bind($key, $val);
        }
        $filteredResult = $this->db->single();
        $recordsFiltered = $filteredResult['total'] ?? 0;

        // Data query
        $orderBy = $columns[$orderColumn] ?? 'l.sort_order, l.ledger_name';
        $dataQuery = "
            SELECT 
                l.*,
                p.ledger_name as parent_name
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

    /**
     * Hero stat tiles for ledger index.
     */
    public function getLedgerIndexStats(): array
    {
        $stats = [
            'active'   => 0,
            'inactive' => 0,
            'system'   => 0,
            'control'  => 0,
        ];

        $this->db->query('SELECT COUNT(*) AS c FROM ledgers WHERE is_active = 1');
        $stats['active'] = (int)($this->db->single()['c'] ?? 0);

        $this->db->query('SELECT COUNT(*) AS c FROM ledgers WHERE is_active = 0');
        $stats['inactive'] = (int)($this->db->single()['c'] ?? 0);

        $this->db->query('SELECT COUNT(*) AS c FROM ledgers WHERE is_system = 1 AND is_active = 1');
        $stats['system'] = (int)($this->db->single()['c'] ?? 0);

        $this->db->query('SELECT COUNT(*) AS c FROM ledgers WHERE is_control_account = 1 AND is_active = 1');
        $stats['control'] = (int)($this->db->single()['c'] ?? 0);

        return $stats;
    }

    /**
     * Snapshot for edit sidebar (journal usage, child accounts).
     */
    public function getLedgerUsage(int $ledgerId): array
    {
        $this->db->query('SELECT is_active, is_system, is_control_account, ledger_code, ledger_name FROM ledgers WHERE id = :id');
        $this->db->bind(':id', $ledgerId);
        $ledger = $this->db->single() ?: [];

        $this->db->query('SELECT COUNT(*) AS c FROM journal_lines WHERE ledger_id = :id');
        $this->db->bind(':id', $ledgerId);
        $journalLines = (int)($this->db->single()['c'] ?? 0);

        $this->db->query('SELECT COUNT(*) AS c FROM ledgers WHERE parent_id = :id');
        $this->db->bind(':id', $ledgerId);
        $children = (int)($this->db->single()['c'] ?? 0);

        return [
            'journal_lines'      => $journalLines,
            'children'           => $children,
            'is_active'          => !empty($ledger['is_active']),
            'is_system'          => !empty($ledger['is_system']),
            'is_control_account' => !empty($ledger['is_control_account']),
            'ledger_code'        => $ledger['ledger_code'] ?? '',
            'ledger_name'        => $ledger['ledger_name'] ?? '',
        ];
    }
}