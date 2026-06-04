<?php
// app/models/CustomerModel.php

require_once '../core/Database.php';
require_once __DIR__ . '/../helpers/Helper.php';


class CustomerModel extends Helper{


    protected $db;

    public function __construct() {
        $this->db = new Database();
    }

    public function getAllCustomers() {
        return $this->Get_All_Customers();
    }

    public function getCustomerById($id) {
        return $this->Get_Customer_By_Id($id);
    }

    // Auto generate customer code
    private function generateCustomerCode() {
        $this->db->query("SELECT COUNT(*) as total FROM customers");
        $row = $this->db->single();
        $next = str_pad($row['total'] + 1, 4, '0', STR_PAD_LEFT);
        return "C-" . $next;
    }

    // Check mobile uniqueness
    public function mobileExists($mobile, $exclude_id = null) {
        $sql = "SELECT id FROM customers WHERE mobile = :mobile";
        if ($exclude_id) {
            $sql .= " AND id != :exclude_id";
        }
        $this->db->query($sql);
        $this->db->bind(':mobile', $mobile);
        if ($exclude_id) $this->db->bind(':exclude_id', $exclude_id);
        return $this->db->single() ? true : false;
    }

    public function createCustomer($data) {
        if ($this->mobileExists($data['mobile'])) {
            return ['status' => 'error', 'message' => 'This mobile number already exists!'];
        }

        $customer_code = $this->generateCustomerCode();

        $this->db->query("
            INSERT INTO customers 
            (customer_code, shop_name, customer_name, mobile, address, 
             sales_person_id, credit_limit, created_by)
            VALUES 
            (:customer_code, :shop_name, :customer_name, :mobile, :address, 
             :sales_person_id, :credit_limit, :created_by)
        ");

        $this->db->bind(':customer_code', $customer_code);
        $this->db->bind(':shop_name', trim($data['shop_name']));
        $this->db->bind(':customer_name', trim($data['customer_name'] ?? ''));
        $this->db->bind(':mobile', trim($data['mobile']));
        $this->db->bind(':address', trim($data['address'] ?? ''));
        $this->db->bind(':sales_person_id', $data['sales_person_id'] ?? null);
        $this->db->bind(':credit_limit', $data['credit_limit'] ?? 0);
        $this->db->bind(':created_by', $_SESSION['user_id'] ?? 1);

        if ($this->db->execute()) {
            return ['status' => 'success', 'message' => 'Customer created successfully!'];
        }
        return ['status' => 'error', 'message' => 'Failed to create customer!'];
    }

    public function updateCustomer($id, $data) {
        if ($this->mobileExists($data['mobile'], $id)) {
            return ['status' => 'error', 'message' => 'This mobile number already exists!'];
        }

        $this->db->query("
            UPDATE customers SET 
                shop_name = :shop_name,
                customer_name = :customer_name,
                mobile = :mobile,
                address = :address,
                sales_person_id = :sales_person_id,
                credit_limit = :credit_limit,
                is_active = :is_active,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
        ");

        $this->db->bind(':shop_name', trim($data['shop_name']));
        $this->db->bind(':customer_name', trim($data['customer_name'] ?? ''));
        $this->db->bind(':mobile', trim($data['mobile']));
        $this->db->bind(':address', trim($data['address'] ?? ''));
        $this->db->bind(':sales_person_id', $data['sales_person_id'] ?? null);
        $this->db->bind(':credit_limit', $data['credit_limit'] ?? 0);
        $this->db->bind(':is_active', $data['is_active'] ?? 1);
        $this->db->bind(':id', $id);

        if ($this->db->execute()) {
            return ['status' => 'success', 'message' => 'Customer updated successfully!'];
        }
        return ['status' => 'error', 'message' => 'Failed to update customer!'];
    }

    public function toggleStatus($id) {
        $this->db->query("UPDATE customers SET is_active = NOT is_active WHERE id = :id");
        $this->db->bind(':id', $id);
        return $this->db->execute();
    }

    /**
     * Soft delete (deactivate) a customer
     */
    public function softDeleteCustomer($id) {
        $this->db->query("UPDATE customers SET is_active = 0 WHERE id = :id");
        $this->db->bind(':id', $id);
        return $this->db->execute();
    }

    /**
     * Returns current outstanding balance for a customer (latest running balance from ledger).
     * Positive value means the customer owes us money.
     */
    public function getOutstandingBalance($customerId): float
    {
        if (!$customerId) return 0.0;
        // Uses the reliable latest running_balance from customer_ledger
        return (float) $this->Get_Customer_Now_Due($customerId);
    }

    /**
     * Returns number of sales invoices for this customer (primary history indicator).
     */
    public function getSalesInvoiceCount($customerId): int
    {
        if (!$customerId) return 0;
        $this->db->query("SELECT COUNT(*) as cnt FROM sales_invoices WHERE customer_id = :cid");
        $this->db->bind(':cid', $customerId);
        $row = $this->db->single();
        return (int)($row['cnt'] ?? 0);
    }

    /**
     * Summary metrics for customer index hero.
     */
    public function getCustomerIndexStats(): array
    {
        $stats = [
            'active'           => 0,
            'inactive'         => 0,
            'with_due'         => 0,
            'total_receivable' => 0.0,
        ];

        $this->db->query('SELECT COUNT(*) AS c FROM customers WHERE is_active = 1');
        $stats['active'] = (int)($this->db->single()['c'] ?? 0);

        $this->db->query('SELECT COUNT(*) AS c FROM customers WHERE is_active = 0');
        $stats['inactive'] = (int)($this->db->single()['c'] ?? 0);

        $this->db->query('
            SELECT COUNT(*) AS c,
                   COALESCE(SUM(cl.running_balance), 0) AS total_due
            FROM customer_ledger cl
            INNER JOIN (
                SELECT customer_id, MAX(id) AS max_id
                FROM customer_ledger
                GROUP BY customer_id
            ) latest ON cl.id = latest.max_id
            INNER JOIN customers c ON c.id = cl.customer_id AND c.is_active = 1
            WHERE cl.running_balance > 0.009
        ');
        $row = $this->db->single() ?: [];
        $stats['with_due'] = (int)($row['c'] ?? 0);
        $stats['total_receivable'] = (float)($row['total_due'] ?? 0);

        return $stats;
    }

    /**
     * AR and sales snapshot for edit sidebar.
     */
    public function getCustomerUsage(int $customerId): array
    {
        $safety = $this->getDeactivationSafetyStatus($customerId);

        return [
            'outstanding_balance' => (float)($safety['outstanding_balance'] ?? 0),
            'sales_count'         => (int)($safety['sales_count'] ?? 0),
            'can_deactivate'      => !empty($safety['can_deactivate']),
            'has_outstanding'     => !empty($safety['has_outstanding']),
            'has_sales_history'   => !empty($safety['has_sales_history']),
        ];
    }

    /**
     * Safety check before deactivating a customer.
     * Returns structured information for clear user messages.
     */
    public function getDeactivationSafetyStatus($customerId): array
    {
        $balance = $this->getOutstandingBalance($customerId);
        $salesCount = $this->getSalesInvoiceCount($customerId);

        $hasOutstanding = $balance > 0.009;
        $hasHistory = $salesCount > 0;

        return [
            'can_deactivate'       => !$hasOutstanding && !$hasHistory,
            'outstanding_balance'  => $balance,
            'sales_count'          => $salesCount,
            'has_outstanding'      => $hasOutstanding,
            'has_sales_history'    => $hasHistory,
        ];
    }

    /**
     * Restore a soft-deleted customer
     */
    public function restoreCustomer($id) {
        $this->db->query("UPDATE customers SET is_active = 1 WHERE id = :id");
        $this->db->bind(':id', $id);
        return $this->db->execute();
    }

    /**
     * Server-side DataTables for Customers
     */
    public function getCustomersForDataTable(array $params): array
    {
        $start          = (int)($params['start'] ?? 0);
        $length         = (int)($params['length'] ?? 25);
        $searchValue    = trim($params['search']['value'] ?? '');
        $orderColumn    = (int)($params['order'][0]['column'] ?? 0);
        $orderDir       = $params['order'][0]['dir'] ?? 'asc';

        // Custom filters
        $filterSalesPerson = $params['filterSalesPerson'] ?? '';
        $filterStatus      = $params['filterStatus'] ?? ''; // 'active', 'inactive'
        $includeDeleted    = !empty($params['includeDeleted']);

        $columns = [
            'c.customer_code',
            'c.shop_name',
            'c.customer_name',
            'balance_due',
            'c.is_active',
        ];

        $baseQuery = "
            FROM customers c
            LEFT JOIN employees e ON c.sales_person_id = e.id
        ";

        $where = [];
        $bindParams = [];

        if (!$includeDeleted) {
            $where[] = "c.is_active = 1";
        } else {
            $where[] = "c.is_active = 0";
        }

        // Global search
        if ($searchValue !== '') {
            $where[] = "(c.shop_name LIKE :search 
                      OR c.customer_name LIKE :search 
                      OR c.mobile LIKE :search 
                      OR c.address LIKE :search 
                      OR c.customer_code LIKE :search)";
            $bindParams[':search'] = "%{$searchValue}%";
        }

        // Sales Person filter
        if ($filterSalesPerson) {
            $where[] = "c.sales_person_id = :sales_person";
            $bindParams[':sales_person'] = $filterSalesPerson;
        }

        // Status filter (can be used with deleted mode)
        if ($filterStatus === 'active' && !$includeDeleted) {
            $where[] = "c.is_active = 1";
        } elseif ($filterStatus === 'inactive') {
            $where[] = "c.is_active = 0";
        }

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        // Total records (for current mode: active OR deleted)
        $totalQuery = "SELECT COUNT(c.id) as total {$baseQuery}";
        if (!$includeDeleted) {
            $totalQuery .= " WHERE c.is_active = 1";
        } else {
            $totalQuery .= " WHERE c.is_active = 0";
        }
        $this->db->query($totalQuery);
        $totalResult = $this->db->single();
        $recordsTotal = $totalResult['total'] ?? 0;

        // Filtered records
        $filteredQuery = "SELECT COUNT(c.id) as total {$baseQuery} {$whereSql}";
        $this->db->query($filteredQuery);
        foreach ($bindParams as $key => $val) {
            $this->db->bind($key, $val);
        }
        $filteredResult = $this->db->single();
        $recordsFiltered = $filteredResult['total'] ?? 0;

        // Data query
        $orderBy = $columns[$orderColumn] ?? 'c.shop_name';
        $dataQuery = "
            SELECT 
                c.id,
                c.customer_code,
                c.shop_name,
                c.customer_name,
                c.mobile,
                c.address,
                c.credit_limit,
                c.is_active,
                e.name AS sales_person_name,
                e.id AS sales_person_id,
                (
                    SELECT COALESCE(running_balance, 0)
                    FROM customer_ledger
                    WHERE customer_id = c.id
                    ORDER BY id DESC
                    LIMIT 1
                ) AS balance_due
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

    // ============================================================
    // CREDIT LIMIT ENFORCEMENT (Phase 3 - Strong Enforcement)
    // ============================================================

    /**
     * Get the customer's current posted due from the ledger only.
     * This is the authoritative value for credit limit checking.
     */
    public function getCurrentDueForCredit($customerId): float
    {
        if (!$customerId) return 0.0;

        // Use latest running_balance (most accurate for posted transactions)
        $this->db->query("
            SELECT COALESCE(running_balance, 0) as due_balance
            FROM customer_ledger 
            WHERE customer_id = :customer_id 
            ORDER BY id DESC LIMIT 1
        ");
        $this->db->bind(':customer_id', $customerId);
        $row = $this->db->single();

        return (float)($row['due_balance'] ?? 0);
    }

    /**
     * Get customer's credit limit
     */
    public function getCreditLimit($customerId): float
    {
        if (!$customerId) return 0.0;

        $this->db->query("SELECT credit_limit FROM customers WHERE id = :id");
        $this->db->bind(':id', $customerId);
        $row = $this->db->single();
        return (float)($row['credit_limit'] ?? 0);
    }

    /**
     * Check if adding a new amount would exceed the credit limit.
     * Only considers already posted invoices in customer_ledger.
     */
    public function wouldExceedCreditLimit(int $customerId, float $newInvoiceAmount): array
    {
        $currentDue     = $this->getCurrentDueForCredit($customerId);
        $creditLimit    = $this->getCreditLimit($customerId);
        $projectedDue   = $currentDue + $newInvoiceAmount;

        $exceeds = $projectedDue > $creditLimit;

        return [
            'exceeds'            => $exceeds,
            'current_due'        => $currentDue,
            'credit_limit'       => $creditLimit,
            'new_invoice_amount' => $newInvoiceAmount,
            'projected_due'      => $projectedDue,
            'excess_amount'      => $exceeds ? ($projectedDue - $creditLimit) : 0,
            'available_credit'   => max(0, $creditLimit - $currentDue),
        ];
    }

    /**
     * Get a nice summary for display in sales screens
     */
    public function getCreditStatus($customerId): array
    {
        $currentDue   = $this->getCurrentDueForCredit($customerId);
        $creditLimit  = $this->getCreditLimit($customerId);
        $available    = max(0, $creditLimit - $currentDue);

        return [
            'credit_limit'     => $creditLimit,
            'current_due'      => $currentDue,
            'available_credit' => $available,
            'utilization'      => $creditLimit > 0 ? round(($currentDue / $creditLimit) * 100, 1) : 0,
            'is_over_limit'    => $currentDue > $creditLimit,
        ];
    }
}