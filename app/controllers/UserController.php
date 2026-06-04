<?php
// app/controllers/UserController.php

require_once '../core/BaseController.php';
require_once '../app/models/UserModel.php';
require_once '../app/models/EmployeeModel.php';
require_once '../app/models/BranchModel.php';
require_once '../core/UserAudit.php';

class UserController extends BaseController {

    private $userModel;
    private $employeeModel;
    private $branchModel;

    public function __construct() {
        $this->requireLogin();
        $this->userModel = new UserModel();
        $this->employeeModel = new EmployeeModel();
        $this->branchModel = new BranchModel();
    }

    // List all users
    public function index() {
        // Handle DataTables server-side AJAX request
        if (isset($_GET['draw'])) {
            $params = $_GET;
            $params['includeDeleted'] = isset($_GET['deleted']) && $_GET['deleted'] == '1';

            try {
                $response = $this->userModel->getUsersForDataTable($params);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode($response);
            } catch (Exception $e) {
                // Always return valid JSON even on error so DataTables doesn't break
                header('Content-Type: application/json; charset=utf-8');
                http_response_code(500);
                echo json_encode([
                    'draw' => (int)($params['draw'] ?? 1),
                    'recordsTotal' => 0,
                    'recordsFiltered' => 0,
                    'data' => [],
                    'error' => 'Server error: ' . $e->getMessage()
                ]);
            }
            exit;
        }

        // Normal page load - load filter data
        $branches = $this->branchModel->getAllActiveBranches();

        $data = [
            'title'    => 'System users',
            'branches' => $branches,
            'stats'    => $this->userModel->getUserIndexStats(),
        ];
        $this->view('user/index', $data);
    }

    // Show create user form
    public function create() {
        $employee_id = $_GET['employee_id'] ?? null;
        $preSelectedEmployee = null;

        if ($employee_id) {
            $preSelectedEmployee = $this->employeeModel->getEmployeeById($employee_id);
        }

        $employees = $this->employeeModel->getAllEmployees();

        $data = [
            'title' => 'Create New User',
            'employees' => $employees,
            'preSelectedEmployee' => $preSelectedEmployee
        ];
        $this->view('user/create', $data);
    }

    // Save new user
    public function store() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->validateCSRF();
            $result = $this->userModel->createUser($_POST);

        if ($result['status'] === 'success') {
            // Audit log
            $audit = new UserAudit();
            $audit->logUserCreated($_SESSION['user_id'] ?? 0, 0, $_POST['username'] ?? 'unknown'); // target id will be improved later

            $_SESSION['success'] = $result['message'];
            $this->redirect('user/index');
        } else {
            $_SESSION['error'] = $result['message'];
            $redirect = 'user/create';
            if (!empty($_POST['employee_id'])) {
                $redirect .= '?employee_id=' . $_POST['employee_id'];
            }
            $this->redirect($redirect);
        }
    }
}
    // Show edit user form
    public function edit($id = null) {
        if (!$id) $this->redirect('user/index');

        $user = $this->userModel->getUserById($id);
        if (!$user) {
            $_SESSION['error'] = "User not found!";
            $this->redirect('user/index');
        }

        $data = [
            'title' => 'Edit User',
            'user' => $user
        ];
        $this->view('user/edit', $data);
    }

    // Update user
    public function update($id = null) {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $id) {
            $this->validateCSRF();
            $result = $this->userModel->updateUser($id, $_POST);

            if ($result['status'] === 'success') {
                $_SESSION['success'] = $result['message'];
            } else {
                $_SESSION['error'] = $result['message'];
            }
        }
        $this->redirect('user/index');
    }

    // Toggle user active/inactive
    public function toggle($id = null) {
        if ($id) {
            if ($this->userModel->toggleStatus($id)) {
                $audit = new UserAudit();
                $audit->logUserStatusChanged($_SESSION['user_id'] ?? 0, (int)$id, true);

                $_SESSION['success'] = "User status updated successfully!";
            } else {
                $_SESSION['error'] = "Cannot deactivate this user. This is the last active user in the system.";
            }
        }
        $this->redirect('user/index');
    }

    // Soft delete user (safer than hard delete)
    public function delete($id = null) {
        if ($id) {
            if ($this->userModel->softDeleteUser((int)$id, $_SESSION['user_id'] ?? 0)) {
                $audit = new UserAudit();
                $audit->log($_SESSION['user_id'] ?? 0, 'user_soft_deleted', (int)$id);

                $_SESSION['success'] = "User has been deleted (soft delete).";
            } else {
                $_SESSION['error'] = "Cannot delete this user. It may be the last active user.";
            }
        }
        $this->redirect('user/index');
    }


    // Show Menu Permission Page
    public function permission($user_id = null) {
        if (!$user_id) $this->redirect('user/index');

        $user = $this->userModel->getUserById($user_id);
        if (!$user) {
            $_SESSION['error'] = "User not found!";
            $this->redirect('user/index');
        }

        // Get all menus
        require_once '../app/models/MenuModel.php';
        $menuModel = new MenuModel();
        $menus = $menuModel->getAllMenus();

        // Get current permissions for this user
        $currentPermissions = $this->userModel->getUserPermissions($user_id);

        $data = [
            'title' => 'Menu Permissions - ' . $user['username'],
            'user' => $user,
            'menus' => $menus,
            'currentPermissions' => $currentPermissions
        ];

        $this->view('user/permission', $data);
    }

    // Save Menu Permissions
    public function save_permissions() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->validateCSRF();
            $user_id = (int)$_POST['user_id'];

            // Use the model to handle the update safely
            $result = $this->userModel->saveUserPermissions($user_id, $_POST['permissions'] ?? []);

            if ($result['status'] === 'success') {
                $audit = new UserAudit();
                $audit->logPermissionsUpdated($_SESSION['user_id'] ?? 0, $user_id);

                $_SESSION['success'] = $result['message'];
            } else {
                $_SESSION['error'] = $result['message'];
            }

            $this->redirect('user/permission/' . $user_id);
        }
    }

    /**
     * Show Change Password form for the currently logged-in user
     */
    public function change_password() {
        $data = [
            'title' => 'Change Password'
        ];
        $this->view('user/change_password', $data);
    }

    /**
     * Handle password change for the logged-in user
     */
    public function update_password() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('user/change_password');
            return;
        }

        $this->validateCSRF();

        $userId = $_SESSION['user_id'] ?? null;
        if (!$userId) {
            $this->redirect('auth/login');
            return;
        }

        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        $result = $this->userModel->changeUserPassword($userId, $current, $new, $confirm);

        if ($result['status'] === 'success') {
            $_SESSION['success'] = $result['message'];
            $this->redirect('dashboard');
        } else {
            $_SESSION['error'] = $result['message'];
            $this->redirect('user/change_password');
        }
    }

    /**
     * Simple Audit Log viewer for User-related actions
     */
    public function audit() {
        $audit = new UserAudit();
        $logs = $audit->getRecentLogs(300, 'user_'); // Filter to user-related actions

        $data = [
            'title' => 'User Audit Logs',
            'logs' => $logs
        ];

        $this->view('user/audit', $data);
    }

}