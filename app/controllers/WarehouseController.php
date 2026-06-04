<?php
// app/controllers/WarehouseController.php

require_once '../core/BaseController.php';
require_once '../app/models/WarehouseModel.php';
require_once '../app/models/BranchModel.php';
require_once '../core/UserAudit.php';

class WarehouseController extends BaseController {

    private $warehouseModel;
    private $branchModel;

    public function __construct() {
        $this->requireLogin();
        $this->warehouseModel = new WarehouseModel();
        $this->branchModel = new BranchModel();
    }

    public function index() {
        // Handle DataTables server-side request
        if (isset($_GET['draw'])) {
            $params = $_GET;
            $params['includeDeleted'] = isset($_GET['deleted']) && $_GET['deleted'] == '1';

            $response = $this->warehouseModel->getWarehousesForDataTable($params);
            header('Content-Type: application/json');
            echo json_encode($response);
            exit;
        }

        // Normal page load
        $showDeleted = isset($_GET['deleted']) && $_GET['deleted'] == '1';

        $branches = $this->branchModel->getAllActiveBranches();

        $data = [
            'title'       => 'Warehouse Management',
            'showDeleted' => $showDeleted,
            'branches'    => $branches,
            'stats'       => $this->warehouseModel->getWarehouseIndexStats(),
        ];
        $this->view('warehouse/index', $data);
    }

    public function create() {
        $branches = $this->branchModel->getAllActiveBranches();
        $data = [
            'title' => 'Create New Warehouse',
            'branches' => $branches
        ];
        $this->view('warehouse/create', $data);
    }

    public function store() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->validateCSRF();

            if ($this->warehouseModel->createWarehouse($_POST)) {
                $audit = new UserAudit();
                $audit->log($_SESSION['user_id'] ?? 0, 'warehouse_created', null, [
                    'warehouse_name' => $_POST['warehouse_name'] ?? ''
                ]);

                $_SESSION['success'] = "Warehouse created successfully!";
                $this->redirect('warehouse/index');
            } else {
                $_SESSION['error'] = "Failed to create warehouse!";
                $this->redirect('warehouse/create');
            }
        }
    }

    public function edit($id = null) {
        if (!$id) $this->redirect('warehouse/index');

        $warehouse = $this->warehouseModel->getWarehouseById($id);
        if (!$warehouse) {
            $_SESSION['error'] = "Warehouse not found!";
            $this->redirect('warehouse/index');
        }

        $branches = $this->branchModel->getAllActiveBranches();

        $data = [
            'title'     => 'Edit Warehouse',
            'warehouse' => $warehouse,
            'branches'  => $branches,
            'usage'     => $this->warehouseModel->getWarehouseUsage((int)$id),
        ];
        $this->view('warehouse/edit', $data);
    }

    public function update($id = null) {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $id) {
            $this->validateCSRF();

            if ($this->warehouseModel->updateWarehouse($id, $_POST)) {
                $audit = new UserAudit();
                $audit->log($_SESSION['user_id'] ?? 0, 'warehouse_updated', (int)$id, [
                    'warehouse_name' => $_POST['warehouse_name'] ?? ''
                ]);

                $_SESSION['success'] = "Warehouse updated successfully!";
            } else {
                $_SESSION['error'] = "Failed to update warehouse!";
            }
        }
        $this->redirect('warehouse/index');
    }

    public function toggle($id = null) {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $id) {
            $this->validateCSRF();

            $warehouse = $this->warehouseModel->getWarehouseById($id);
            if (!$warehouse) {
                echo json_encode(['status' => 'error', 'message' => 'Warehouse not found.']);
                exit;
            }

            $isCurrentlyActive = $warehouse['is_active'];

            // Safety check: Prevent deactivating warehouse with stock
            if ($isCurrentlyActive) {
                if ($this->warehouseModel->hasStock($id)) {
                    $stockCount = $this->warehouseModel->getWarehouseStockCount($id);
                    $message = "Cannot deactivate this warehouse. It still has " . number_format($stockCount, 2) . " units of stock. Please move or adjust the stock first.";
                    echo json_encode(['status' => 'error', 'message' => $message]);
                    exit;
                }
            }

            if ($this->warehouseModel->toggleStatus($id)) {
                $audit = new UserAudit();
                $audit->log($_SESSION['user_id'] ?? 0, 'warehouse_status_changed', (int)$id);

                echo json_encode(['status' => 'success', 'message' => 'Warehouse status updated!']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Failed to update status!']);
            }
            exit;
        }
        $this->redirect('warehouse/index');
    }
}