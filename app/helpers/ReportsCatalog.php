<?php
// app/helpers/ReportsCatalog.php — Report metadata for hub & premium frames

class ReportsCatalog
{
    /** @var array<string, array>|null */
    private static ?array $byId = null;

    public static function all(): array
    {
        if (self::$byId === null) {
            self::$byId = [];
            foreach (self::categories() as $cat) {
                foreach ($cat['reports'] as $r) {
                    $r['category_id'] = $cat['id'];
                    $r['category_label'] = $cat['label'];
                    $r['category_icon'] = $cat['icon'];
                    $r['category_accent'] = $cat['accent'];
                    self::$byId[$r['id']] = $r;
                }
            }
        }

        return self::$byId;
    }

    public static function get(string $id): ?array
    {
        return self::all()[$id] ?? null;
    }

    public static function featured(): array
    {
        $out = [];
        foreach (self::all() as $r) {
            if (!empty($r['featured'])) {
                $out[] = $r;
            }
        }

        return $out;
    }

    public static function categories(): array
    {
        return [
            [
                'id'     => 'sales',
                'label'  => 'Sales & Revenue',
                'icon'   => 'fa-chart-line',
                'accent' => 'sales',
                'tagline'=> 'Track invoices, collections, and customer momentum',
                'reports'=> [
                    self::r('revenueOverview', 'Revenue Overview', 'Invoice-level register with customer & salesman filters', 'Report/revenueOverview', 'fa-file-invoice-dollar', ['invoice', 'export'], 30, true),
                    self::r('salesFunnelPipeline', 'Sales Funnel & Pipeline', 'Complete deal flow visibility with funnel, metrics, table, trends', 'Report/salesFunnelPipeline', 'fa-filter', ['funnel', 'pipeline'], 30, true),
                    self::r('customerPerformance', 'Customer Performance', '360° customer value, loyalty, CLV, churn risk & segmentation', 'Report/customerPerformance', 'fa-users', ['customer', 'clv', 'churn'], 30, true),
                ],
            ],
            [
                'id'     => 'purchase',
                'label'  => 'Purchase & Payables',
                'icon'   => 'fa-truck-loading',
                'accent' => 'purchase',
                'tagline'=> 'Supplier spend, GRN history, and what you still owe',
                'reports'=> [
                    self::r('supplier_wise_purchase', 'Supplier-wise Purchase', 'Spend profile per supplier — negotiate smarter', 'Report/SupplierWisePurchase', 'fa-industry', ['supplier'], 30),
                    self::r('payable_aging', 'Payable Aging', 'Outstanding supplier balances by age bucket', 'Report/PayableAging', 'fa-clock', ['aging', 'finance'], 0, true),
                ],
            ],
            [
                'id'     => 'inventory',
                'label'  => 'Inventory & Stock',
                'icon'   => 'fa-warehouse',
                'accent' => 'inventory',
                'tagline'=> 'On-hand truth, valuation, and movement trails',
                'reports'=> [
                    self::r('product_stock_analysis', 'Product Stock Analysis', 'In/out movement with opening & closing', 'Report/ProductStockAnalysis', 'fa-microscope', ['movement'], 30),
                    self::r('product_movement', 'Product Movement', 'Chronological ledger for one SKU', 'Report/ProductMovement', 'fa-route', ['movement'], 30),
                ],
            ],
            [
                'id'     => 'finance',
                'label'  => 'Finance & Control',
                'icon'   => 'fa-scale-balanced',
                'accent' => 'finance',
                'tagline'=> 'GL integrity, cash day book, and branch ledgers',
                'reports'=> [
                    self::r('trial_balance', 'Trial Balance', 'Debit = Credit? — accounting health check', 'Report/TrialBalance', 'fa-scale-balanced', ['gl', 'export'], 0, true),
                    self::r('daily_cash_book', 'Day Book (Cash & Bank)', 'Split view: receipts vs payments in the period', 'Report/DailyCashBook', 'fa-book-open', ['cash'], 7, true),
                    self::r('branch_ledger', 'Branch Intercompany Ledger', 'Due between branches — settlement trail', 'Report/BranchWiseLedger', 'fa-arrows-left-right', ['branch'], 30),
                ],
            ],
            [
                'id'     => 'ops',
                'label'  => 'Operations',
                'icon'   => 'fa-clipboard-check',
                'accent' => 'ops',
                'tagline'=> 'Control reports outside the standard register',
                'reports'=> [
                    [
                        'id'       => 'stocktake_weekly',
                        'title'    => 'Stock Take — Weekly Control',
                        'tagline'  => 'Posted sessions, variance totals, top SKU deltas',
                        'route'    => 'StockTake/weekly',
                        'icon'     => 'fa-clipboard-check',
                        'tags'     => ['control', 'variance'],
                        'preset_days' => 7,
                        'featured' => false,
                        'filter_type' => 'range',
                    ],
                    [
                        'id'       => 'stocktake_variance',
                        'title'    => 'Stock Take — Variance Detail',
                        'tagline'  => 'Line-level count vs system by session',
                        'route'    => 'StockTake/variance',
                        'icon'     => 'fa-table',
                        'tags'     => ['detail'],
                        'preset_days' => 30,
                        'featured' => false,
                        'filter_type' => 'range',
                    ],
                    [
                        'id'       => 'branch_demand_weekly',
                        'title'    => 'Branch Demand — Weekly',
                        'tagline'  => 'Inter-branch demand, settlement & floor stock',
                        'route'    => 'BranchDemand/weekly',
                        'icon'     => 'fa-share-nodes',
                        'tags'     => ['branch'],
                        'preset_days' => 7,
                        'featured' => false,
                        'filter_type' => 'range',
                    ],
                ],
            ],
        ];
    }

    private static function r(
        string $id,
        string $title,
        string $tagline,
        string $route,
        string $icon,
        array $tags,
        int $presetDays,
        bool $featured = false
    ): array {
        return [
            'id'          => $id,
            'title'       => $title,
            'tagline'     => $tagline,
            'route'       => $route,
            'icon'        => $icon,
            'tags'        => $tags,
            'preset_days' => $presetDays,
            'featured'    => $featured,
            'filter_type' => $presetDays > 0 ? 'range' : ($id === 'receivable_aging' || $id === 'payable_aging' ? 'as_of' : 'range'),
        ];
    }

    public static function buildRunUrl(array $report, string $lens = 'mtd'): string
    {
        $base = (defined('BASE_URL') ? BASE_URL : '/') . ($report['route'] ?? '');
        $params = ['search' => '1'];

        $today = date('Y-m-d');
        if (($report['filter_type'] ?? '') === 'as_of') {
            $params['as_of_date'] = $today;
        } else {
            $days = (int)($report['preset_days'] ?? 30);
            if ($lens === 'today') {
                $params['from_date'] = $today;
                $params['to_date'] = $today;
            } elseif ($lens === 'mtd') {
                $params['from_date'] = date('Y-m-01');
                $params['to_date'] = $today;
            } elseif ($lens === 'last7') {
                $params['from_date'] = date('Y-m-d', strtotime('-6 days'));
                $params['to_date'] = $today;
            } else {
                $params['from_date'] = date('Y-m-d', strtotime('-' . max(1, $days) . ' days'));
                $params['to_date'] = $today;
            }
        }

        return $base . (str_contains($base, '?') ? '&' : '?') . http_build_query($params);
    }
}