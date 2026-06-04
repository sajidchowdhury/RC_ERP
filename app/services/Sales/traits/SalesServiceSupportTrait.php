<?php
// app/services/Sales/traits/SalesServiceSupportTrait.php — shared stock validation

require_once __DIR__ . '/../../Stock/StockService.php';

trait SalesServiceSupportTrait
{
    protected ?StockService $stockService = null;

    protected function stock(): StockService
    {
        if ($this->stockService === null) {
            $this->stockService = new StockService($this->db);
        }

        return $this->stockService;
    }

    protected function validateCartStockAvailability(array $items, int $branch_id, ?int $excludeInvoiceId = null): array
    {
        $qtyByProduct = [];
        foreach ($items as $item) {
            $pid = (int)($item['product_id'] ?? 0);
            if ($pid <= 0) {
                continue;
            }
            $qtyByProduct[$pid] = ($qtyByProduct[$pid] ?? 0) + (float)($item['qty'] ?? 0);
        }

        return $this->stock()->assertBranchProductsAvailable($branch_id, $qtyByProduct, $excludeInvoiceId);
    }

    protected function getCustomerRunningBalance($customer_id)
    {
        return $this->Get_Customer_Now_Due($customer_id);
    }
}
