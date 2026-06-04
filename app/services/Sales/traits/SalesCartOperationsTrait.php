<?php
// app/services/Sales/traits/SalesCartOperationsTrait.php — Phase 6 (extracted from SalesModel)

trait SalesCartOperationsTrait
{
    public function addToCart($data) { 

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $customer_id = $data['customer_id'] ?? 0;
    $product_id  = $data['product_id'] ?? 0;

    if (!$customer_id) {
        return ['status' => 'error', 'message' => 'Customer required'];
    }

    $qty  = isset($data['qty']) ? (float)$data['qty'] : 0;
    $rate = isset($data['rate']) ? (float)$data['rate'] : 0;

    if ($qty <= 0 || $rate <= 0) {
        return ['status' => 'error', 'message' => 'Invalid qty or rate'];
    }

    $cartKey = 'sales_draft_carts';

    if (!isset($_SESSION[$cartKey][$customer_id])) {
        $_SESSION[$cartKey][$customer_id] = [];
    }

    // Update if exists
    foreach ($_SESSION[$cartKey][$customer_id] as &$item) {
        if ($item['product_id'] == $product_id) {
            $item['qty'] += $qty;
            $item['total'] = $item['qty'] * $item['rate'];
            $this->persistDraftCartToDb((int)$customer_id, $_SESSION[$cartKey][$customer_id]);
            return ['status' => 'success', 'message' => 'Quantity updated'];
        }
    }

    // Add new
    $_SESSION[$cartKey][$customer_id][] = [
        'product_id'   => $product_id,
        'product_name' => htmlspecialchars($data['product_name'] ?? ''),
        'qty'          => $qty,
        'rate'         => $rate,
        'total'        => $qty * $rate
    ];

    $this->persistDraftCartToDb((int)$customer_id, $_SESSION[$cartKey][$customer_id]);

    return ['status' => 'success', 'message' => 'Item added to cart'];


    }          

    public function loadCart($customer_id) {
        $this->hydrateSessionCartFromDb((int)$customer_id);
        $cartKey = 'sales_draft_carts';
        $items = $_SESSION[$cartKey][$customer_id] ?? [];
        $subtotal = array_sum(array_column($items, 'total'));
        return ['items' => $items, 'subtotal' => $subtotal];
    }

    /**
     * List all open session draft carts (multi-customer POS).
     */
    public function listDraftCarts(): array
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $carts = $_SESSION['sales_draft_carts'] ?? [];
        $result = [];

        foreach ($carts as $customerId => $items) {
            $customerId = (int)$customerId;
            if ($customerId <= 0 || empty($items)) {
                continue;
            }

            $cust = $this->Get_Customer_By_Id($customerId);
            $shop = $cust['shop_name'] ?? '';
            $name = $cust['customer_name'] ?? '';
            $mobile = $cust['mobile'] ?? '';
            $label = trim($shop ?: $name);
            if ($mobile) {
                $label = $label ? "{$label} · {$mobile}" : $mobile;
            }
            if ($label === '') {
                $label = "Customer #{$customerId}";
            }

            $subtotal = array_sum(array_column($items, 'total'));
            $result[] = [
                'customer_id' => $customerId,
                'label'       => $label,
                'shop_name'   => $shop,
                'customer_name' => $name,
                'mobile'      => $mobile,
                'item_count'  => count($items),
                'subtotal'    => round($subtotal, 2),
            ];
        }

        usort($result, fn($a, $b) => ($b['item_count'] <=> $a['item_count']));

        return $result;
    }


 public function deleteFromCart($data) {
        $customer_id = $data['customer_id'] ?? 0;
        $index = $data['index'] ?? -1;
        if (!$customer_id || $index < 0) return ['status' => 'error', 'message' => 'Invalid data'];

        $cartKey = 'sales_draft_carts';
        if (isset($_SESSION[$cartKey][$customer_id][$index])) {
            unset($_SESSION[$cartKey][$customer_id][$index]);
            $_SESSION[$cartKey][$customer_id] = array_values($_SESSION[$cartKey][$customer_id]); // re-index
            $this->persistDraftCartToDb((int)$customer_id, $_SESSION[$cartKey][$customer_id]);
            return ['status' => 'success', 'message' => 'Item removed'];
        }
        return ['status' => 'error', 'message' => 'Item not found'];
    }

    public function updateCartItem($data) {
        $customer_id = $data['customer_id'] ?? 0;
        $index = $data['index'] ?? -1;
        $qty = floatval($data['qty'] ?? 0);
        $rate = floatval($data['rate'] ?? 0);
        if (!$customer_id || $index < 0 || $qty <= 0) return ['status' => 'error'];

        $cartKey = 'sales_draft_carts';
        if (isset($_SESSION[$cartKey][$customer_id][$index])) {
            $_SESSION[$cartKey][$customer_id][$index]['qty'] = $qty;
            $_SESSION[$cartKey][$customer_id][$index]['rate'] = $rate;
            $_SESSION[$cartKey][$customer_id][$index]['total'] = $qty * $rate;
            $this->persistDraftCartToDb((int)$customer_id, $_SESSION[$cartKey][$customer_id]);
            return ['status' => 'success'];
        }
        return ['status' => 'error'];
    }

    // Clear session cart for a specific customer
    public function clearTabCart($data)
    {
        $customer_id = $data['customer_id'] ?? 0;
        
        if ($customer_id && isset($_SESSION['sales_draft_carts'][$customer_id])) {
            unset($_SESSION['sales_draft_carts'][$customer_id]);
        }
        $this->removeDraftCartFromDb((int)$customer_id);

        return ['status' => 'success'];
    }

    /** Alias used by SalesController::delete_tab_cart */
    public function deleteTabCart($data)
    {
        return $this->clearTabCart($data);
    }

    protected function usesDbDraftCarts(): bool
    {
        return defined('SALES_DB_DRAFT_CARTS') && SALES_DB_DRAFT_CARTS;
    }

    protected function dbDraftCartTableExists(): bool
    {
        try {
            $this->db->query("SHOW TABLES LIKE 'sales_draft_carts'");
            return (bool)$this->db->single();
        } catch (Throwable $e) {
            return false;
        }
    }

    protected function persistDraftCartToDb(int $customerId, array $items): void
    {
        if (!$this->usesDbDraftCarts() || !$this->dbDraftCartTableExists() || $customerId <= 0) {
            return;
        }

        $userId = (int)($_SESSION['user_id'] ?? 0);
        $branchId = (int)($_SESSION['branch_id'] ?? 0);
        if ($userId <= 0) {
            return;
        }

        $json = json_encode(array_values($items), JSON_UNESCAPED_UNICODE);
        $this->db->query("
            INSERT INTO sales_draft_carts (user_id, branch_id, customer_id, items_json)
            VALUES (:uid, :bid, :cid, :json)
            ON DUPLICATE KEY UPDATE items_json = VALUES(items_json), branch_id = VALUES(branch_id)
        ");
        $this->db->bind(':uid', $userId);
        $this->db->bind(':bid', $branchId);
        $this->db->bind(':cid', $customerId);
        $this->db->bind(':json', $json);
        $this->db->execute();
    }

    protected function removeDraftCartFromDb(int $customerId): void
    {
        if (!$this->usesDbDraftCarts() || !$this->dbDraftCartTableExists() || $customerId <= 0) {
            return;
        }

        $userId = (int)($_SESSION['user_id'] ?? 0);
        if ($userId <= 0) {
            return;
        }

        $this->db->query("
            DELETE FROM sales_draft_carts
            WHERE user_id = :uid AND customer_id = :cid
        ");
        $this->db->bind(':uid', $userId);
        $this->db->bind(':cid', $customerId);
        $this->db->execute();
    }

    protected function hydrateSessionCartFromDb(int $customerId): void
    {
        if (!$this->usesDbDraftCarts() || !$this->dbDraftCartTableExists() || $customerId <= 0) {
            return;
        }

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!empty($_SESSION['sales_draft_carts'][$customerId])) {
            return;
        }

        $userId = (int)($_SESSION['user_id'] ?? 0);
        if ($userId <= 0) {
            return;
        }

        $this->db->query("
            SELECT items_json FROM sales_draft_carts
            WHERE user_id = :uid AND customer_id = :cid
            LIMIT 1
        ");
        $this->db->bind(':uid', $userId);
        $this->db->bind(':cid', $customerId);
        $row = $this->db->single();
        if (!$row || empty($row['items_json'])) {
            return;
        }

        $items = json_decode($row['items_json'], true);
        if (is_array($items) && $items !== []) {
            $_SESSION['sales_draft_carts'][$customerId] = $items;
        }
    }

}
