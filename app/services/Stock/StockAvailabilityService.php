<?php
// app/services/Stock/StockAvailabilityService.php — SSOT for available / pipeline / physical qty

require_once __DIR__ . '/../../../core/Database.php';

class StockAvailabilityService
{
    protected Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? new Database();
    }

    /**
     * Branch-level available (physical − pipeline on open invoices for this branch).
     */
    public function getBranchAvailableQty(int $productId, ?int $branchId = null, ?int $excludeInvoiceId = null): float
    {
        if (!$branchId) {
            $branchId = (int)($_SESSION['branch_id'] ?? 1);
        }

        $excludeSql = $this->excludeInvoiceSql($excludeInvoiceId);

        $this->db->query("
            SELECT
                GREATEST(
                    0,
                    COALESCE(SUM(ws.qty), 0) - COALESCE(SUM(d.pending_qty), 0)
                ) AS available_qty
            FROM warehouse_stock ws
            LEFT JOIN (
                SELECT
                    sid.warehouse_id,
                    sid.product_id,
                    SUM(sid.ordered_qty - sid.dispatched_qty) AS pending_qty
                FROM sales_invoice_dispatches sid
                INNER JOIN sales_invoices si ON si.id = sid.sales_invoice_id
                WHERE sid.product_id = :pid_pending
                  AND sid.ordered_qty > sid.dispatched_qty
                  AND si.status NOT IN ('challan_completed','reversed')
                  AND COALESCE(si.is_reversed, 0) = 0
                  AND si.branch_id = :bid_pending
                  {$excludeSql}
                GROUP BY sid.warehouse_id, sid.product_id
            ) d ON d.product_id = ws.product_id AND d.warehouse_id = ws.warehouse_id
            WHERE ws.product_id = :pid
              AND ws.warehouse_id IN (SELECT id FROM warehouses WHERE branch_id = :bid)
        ");

        $this->db->bind(':pid', $productId);
        $this->db->bind(':pid_pending', $productId);
        $this->db->bind(':bid', $branchId);
        $this->db->bind(':bid_pending', $branchId);
        $this->bindExcludeInvoice($excludeInvoiceId);

        $result = $this->db->single();

        return (float)($result['available_qty'] ?? 0);
    }

    /**
     * Available in one warehouse (physical − pipeline on open invoices for that warehouse's branch).
     */
    public function getWarehouseAvailableQty(int $productId, int $warehouseId, ?int $excludeInvoiceId = null): float
    {
        $excludeSql = $this->excludeInvoiceSql($excludeInvoiceId);

        $this->db->query("
            SELECT GREATEST(
                0,
                COALESCE(ws.qty, 0) - COALESCE(p.pending_qty, 0)
            ) AS available_qty
            FROM warehouse_stock ws
            LEFT JOIN (
                SELECT
                    sid.warehouse_id,
                    sid.product_id,
                    SUM(sid.ordered_qty - sid.dispatched_qty) AS pending_qty
                FROM sales_invoice_dispatches sid
                INNER JOIN sales_invoices si ON si.id = sid.sales_invoice_id
                INNER JOIN warehouses wh ON wh.id = sid.warehouse_id
                WHERE sid.warehouse_id = :wid
                  AND sid.product_id = :pid
                  AND sid.ordered_qty > sid.dispatched_qty
                  AND si.status NOT IN ('challan_completed', 'reversed')
                  AND COALESCE(si.is_reversed, 0) = 0
                  AND si.branch_id = wh.branch_id
                  {$excludeSql}
                GROUP BY sid.warehouse_id, sid.product_id
            ) p ON p.warehouse_id = ws.warehouse_id AND p.product_id = ws.product_id
            WHERE ws.product_id = :pid2
              AND ws.warehouse_id = :wid2
        ");

        $this->db->bind(':pid', $productId);
        $this->db->bind(':wid', $warehouseId);
        $this->db->bind(':pid2', $productId);
        $this->db->bind(':wid2', $warehouseId);
        $this->bindExcludeInvoice($excludeInvoiceId);

        $row = $this->db->single();

        return (float)($row['available_qty'] ?? 0);
    }

    /**
     * Per-warehouse breakdown for a branch (modal, challan picker, purchase return, etc.).
     *
     * @return list<array{id:int,warehouse_name:string,physical_qty:float,pipeline_qty:float,available_qty:float}>
     */
    public function getWarehouseWiseStock(int $productId, int $branchId, ?int $excludeInvoiceId = null): array
    {
        $excludeSql = $this->excludeInvoiceSql($excludeInvoiceId);

        $this->db->query("
            SELECT
                w.id,
                w.warehouse_name,
                COALESCE(ws.qty, 0) AS physical_qty,
                COALESCE(p.pending_qty, 0) AS pipeline_qty,
                GREATEST(
                    0,
                    COALESCE(ws.qty, 0) - COALESCE(p.pending_qty, 0)
                ) AS available_qty
            FROM warehouses w
            LEFT JOIN warehouse_stock ws
                ON ws.warehouse_id = w.id
               AND ws.product_id = :pid
            LEFT JOIN (
                SELECT
                    sid.warehouse_id,
                    sid.product_id,
                    SUM(sid.ordered_qty - sid.dispatched_qty) AS pending_qty
                FROM sales_invoice_dispatches sid
                INNER JOIN sales_invoices si ON si.id = sid.sales_invoice_id
                INNER JOIN warehouses wh ON wh.id = sid.warehouse_id
                WHERE sid.product_id = :pid2
                  AND sid.ordered_qty > sid.dispatched_qty
                  AND si.status NOT IN ('challan_completed','reversed')
                  AND COALESCE(si.is_reversed, 0) = 0
                  AND si.branch_id = wh.branch_id
                  {$excludeSql}
                GROUP BY sid.warehouse_id, sid.product_id
            ) p ON p.warehouse_id = w.id AND p.product_id = :pid3
            WHERE w.branch_id = :bid
              AND w.is_active = 1
            ORDER BY w.warehouse_name
        ");

        $this->db->bind(':pid', $productId);
        $this->db->bind(':pid2', $productId);
        $this->db->bind(':pid3', $productId);
        $this->db->bind(':bid', $branchId);
        $this->bindExcludeInvoice($excludeInvoiceId);

        $rows = $this->db->resultSet();

        return array_map(static function (array $row): array {
            $physical = (float)($row['physical_qty'] ?? 0);
            $pipeline = (float)($row['pipeline_qty'] ?? 0);
            $available = (float)($row['available_qty'] ?? 0);

            return [
                'id'              => (int)$row['id'],
                'warehouse_name'  => (string)$row['warehouse_name'],
                'physical_qty'    => $physical,
                'pipeline_qty'    => $pipeline,
                'available_qty'   => $available,
            ];
        }, $rows ?: []);
    }

    /**
     * Product search suggestions — branch available matches getBranchAvailableQty().
     *
     * @return list<array<string, mixed>>
     */
    public function searchProductsWithStock(string $term, int $branchId): array
    {
        $term = trim($term);
        if ($term === '') {
            return [];
        }

        $this->db->query("
            SELECT
                p.id,
                p.product_code,
                p.product_name,
                ph.sales_rate AS price,
                GREATEST(
                    0,
                    COALESCE(phys.physical_qty, 0) - COALESCE(pend.pending_qty, 0)
                ) AS available_qty
            FROM products p
            LEFT JOIN product_price_history ph
                ON p.id = ph.product_id
               AND ph.effective_from = (
                    SELECT MAX(effective_from)
                    FROM product_price_history
                    WHERE product_id = p.id
               )
            LEFT JOIN (
                SELECT ws.product_id, SUM(ws.qty) AS physical_qty
                FROM warehouse_stock ws
                WHERE ws.warehouse_id IN (
                    SELECT id FROM warehouses WHERE branch_id = :branch_id
                )
                GROUP BY ws.product_id
            ) phys ON phys.product_id = p.id
            LEFT JOIN (
                SELECT
                    sid.product_id,
                    SUM(sid.ordered_qty - sid.dispatched_qty) AS pending_qty
                FROM sales_invoice_dispatches sid
                INNER JOIN sales_invoices si ON si.id = sid.sales_invoice_id
                WHERE sid.product_id IS NOT NULL
                  AND sid.ordered_qty > sid.dispatched_qty
                  AND si.status NOT IN ('challan_completed', 'reversed')
                  AND COALESCE(si.is_reversed, 0) = 0
                  AND si.branch_id = :branch_id2
                GROUP BY sid.product_id
            ) pend ON pend.product_id = p.id
            WHERE (p.product_name LIKE :term OR p.product_code LIKE :term)
              AND p.is_active = 1
            ORDER BY p.product_name
            LIMIT 30
        ");
        $this->db->bind(':term', '%' . $term . '%');
        $this->db->bind(':branch_id', $branchId);
        $this->db->bind(':branch_id2', $branchId);

        return $this->db->resultSet() ?: [];
    }

    /**
     * Branch summary for sales product panel (same available as search).
     */
    public function getBranchStockSummary(int $productId, int $branchId, ?int $excludeInvoiceId = null): array
    {
        $available = $this->getBranchAvailableQty($productId, $branchId, $excludeInvoiceId);

        $this->db->query("
            SELECT COALESCE(SUM(ws.qty), 0) AS physical_qty
            FROM warehouse_stock ws
            WHERE ws.product_id = :pid
              AND ws.warehouse_id IN (SELECT id FROM warehouses WHERE branch_id = :bid)
        ");
        $this->db->bind(':pid', $productId);
        $this->db->bind(':bid', $branchId);
        $physical = (float)($this->db->single()['physical_qty'] ?? 0);
        $pipeline = max(0.0, $physical - $available);

        return [
            'branch_id'      => $branchId,
            'available_qty'  => $available,
            'physical_qty'   => $physical,
            'pipeline_qty'   => $pipeline,
        ];
    }

    private function excludeInvoiceSql(?int $excludeInvoiceId): string
    {
        return $excludeInvoiceId ? ' AND si.id != :exclude_inv ' : '';
    }

    private function bindExcludeInvoice(?int $excludeInvoiceId): void
    {
        if ($excludeInvoiceId) {
            $this->db->bind(':exclude_inv', (int)$excludeInvoiceId);
        }
    }
}