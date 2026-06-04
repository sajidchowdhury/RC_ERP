<?php
// app/controllers/LedgerController.php

require_once '../core/BaseController.php';
require_once '../app/models/LedgerModel.php';
require_once '../core/UserAudit.php';

class LedgerController extends BaseController {

    private $ledgerModel;
    private $userAudit;

    public function __construct() {
        $this->requireLogin();
        $this->ledgerModel = new LedgerModel();
        $this->userAudit = new UserAudit();
    }

    public function index() {
        // Handle DataTables server-side request
        if (isset($_GET['draw'])) {
            $params = $_GET;

            // Support "Show Inactive" mode
            if (isset($_GET['inactive']) && $_GET['inactive'] == '1') {
                $params['showInactive'] = true;
            }

            $response = $this->ledgerModel->getLedgersForDataTable($params);
            header('Content-Type: application/json');
            echo json_encode($response);
            exit;
        }

        // Normal page load
        $showInactive = isset($_GET['inactive']) && $_GET['inactive'] == '1';

        $data = [
            'title'        => $showInactive ? 'Inactive ledgers' : 'Chart of Accounts',
            'showInactive' => $showInactive,
            'stats'        => $this->ledgerModel->getLedgerIndexStats(),
        ];

        $this->view('ledger/index', $data);
    }

    public function create() {
        $ledgers = $this->ledgerModel->getAllLedgers(); // for parent dropdown
        $data = [
            'title' => 'Create New Ledger Head',
            'ledgers' => $ledgers
        ];
        $this->view('ledger/create', $data);
    }

    public function store() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->validateCSRF();

            if ($this->ledgerModel->createLedger($_POST)) {
                $this->userAudit->log($_SESSION['user_id'] ?? 0, 'ledger_created', null, [
                    'ledger_name' => $_POST['ledger_name'] ?? ''
                ]);
                $_SESSION['success'] = "Ledger created successfully!";
                $this->redirect('ledger/index');
            } else {
                $_SESSION['error'] = "Failed to create ledger!";
                $this->redirect('ledger/create');
            }
        }
    }

    public function edit($id = null) {
        if (!$id) $this->redirect('ledger/index');

        $ledger = $this->ledgerModel->getLedgerById($id);
        if (!$ledger) {
            $_SESSION['error'] = "Ledger not found!";
            $this->redirect('ledger/index');
        }

        $ledgers = $this->ledgerModel->getAllLedgers();
        $isSystem = !empty($ledger['is_system']);

        $data = [
            'title'    => 'Edit ledger',
            'ledger'   => $ledger,
            'ledgers'  => $ledgers,
            'isSystem' => $isSystem,
            'usage'    => $this->ledgerModel->getLedgerUsage((int)$id),
        ];
        $this->view('ledger/edit', $data);
    }

    public function update($id = null) {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $id) {
            $this->validateCSRF();

            if ($this->ledgerModel->isSystemLedger($id)) {
                $_SESSION['error'] = "System ledgers cannot be modified.";
                $this->redirect('ledger/index');
                return;
            }

            if ($this->ledgerModel->updateLedger($id, $_POST)) {
                $this->userAudit->log($_SESSION['user_id'] ?? 0, 'ledger_updated', (int)$id);
                $_SESSION['success'] = "Ledger updated successfully!";
            } else {
                $_SESSION['error'] = "Failed to update ledger!";
            }
        }
        $this->redirect('ledger/index');
    }

    public function toggle($id = null) {
        if ($id) {
            if ($this->ledgerModel->isSystemLedger($id)) {
                $_SESSION['error'] = "System ledgers cannot be deactivated.";
                $this->redirect('ledger/index');
                return;
            }

            if ($this->ledgerModel->toggleStatus($id)) {
                $this->userAudit->log($_SESSION['user_id'] ?? 0, 'ledger_status_changed', (int)$id);
                $_SESSION['success'] = "Ledger status updated!";
            } else {
                $_SESSION['error'] = "Failed to update status!";
            }
        }
        $this->redirect('ledger/index');
    }

    /**
     * Delete / soft-delete a ledger.
     * System ledgers are always protected.
     * For non-system ledgers we currently advise using deactivate (toggle).
     * This method exists for route completeness and future hard/soft delete with safety checks.
     */
    public function delete($id = null) {
        if (!$id) {
            $_SESSION['error'] = "Invalid ledger ID.";
            $this->redirect('ledger/index');
            return;
        }

        if ($this->ledgerModel->isSystemLedger($id)) {
            $msg = "System ledgers cannot be deleted. They are required for accounting integrity.";
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                header('Content-Type: application/json');
                echo json_encode(['status' => 'error', 'message' => $msg]);
                exit;
            }
            $_SESSION['error'] = $msg;
            $this->redirect('ledger/index');
            return;
        }

        // Non-system: for safety, do not hard-delete (journals may reference).
        // Soft deactivate instead, or inform to use toggle.
        if ($this->ledgerModel->softDeleteLedger($id)) {
            $this->userAudit->log($_SESSION['user_id'] ?? 0, 'ledger_deleted', (int)$id, [
                'via' => 'delete_action'
            ]);
            $msg = "Ledger deactivated (soft delete).";
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                header('Content-Type: application/json');
                echo json_encode(['status' => 'success', 'message' => $msg]);
                exit;
            }
            $_SESSION['success'] = $msg;
        } else {
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                header('Content-Type: application/json');
                echo json_encode(['status' => 'error', 'message' => 'Failed to delete ledger.']);
                exit;
            }
            $_SESSION['error'] = "Failed to delete ledger.";
        }
        $this->redirect('ledger/index');
    }

    public function audit() {
        $logs = $this->userAudit->getRecentLogs(300, 'ledger_');

        $data = [
            'title' => 'Ledger Audit Logs',
            'logs'  => $logs
        ];

        $this->view('ledger/audit', $data);
    }
}