<?php
// app/controllers/BankController.php

require_once '../core/BaseController.php';
require_once '../app/models/BankModel.php';
require_once '../app/models/BankLedgerMappingModel.php';
require_once '../app/models/LedgerModel.php';
require_once '../core/UserAudit.php';

class BankController extends BaseController {

    private $bankModel;
    private $userAudit;

    public function __construct() {
        $this->requireLogin();
        $this->bankModel = new BankModel();
        $this->userAudit = new UserAudit();
    }

    public function index() {
        // Handle DataTables server-side request
        if (isset($_GET['draw'])) {
            $params = $_GET;
            $params['includeDeleted'] = isset($_GET['deleted']) && $_GET['deleted'] == '1';

            $response = $this->bankModel->getBanksForDataTable($params);
            header('Content-Type: application/json');
            echo json_encode($response);
            exit;
        }

        // Normal page load
        $showDeleted = isset($_GET['deleted']) && $_GET['deleted'] == '1';

        $data = [
            'title'       => $showDeleted ? 'Inactive bank accounts' : 'Bank accounts',
            'showDeleted' => $showDeleted,
            'stats'       => $this->bankModel->getBankIndexStats(),
        ];
        $this->view('bank/index', $data);
    }

    public function create() {
        $data = ['title' => 'New bank account'];
        $this->view('bank/create', $data);
    }

    public function store() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->validateCSRF();

            if ($this->bankModel->createBank($_POST)) {
                $this->userAudit->log($_SESSION['user_id'] ?? 0, 'bank_created', null, [
                    'bank_name' => $_POST['bank_name'] ?? ''
                ]);

                $_SESSION['success'] = "Bank account created successfully!";
                $this->redirect('bank/index');
            } else {
                $_SESSION['error'] = "Failed to create bank account!";
                $this->redirect('bank/create');
            }
        }
    }

    public function edit($id = null) {
        if (!$id) $this->redirect('bank/index');

        $bank = $this->bankModel->getById($id);
        if (!$bank) {
            $_SESSION['error'] = "Bank account not found!";
            $this->redirect('bank/index');
        }

        $mappingModel = new BankLedgerMappingModel();
        $ledgerModel = new LedgerModel();

        $data = [
            'title' => 'Edit Bank Account',
            'bank'  => $bank,
            'usage' => $this->bankModel->getBankUsage((int)$id),
            'gl_ledger_id' => $mappingModel->getLedgerIdForBank((int)$id),
            'bank_gl_ledgers' => $ledgerModel->getLedgersByNature('cash_bank'),
            'bank_mapping_enabled' => $mappingModel->tableExists(),
        ];
        $this->view('bank/edit', $data);
    }

    public function update($id = null) {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $id) {
            $this->validateCSRF();

            if ($this->bankModel->updateBank($id, $_POST)) {
                $mappingModel = new BankLedgerMappingModel();
                if ($mappingModel->tableExists() && isset($_POST['gl_ledger_id'])) {
                    $glId = (int)$_POST['gl_ledger_id'];
                    if ($glId > 0) {
                        $mappingModel->saveMapping((int)$id, $glId);
                    }
                }

                $this->userAudit->log($_SESSION['user_id'] ?? 0, 'bank_updated', (int)$id, [
                    'bank_name' => $_POST['bank_name'] ?? ''
                ]);

                $_SESSION['success'] = "Bank account updated successfully!";
            } else {
                $_SESSION['error'] = "Failed to update bank account!";
            }
        }
        $this->redirect('bank/index');
    }

    public function toggle($id = null) {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $id) {
            $this->validateCSRF();

            if ($this->bankModel->toggleStatus($id)) {
                $this->userAudit->log($_SESSION['user_id'] ?? 0, 'bank_status_changed', (int)$id);

                echo json_encode(['status' => 'success', 'message' => 'Bank status updated successfully!']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Failed to update status!']);
            }
            exit;
        }
        $this->redirect('bank/index');
    }

    /**
     * Soft delete (deactivate) a bank
     */
    public function delete($id = null) {
        if (!$id) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid ID']);
            exit;
        }

        $this->validateCSRF();

        if ($this->bankModel->softDeleteBank($id)) {
            $this->userAudit->log($_SESSION['user_id'] ?? 0, 'bank_deactivated', (int)$id);

            echo json_encode(['status' => 'success', 'message' => 'Bank deactivated successfully.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to deactivate bank.']);
        }
        exit;
    }

    /**
     * Restore a soft-deleted bank
     */
    public function restore($id = null) {
        if (!$id) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid ID']);
            exit;
        }

        $this->validateCSRF();

        if ($this->bankModel->restoreBank($id)) {
            $this->userAudit->log($_SESSION['user_id'] ?? 0, 'bank_restored', (int)$id);

            echo json_encode(['status' => 'success', 'message' => 'Bank restored successfully.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to restore bank.']);
        }
        exit;
    }

    /**
     * Audit Log viewer for Bank-related actions
     */
    public function audit() {
        $logs = $this->userAudit->getRecentLogs(300, 'bank_');

        $data = [
            'title' => 'Bank Audit Logs',
            'logs' => $logs
        ];

        $this->view('bank/audit', $data);
    }
}