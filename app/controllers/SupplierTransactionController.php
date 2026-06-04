<?php
// app/controllers/SupplierTransactionController.php

require_once '../core/BaseController.php';
require_once '../app/models/SupplierTransactionModel.php';
require_once '../app/helpers/Helper.php';
require_once '../core/UserAudit.php';

class SupplierTransactionController extends BaseController {

    private SupplierTransactionModel $model;
    private UserAudit $userAudit;

    public function __construct() {
        $this->requireLogin();
        $this->model = new SupplierTransactionModel();
        $this->userAudit = new UserAudit();
    }

    public function index() {
        $branchId = Helper::sessionBranchId() ?: (int)($_SESSION['branch_id'] ?? 0);
        $today = date('Y-m-d');

        if (!isset($_GET['date_from']) && !isset($_GET['date_to'])) {
            $dateFrom = $today;
            $dateTo = $today;
        } else {
            $dateFrom = trim((string)($_GET['date_from'] ?? '')) ?: null;
            $dateTo = trim((string)($_GET['date_to'] ?? '')) ?: null;
            if ($dateFrom === null && $dateTo === null) {
                $dateFrom = $today;
                $dateTo = $today;
            }
        }

        $filters = [
            'date_from'        => $dateFrom,
            'date_to'          => $dateTo,
            'transaction_type' => $_GET['transaction_type'] ?? 'all',
            'status'           => $_GET['status'] ?? 'all',
            'payment_mode'     => $_GET['payment_mode'] ?? 'all',
            'supplier_id'      => $_GET['supplier_id'] ?? null,
        ];

        $listBranchId = ($this->model->canOverrideBranch() && $branchId <= 0) ? null : ($branchId ?: null);
        $transactions = $this->model->getFilteredTransactions($filters, $listBranchId);

        foreach ($transactions as &$row) {
            $row['can_reverse'] = $this->model->canUserReversePayment($row);
        }
        unset($row);

        $this->view('Accounting/supplier/index', [
            'title'        => 'Supplier payments',
            'transactions' => $transactions,
            'branch_name'  => $_SESSION['branch_name'] ?? 'Branch',
            'filters'      => $filters,
            'stats'        => $this->model->getSupplierTransactionIndexStats($listBranchId),
            'suppliers'    => $this->model->getSuppliers(),
        ]);
    }

    public function create() {
        $this->view('Accounting/supplier/create', [
            'title'       => 'New supplier payment',
            'suppliers'   => $this->model->getSuppliers(),
            'banks'       => $this->model->getBanks(),
            'employees'   => $this->model->getEmployeesForUser(),
            'today'       => date('Y-m-d'),
            'branch_name' => $_SESSION['branch_name'] ?? 'Branch',
        ]);
    }

    public function store() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->sendJson(['status' => 'error', 'message' => 'Invalid request'], 405);
            return;
        }

        try {
            $this->validateCSRF();
            $result = $this->model->createTransaction($_POST);

            if (($result['status'] ?? '') === 'success') {
                $this->userAudit->log($_SESSION['user_id'] ?? 0, 'supplier_transaction_created', (int)($result['payment_id'] ?? 0), [
                    'payment_code'     => $result['payment_code'] ?? '',
                    'transaction_type' => $_POST['transaction_type'] ?? 'payment',
                    'amount'           => $_POST['amount'] ?? 0,
                    'supplier_id'      => $_POST['supplier_id'] ?? 0,
                    'journal_entry_id' => $result['journal_entry_id'] ?? null,
                ]);
            }

            $this->sendJson($result);
        } catch (Throwable $e) {
            error_log('SupplierTransaction store: ' . $e->getMessage());
            $this->sendJson([
                'status'  => 'error',
                'message' => $this->safeClientMessage($e, 'Could not save transaction. Please try again.'),
            ], 500);
        }
    }

    public function get_due() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->sendJson(['status' => 'error', 'message' => 'Invalid request'], 405);
            return;
        }

        $supplier_id = (int)($_POST['supplier_id'] ?? 0);
        $due = $this->model->getSupplierDue($supplier_id);

        $this->sendJson([
            'status'        => 'success',
            'due_balance'   => $due,
            'due_formatted' => number_format($due, 2),
        ]);
    }

    public function reverse() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->sendJson(['status' => 'error', 'message' => 'Invalid request'], 405);
            return;
        }

        try {
            $this->validateCSRF();
            $id = (int)($_POST['id'] ?? 0);
            $reason = trim((string)($_POST['reason'] ?? $_POST['reverse_reason'] ?? ''));

            if ($id <= 0) {
                $this->sendJson(['status' => 'error', 'message' => 'Payment id is required']);
                return;
            }

            $result = $this->model->reverseTransaction($id, $reason);

            if (($result['status'] ?? '') === 'success') {
                $this->userAudit->log($_SESSION['user_id'] ?? 0, 'supplier_transaction_reversed', $id, ['reason' => $reason]);
                $result['redirect_url'] = BASE_URL . 'SupplierTransaction/details/' . $id;
            }

            $this->sendJson($result);
        } catch (Throwable $e) {
            error_log('SupplierTransaction reverse: ' . $e->getMessage());
            $this->sendJson([
                'status'  => 'error',
                'message' => $this->safeClientMessage($e, 'Could not reverse transaction. Please try again.'),
            ], 500);
        }
    }

    public function slip($id = null) {
        if (!$id) {
            $this->redirect('SupplierTransaction');
            return;
        }

        $transaction = $this->model->getTransactionById((int)$id);
        if (!$transaction || !$this->model->userCanAccessPayment($transaction)) {
            $_SESSION['error'] = 'Transaction not found!';
            $this->redirect('SupplierTransaction');
            return;
        }

        $this->view('Accounting/supplier/slip', [
            'title'       => 'Supplier payment slip',
            'transaction' => $transaction,
        ]);
    }

    public function details($id = null) {
        if (!$id) {
            $this->redirect('SupplierTransaction');
            return;
        }

        $transaction = $this->model->getTransactionById((int)$id);
        if (!$transaction || !$this->model->userCanAccessPayment($transaction)) {
            $_SESSION['error'] = 'Transaction not found!';
            $this->redirect('SupplierTransaction');
            return;
        }

        $paymentId = (int)$id;

        $this->view('Accounting/supplier/details', [
            'title'         => 'Payment — ' . ($transaction['payment_code'] ?? ''),
            'transaction'   => $transaction,
            'ledger'        => $this->model->getLedgerEntriesForPayment($paymentId),
            'journal_entry' => $this->model->getJournalEntryForPayment($paymentId),
            'supplier_due'  => $this->model->getSupplierDue((int)$transaction['supplier_id']),
            'can_reverse'   => $this->model->canUserReversePayment($transaction),
        ]);
    }
}