<?php
// app/controllers/ProductController.php

require_once '../core/BaseController.php';
require_once '../app/models/ProductModel.php';
require_once '../core/UserAudit.php';

class ProductController extends BaseController {

    private $model;
    private $userAudit;

    public function __construct() {
        $this->requireLogin();
        $this->model = new ProductModel();
        $this->userAudit = new UserAudit();
    }

    public function index() {
        // Handle DataTables server-side request
        if (isset($_GET['draw'])) {
            $params = $_GET;
            $params['includeDeleted'] = isset($_GET['deleted']) && $_GET['deleted'] == '1';

            $response = $this->model->getProductsForDataTable($params);
            header('Content-Type: application/json');
            echo json_encode($response);
            exit;
        }

        // Normal page load
        $showDeleted = isset($_GET['deleted']) && $_GET['deleted'] == '1';

        $categories = $this->model->getCategories();
        $units = ['Pcs', 'Carton', 'KG', 'Bag', 'Dobe', 'Set'];

        $data = [
            'title'       => 'Product Management',
            'showDeleted' => $showDeleted,
            'categories'  => $categories,
            'units'       => $units,
            'stats'       => $this->model->getProductIndexStats(),
            'publicUrl'   => defined('PUBLIC_URL') ? PUBLIC_URL : BASE_URL,
        ];
        $this->view('products/index', $data);
    }

    public function create() {
        $categories = $this->model->getCategories();
        $data = [
            'title'      => 'Create New Product',
            'categories' => $categories,
            'units'      => ['Pcs', 'Carton', 'KG', 'Bag', 'Dobe', 'Set'],
            'publicUrl'  => defined('PUBLIC_URL') ? PUBLIC_URL : BASE_URL,
        ];
        $this->view('products/create', $data);
    }




public function edit($id = null) {
    if (!$id) {
        $this->redirect('product');
    }

    $product = $this->model->getById($id);
    if (!$product) {
        $this->abortPage('Product not found.', 'product');
    }

    $categories = $this->model->getCategories();
    $snapshot = $this->model->getProductStockAndPrice((int)$id);
    $data = [
        'title'      => 'Edit Product',
        'product'    => $product,
        'categories' => $categories,
        'snapshot'   => $snapshot,
        'publicUrl'  => defined('PUBLIC_URL') ? PUBLIC_URL : BASE_URL,
        'units'      => ['Pcs', 'Carton', 'KG', 'Bag', 'Dobe', 'Set'],
    ];
    $this->view('products/edit', $data);
}



    public function update($id) {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->validateCSRF();

            $product = $this->model->getById($id);
            if (!$product) {
                $_SESSION['error'] = "Product not found!";
                $this->redirect('product');
                return;
            }

            $data = [
                'product_name' => trim($_POST['product_name']),
                'category_id'  => $_POST['category_id'],
                'unit'         => $_POST['unit'],
                'pcs_per_carton' => $_POST['pcs_per_carton'] ?? 0,
                'safety_stock'   => $_POST['safety_stock'] ?? 0
            ];

            // Handle image replacement
            $imageUploadFailed = false;
            if (!empty($_FILES['image']['name'])) {
                // Delete old image if exists
                if (!empty($product['image'])) {
                    $this->model->deleteProductImage($product['image']);
                }

                $imagePath = $this->model->uploadProductImage($_FILES['image']);
                if ($imagePath) {
                    $data['image'] = $imagePath;
                } else {
                    $imageUploadFailed = true;
                }
            }

            if ($this->model->update($id, $data)) {
                $audit = new UserAudit();
                $audit->log($_SESSION['user_id'] ?? 0, 'product_updated', (int)$id, [
                    'product_name' => $data['product_name']
                ]);

                if ($imageUploadFailed) {
                    $_SESSION['error'] = "Product updated, but new image upload failed (invalid file type or too large).";
                } else {
                    $_SESSION['success'] = "Product updated successfully!";
                }
                $this->redirect('product');
            }
        }
    }



public function price_history($id = null) {
    if (!$id) {
        $this->redirect('product');
    }

    $product = $this->model->getById($id);
    if (!$product) {
        $this->abortPage('Product not found.', 'product');
    }

    $history = $this->model->getPriceHistory($id);

    $data = [
        'title' => 'Price History - ' . $product['product_name'],
        'product' => $product,
        'history' => $history
    ];

    $this->view('products/price_history', $data);
}

// Add this new method to add price
public function add_price($id = null) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $id) {
        $this->validateCSRF();

        $sales_rate = $_POST['sales_rate'] ?? 0;

        if ($sales_rate > 0) {
            $this->model->addPriceHistory($id, $sales_rate);

           $this->userAudit->log($_SESSION['user_id'] ?? 0, 'product_price_added', (int)$id, [
                'sales_rate' => $sales_rate
            ]);

            $this->redirect('product/price_history/' . $id . '?success=1');
        }
        $this->redirect('product/price_history/' . $id);
    }
}
   
public function store() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $this->validateCSRF();

        $model = new ProductModel();

        $data = [
            'product_code'   => $model->generateProductCode(),
            'product_name'   => trim($_POST['product_name']),
            'category_id'    => $_POST['category_id'],
            'unit'           => $_POST['unit'],
            'pcs_per_carton' => $_POST['pcs_per_carton'] ?? 0,
            'safety_stock'   => $_POST['safety_stock'] ?? 0
        ];

        // Handle image upload
        $imageUploadFailed = false;
        if (!empty($_FILES['image']['name'])) {
            $imagePath = $model->uploadProductImage($_FILES['image']);
            if ($imagePath) {
                $data['image'] = $imagePath;
            } else {
                $imageUploadFailed = true;
            }
        }

        if ($this->model->create($data)) {
            $audit = new UserAudit();
            $audit->log($_SESSION['user_id'] ?? 0, 'product_created', null, [
                'product_name' => $data['product_name'],
                'product_code' => $data['product_code']
            ]);

            if ($imageUploadFailed) {
                $_SESSION['error'] = "Product created, but image upload failed (invalid file type or too large).";
            } else {
                $_SESSION['success'] = "Product created successfully!";
            }
            $this->redirect('product');
        }
    }
}


public function delete_price($price_id = null) {
    if ($price_id && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $this->validateCSRF();

        $this->model->deletePriceHistory($price_id);

        $this->userAudit->log($_SESSION['user_id'] ?? 0, 'product_price_deleted', null, [
            'price_history_id' => (int)$price_id
        ]);

        echo json_encode(['status' => 'success']);
        exit;
    }
    echo json_encode(['status' => 'error']);
    exit;
}

public function delete($id = null) {
    if (!$id) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid ID']);
        exit;
    }

    $this->validateCSRF();

    if ($this->model->delete($id)) {
        $this->userAudit->log($_SESSION['user_id'] ?? 0, 'product_deactivated', (int)$id);

        echo json_encode([
            'status' => 'success',
            'message' => 'Product deactivated successfully'
        ]);
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'Cannot deactivate this product! It has been used in transactions.'
        ]);
    }
    exit;
}

    /**
     * Restore a soft-deleted product
     */
    public function restore($id = null) {
        if (!$id) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid ID']);
            exit;
        }

        $this->validateCSRF();

        if ($this->model->restore($id)) {
            $this->userAudit->log($_SESSION['user_id'] ?? 0, 'product_restored', (int)$id);

            echo json_encode([
                'status' => 'success',
                'message' => 'Product restored successfully'
            ]);
        } else {
            echo json_encode([
                'status' => 'error',
                'message' => 'Failed to restore product'
            ]);
        }
        exit;
    }

    /**
     * Audit Log viewer for Product-related actions
     */
    public function audit() {
        $logs = $this->userAudit->getRecentLogs(300, 'product_'); // Only product-related actions

        $data = [
            'title' => 'Product Audit Logs',
            'logs' => $logs
        ];

        $this->view('products/audit', $data);
    }

    // =====================================================
    // CATEGORY MANAGEMENT (Phase 3 - Advanced)
    // =====================================================

    public function categories() {
        if (isset($_GET['draw'])) {
            $params = $_GET;
            $params['includeDeleted'] = isset($_GET['deleted']) && $_GET['deleted'] == '1';

            $response = $this->model->getCategoriesForDataTable($params);
            header('Content-Type: application/json');
            echo json_encode($response);
            exit;
        }

        $showDeleted = isset($_GET['deleted']) && $_GET['deleted'] == '1';

        $data = [
            'title'       => 'Category Management',
            'showDeleted' => $showDeleted,
            'stats'       => $this->model->getCategoryIndexStats(),
        ];
        $this->view('products/categories/index', $data);
    }

    public function categoryCreate() {
        $data = ['title' => 'Create Category'];
        $this->view('products/categories/create', $data);
    }

    public function categoryStore() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->validateCSRF();

            $name = trim($_POST['category_name'] ?? '');

            if (empty($name)) {
                $_SESSION['error'] = "Category name is required.";
                $this->redirect('product/categoryCreate');
                return;
            }

            if ($this->model->createCategory($name)) {
                $this->userAudit->log($_SESSION['user_id'] ?? 0, 'product_category_created', null, ['category_name' => $name]);

                $_SESSION['success'] = "Category created successfully!";
                $this->redirect('product/categories');
            } else {
                $_SESSION['error'] = "Failed to create category.";
                $this->redirect('product/categoryCreate');
            }
        }
    }

    public function categoryEdit($id = null) {
        if (!$id) {
            $this->redirect('product/categories');
        }

        $category = $this->model->getCategoryById($id);
        if (!$category) {
            $_SESSION['error'] = "Category not found.";
            $this->redirect('product/categories');
            return;
        }

        $data = [
            'title'    => 'Edit Category',
            'category' => $category
        ];
        $this->view('products/categories/edit', $data);
    }

    public function categoryUpdate($id = null) {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $id) {
            $this->validateCSRF();

            $name = trim($_POST['category_name'] ?? '');

            if (empty($name)) {
                $_SESSION['error'] = "Category name is required.";
                $this->redirect('product/categoryEdit/' . $id);
                return;
            }

            if ($this->model->updateCategory($id, $name)) {
                $this->userAudit->log($_SESSION['user_id'] ?? 0, 'product_category_updated', (int)$id, ['category_name' => $name]);

                $_SESSION['success'] = "Category updated successfully!";
                $this->redirect('product/categories');
            } else {
                $_SESSION['error'] = "Failed to update category.";
                $this->redirect('product/categoryEdit/' . $id);
            }
        }
    }

    public function categoryDelete($id = null) {
        if (!$id) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid ID']);
            exit;
        }

        $this->validateCSRF();

        if ($this->model->softDeleteCategory($id)) {
            $this->userAudit->log($_SESSION['user_id'] ?? 0, 'product_category_deactivated', (int)$id);

            echo json_encode(['status' => 'success', 'message' => 'Category deactivated successfully.']);
        } else {
            echo json_encode([
                'status' => 'error',
                'message' => 'Cannot deactivate this category because it is still assigned to active products.'
            ]);
        }
        exit;
    }

    public function categoryRestore($id = null) {
        if (!$id) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid ID']);
            exit;
        }

        $this->validateCSRF();

        if ($this->model->restoreCategory($id)) {
            $this->userAudit->log($_SESSION['user_id'] ?? 0, 'product_category_restored', (int)$id);

            echo json_encode(['status' => 'success', 'message' => 'Category restored successfully.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to restore category.']);
        }
        exit;
    }

    // =====================================================
    // BULK ACTIONS (Phase 3)
    // =====================================================

    public function bulkAction() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
            exit;
        }

        $this->validateCSRF();

        $action = $_POST['action'] ?? '';
        $ids = isset($_POST['ids']) ? (array)$_POST['ids'] : [];

        if (empty($ids) || empty($action)) {
            echo json_encode(['status' => 'error', 'message' => 'No items selected or invalid action']);
            exit;
        }

        // Sanitize IDs
        $ids = array_map('intval', $ids);
        $ids = array_filter($ids);

        $success = false;
        $message = '';

        switch ($action) {
            case 'deactivate':
                $success = $this->model->bulkDeactivate($ids);
                if ($success) {
                     $this->userAudit->log($_SESSION['user_id'] ?? 0, 'product_bulk_deactivated', null, ['count' => count($ids)]);
                    $message = count($ids) . ' product(s) deactivated successfully.';
                } else {
                    $message = 'Some products could not be deactivated (they may have active transactions or other restrictions).';
                }
                break;

            case 'restore':
                $success = $this->model->bulkRestore($ids);
                if ($success) {
                    $this->userAudit->log($_SESSION['user_id'] ?? 0, 'product_bulk_restored', null, ['count' => count($ids)]);
                    $message = count($ids) . ' product(s) restored successfully.';
                } else {
                    $message = 'Failed to restore some products.';
                }
                break;

            default:
                $message = 'Invalid bulk action.';
        }

        echo json_encode([
            'status' => $success ? 'success' : 'error',
            'message' => $message
        ]);
        exit;
    }

}
?>