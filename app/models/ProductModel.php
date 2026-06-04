<?php
// app/models/ProductModel.php

require_once '../core/Database.php';
require_once __DIR__ . '/../helpers/Helper.php';

class ProductModel extends Helper{

    protected $db;

    public function __construct() {
        $this->db = new Database();
    }

    // Auto generate next product code (RC-0001, RC-0002, ...)
    public function generateProductCode() {
        $this->db->query("SELECT MAX(CAST(SUBSTRING(product_code, 4) AS UNSIGNED)) as last_num FROM products");
        $row = $this->db->single();
        $next = ($row['last_num'] ?? 0) + 1;
        return 'P-' . str_pad($next, 4, '0', STR_PAD_LEFT);
    }

    public function getAll() {
    
        return $this->Get_All_Active_Product();
    }

    public function getById($id) {
      
        return $this->Get_Product_By_Id($id);
    }

    // Check if product can be safely deleted
    public function canDelete($id) {
        // Check if used anywhere
        $tables = [
            'stock_transactions' => 'product_id',
            'sales_invoice_items' => 'product_id',
            'purchase_receive_items' => 'product_id',
            'branch_demand_items' => 'product_id',
            'product_price_history' => 'product_id'
        ];

        foreach ($tables as $table => $column) {
            $this->db->query("SELECT COUNT(*) as total FROM $table WHERE $column = :id");
            $this->db->bind(':id', $id);
            $count = $this->db->single()['total'] ?? 0;
            if ($count > 0) return false;
        }
        return true;
    }

    public function create($data) {
        $this->db->query("INSERT INTO products 
            (product_code, product_name, category_id, unit, pcs_per_carton, safety_stock, image, created_by) 
            VALUES (:code, :name, :cat, :unit, :pcs, :safety, :image, :created_by)");
        
        $this->db->bind(':code', $data['product_code']);
        $this->db->bind(':name', $data['product_name']);
        $this->db->bind(':cat', !empty($data['category_id']) ? $data['category_id'] : null);
        $this->db->bind(':unit', $data['unit']);
        $this->db->bind(':pcs', $data['pcs_per_carton'] ?? 0);
        $this->db->bind(':safety', $data['safety_stock'] ?? 0);
        $this->db->bind(':image', $data['image'] ?? null);
        $this->db->bind(':created_by', $_SESSION['employee_id'] ?? 1);

        return $this->db->execute();
    }


    public function deletePriceHistory($price_id) {
    $this->db->query("DELETE FROM product_price_history WHERE id = :id");
    $this->db->bind(':id', $price_id);
    return $this->db->execute();
}


    public function update($id, $data) {
        $sql = "UPDATE products SET 
            product_name = :name,
            category_id = :cat,
            unit = :unit,
            pcs_per_carton = :pcs,
            safety_stock = :safety";

        if (isset($data['image'])) {
            $sql .= ", image = :image";
        }

        $sql .= " WHERE id = :id";

        $this->db->query($sql);

        $this->db->bind(':name', $data['product_name']);
        $this->db->bind(':cat', !empty($data['category_id']) ? $data['category_id'] : null);
        $this->db->bind(':unit', $data['unit']);
        $this->db->bind(':pcs', $data['pcs_per_carton'] ?? 0);
        $this->db->bind(':safety', $data['safety_stock'] ?? 0);
        $this->db->bind(':id', $id);

        if (isset($data['image'])) {
            $this->db->bind(':image', $data['image']);
        }

        return $this->db->execute();
    }



    public function delete($id) {
        if (!$this->canDelete($id)) {
            return false; // blocked
        }
        $this->db->query("UPDATE products SET is_active = 0 WHERE id = :id");
        $this->db->bind(':id', $id);
        return $this->db->execute();
    }

    /**
     * Restore a soft-deleted product
     */
    public function restore($id) {
        $this->db->query("UPDATE products SET is_active = 1 WHERE id = :id");
        $this->db->bind(':id', $id);
        return $this->db->execute();
    }

    // Price History
    public function addPriceHistory($product_id, $sales_rate) {
        $this->db->query("INSERT INTO product_price_history 
            (product_id, effective_from, sales_rate, created_by) 
            VALUES (:pid, NOW(), :rate, :created_by)");
        
        $this->db->bind(':pid', $product_id);
        $this->db->bind(':rate', $sales_rate);
        $this->db->bind(':created_by', $_SESSION['employee_id'] ?? 1);
        return $this->db->execute();
    }

    public function getPriceHistory($product_id) {
        $this->db->query("SELECT * FROM product_price_history 
                          WHERE product_id = :pid 
                          ORDER BY effective_from DESC");
        $this->db->bind(':pid', $product_id);
        return $this->db->resultSet();
    }

    public function getCategories() {
        return $this->Get_All_Categories();
    }

    /**
     * Upload product image securely
     */
    public function uploadProductImage(array $file): ?string
    {
        if (empty($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
            return null;
        }

        $maxSize = 2 * 1024 * 1024; // 2MB
        if ($file['size'] > $maxSize) {
            return null;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        $allowedMimes = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp'
        ];

        if (!isset($allowedMimes[$mime])) {
            return null;
        }

        $uploadDir = __DIR__ . '/../../public/uploads/products/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $extension = $allowedMimes[$mime];
        $filename = 'prod_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
        $destination = $uploadDir . $filename;

        if (move_uploaded_file($file['tmp_name'], $destination)) {
            // Verify it's a real image
            $imgInfo = @getimagesize($destination);
            if ($imgInfo === false) {
                @unlink($destination);
                return null;
            }
            return 'uploads/products/' . $filename;
        }

        return null;
    }

    public function deleteProductImage(?string $imagePath): void
    {
        if (!$imagePath) return;

        $fullPath = __DIR__ . '/../../public/' . $imagePath;
        if (file_exists($fullPath)) {
            @unlink($fullPath);
        }
    }

    /**
     * Hero metrics for product catalog index.
     */
    public function getProductIndexStats(): array
    {
        $stats = [
            'active'     => 0,
            'inactive'   => 0,
            'categories' => 0,
            'with_stock' => 0,
        ];

        $this->db->query('SELECT COUNT(*) AS c FROM products WHERE is_active = 1');
        $stats['active'] = (int)($this->db->single()['c'] ?? 0);

        $this->db->query('SELECT COUNT(*) AS c FROM products WHERE is_active = 0');
        $stats['inactive'] = (int)($this->db->single()['c'] ?? 0);

        $this->db->query('SELECT COUNT(*) AS c FROM product_categories WHERE is_active = 1');
        $stats['categories'] = (int)($this->db->single()['c'] ?? 0);

        $this->db->query('
            SELECT COUNT(DISTINCT ws.product_id) AS c
            FROM warehouse_stock ws
            INNER JOIN products p ON p.id = ws.product_id AND p.is_active = 1
            WHERE ws.qty > 0.0001
        ');
        $stats['with_stock'] = (int)($this->db->single()['c'] ?? 0);

        return $stats;
    }

    /**
     * Stock and latest price for product edit sidebar.
     */
    public function getProductStockAndPrice(int $productId): array
    {
        $this->db->query('
            SELECT
                COALESCE((SELECT SUM(ws.qty) FROM warehouse_stock ws WHERE ws.product_id = :id), 0) AS total_stock,
                COALESCE((
                    SELECT ph.sales_rate FROM product_price_history ph
                    WHERE ph.product_id = :id2
                    ORDER BY ph.effective_from DESC LIMIT 1
                ), 0) AS current_price
        ');
        $this->db->bind(':id', $productId);
        $this->db->bind(':id2', $productId);
        $row = $this->db->single() ?: [];

        return [
            'total_stock'   => (float)($row['total_stock'] ?? 0),
            'current_price' => (float)($row['current_price'] ?? 0),
        ];
    }

    /**
     * Metrics for category index.
     */
    public function getCategoryIndexStats(): array
    {
        $this->db->query('SELECT COUNT(*) AS c FROM product_categories WHERE is_active = 1');
        $active = (int)($this->db->single()['c'] ?? 0);
        $this->db->query('SELECT COUNT(*) AS c FROM product_categories WHERE is_active = 0');
        $inactive = (int)($this->db->single()['c'] ?? 0);
        $this->db->query('SELECT COUNT(*) AS c FROM products WHERE is_active = 1');
        $products = (int)($this->db->single()['c'] ?? 0);

        return ['active' => $active, 'inactive' => $inactive, 'products' => $products];
    }

    /**
     * Server-side DataTables for Products
     */
    public function getProductsForDataTable(array $params): array
    {
        $start          = (int)($params['start'] ?? 0);
        $length         = (int)($params['length'] ?? 25);
        $searchValue    = trim($params['search']['value'] ?? '');
        $orderColumn    = (int)($params['order'][0]['column'] ?? 0);
        $orderDir       = $params['order'][0]['dir'] ?? 'asc';

        // Custom filters
        $filterCategory = $params['filterCategory'] ?? '';
        $filterUnit     = $params['filterUnit'] ?? '';
        $includeDeleted = !empty($params['includeDeleted']);

        $columns = [
            'p.product_code', 
            'p.product_name', 
            'c.category_name', 
            'p.unit',
            'total_stock',
            'current_price'
        ];

        $baseQuery = "
            FROM products p
            LEFT JOIN product_categories c ON p.category_id = c.id
        ";

        $where = [];
        $bindParams = [];

        if (!$includeDeleted) {
            $where[] = "p.is_active = 1";
        }

        // Global search
        if ($searchValue !== '') {
            $where[] = "(p.product_name LIKE :search OR p.product_code LIKE :search)";
            $bindParams[':search'] = "%{$searchValue}%";
        }

        // Custom filters
        if ($filterCategory) {
            $where[] = "p.category_id = :category";
            $bindParams[':category'] = $filterCategory;
        }

        if ($filterUnit) {
            $where[] = "p.unit = :unit";
            $bindParams[':unit'] = $filterUnit;
        }

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        // Total records
        $totalQuery = "SELECT COUNT(p.id) as total {$baseQuery}";
        if (!$includeDeleted) {
            $totalQuery .= " WHERE p.is_active = 1";
        }
        $this->db->query($totalQuery);
        $totalResult = $this->db->single();
        $recordsTotal = $totalResult['total'] ?? 0;

        // Filtered records
        $filteredQuery = "SELECT COUNT(p.id) as total {$baseQuery} {$whereSql}";
        $this->db->query($filteredQuery);
        foreach ($bindParams as $key => $val) {
            $this->db->bind($key, $val);
        }
        $filteredResult = $this->db->single();
        $recordsFiltered = $filteredResult['total'] ?? 0;

        // Data query
        $orderBy = $columns[$orderColumn] ?? 'p.product_name';

        $dataQuery = "
            SELECT 
                p.id, 
                p.product_code, 
                p.product_name, 
                p.unit, 
                p.is_active,
                p.image,
                c.category_name,
                
                -- Total stock across all branches/warehouses 
                -- Single Source of Truth: warehouse_stock table (updated via StockTransactionModel)
                COALESCE((
                    SELECT SUM(ws.qty) 
                    FROM warehouse_stock ws 
                    WHERE ws.product_id = p.id
                ), 0) as total_stock,
                
                -- Latest selling price (from price history)
                COALESCE((
                    SELECT ph.sales_rate 
                    FROM product_price_history ph 
                    WHERE ph.product_id = p.id 
                    ORDER BY ph.effective_from DESC 
                    LIMIT 1
                ), 0) as current_price
                
            {$baseQuery}
            {$whereSql}
            ORDER BY {$orderBy} {$orderDir}
            LIMIT {$start}, {$length}
        ";

        $this->db->query($dataQuery);
        foreach ($bindParams as $key => $val) {
            $this->db->bind($key, $val);
        }
        $data = $this->db->resultSet();

        return [
            'draw'            => (int)($params['draw'] ?? 1),
            'recordsTotal'    => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data'            => $data
        ];
    }

    // =====================================================
    // CATEGORY MANAGEMENT (Phase 3 - Advanced)
    // =====================================================

    /**
     * Server-side DataTables for Categories
     */
    public function getCategoriesForDataTable(array $params): array
    {
        $start          = (int)($params['start'] ?? 0);
        $length         = (int)($params['length'] ?? 25);
        $searchValue    = trim($params['search']['value'] ?? '');
        $orderColumn    = (int)($params['order'][0]['column'] ?? 0);
        $orderDir       = $params['order'][0]['dir'] ?? 'asc';

        $includeDeleted = !empty($params['includeDeleted']);

        $columns = ['c.category_name'];

        $baseQuery = "FROM product_categories c";

        $where = [];
        $bindParams = [];

        if (!$includeDeleted) {
            $where[] = "c.is_active = 1";
        }

        if ($searchValue !== '') {
            $where[] = "c.category_name LIKE :search";
            $bindParams[':search'] = "%{$searchValue}%";
        }

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        // Total records
        $totalQuery = "SELECT COUNT(c.id) as total {$baseQuery}";
        if (!$includeDeleted) {
            $totalQuery .= " WHERE c.is_active = 1";
        }
        $this->db->query($totalQuery);
        $totalResult = $this->db->single();
        $recordsTotal = $totalResult['total'] ?? 0;

        // Filtered records
        $filteredQuery = "SELECT COUNT(c.id) as total {$baseQuery} {$whereSql}";
        $this->db->query($filteredQuery);
        foreach ($bindParams as $key => $val) {
            $this->db->bind($key, $val);
        }
        $filteredResult = $this->db->single();
        $recordsFiltered = $filteredResult['total'] ?? 0;

        // Data query
        $orderBy = $columns[$orderColumn] ?? 'c.category_name';
        $dataQuery = "
            SELECT 
                c.id, 
                c.category_name, 
                c.is_active,
                (SELECT COUNT(*) FROM products WHERE category_id = c.id AND is_active = 1) as product_count
            {$baseQuery}
            {$whereSql}
            ORDER BY {$orderBy} {$orderDir}
            LIMIT {$start}, {$length}
        ";

        $this->db->query($dataQuery);
        foreach ($bindParams as $key => $val) {
            $this->db->bind($key, $val);
        }
        $data = $this->db->resultSet();

        return [
            'draw'            => (int)($params['draw'] ?? 1),
            'recordsTotal'    => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data'            => $data
        ];
    }

    public function createCategory($name) {
        $this->db->query("INSERT INTO product_categories (category_name, is_active) VALUES (:name, 1)");
        $this->db->bind(':name', trim($name));
        return $this->db->execute();
    }

    public function updateCategory($id, $name) {
        $this->db->query("UPDATE product_categories SET category_name = :name WHERE id = :id");
        $this->db->bind(':name', trim($name));
        $this->db->bind(':id', $id);
        return $this->db->execute();
    }

    public function softDeleteCategory($id) {
        // Prevent deleting if active products are using it
        if (!$this->canDeleteCategory($id)) {
            return false;
        }
        $this->db->query("UPDATE product_categories SET is_active = 0 WHERE id = :id");
        $this->db->bind(':id', $id);
        return $this->db->execute();
    }

    public function restoreCategory($id) {
        $this->db->query("UPDATE product_categories SET is_active = 1 WHERE id = :id");
        $this->db->bind(':id', $id);
        return $this->db->execute();
    }

    public function canDeleteCategory($id) {
        $this->db->query("SELECT COUNT(*) as total FROM products WHERE category_id = :id AND is_active = 1");
        $this->db->bind(':id', $id);
        $result = $this->db->single();
        return ($result['total'] ?? 0) == 0;
    }

    public function getCategoryById($id) {
        $this->db->query("SELECT * FROM product_categories WHERE id = :id");
        $this->db->bind(':id', $id);
        return $this->db->single();
    }

    // =====================================================
    // BULK ACTIONS (Phase 3)
    // =====================================================

    public function bulkDeactivate(array $ids) {
        if (empty($ids)) return false;

        // Filter only deletable products
        $deletableIds = [];
        foreach ($ids as $id) {
            if ($this->canDelete($id)) {
                $deletableIds[] = (int)$id;
            }
        }

        if (empty($deletableIds)) return false;

        $placeholders = implode(',', array_fill(0, count($deletableIds), '?'));
        $this->db->query("UPDATE products SET is_active = 0 WHERE id IN ($placeholders)");
        
        foreach ($deletableIds as $i => $id) {
            $this->db->bind($i, $id);
        }

        return $this->db->execute();
    }

    public function bulkRestore(array $ids) {
        if (empty($ids)) return false;

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $this->db->query("UPDATE products SET is_active = 1 WHERE id IN ($placeholders)");

        foreach ($ids as $i => $id) {
            $this->db->bind($i, (int)$id);
        }

        return $this->db->execute();
    }
}
?>