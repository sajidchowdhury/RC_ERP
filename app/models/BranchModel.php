<?php
// app/models/BranchModel.php

require_once '../core/Database.php';
require_once __DIR__ . '/../helpers/Helper.php';


class BranchModel extends Helper{

    protected $db;

    public function __construct() {
        $this->db = new Database();
    }

       public function getAllBranches() {
   
        return $this->Get_All_Branches();
    }

    
    // Get all active branches
public function getAllActiveBranches() {
  
    return $this->Get_All_Active_Branches();
}
    // Get single branch
    public function getBranchById($id) {
        return $this->Get_Branch_By_Id($id);
    }

      public function createBranch($data) {
        $this->db->query("
            INSERT INTO branches 
            (branch_code, branch_name, address, phone, email, created_by)
            VALUES 
            (:branch_code, :branch_name, :address, :phone, :email, :created_by)
        ");

        $this->db->bind(':branch_code', trim($data['branch_code']));
        $this->db->bind(':branch_name', trim($data['branch_name']));
        $this->db->bind(':address', trim($data['address'] ?? ''));
        $this->db->bind(':phone', trim($data['phone'] ?? ''));
        $this->db->bind(':email', trim($data['email'] ?? ''));
        $this->db->bind(':created_by', $_SESSION['user_id'] ?? 1);

        return $this->db->execute();
    }

    public function updateBranch($id, $data) {
        $this->db->query("
            UPDATE branches SET 
                branch_code = :branch_code,
                branch_name = :branch_name,
                address = :address,
                phone = :phone,
                email = :email,
                is_active = :is_active,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
        ");

        $this->db->bind(':branch_code', trim($data['branch_code']));
        $this->db->bind(':branch_name', trim($data['branch_name']));
        $this->db->bind(':address', trim($data['address'] ?? ''));
        $this->db->bind(':phone', trim($data['phone'] ?? ''));
        $this->db->bind(':email', trim($data['email'] ?? ''));
        $this->db->bind(':is_active', $data['is_active'] ?? 1);
        $this->db->bind(':id', $id);

        return $this->db->execute();
    }

  
    public function toggleStatus($id) {
        $this->db->query("UPDATE branches SET is_active = NOT is_active WHERE id = :id");
        $this->db->bind(':id', $id);
        return $this->db->execute();
    }

    /**
     * Get counts of related active records for a branch
     */
    public function getBranchUsage($id) {
        $usage = [
            'warehouses' => 0,
            'employees'  => 0,
        ];

        // Count active warehouses
        $this->db->query("SELECT COUNT(*) as total FROM warehouses WHERE branch_id = :id AND is_active = 1");
        $this->db->bind(':id', $id);
        $result = $this->db->single();
        $usage['warehouses'] = $result['total'] ?? 0;

        // Count active employees
        $this->db->query("SELECT COUNT(*) as total FROM employees WHERE branch_id = :id AND is_active = 1");
        $this->db->bind(':id', $id);
        $result = $this->db->single();
        $usage['employees'] = $result['total'] ?? 0;

        return $usage;
    }

    /**
     * Check if branch can be safely deactivated
     */
    public function canDeactivateBranch($id) {
        $usage = $this->getBranchUsage($id);
        return ($usage['warehouses'] == 0 && $usage['employees'] == 0);
    }

    /**
     * Summary metrics for branch index hero.
     */
    public function getBranchIndexStats(): array
    {
        $stats = [
            'active'     => 0,
            'inactive'   => 0,
            'warehouses' => 0,
            'employees'  => 0,
        ];

        $this->db->query('SELECT COUNT(*) AS c FROM branches WHERE is_active = 1');
        $stats['active'] = (int)($this->db->single()['c'] ?? 0);

        $this->db->query('SELECT COUNT(*) AS c FROM branches WHERE is_active = 0');
        $stats['inactive'] = (int)($this->db->single()['c'] ?? 0);

        $this->db->query('SELECT COUNT(*) AS c FROM warehouses WHERE is_active = 1');
        $stats['warehouses'] = (int)($this->db->single()['c'] ?? 0);

        $this->db->query('SELECT COUNT(*) AS c FROM employees WHERE is_active = 1');
        $stats['employees'] = (int)($this->db->single()['c'] ?? 0);

        return $stats;
    }

    /**
     * Server-side DataTables for Branches
     */
    public function getBranchesForDataTable(array $params): array
    {
        $start          = (int)($params['start'] ?? 0);
        $length         = (int)($params['length'] ?? 25);
        $searchValue    = trim($params['search']['value'] ?? '');
        $orderColumn    = (int)($params['order'][0]['column'] ?? 0);
        $orderDir       = $params['order'][0]['dir'] ?? 'asc';

        // Custom filter: status + deleted mode
        $filterStatus   = $params['filterStatus'] ?? '';
        $includeDeleted = !empty($params['includeDeleted']); // for "Show Deleted" mode

        $columns = ['b.branch_code', 'b.branch_name', 'b.address', 'b.phone', 'b.email', 'b.is_active'];

        $baseQuery = "FROM branches b";

        $where = [];
        $bindParams = [];

        if (!$includeDeleted) {
            $where[] = "b.is_active = 1";
        } else {
            $where[] = "b.is_active = 0";
        }

        // Additional status filter (can be used together with deleted mode)
        if ($filterStatus === 'active' && !$includeDeleted) {
            $where[] = "b.is_active = 1";
        } elseif ($filterStatus === 'inactive' && !$includeDeleted) {
            $where[] = "b.is_active = 0";
        }

        // Global search
        if ($searchValue !== '') {
            $where[] = "(b.branch_name LIKE :search 
                      OR b.branch_code LIKE :search 
                      OR b.address LIKE :search 
                      OR b.phone LIKE :search 
                      OR b.email LIKE :search)";
            $bindParams[':search'] = "%{$searchValue}%";
        }

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        // Total records (without filters)
        $totalQuery = "SELECT COUNT(b.id) as total {$baseQuery}";
        $this->db->query($totalQuery);
        $totalResult = $this->db->single();
        $recordsTotal = $totalResult['total'] ?? 0;

        // Filtered records
        $filteredQuery = "SELECT COUNT(b.id) as total {$baseQuery} {$whereSql}";
        $this->db->query($filteredQuery);
        foreach ($bindParams as $key => $val) {
            $this->db->bind($key, $val);
        }
        $filteredResult = $this->db->single();
        $recordsFiltered = $filteredResult['total'] ?? 0;

        // Data query
        $orderBy = $columns[$orderColumn] ?? 'b.branch_name';
        $dataQuery = "
            SELECT 
                b.id,
                b.branch_code,
                b.branch_name,
                b.address,
                b.phone,
                b.email,
                b.is_active,
                (
                    SELECT COUNT(*)
                    FROM warehouses w
                    WHERE w.branch_id = b.id AND w.is_active = 1
                ) AS warehouse_count,
                (
                    SELECT COUNT(*)
                    FROM employees e
                    WHERE e.branch_id = b.id AND e.is_active = 1
                ) AS employee_count
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