<?php
// app/models/WarehouseModel.php

require_once '../core/Database.php';
require_once __DIR__ . '/../helpers/Helper.php';

class WarehouseModel extends Helper{

    protected $db;

    public function __construct() {
        $this->db = new Database();
    }

    // Get all warehouses with branch name
    public function getAllWarehouses() {
      
        return $this->Get_All_Warehouses();
    }

    // Get warehouses by branch (useful for dropdowns)
    public function getWarehousesByBranch($branch_id) {
        return $this->Get_Warehouse_By_Branch($branch_id) ;
    }

    public function belongsToBranch(int $warehouseId, int $branchId): bool
    {
        return $this->warehouseBelongsToBranch($warehouseId, $branchId);
    }

    public function getWarehouseById($id) {
        
        return $this->Get_Warehouse_By_Id($id);
    }

    public function createWarehouse($data) {
        $this->db->query("
            INSERT INTO warehouses 
            (warehouse_code, warehouse_name, branch_id, address, created_by)
            VALUES 
            (:warehouse_code, :warehouse_name, :branch_id, :address, :created_by)
        ");

        $this->db->bind(':warehouse_code', trim($data['warehouse_code']));
        $this->db->bind(':warehouse_name', trim($data['warehouse_name']));
        $this->db->bind(':branch_id', $data['branch_id']);
        $this->db->bind(':address', trim($data['address'] ?? ''));
        $this->db->bind(':created_by', $_SESSION['user_id'] ?? 1);

        return $this->db->execute();
    }

    public function updateWarehouse($id, $data) {
        $this->db->query("
            UPDATE warehouses SET 
                warehouse_code = :warehouse_code,
                warehouse_name = :warehouse_name,
                branch_id = :branch_id,
                address = :address,
                is_active = :is_active,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
        ");

        $this->db->bind(':warehouse_code', trim($data['warehouse_code']));
        $this->db->bind(':warehouse_name', trim($data['warehouse_name']));
        $this->db->bind(':branch_id', $data['branch_id']);
        $this->db->bind(':address', trim($data['address'] ?? ''));
        $this->db->bind(':is_active', $data['is_active'] ?? 1);
        $this->db->bind(':id', $id);

        return $this->db->execute();
    }

    public function toggleStatus($id) {
        $this->db->query("UPDATE warehouses SET is_active = NOT is_active WHERE id = :id");
        $this->db->bind(':id', $id);
        return $this->db->execute();
    }

    /**
     * Check if warehouse has any stock
     */
    public function hasStock($warehouseId) {
        $this->db->query("SELECT COUNT(*) as total FROM warehouse_stock WHERE warehouse_id = :wid AND qty > 0");
        $this->db->bind(':wid', $warehouseId);
        $result = $this->db->single();
        return ($result['total'] ?? 0) > 0;
    }

    /**
     * Get total stock quantity in a warehouse
     */
    public function getWarehouseStockCount($warehouseId) {
        $this->db->query("SELECT COALESCE(SUM(qty), 0) as total_qty FROM warehouse_stock WHERE warehouse_id = :wid");
        $this->db->bind(':wid', $warehouseId);
        $result = $this->db->single();
        return $result['total_qty'] ?? 0;
    }


    /**
     * Summary metrics for warehouse index hero.
     */
    public function getWarehouseIndexStats(): array
    {
        $stats = [
            'active'    => 0,
            'inactive'  => 0,
            'branches'  => 0,
            'stock_qty' => 0.0,
        ];

        $this->db->query('SELECT COUNT(*) AS c FROM warehouses WHERE is_active = 1');
        $stats['active'] = (int)($this->db->single()['c'] ?? 0);

        $this->db->query('SELECT COUNT(*) AS c FROM warehouses WHERE is_active = 0');
        $stats['inactive'] = (int)($this->db->single()['c'] ?? 0);

        $this->db->query('SELECT COUNT(DISTINCT branch_id) AS c FROM warehouses WHERE is_active = 1 AND branch_id IS NOT NULL');
        $stats['branches'] = (int)($this->db->single()['c'] ?? 0);

        $this->db->query('SELECT COALESCE(SUM(qty), 0) AS total FROM warehouse_stock WHERE qty > 0');
        $stats['stock_qty'] = (float)($this->db->single()['total'] ?? 0);

        return $stats;
    }

    /**
     * Stock snapshot for edit sidebar.
     */
    public function getWarehouseUsage(int $warehouseId): array
    {
        $this->db->query('
            SELECT COALESCE(SUM(qty), 0) AS total_qty,
                   COUNT(DISTINCT product_id) AS product_lines
            FROM warehouse_stock
            WHERE warehouse_id = :id AND qty > 0.0001
        ');
        $this->db->bind(':id', $warehouseId);
        $row = $this->db->single() ?: [];

        return [
            'total_qty'      => (float)($row['total_qty'] ?? 0),
            'product_lines'  => (int)($row['product_lines'] ?? 0),
            'has_stock'      => ((float)($row['total_qty'] ?? 0)) > 0.0001,
        ];
    }

    /**
     * Server-side DataTables for Warehouses
     */
    public function getWarehousesForDataTable(array $params): array
    {
        $start          = (int)($params['start'] ?? 0);
        $length         = (int)($params['length'] ?? 25);
        $searchValue    = trim($params['search']['value'] ?? '');
        $orderColumn    = (int)($params['order'][0]['column'] ?? 0);
        $orderDir       = $params['order'][0]['dir'] ?? 'asc';

        // Custom filters
        $filterBranch   = $params['filterBranch'] ?? '';
        $includeDeleted = !empty($params['includeDeleted']);

        $columns = ['w.warehouse_code', 'w.warehouse_name', 'b.branch_name', 'w.address'];

        $baseQuery = "
            FROM warehouses w
            LEFT JOIN branches b ON w.branch_id = b.id
        ";

        $where = [];
        $bindParams = [];

        if (!$includeDeleted) {
            $where[] = "w.is_active = 1";
        } else {
            $where[] = "w.is_active = 0";
        }

        // Global search
        if ($searchValue !== '') {
            $where[] = "(w.warehouse_name LIKE :search 
                      OR w.warehouse_code LIKE :search 
                      OR w.address LIKE :search 
                      OR b.branch_name LIKE :search)";
            $bindParams[':search'] = "%{$searchValue}%";
        }

        // Branch filter
        if ($filterBranch) {
            $where[] = "w.branch_id = :branch";
            $bindParams[':branch'] = $filterBranch;
        }

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        // Total records
        $totalQuery = "SELECT COUNT(w.id) as total {$baseQuery}";
        if (!$includeDeleted) {
            $totalQuery .= " WHERE w.is_active = 1";
        }
        $this->db->query($totalQuery);
        $totalResult = $this->db->single();
        $recordsTotal = $totalResult['total'] ?? 0;

        // Filtered records
        $filteredQuery = "SELECT COUNT(w.id) as total {$baseQuery} {$whereSql}";
        $this->db->query($filteredQuery);
        foreach ($bindParams as $key => $val) {
            $this->db->bind($key, $val);
        }
        $filteredResult = $this->db->single();
        $recordsFiltered = $filteredResult['total'] ?? 0;

        // Data query
        $orderBy = $columns[$orderColumn] ?? 'w.warehouse_name';
        $dataQuery = "
            SELECT 
                w.id,
                w.warehouse_code,
                w.warehouse_name,
                w.address,
                w.is_active,
                b.branch_name,
                b.id AS branch_id,
                (
                    SELECT COALESCE(SUM(ws.qty), 0)
                    FROM warehouse_stock ws
                    WHERE ws.warehouse_id = w.id AND ws.qty > 0.0001
                ) AS stock_qty,
                (
                    SELECT COUNT(DISTINCT ws.product_id)
                    FROM warehouse_stock ws
                    WHERE ws.warehouse_id = w.id AND ws.qty > 0.0001
                ) AS product_lines
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