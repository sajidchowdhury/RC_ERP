<?php
// app/controllers/CustomerController.php

require_once '../core/BaseController.php';
require_once '../app/models/CustomerModel.php';
require_once '../app/models/EmployeeModel.php';
require_once '../core/UserAudit.php';

class CustomerController extends BaseController {

    private $customerModel;
    private $employeeModel;
    private $userAudit;

    public function __construct() {
        $this->requireLogin();
        $this->customerModel = new CustomerModel();
        $this->employeeModel = new EmployeeModel();
        $this->userAudit = new UserAudit();
    }

    public function index() {
        // Handle DataTables server-side request
        if (isset($_GET['draw'])) {
            $params = $_GET;
            $params['includeDeleted'] = isset($_GET['deleted']) && $_GET['deleted'] == '1';

            $response = $this->customerModel->getCustomersForDataTable($params);
            header('Content-Type: application/json');
            echo json_encode($response);
            exit;
        }

        // Normal page load
        $showDeleted = isset($_GET['deleted']) && $_GET['deleted'] == '1';

        $salesPersons = $this->employeeModel->getAllEmployees(); // For filter dropdown

        $data = [
            'title'        => $showDeleted ? 'Inactive customers' : 'Customer directory',
            'showDeleted'  => $showDeleted,
            'salesPersons' => $salesPersons,
            'stats'        => $this->customerModel->getCustomerIndexStats(),
        ];
        $this->view('customer/index', $data);
    }

    public function create() {
        $salesPersons = $this->employeeModel->getAllEmployees(); // Or filter by role if needed
        $data = [
            'title' => 'Create New Customer',
            'salesPersons' => $salesPersons
        ];
        $this->view('customer/create', $data);
    }

    public function store() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->validateCSRF();

            $result = $this->customerModel->createCustomer($_POST);

            if ($result['status'] === 'success') {
                $this->userAudit->log($_SESSION['user_id'] ?? 0, 'customer_created', null, [
                    'customer_name' => $_POST['customer_name'] ?? '',
                    'shop_name' => $_POST['shop_name'] ?? ''
                ]);

                $_SESSION['success'] = $result['message'];
                $this->redirect('customer/index');
            } else {
                $_SESSION['error'] = $result['message'];
                $this->redirect('customer/create');
            }
        }
    }

    public function edit($id = null) {
        if (!$id) $this->redirect('customer/index');

        $customer = $this->customerModel->getCustomerById($id);
        if (!$customer) {
            $_SESSION['error'] = "Customer not found!";
            $this->redirect('customer/index');
        }

        $salesPersons = $this->employeeModel->getAllEmployees();

        $data = [
            'title'        => 'Edit Customer',
            'customer'     => $customer,
            'salesPersons' => $salesPersons,
            'usage'        => $this->customerModel->getCustomerUsage((int)$id),
        ];
        $this->view('customer/edit', $data);
    }

    public function update($id = null) {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $id) {
            $this->validateCSRF();

            $result = $this->customerModel->updateCustomer($id, $_POST);

            if ($result['status'] === 'success') {
                $this->userAudit->log($_SESSION['user_id'] ?? 0, 'customer_updated', (int)$id, [
                    'customer_name' => $_POST['customer_name'] ?? '',
                    'shop_name' => $_POST['shop_name'] ?? '',
                    'mobile' => $_POST['mobile'] ?? ''
                ]);

                $_SESSION['success'] = $result['message'];
            } else {
                $_SESSION['error'] = $result['message'];
            }
        }
        $this->redirect('customer/index');
    }

    public function toggle($id = null) {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $id) {
            $this->validateCSRF();

            $customer = $this->customerModel->getCustomerById($id);
            if (!$customer) {
                echo json_encode(['status' => 'error', 'message' => 'Customer not found.']);
                exit;
            }

            $isCurrentlyActive = (int)$customer['is_active'] === 1;

            // Safety check only when trying to deactivate
            if ($isCurrentlyActive) {
                $safety = $this->customerModel->getDeactivationSafetyStatus((int)$id);
                if (!$safety['can_deactivate']) {
                    $msg = "Cannot deactivate this customer.";
                    if ($safety['has_outstanding']) {
                        $msg .= " Outstanding balance: " . number_format($safety['outstanding_balance'], 2);
                    }
                    if ($safety['has_sales_history']) {
                        $msg .= ($safety['has_outstanding'] ? ". " : " ") . "Has " . number_format($safety['sales_count']) . " sales record(s).";
                    }
                    $msg .= " Clear dues before changing status.";
                    echo json_encode(['status' => 'error', 'message' => $msg]);
                    exit;
                }
            }

            if ($this->customerModel->toggleStatus($id)) {
                $this->userAudit->log($_SESSION['user_id'] ?? 0, 'customer_status_changed', (int)$id);

                echo json_encode(['status' => 'success', 'message' => 'Customer status updated!']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Failed to update status!']);
            }
            exit;
        }
        $this->redirect('customer/index');
    }

    /**
     * Soft delete (deactivate) a customer
     * Includes safety checks for outstanding balance and sales history.
     */
    public function delete($id = null) {
        if (!$id) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid ID']);
            exit;
        }

        $this->validateCSRF();

        $safety = $this->customerModel->getDeactivationSafetyStatus((int)$id);

        if (!$safety['can_deactivate']) {
            $msg = "Cannot deactivate this customer.";
            if ($safety['has_outstanding']) {
                $msg .= " Outstanding balance: " . number_format($safety['outstanding_balance'], 2);
            }
            if ($safety['has_sales_history']) {
                $msg .= ($safety['has_outstanding'] ? ". " : " ") . "Has " . number_format($safety['sales_count']) . " sales record(s).";
            }
            $msg .= " Please clear all dues or review related records before archiving.";

            echo json_encode(['status' => 'error', 'message' => $msg, 'safety' => $safety]);
            exit;
        }

        if ($this->customerModel->softDeleteCustomer($id)) {
            $this->userAudit->log($_SESSION['user_id'] ?? 0, 'customer_deactivated', (int)$id);

            echo json_encode(['status' => 'success', 'message' => 'Customer deactivated successfully.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to deactivate customer.']);
        }
        exit;
    }

    /**
     * Restore a soft-deleted customer
     */
    public function restore($id = null) {
        if (!$id) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid ID']);
            exit;
        }

        $this->validateCSRF();

        if ($this->customerModel->restoreCustomer($id)) {
            $this->userAudit->log($_SESSION['user_id'] ?? 0, 'customer_restored', (int)$id);

            echo json_encode(['status' => 'success', 'message' => 'Customer restored successfully.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to restore customer.']);
        }
        exit;
    }

    /**
     * Audit Log viewer for Customer-related actions
     */
    public function audit() {
        $logs = $this->userAudit->getRecentLogs(300, 'customer_'); // Only customer-related actions

        $data = [
            'title' => 'Customer Audit Logs',
            'logs' => $logs
        ];

        $this->view('customer/audit', $data);
    }
}