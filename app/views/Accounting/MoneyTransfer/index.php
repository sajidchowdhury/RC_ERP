<?php
ob_start();
$title = 'Money Transfers';
$showReversed = $showReversed ?? false;
$stats = $stats ?? ['total' => 0, 'active' => 0, 'reversed' => 0, 'today' => 0, 'this_month' => 0];
$branch_name = $branch_name ?? 'Head Office';
?>

<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/branch-index.css">
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/accounting-money-flow.css">
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/money-transfer-theme.css">

<input type="hidden" id="base_url" value="<?= BASE_URL ?>">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES) ?>">

<div class="branch-hub money-transfer-theme container-fluid py-2">
    <header class="branch-hub-hero">
        <div>
            <h1><i class="fas fa-exchange-alt me-2"></i>Money Transfers</h1>
            <p>Internal cash and bank movements between branches</p>
            <span class="hero-badge"><i class="fas fa-building"></i> <?= htmlspecialchars($branch_name, ENT_QUOTES) ?> · Tk</span>
        </div>
        <div class="branch-hub-actions">
            <?php if (!$showReversed): ?>
                <a href="<?= BASE_URL ?>MoneyTransfer/create" class="btn btn-light btn-sm">
                    <i class="fas fa-plus me-1"></i> New Transfer
                </a>
            <?php endif; ?>
            <a href="<?= BASE_URL ?>MoneyTransfer/audit" class="btn btn-outline-dark btn-sm">
                <i class="fas fa-history me-1"></i> Audit Logs
            </a>
            <?php if ($showReversed): ?>
                <a href="<?= BASE_URL ?>MoneyTransfer" class="btn btn-outline-secondary btn-sm">
                    <i class="fas fa-exchange-alt me-1"></i> Show Active
                </a>
            <?php else: ?>
                <a href="<?= BASE_URL ?>MoneyTransfer?reversed=1" class="btn btn-outline-secondary btn-sm">
                    <i class="fas fa-undo me-1"></i> Show Reversed
                </a>
            <?php endif; ?>
        </div>
    </header>

    <!-- Stats -->
    <div class="branch-hub-stats">
        <div class="branch-stat-card">
            <div class="branch-stat-icon orange"><i class="fas fa-exchange-alt"></i></div>
            <div><div class="stat-value"><?= (int)($stats['total'] ?? 0) ?></div><div class="stat-label">Total Transfers</div></div>
        </div>
        <div class="branch-stat-card">
            <div class="branch-stat-icon teal"><i class="fas fa-check-circle"></i></div>
            <div><div class="stat-value"><?= (int)($stats['active'] ?? 0) ?></div><div class="stat-label">Active</div></div>
        </div>
        <div class="branch-stat-card">
            <div class="branch-stat-icon slate"><i class="fas fa-rotate-left"></i></div>
            <div><div class="stat-value"><?= (int)($stats['reversed'] ?? 0) ?></div><div class="stat-label">Reversed</div></div>
        </div>
        <div class="branch-stat-card">
            <div class="branch-stat-icon indigo"><i class="fas fa-calendar-day"></i></div>
            <div>
                <div class="stat-value">Tk <?= number_format((float)($stats['today'] ?? 0), 0) ?></div>
                <div class="stat-label">Today</div>
            </div>
        </div>
    </div>

    <!-- Quick Nav -->
    <nav class="branch-hub-quick">
        <a href="<?= BASE_URL ?>bank"><i class="fas fa-building-columns"></i> Banks</a>
        <a href="<?= BASE_URL ?>branch"><i class="fas fa-code-branch"></i> Branches</a>
        <a href="<?= BASE_URL ?>ledger"><i class="fas fa-book"></i> Ledger</a>
        <a href="<?= BASE_URL ?>MoneyTransfer/audit"><i class="fas fa-history"></i> Audit</a>
    </nav>

    <div class="branch-hub-panel">
        <!-- Filters -->
        <div class="branch-hub-filters">
            <div class="row g-3 align-items-end">
                <div class="col-sm-3 col-md-2">
                    <div class="filter-label">From Date</div>
                    <input type="date" id="fromDate" class="form-control form-control-sm" value="<?= $fromDate ?? '' ?>">
                </div>
                <div class="col-sm-3 col-md-2">
                    <div class="filter-label">To Date</div>
                    <input type="date" id="toDate" class="form-control form-control-sm" value="<?= $toDate ?? '' ?>">
                </div>
                <div class="col-sm-3 col-md-2">
                    <div class="filter-label">Transfer Type</div>
                    <select id="filterType" class="form-select form-select-sm">
                        <option value="">All Types</option>
                        <option value="cash_to_bank">Cash → Bank</option>
                        <option value="bank_to_cash">Bank → Cash</option>
                        <option value="cash_to_cash">Cash → Cash</option>
                        <option value="bank_to_bank">Bank → Bank</option>
                    </select>
                </div>
                <div class="col-sm-3 col-md-2">
                    <div class="filter-label">Status</div>
                    <select id="filterStatus" class="form-select form-select-sm">
                        <option value="">All</option>
                        <option value="active">Active</option>
                        <option value="reversed">Reversed</option>
                    </select>
                </div>
                <div class="col-sm-auto">
                    <button type="button" id="clearFilters" class="btn btn-outline-secondary btn-sm btn-clear">
                        <i class="fas fa-rotate-left me-1"></i> Reset
                    </button>
                </div>
            </div>
        </div>

        <!-- Desktop Table -->
        <div class="branch-hub-table-wrap d-none d-md-block">
            <table class="table table-borderless mb-0 w-100" id="transferTable">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Code</th>
                        <th>Type</th>
                        <th class="text-end">Amount</th>
                        <th>From</th>
                        <th>To</th>
                        <th>Branches</th>
                        <th>Status</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>

        <!-- Mobile Cards -->
        <div id="transferCards" class="d-md-none"></div>
    </div>
</div>

<style>
.money-transfer-theme .branch-stat-icon.orange { background: linear-gradient(135deg, #fd7e14, #e05c00); }
</style>

<script>window.showReversed = <?= !empty($showReversed) ? 'true' : 'false' ?>;</script>
<script src="<?= BASE_URL ?>assets/js/MoneyTransfer.js"></script>

<?php
$content = ob_get_clean();
require_once '../app/views/layouts/main.php';
?>