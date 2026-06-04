<?php
ob_start();
$title = $title ?? 'Supplier payments';
$transactions = $transactions ?? [];
$filters = $filters ?? [];
$stats = $stats ?? ['total' => 0, 'active' => 0, 'reversed' => 0, 'paid_today' => 0, 'paid_month' => 0];
$branch_name = $branch_name ?? 'Head Office';
$suppliers = $suppliers ?? [];

function suppTxnTypeLabel(string $type): string {
    return match ($type) {
        'payment' => 'Payment',
        'advance' => 'Advance',
        'receive' => 'Receive',
        default => ucfirst($type),
    };
}
function suppTxnTypeClass(string $type): string {
    $t = preg_replace('/[^a-z_]/', '', strtolower($type));
    return in_array($t, ['payment', 'advance', 'receive'], true) ? $t : 'payment';
}
?>
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/branch-index.css">
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/accounting-money-flow.css">
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/supplier-transaction-theme.css">
<input type="hidden" id="base_url" value="<?= BASE_URL ?>">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES) ?>">

<div class="branch-hub supp-txn-theme acct-money-app container-fluid py-2" id="supplierTransactionIndex">
    <header class="branch-hub-hero">
        <div>
            <h1><i class="fas fa-handshake me-2"></i>Supplier payments</h1>
            <p>Payment, advance, and receive — supplier ledger, GL, and bank book (real money flow).</p>
            <span class="hero-badge"><i class="fas fa-building"></i> <?= htmlspecialchars($branch_name, ENT_QUOTES) ?> · Tk</span>
            <?php
            $todayYmd = date('Y-m-d');
            $showingToday = ($filters['date_from'] ?? '') === $todayYmd && ($filters['date_to'] ?? '') === $todayYmd;
            if ($showingToday): ?>
            <span class="hero-badge ms-1"><i class="fas fa-calendar-day"></i> Today</span>
            <?php endif; ?>
        </div>
        <div class="branch-hub-actions">
            <a href="<?= BASE_URL ?>SupplierTransaction/create" class="btn btn-light btn-sm">
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
                <div class="stat-value">Tk <?= number_format((float)($stats['paid_today'] ?? 0), 0) ?></div>
                <div class="stat-label">Paid today</div>
            </div>
        </div>
        <div class="branch-stat-card">
            <div class="branch-stat-icon indigo"><i class="fas fa-calendar"></i></div>
            <div>
                <div class="stat-value">Tk <?= number_format((float)($stats['paid_month'] ?? 0), 0) ?></div>
                <div class="stat-label">Paid this month</div>
            </div>
        </div>
        <div class="branch-stat-card">
            <div class="branch-stat-icon slate"><i class="fas fa-rotate-left"></i></div>
            <div><div class="stat-value"><?= (int)($stats['reversed'] ?? 0) ?></div><div class="stat-label">Reversed</div></div>
        </div>
    </div>

    <nav class="branch-hub-quick">
        <a href="<?= BASE_URL ?>supplier"><i class="fas fa-truck"></i> Suppliers</a>
        <a href="<?= BASE_URL ?>bank"><i class="fas fa-building-columns"></i> Banks</a>
        <a href="<?= BASE_URL ?>ledger"><i class="fas fa-book"></i> Chart of accounts</a>
    </nav>

    <div class="branch-hub-panel">
        <form method="get" class="branch-hub-filters" id="suppTxnFilterForm">
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
                        <option value="payment" <?= ($filters['transaction_type'] ?? '') === 'payment' ? 'selected' : '' ?>>Payment</option>
                        <option value="advance" <?= ($filters['transaction_type'] ?? '') === 'advance' ? 'selected' : '' ?>>Advance</option>
                        <option value="receive" <?= ($filters['transaction_type'] ?? '') === 'receive' ? 'selected' : '' ?>>Receive</option>
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
                    <div class="filter-label">Supplier</div>
                    <select name="supplier_id" class="form-select form-select-sm">
                        <option value="">All suppliers</option>
                        <?php foreach ($suppliers as $s): ?>
                        <option value="<?= (int)$s['id'] ?>"<?= (int)($filters['supplier_id'] ?? 0) === (int)$s['id'] ? ' selected' : '' ?>>
                            <?= htmlspecialchars($s['supplier_name'] ?? '', ENT_QUOTES) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-md-4 d-flex gap-2 flex-wrap align-items-end">
                    <button type="submit" class="btn btn-primary btn-sm flex-fill"><i class="fas fa-search me-1"></i> Search</button>
                    <a href="<?= BASE_URL ?>SupplierTransaction" class="btn btn-outline-secondary btn-sm" title="Today only">Today</a>
                </div>
            </div>
        </form>

        <?php if (empty($transactions)): ?>
        <p class="text-muted text-center py-3 mb-0 d-none d-md-block" id="suppTxnEmptyDesktop">
            No transactions match these filters.
            <a href="<?= BASE_URL ?>SupplierTransaction/create">Record a payment</a>
        </p>
        <?php endif; ?>

        <div class="branch-hub-table-wrap d-none d-md-block<?= empty($transactions) ? ' d-none' : '' ?>">
            <table class="table table-borderless mb-0 w-100" id="suppTxnTable">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Voucher</th>
                        <th>Supplier</th>
                        <th>Type</th>
                        <th class="text-end">Amount</th>
                        <th>Mode</th>
                        <th class="d-none d-lg-table-cell">Paid by</th>
                        <th>Status</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($transactions as $t):
                        $isReversed = !empty($t['is_reversed']);
                        $type = (string)($t['transaction_type'] ?? 'payment');
                        $typeCls = suppTxnTypeClass($type);
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
                                <div class="branch-avatar"><?= htmlspecialchars(substr(trim($t['supplier_name'] ?? '?'), 0, 1), ENT_QUOTES) ?></div>
                                <div>
                                    <div class="name"><?= htmlspecialchars($t['supplier_name'] ?? '', ENT_QUOTES) ?></div>
                                    <?php if (!empty($t['mobile'])): ?>
                                    <div class="branch-contact"><i class="fas fa-phone"></i> <?= htmlspecialchars($t['mobile'], ENT_QUOTES) ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td><span class="supp-txn-type-pill <?= $typeCls ?>"><?= htmlspecialchars(suppTxnTypeLabel($type), ENT_QUOTES) ?></span></td>
                        <td class="text-end supp-txn-amount <?= $typeCls ?>">Tk <?= number_format((float)($t['amount'] ?? 0), 2) ?></td>
                        <td><?= strtoupper(htmlspecialchars($t['payment_mode'] ?? '', ENT_QUOTES)) ?></td>
                        <td class="d-none d-lg-table-cell"><?= htmlspecialchars($t['collected_by_name'] ?? '—', ENT_QUOTES) ?></td>
                        <td><?= $isReversed
                            ? '<span class="branch-status-pill inactive"><span class="dot"></span> Reversed</span>'
                            : '<span class="branch-status-pill active"><span class="dot"></span> Active</span>' ?></td>
                        <td class="text-center">
                            <div class="branch-action-bar">
                                <a href="<?= BASE_URL ?>SupplierTransaction/details/<?= (int)$t['id'] ?>" class="btn-action view" title="Details">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <?php if (!empty($t['can_reverse'])): ?>
                                <button type="button" class="btn-action toggle-off js-supp-reverse"
                                    data-payment-id="<?= (int)$t['id'] ?>"
                                    data-payment-code="<?= htmlspecialchars($t['payment_code'] ?? '', ENT_QUOTES) ?>"
                                    title="Reverse transaction">
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
        <div id="suppTxnCards" class="d-md-none" aria-live="polite"></div>
    </div>
</div>

<script>window.ST_BOOT = { baseUrl: <?= json_encode(BASE_URL, JSON_THROW_ON_ERROR) ?> };</script>
<script src="<?= BASE_URL ?>assets/js/SupplierTransaction.js"></script>

<?php
$content = ob_get_clean();
require_once '../app/views/layouts/main.php';