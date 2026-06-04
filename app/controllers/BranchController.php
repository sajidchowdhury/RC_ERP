<?php
// app/controllers/BranchController.php

require_once '../core/BaseController.php';
require_once '../app/models/BranchModel.php';
require_once '../core/UserAudit.php';

class BranchController extends BaseController {

    private $branchModel;

    public function __construct() {
        $this->requireLogin();
        $this->branchModel = new BranchModel();
    }

    public function index() {
        // Handle DataTables server-side request
        if (isset($_GET['draw'])) {
            $params = $_GET;
            $params['includeDeleted'] = isset($_GET['deleted']) && $_GET['deleted'] == '1';

            $response = $this->branchModel->getBranchesForDataTable($params);
            header('Content-Type: application/json');
            echo json_encode($response);
            exit;
        }

        // Normal page load
        $showDeleted = isset($_GET['deleted']) && $_GET['deleted'] == '1';

        $data = [
            'title'       => 'Branch Management',
            'showDeleted' => $showDeleted,
            'stats'       => $this->branchModel->getBranchIndexStats(),
        ];
        $this->view('branch/index', $data);
    }

    public function create() {
        $data = ['title' => 'Create New Branch'];
        $this->view('branch/create', $data);
    }

    public function store() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->validateCSRF();

            if ($this->branchModel->createBranch($_POST)) {
                $audit = new UserAudit();
                $audit->log($_SESSION['user_id'] ?? 0, 'branch_created', null, [
                    'branch_name' => $_POST['branch_name'] ?? ''
                ]);

                $_SESSION['success'] = "Branch created successfully!";
                $this->redirect('branch/index');
            } else {
                $_SESSION['error'] = "Failed to create branch!";
                $this->redirect('branch/create');
            }
        }
    }

    public function edit($id = null) {
        if (!$id) $this->redirect('branch/index');

        $branch = $this->branchModel->getBranchById($id);
        if (!$branch) {
            $_SESSION['error'] = "Branch not found!";
            $this->redirect('branch/index');
        }

        $data = [
            'title'  => 'Edit Branch',
            'branch' => $branch,
            'usage'  => $this->branchModel->getBranchUsage((int)$id),
        ];
        $this->view('branch/edit', $data);
    }

    public function update($id = null) {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $id) {
            $this->validateCSRF();

            if ($this->branchModel->updateBranch($id, $_POST)) {
                $audit = new UserAudit();
                $audit->log($_SESSION['user_id'] ?? 0, 'branch_updated', (int)$id, [
                    'branch_name' => $_POST['branch_name'] ?? ''
                ]);

                $_SESSION['success'] = "Branch updated successfully!";
            } else {
                $_SESSION['error'] = "Failed to update branch!";
            }
        }
        $this->redirect('branch/index');
    }

    public function toggle($id = null) {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $id) {
            $this->validateCSRF();

            $branch = $this->branchModel->getBranchById($id);
            if (!$branch) {
                echo json_encode(['status' => 'error', 'message' => 'Branch not found.']);
                exit;
            }

            $isCurrentlyActive = $branch['is_active'];

            // Safety check before deactivating
            if ($isCurrentlyActive) {
                if (!$this->branchModel->canDeactivateBranch($id)) {
                    $usage = $this->branchModel->getBranchUsage($id);
                    $message = "Cannot deactivate this branch. ";
                    if ($usage['warehouses'] > 0) {
                        $message .= "It has {$usage['warehouses']} active warehouse(s). ";
                    }
                    if ($usage['employees'] > 0) {
                        $message .= "It has {$usage['employees']} active employee(s). ";
                    }
                    $message .= "Please reassign or deactivate them first.";

                    echo json_encode(['status' => 'error', 'message' => $message]);
                    exit;
                }
            }

            if ($this->branchModel->toggleStatus($id)) {
                // Fetch updated status for accurate logging
                $updatedBranch = $this->branchModel->getBranchById($id);
                $newStatus = $updatedBranch ? ($updatedBranch['is_active'] ? 'activated' : 'deactivated') : 'changed';

                $audit = new UserAudit();
                $audit->log($_SESSION['user_id'] ?? 0, 'branch_status_changed', (int)$id, [
                    'new_status' => $newStatus
                ]);

                echo json_encode(['status' => 'success', 'message' => 'Branch status updated!']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Failed to update status!']);
            }
            exit;
        }
        $this->redirect('branch/index');
    }

    /**
     * Audit Log viewer for Branch-related actions
     */
    public function audit() {
        $audit = new UserAudit();
        $logs = $audit->getRecentLogs(300, 'branch_'); // Only branch-related actions

        $data = [
            'title' => 'Branch Audit Logs',
            'logs' => $logs
        ];

        $this->view('branch/audit', $data);
    }

}