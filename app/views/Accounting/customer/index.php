<?php
ob_start();
$title = $title ?? 'Customer payments';
$transactions = $transactions ?? [];
$filters = $filters ?? [];
$stats = $stats ?? ['total' => 0, 'active' => 0, 'reversed' => 0, 'received_today' => 0, 'received_month' => 0];
$branch_name = $branch_name ?? 'Head Office';
$customers = $customers ?? [];

function custTxnTypeLabel(string $type): string {
    return match ($type) {
        'receive' => 'Receive',
        'payment' => 'Payment',
        'discount' => 'Discount',
        'write_off' => 'Write-off',
        default => ucfirst($type),
    };
}
function custTxnTypeClass(string $type): string {
    $t = preg_replace('/[^a-z_]/', '', strtolower($type));
    return in_array($t, ['receive', 'payment', 'discount', 'write_off'], true) ? $t : 'receive';
}
?>
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/branch-index.css">
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/accounting-money-flow.css">
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/customer-transaction-theme.css">
<input type="hidden" id="base_url" value="<?= BASE_URL ?>">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES) ?>">

<div class="branch-hub cust-txn-theme acct-money-app container-fluid py-2" id="customerTransactionIndex">
    <header class="branch-hub-hero">
        <div>
            <h1><i class="fas fa-hand-holding-dollar me-2"></i>Customer payments</h1>
            <p>Receive, refund, discount, write-off — customer ledger, GL, and bank book (real money flow).</p>
            <span class="hero-badge"><i class="fas fa-building"></i> <?= htmlspecialchars($branch_name, ENT_QUOTES) ?> · Tk</span>
            <?php
            $todayYmd = date('Y-m-d');
            $showingToday = ($filters['date_from'] ?? '') === $todayYmd && ($filters['date_to'] ?? '') === $todayYmd;
            if ($showingToday): ?>
            <span class="hero-badge ms-1"><i class="fas fa-calendar-day"></i> Today</span>
            <?php endif; ?>
        </div>
        <div class="branch-hub-actions">
            <a href="<?= BASE_URL ?>CustomerTransaction/create" class="btn btn-light btn-sm">
                <i class="fas fa-plus me-1"></i> New payment
            </a>
        </div>
    </header>

    <div class="branch-hub-stats">
        <div class="branch-stat-card">
            <div class="branch-stat-icon teal"><i class="fas fa-receipt"></i></div>
            <div><div class="stat-value"><?= (int)($stats['active'] ?? 0) ?></div><div class="stat-label">Active vouchers</div></div>
        </div>
        <div class="branch-stat-card">
            <div class="branch-stat-icon amber"><i class="fas fa-sun"></i></div>
            <div>
                <div class="stat-value">Tk <?= number_format((float)($stats['received_today'] ?? 0), 0) ?></div>
                <div class="stat-label">Received today</div>
            </div>
        </div>
        <div class="branch-stat-card">
            <div class="branch-stat-icon indigo"><i class="fas fa-calendar"></i></div>
            <div>
                <div class="stat-value">Tk <?= number_format((float)($stats['received_month'] ?? 0), 0) ?></div>
                <div class="stat-label">Received this month</div>
            </div>
        </div>
        <div class="branch-stat-card">
            <div class="branch-stat-icon slate"><i class="fas fa-rotate-left"></i></div>
            <div><div class="stat-value"><?= (int)($stats['reversed'] ?? 0) ?></div><div class="stat-label">Reversed</div></div>
        </div>
    </div>

    <nav class="branch-hub-quick">
        <a href="<?= BASE_URL ?>customer"><i class="fas fa-users"></i> Customers</a>
        <a href="<?= BASE_URL ?>bank"><i class="fas fa-building-columns"></i> Banks</a>
        <a href="<?= BASE_URL ?>ledger"><i class="fas fa-book"></i> Chart of accounts</a>
    </nav>

    <div class="branch-hub-panel">
        <form method="get" class="branch-hub-filters" id="custTxnFilterForm">
            <div class="row g-3 align-items-end">
                <div class="col-6 col-md-2">
                    <div class="filter-label">From</div>
                    <input type="date" name="date_from" class="form-control form-control-sm"
                           value="<?= htmlspecialchars($filters['date_from'] ?? '', ENT_QUOTES) ?>">
                </div>
                <div class="col-6 col-md-2">
                    <div class="filter-label">To</div>
                    <input type="date" name="date_to" class="form-control form-control-sm"
                           value="<?= htmlspecialchars($filters['date_to'] ?? '', ENT_QUOTES) ?>">
                </div>
                <div class="col-6 col-md-2">
                    <div class="filter-label">Type</div>
                    <select name="transaction_type" class="form-select form-select-sm">
                        <option value="all">All types</option>
                        <option value="receive" <?= ($filters['transaction_type'] ?? '') === 'receive' ? 'selected' : '' ?>>Receive</option>
                        <option value="payment" <?= ($filters['transaction_type'] ?? '') === 'payment' ? 'selected' : '' ?>>Payment</option>
                        <option value="discount" <?= ($filters['transaction_type'] ?? '') === 'discount' ? 'selected' : '' ?>>Discount</option>
                        <option value="write_off" <?= ($filters['transaction_type'] ?? '') === 'write_off' ? 'selected' : '' ?>>Write-off</option>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <div class="filter-label">Status</div>
                    <select name="status" class="form-select form-select-sm">
                        <option value="all">All</option>
                        <option value="active" <?= ($filters['status'] ?? '') === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="reversed" <?= ($filters['status'] ?? '') === 'reversed' ? 'selected' : '' ?>>Reversed</option>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <div class="filter-label">Mode</div>
                    <select name="payment_mode" class="form-select form-select-sm">
                        <option value="all">Any</option>
                        <option value="cash" <?= ($filters['payment_mode'] ?? '') === 'cash' ? 'selected' : '' ?>>Cash</option>
                        <option value="bank" <?= ($filters['payment_mode'] ?? '') === 'bank' ? 'selected' : '' ?>>Bank</option>
                    </select>
                </div>
                <div class="col-12 col-md-4">
                    <div class="filter-label">Customer</div>
                    <select name="customer_id" class="form-select form-select-sm">
                        <option value="">All customers</option>
                        <?php foreach ($customers as $c): ?>
                        <option value="<?= (int)$c['id'] ?>"<?= (int)($filters['customer_id'] ?? 0) === (int)$c['id'] ? ' selected' : '' ?>>
                            <?= htmlspecialchars($c['shop_name'] ?? '', ENT_QUOTES) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-md-4 d-flex gap-2 flex-wrap align-items-end">
                    <button type="submit" class="btn btn-primary btn-sm flex-fill"><i class="fas fa-search me-1"></i> Search</button>
                    <a href="<?= BASE_URL ?>CustomerTransaction" class="btn btn-outline-secondary btn-sm" title="Today only">Today</a>
                </div>
            </div>
        </form>

        <?php if (empty($transactions)): ?>
        <p class="text-muted text-center py-3 mb-0 d-none d-md-block" id="custTxnEmptyDesktop">
            No payments match these filters.
            <a href="<?= BASE_URL ?>CustomerTransaction/create">Record a payment</a>
        </p>
        <?php endif; ?>

        <div class="branch-hub-table-wrap d-none d-md-block<?= empty($transactions) ? ' d-none' : '' ?>">
            <table class="table table-borderless mb-0 w-100" id="custTxnTable">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Voucher</th>
                        <th>Customer</th>
                        <th>Type</th>
                        <th class="text-end">Amount</th>
                        <th>Mode</th>
                        <th class="d-none d-lg-table-cell">Collected by</th>
                        <th>Status</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($transactions as $t):
                        $isReversed = !empty($t['is_reversed']);
                        $type = (string)($t['transaction_type'] ?? 'receive');
                        $typeCls = custTxnTypeClass($type);
                        $amtCls = custTxnTypeClass($type);
                    ?>
                    <tr data-type="<?= htmlspecialchars($type, ENT_QUOTES) ?>"
                        data-status="<?= $isReversed ? 'reversed' : 'active' ?>"
                        data-mode="<?= htmlspecialchars(strtolower($t['payment_mode'] ?? ''), ENT_QUOTES) ?>"
                        <?= $isReversed ? 'class="table-secondary"' : '' ?>>
                        <td data-order="<?= htmlspecialchars($t['payment_date'] ?? '', ENT_QUOTES) ?>">
                            <small class="text-nowrap"><?= date('d M Y', strtotime($t['payment_date'] ?? 'now')) ?></small>
                        </td>
                        <td><span class="branch-code-pill"><?= htmlspecialchars($t['payment_code'] ?? '', ENT_QUOTES) ?></span></td>
                        <td>
                            <div class="branch-name-cell">
                                <div class="branch-avatar"><?= htmlspecialchars(substr(trim($t['shop_name'] ?? '?'), 0, 1), ENT_QUOTES) ?></div>
                                <div>
                                    <div class="name"><?= htmlspecialchars($t['shop_name'] ?? '', ENT_QUOTES) ?></div>
                                    <?php if (!empty($t['mobile'])): ?>
                                    <div class="branch-contact"><i class="fas fa-phone"></i> <?= htmlspecialchars($t['mobile'], ENT_QUOTES) ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td><span class="cust-txn-type-pill <?= $typeCls ?>"><?= htmlspecialchars(custTxnTypeLabel($type), ENT_QUOTES) ?></span></td>
                        <td class="text-end cust-txn-amount <?= $amtCls ?>">Tk <?= number_format((float)($t['amount'] ?? 0), 2) ?></td>
                        <td><?= strtoupper(htmlspecialchars($t['payment_mode'] ?? '', ENT_QUOTES)) ?></td>
                        <td class="d-none d-lg-table-cell"><?= htmlspecialchars($t['collected_by_name'] ?? '—', ENT_QUOTES) ?></td>
                        <td><?= $isReversed
                            ? '<span class="branch-status-pill inactive"><span class="dot"></span> Reversed</span>'
                            : '<span class="branch-status-pill active"><span class="dot"></span> Active</span>' ?></td>
                        <td class="text-center">
                            <div class="branch-action-bar">
                                <a href="<?= BASE_URL ?>CustomerTransaction/details/<?= (int)$t['id'] ?>" class="btn-action view" title="Details">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <?php if (!empty($t['can_reverse'])): ?>
                                <button type="button" class="btn-action toggle-off js-cust-reverse"
                                    data-payment-id="<?= (int)$t['id'] ?>"
                                    data-payment-code="<?= htmlspecialchars($t['payment_code'] ?? '', ENT_QUOTES) ?>"
                                    title="Reverse payment">
                                    <i class="fas fa-rotate-left"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div id="custTxnCards" class="d-md-none" aria-live="polite"></div>
    </div>
</div>

<script>window.CT_BOOT = { baseUrl: <?= json_encode(BASE_URL, JSON_THROW_ON_ERROR) ?> };</script>
<script src="<?= BASE_URL ?>assets/js/CustomerTransaction.js"></script>

<?php
$content = ob_get_clean();
require_once '../app/views/layouts/main.php';