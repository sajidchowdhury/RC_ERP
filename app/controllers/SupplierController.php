<?php
// app/controllers/SupplierController.php

require_once '../core/BaseController.php';
require_once '../app/models/SupplierModel.php';
require_once '../core/UserAudit.php';

class SupplierController extends BaseController {

    private $supplierModel;
    private $userAudit;

    public function __construct() {
        $this->requireLogin();
        $this->supplierModel = new SupplierModel();
        $this->userAudit = new UserAudit();
    }

    public function index() {
        // Handle DataTables server-side request
        if (isset($_GET['draw'])) {
            $params = $_GET;
            $params['includeDeleted'] = isset($_GET['deleted']) && $_GET['deleted'] == '1';

            $response = $this->supplierModel->getSuppliersForDataTable($params);
            header('Content-Type: application/json');
            echo json_encode($response);
            exit;
        }

        // Normal page load
        $showDeleted = isset($_GET['deleted']) && $_GET['deleted'] == '1';

        $data = [
            'title'       => $showDeleted ? 'Inactive suppliers' : 'Supplier directory',
            'showDeleted' => $showDeleted,
            'stats'       => $this->supplierModel->getSupplierIndexStats(),
        ];
        $this->view('supplier/index', $data);
    }

    public function create() {
        $data = ['title' => 'Create New Supplier'];
        $this->view('supplier/create', $data);
    }

    public function store() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->validateCSRF();

            $result = $this->supplierModel->createSupplier($_POST);

            if ($result['status'] === 'success') {
                $this->userAudit->log($_SESSION['user_id'] ?? 0, 'supplier_created', null, [
                    'supplier_name' => $_POST['supplier_name'] ?? ''
                ]);

                $_SESSION['success'] = $result['message'];
                $this->redirect('supplier/index');
            } else {
                $_SESSION['error'] = $result['message'];
                $this->redirect('supplier/create');
            }
        }
    }

    public function edit($id = null) {
        if (!$id) $this->redirect('supplier/index');

        $supplier = $this->supplierModel->getSupplierById($id);
        if (!$supplier) {
            $_SESSION['error'] = "Supplier not found!";
            $this->redirect('supplier/index');
        }

        $data = [
            'title'    => 'Edit Supplier',
            'supplier' => $supplier,
            'usage'    => $this->supplierModel->getSupplierUsage((int)$id),
        ];
        $this->view('supplier/edit', $data);
    }

    public function update($id = null) {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $id) {
            $this->validateCSRF();

            $result = $this->supplierModel->updateSupplier($id, $_POST);

            if ($result['status'] === 'success') {
                $this->userAudit->log($_SESSION['user_id'] ?? 0, 'supplier_updated', (int)$id, [
                    'supplier_name' => $_POST['supplier_name'] ?? ''
                ]);

                $_SESSION['success'] = $result['message'];
            } else {
                $_SESSION['error'] = $result['message'];
            }
        }
        $this->redirect('supplier/index');
    }

    public function toggle($id = null) {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $id) {
            $this->validateCSRF();

            $supplier = $this->supplierModel->getSupplierById($id);
            if (!$supplier) {
                echo json_encode(['status' => 'error', 'message' => 'Supplier not found.']);
                exit;
            }

            $isCurrentlyActive = (int)$supplier['is_active'] === 1;

            // Safety check only when trying to deactivate
            if ($isCurrentlyActive) {
                $safety = $this->supplierModel->getDeactivationSafetyStatus((int)$id);
                if (!$safety['can_deactivate']) {
                    $msg = "Cannot deactivate this supplier.";
                    if ($safety['has_outstanding']) {
                        $msg .= " Outstanding balance: " . number_format($safety['outstanding_balance'], 2);
                    }
                    if ($safety['has_purchase_history']) {
                        $msg .= ($safety['has_outstanding'] ? ". " : " ") . "Has " . number_format($safety['purchase_count']) . " purchase record(s).";
                    }
                    $msg .= " Clear dues before changing status.";
                    echo json_encode(['status' => 'error', 'message' => $msg]);
                    exit;
                }
            }

            if ($this->supplierModel->toggleStatus($id)) {
                $this->userAudit->log($_SESSION['user_id'] ?? 0, 'supplier_status_changed', (int)$id);

                echo json_encode(['status' => 'success', 'message' => 'Supplier status updated successfully!']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Failed to update status!']);
            }
            exit;
        }
        $this->redirect('supplier/index');
    }

    /**
     * Soft delete (deactivate) a supplier
     */
    public function delete($id = null) {
        if (!$id) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid ID']);
            exit;
        }

        $this->validateCSRF();

        $safety = $this->supplierModel->getDeactivationSafetyStatus((int)$id);

        if (!$safety['can_deactivate']) {
            $msg = "Cannot deactivate this supplier.";
            if ($safety['has_outstanding']) {
                $msg .= " Outstanding balance: " . number_format($safety['outstanding_balance'], 2);
            }
            if ($safety['has_purchase_history']) {
                $msg .= ($safety['has_outstanding'] ? ". " : " ") . "Has " . number_format($safety['purchase_count']) . " purchase record(s).";
            }
            $msg .= " Please clear dues or review related purchase records before archiving.";

            echo json_encode(['status' => 'error', 'message' => $msg, 'safety' => $safety]);
            exit;
        }

        if ($this->supplierModel->softDeleteSupplier($id)) {
            $this->userAudit->log($_SESSION['user_id'] ?? 0, 'supplier_deactivated', (int)$id);

            echo json_encode(['status' => 'success', 'message' => 'Supplier deactivated successfully.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to deactivate supplier.']);
        }
        exit;
    }

    /**
     * Restore a soft-deleted supplier
     */
    public function restore($id = null) {
        if (!$id) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid ID']);
            exit;
        }

        $this->validateCSRF();

        if ($this->supplierModel->restoreSupplier($id)) {
            $this->userAudit->log($_SESSION['user_id'] ?? 0, 'supplier_restored', (int)$id);

            echo json_encode(['status' => 'success', 'message' => 'Supplier restored successfully.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to restore supplier.']);
        }
        exit;
    }

    /**
     * Audit Log viewer for Supplier-related actions
     */
    public function audit() {
        $logs = $this->userAudit->getRecentLogs(300, 'supplier_'); // Only supplier-related actions

        $data = [
            'title' => 'Supplier Audit Logs',
            'logs' => $logs
        ];

        $this->view('supplier/audit', $data);
    }
}