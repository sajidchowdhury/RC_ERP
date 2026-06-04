<?php
// app/controllers/EmployeeController.php

require_once '../core/BaseController.php';
require_once '../app/models/EmployeeModel.php';
require_once '../app/models/BranchModel.php';
require_once '../core/UserAudit.php';

class EmployeeController extends BaseController {

    private $model;

    public function __construct() {
        $this->requireLogin();
        $this->model = new EmployeeModel();
    }

    public function index() {
        // Handle DataTables server-side request
        if (isset($_GET['draw'])) {
            $params = $_GET;
            $params['includeDeleted'] = isset($_GET['deleted']) && $_GET['deleted'] == '1';

            $response = $this->model->getEmployeesForDataTable($params);
            header('Content-Type: application/json');
            echo json_encode($response);
            exit;
        }

        // Normal page load
        $showDeleted = isset($_GET['deleted']) && $_GET['deleted'] == '1';

        // For filters
        $branchModel = new BranchModel();
        $branches = $branchModel->getAllActiveBranches();

        $roles = ['salesman', 'warehouse_manager', 'dispatcher', 'accountant', 'manager', 'hr', 'other'];

        $data = [
            'title'       => $showDeleted ? 'Deleted employees' : 'Workforce directory',
            'showDeleted' => $showDeleted,
            'branches'    => $branches,
            'roles'       => $roles,
            'stats'       => $this->model->getEmployeeIndexStats(),
        ];
        $this->view('employee/index', $data);
    }

public function create() {
    $branchModel = new BranchModel();   // Load Branch Model

    $data = [
        'title'    => 'Create New Employee',
        'branches' => $branchModel->getAllActiveBranches(),
        'roles'    => ['salesman', 'warehouse_manager', 'dispatcher', 'accountant', 'manager', 'hr', 'other'],
    ];
    $this->view('employee/create', $data);
}
    public function store() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->validateCSRF();

            $employee_id = $this->model->createEmployee($_POST);

            if ($employee_id) {
                // Handle photo upload after employee is created (so we have the real code)
                $photoUploadFailed = false;
                if (!empty($_FILES['photo']['name'])) {
                    $employee = $this->model->getEmployeeById($employee_id);
                    if ($employee && $employee['employee_code']) {
                        $photoPath = $this->model->uploadEmployeePhoto($_FILES['photo'], $employee['employee_code']);
                        if ($photoPath) {
                            $this->model->updateEmployeePhoto($employee_id, $photoPath);
                        } else {
                            $photoUploadFailed = true;
                        }
                    }
                }

                $audit = new UserAudit();
                $audit->log($_SESSION['user_id'] ?? 0, 'employee_created', $employee_id, ['name' => $_POST['name'] ?? '']);

                if ($photoUploadFailed) {
                    $_SESSION['error'] = "Employee created, but the photo could not be uploaded (check file type/size).";
                } else {
                    $_SESSION['success'] = "Employee created successfully!";
                }
                $this->redirect('employee/index');
            } else {
                $_SESSION['error'] = "Failed to create employee!";
                $this->redirect('employee/create');
            }
        }
    }

public function edit($id = null) {
    if (!$id) $this->redirect('employee/index');

    $employee = $this->model->getEmployeeById($id);
    if (!$employee) {
        $_SESSION['error'] = "Employee not found!";
        $this->redirect('employee/index');
    }

    $branchModel = new BranchModel();

    $data = [
        'title'    => 'Edit Employee',
        'employee' => $employee,
        'branches' => $branchModel->getAllActiveBranches(),
        'roles'    => ['salesman', 'warehouse_manager', 'dispatcher', 'accountant', 'manager', 'hr', 'other'],
        'usage'    => $this->model->getEmployeeUsage((int)$id),
    ];
    $this->view('employee/edit', $data);
}

    public function update($id = null) {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $id) {
            $this->validateCSRF();

            $employee = $this->model->getEmployeeById($id);
            if (!$employee) {
                $_SESSION['error'] = "Employee not found!";
                $this->redirect('employee/index');
                return;
            }

            // Handle photo update / removal
            $removePhoto = !empty($_POST['remove_photo']);

            $photoUploadIssue = false;
            if (!empty($_FILES['photo']['name'])) {
                // Delete old photo if exists (we are replacing)
                if (!empty($employee['photo'])) {
                    $this->model->deleteEmployeePhoto($employee['photo']);
                }

                $photoPath = $this->model->uploadEmployeePhoto($_FILES['photo'], $employee['employee_code']);
                if ($photoPath) {
                    $_POST['photo'] = $photoPath;
                } else {
                    $photoUploadIssue = true;
                }
            } elseif ($removePhoto && !empty($employee['photo'])) {
                // User explicitly wants to remove current photo
                $this->model->deleteEmployeePhoto($employee['photo']);
                $_POST['photo'] = ''; // model will set to NULL because empty
            }

            if ($this->model->updateEmployee($id, $_POST)) {
                $audit = new UserAudit();
                $audit->log($_SESSION['user_id'] ?? 0, 'employee_updated', (int)$id, ['name' => $_POST['name'] ?? '']);

                if ($photoUploadIssue) {
                    $_SESSION['error'] = "Employee updated, but new photo could not be uploaded (invalid file).";
                } else {
                    $_SESSION['success'] = "Employee updated successfully!";
                }
            } else {
                $_SESSION['error'] = "Failed to update employee!";
            }
        }
        $this->redirect('employee/index');
    }

    public function toggle($id = null) {
        if ($id) {
            if ($this->model->toggleStatus($id)) {
                $audit = new UserAudit();
                $audit->log($_SESSION['user_id'] ?? 0, 'employee_status_changed', (int)$id);

                $_SESSION['success'] = "Employee status updated!";
            } else {
                // Provide helpful guidance
                $refs = $this->model->getReferenceCounts((int)$id);
                $msg = "Cannot deactivate this employee.";

                if ($refs['users'] > 0) {
                    $msg = "Cannot deactivate this employee because they have a linked user account. Please manage or deactivate the user account first.";
                }

                $_SESSION['error'] = $msg;
            }
        }
        $this->redirect('employee/index');
    }

    // Soft delete employee
    public function delete($id = null) {
        if ($id) {
            if ($this->model->softDeleteEmployee((int)$id, $_SESSION['user_id'] ?? 0)) {
                $audit = new UserAudit();
                $audit->log($_SESSION['user_id'] ?? 0, 'employee_soft_deleted', (int)$id);

                $_SESSION['success'] = "Employee has been deleted (soft delete).";
            } else {
                $refs = $this->model->getReferenceCounts((int)$id);
                $msg = "Cannot delete this employee.";

                if ($refs['users'] > 0) {
                    $msg = "Cannot delete this employee because they have a linked user account. Please handle the user first.";
                } elseif ($refs['sales_invoices'] > 0 || $refs['customers'] > 0) {
                    $msg = "Cannot delete this employee because they have historical sales or customer records.";
                }

                $_SESSION['error'] = $msg;
            }
        }
        $this->redirect('employee/index');
    }

    // Restore soft-deleted employee
    public function restore($id = null) {
        if ($id) {
            if ($this->model->restoreEmployee((int)$id)) {
                $audit = new UserAudit();
                $audit->log($_SESSION['user_id'] ?? 0, 'employee_restored', (int)$id);

                $_SESSION['success'] = "Employee has been restored.";
            } else {
                $_SESSION['error'] = "Failed to restore employee.";
            }
        }
        $this->redirect('employee/index?deleted=1');
    }

    /**
     * Simple Audit Log viewer for Employee-related actions
     */
    public function audit() {
        $audit = new UserAudit();
        $logs = $audit->getRecentLogs(300, 'employee_'); // Only employee-related actions

        $data = [
            'title' => 'Employee Audit Logs',
            'logs' => $logs
        ];

        $this->view('employee/audit', $data);
    }

    /**
     * Quick View Modal - Phase 2.1
     * Returns HTML fragment for the modal
     */
    public function quickView($id = null) {
        if (!$id) {
            http_response_code(404);
            echo "Employee not found";
            exit;
        }

        $employee = $this->model->getEmployeeById((int)$id);

        if (!$employee) {
            http_response_code(404);
            echo "Employee not found";
            exit;
        }

        // Load the modal partial
        require '../app/views/employee/partials/quick_view.php';
        exit;
    }
}