<?php
// app/models/UserModel.php

require_once '../core/Database.php';
require_once __DIR__ . '/../helpers/Helper.php';

class UserModel extends Helper{

    protected $db;

    public function __construct() {
        $this->db = new Database();
    }

    public function getAllUsers(bool $includeDeleted = false) {
        return $this->get_All_Users($includeDeleted);
    }

    /**
     * Summary metrics for user index hero.
     */
    public function getUserIndexStats(): array
    {
        $notDeleted = "(deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00')";
        $stats = [
            'active'   => 0,
            'inactive' => 0,
            'total'    => 0,
        ];

        $this->db->query("SELECT COUNT(*) AS c FROM users WHERE is_active = 1 AND {$notDeleted}");
        $stats['active'] = (int)($this->db->single()['c'] ?? 0);

        $this->db->query("SELECT COUNT(*) AS c FROM users WHERE is_active = 0 AND {$notDeleted}");
        $stats['inactive'] = (int)($this->db->single()['c'] ?? 0);

        $this->db->query("SELECT COUNT(*) AS c FROM users WHERE {$notDeleted}");
        $stats['total'] = (int)($this->db->single()['c'] ?? 0);

        return $stats;
    }

    /**
     * Server-side data for DataTables (Users list with pagination, search, filters)
     */
    public function getUsersForDataTable(array $params): array
    {
        $start          = (int)($params['start'] ?? 0);
        $length         = (int)($params['length'] ?? 25);
        $searchValue    = trim($params['search']['value'] ?? '');
        $orderColumn    = (int)($params['order'][0]['column'] ?? 0);
        $orderDir       = $params['order'][0]['dir'] ?? 'asc';

        // Custom filters
        $filterBranch   = $params['filterBranch'] ?? '';
        $filterStatus   = $params['filterStatus'] ?? '';
        $includeDeleted = !empty($params['includeDeleted']);

        // Columns for ordering (must match the order in the view)
        $columns = [
            0 => 'e.name',           // Employee
            1 => 'u.username',       // Username
            2 => 'b.branch_name',    // Branch
            3 => 'u.is_active',      // Status
            4 => 'u.last_login'      // Last Login
        ];

        $baseQuery = "
            FROM users u
            JOIN employees e ON u.employee_id = e.id
            LEFT JOIN branches b ON e.branch_id = b.id
        ";

        $where = [];
        $bindParams = [];

        if (!$includeDeleted) {
            $where[] = "(u.deleted_at IS NULL OR u.deleted_at = '0000-00-00 00:00:00')";
        }

        // Global search
        if ($searchValue !== '') {
            $where[] = "(u.username LIKE :search OR e.name LIKE :search OR e.employee_code LIKE :search)";
            $bindParams[':search'] = "%{$searchValue}%";
        }

        // Custom filters
        if ($filterBranch) {
            $where[] = "b.branch_name = :branch";
            $bindParams[':branch'] = $filterBranch;
        }

        if ($filterStatus === 'active') {
            $where[] = "u.is_active = 1";
        } elseif ($filterStatus === 'inactive') {
            $where[] = "u.is_active = 0";
        }

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        // Total records
        $totalSql = "SELECT COUNT(DISTINCT u.id) as total {$baseQuery}";
        if (!$includeDeleted) {
            $totalSql .= " WHERE (u.deleted_at IS NULL OR u.deleted_at = '0000-00-00 00:00:00')";
        }
        $this->db->query($totalSql);
        $totalResult = $this->db->single();
        $recordsTotal = $totalResult['total'] ?? 0;

        // Filtered records
        $filteredSql = "SELECT COUNT(DISTINCT u.id) as total {$baseQuery} {$whereSql}";
        $this->db->query($filteredSql);
        foreach ($bindParams as $key => $val) {
            $this->db->bind($key, $val);
        }
        $filteredResult = $this->db->single();
        $recordsFiltered = $filteredResult['total'] ?? 0;

        // Data with ordering and limit
        $orderBy = $columns[$orderColumn] ?? 'e.name';
        $dataSql = "
            SELECT 
                u.id, u.username, u.is_active, u.last_login,
                e.name as employee_name, e.employee_code,
                b.branch_name
            {$baseQuery}
            {$whereSql}
            ORDER BY {$orderBy} {$orderDir}
            LIMIT {$start}, {$length}
        ";

        $this->db->query($dataSql);
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

    public function getUserById($id) {
      
        return $this->get_User_By_Id($id);
    }

    // Check if employee already has a user account
    public function employeeHasUser($employee_id) {
        $this->db->query("SELECT id FROM users WHERE employee_id = :employee_id LIMIT 1");
        $this->db->bind(':employee_id', $employee_id);
        return $this->db->single() ? true : false;
    }

    // Check username exists
    public function usernameExists($username, $exclude_id = null) {
        $sql = "SELECT id FROM users WHERE username = :username";
        if ($exclude_id) {
            $sql .= " AND id != :exclude_id";
        }
        $this->db->query($sql);
        $this->db->bind(':username', $username);
        if ($exclude_id) $this->db->bind(':exclude_id', $exclude_id);
        return $this->db->single() ? true : false;
    }

    // Create User
    public function createUser($data) {
        if ($this->usernameExists($data['username'])) {
            return ['status' => 'error', 'message' => 'Username already exists!'];
        }

        if ($this->employeeHasUser($data['employee_id'])) {
            return ['status' => 'error', 'message' => 'This employee already has a user account!'];
        }

        // Password validation
        $password = $data['password'] ?? '';
        $confirm  = $data['confirm_password'] ?? $password;

        if ($password !== $confirm) {
            return ['status' => 'error', 'message' => 'Password and Confirm Password do not match!'];
        }

        $validation = $this->validatePasswordStrength($password);
        if ($validation !== true) {
            return ['status' => 'error', 'message' => $validation];
        }

        $this->db->query("
            INSERT INTO users (employee_id, username, password_hash, created_by)
            VALUES (:employee_id, :username, :password_hash, :created_by)
        ");

        $this->db->bind(':employee_id', $data['employee_id']);
        $this->db->bind(':username', trim($data['username']));
        $this->db->bind(':password_hash', password_hash($password, PASSWORD_DEFAULT));
        $this->db->bind(':created_by', $_SESSION['user_id'] ?? 1);

        return $this->db->execute() 
            ? ['status' => 'success', 'message' => 'User created successfully!']
            : ['status' => 'error', 'message' => 'Failed to create user!'];
    }

    // Update User
    public function updateUser($id, $data) {
        if ($this->usernameExists($data['username'], $id)) {
            return ['status' => 'error', 'message' => 'Username already exists!'];
        }

        $sql = "UPDATE users SET username = :username, is_active = :is_active";
        $password = $data['password'] ?? '';

        if (!empty($password)) {
            $sql .= ", password_hash = :password_hash";
        }
        $sql .= " WHERE id = :id";

        $this->db->query($sql);
        $this->db->bind(':username', trim($data['username']));
        $this->db->bind(':is_active', $data['is_active'] ?? 1);
        $this->db->bind(':id', $id);

        if (!empty($password)) {
            $validation = $this->validatePasswordStrength($password);
            if ($validation !== true) {
                return ['status' => 'error', 'message' => $validation];
            }
            $this->db->bind(':password_hash', password_hash($password, PASSWORD_DEFAULT));
        }

        return $this->db->execute() 
            ? ['status' => 'success', 'message' => 'User updated successfully!']
            : ['status' => 'error', 'message' => 'Failed to update user!'];
    }

    public function toggleStatus($id) {
        // First, check current status
        $this->db->query("SELECT is_active FROM users WHERE id = :id");
        $this->db->bind(':id', $id);
        $current = $this->db->single();

        if (!$current) {
            return false;
        }

        $isCurrentlyActive = (bool)$current['is_active'];

        // If we are trying to deactivate this user, make sure they are not the last active user
        if ($isCurrentlyActive) {
            $this->db->query("SELECT COUNT(*) as active_count FROM users WHERE is_active = 1 AND (deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00')");
            $count = $this->db->single();

            if ($count && (int)$count['active_count'] <= 1) {
                // This is the last active user — do not allow deactivation
                return false;
            }
        }

        $this->db->query("UPDATE users SET is_active = NOT is_active WHERE id = :id");
        $this->db->bind(':id', $id);
        return $this->db->execute();
    }

    /**
     * Soft delete a user (recommended over hard delete)
     */
    public function softDeleteUser(int $id, int $deletedBy): bool
    {
        // Prevent soft deleting the last active user
        $this->db->query("SELECT COUNT(*) as active_count FROM users WHERE is_active = 1 AND (deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00')");
        $count = $this->db->single();

        if ($count && (int)$count['active_count'] <= 1) {
            return false; // Cannot delete the last active user
        }

        $this->db->query("
            UPDATE users 
            SET deleted_at = NOW(), 
                is_active = 0,
                deleted_by = :deleted_by
            WHERE id = :id
        ");
        $this->db->bind(':id', $id);
        $this->db->bind(':deleted_by', $deletedBy);

        return $this->db->execute();
    }

    /**
     * Restore a soft-deleted user
     */
    public function restoreUser(int $id): bool
    {
        $this->db->query("
            UPDATE users 
            SET deleted_at = NULL, 
                deleted_by = NULL,
                is_active = 1
            WHERE id = :id
        ");
        $this->db->bind(':id', $id);
        return $this->db->execute();
    }

    // Get current permissions for a user
    public function getUserPermissions($user_id) {
        $this->db->query("
            SELECT menu_id, can_view, can_edit 
            FROM user_menu_permissions 
            WHERE user_id = :user_id
        ");
        $this->db->bind(':user_id', $user_id);
        $result = $this->db->resultSet();

        $permissions = [];
        foreach ($result as $row) {
            $permissions[$row['menu_id']] = [
                'view' => (int)$row['can_view'],
                'edit' => (int)$row['can_edit']
            ];
        }
        return $permissions;
    }

    // Save single permission
    public function savePermission($user_id, $menu_id, $can_view, $can_edit) {
        $this->db->query("
            INSERT INTO user_menu_permissions 
            (user_id, menu_id, can_view, can_edit) 
            VALUES (:user_id, :menu_id, :can_view, :can_edit)
        ");
        $this->db->bind(':user_id', $user_id);
        $this->db->bind(':menu_id', $menu_id);
        $this->db->bind(':can_view', $can_view);
        $this->db->bind(':can_edit', $can_edit);
        return $this->db->execute();
    }

    // Delete all permissions for a user
    public function deleteAllPermissions($user_id) {
        $this->db->query("DELETE FROM user_menu_permissions WHERE user_id = :user_id");
        $this->db->bind(':user_id', $user_id);
        return $this->db->execute();
    }

    /**
     * Safely save user permissions using database transaction
     * Includes protection against self-locking (removing own critical access)
     */
    public function saveUserPermissions(int $userId, array $permissionsData): array
    {
        $isSelfEdit = ($userId === ($_SESSION['user_id'] ?? 0));

        // If editing own permissions, pre-check for critical access
        if ($isSelfEdit) {
            $hasUserManagementAccess = false;

            if (!empty($permissionsData)) {
                // We need to know which menu_id corresponds to User Management
                // For now, we'll check after building the new permission set
                // A better long-term solution is to mark critical menus in DB
            }
        }

        $this->db->beginTransaction();

        try {
            // Step 1: Remove all existing permissions for this user
            $this->deleteAllPermissions($userId);

            $newPermissions = [];

            // Step 2: Insert new permissions
            if (!empty($permissionsData) && is_array($permissionsData)) {
                foreach ($permissionsData as $menuId => $perm) {
                    $canView = isset($perm['view']) ? 1 : 0;
                    $canEdit = isset($perm['edit']) ? 1 : 0;

                    if ($canView || $canEdit) {
                        $this->savePermission($userId, (int)$menuId, $canView, $canEdit);
                        $newPermissions[(int)$menuId] = ['view' => $canView, 'edit' => $canEdit];
                    }
                }
            }

            // === SELF-LOCKING PROTECTION ===
            if ($isSelfEdit) {
                // Fetch menu IDs for critical sections (User Management)
                $this->db->query("SELECT id FROM menus WHERE controller = 'user' LIMIT 1");
                $userMenu = $this->db->single();

                if ($userMenu) {
                    $userMenuId = (int)$userMenu['id'];
                    $hasAccess = isset($newPermissions[$userMenuId]['view']) && $newPermissions[$userMenuId]['view'] == 1;

                    if (!$hasAccess) {
                        $this->db->rollback();
                        return [
                            'status' => 'error',
                            'message' => 'You cannot remove your own access to User Management. This would lock you out of the system.'
                        ];
                    }
                }
            }

            $this->db->commit();
            return ['status' => 'success', 'message' => 'Menu permissions updated successfully!'];

        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Permission Save Error for User {$userId}: " . $e->getMessage());
            return ['status' => 'error', 'message' => 'Failed to save permissions. Changes were rolled back.'];
        }
    }

    /**
     * Professional password strength policy
     * Returns true on success, or error message string on failure.
     */
    private function validatePasswordStrength(string $password): true|string
    {
        if (strlen($password) < 8) {
            return 'Password must be at least 8 characters long.';
        }

        if (!preg_match('/[A-Za-z]/', $password)) {
            return 'Password must contain at least one letter.';
        }

        if (!preg_match('/[0-9]/', $password)) {
            return 'Password must contain at least one number.';
        }

        // Optional stronger rule (uncomment if desired):
        // if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        //     return 'Password must contain at least one special character.';
        // }

        return true;
    }

    /**
     * Allow a logged-in user to change their own password
     */
    public function changeUserPassword(int $userId, string $currentPassword, string $newPassword, string $confirmPassword)
    {
        if ($newPassword !== $confirmPassword) {
            return ['status' => 'error', 'message' => 'New password and confirmation do not match.'];
        }

        $validation = $this->validatePasswordStrength($newPassword);
        if ($validation !== true) {
            return ['status' => 'error', 'message' => $validation];
        }

        // Fetch current hash
        $this->db->query("SELECT password_hash FROM users WHERE id = :id");
        $this->db->bind(':id', $userId);
        $user = $this->db->single();

        if (!$user || !password_verify($currentPassword, $user['password_hash'])) {
            return ['status' => 'error', 'message' => 'Current password is incorrect.'];
        }

        // Update password
        $this->db->query("UPDATE users SET password_hash = :hash WHERE id = :id");
        $this->db->bind(':hash', password_hash($newPassword, PASSWORD_DEFAULT));
        $this->db->bind(':id', $userId);

        if ($this->db->execute()) {
            return ['status' => 'success', 'message' => 'Password changed successfully!'];
        }

        return ['status' => 'error', 'message' => 'Failed to update password.'];
    }
}