<?php
ob_start();
$title = $title ?? 'New customer payment';
$customers = $customers ?? [];
$banks = $banks ?? [];
$employees = $employees ?? [];
$today = $today ?? date('Y-m-d');
$csrfToken = htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES);
?>
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/branch-index.css">
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/accounting-money-flow.css">
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/customer-transaction-theme.css">
<input type="hidden" id="base_url" value="<?= BASE_URL ?>">

<div class="branch-hub cust-txn-theme acct-money-app container-fluid py-2" id="customerTransactionCreate">
    <header class="branch-hub-hero">
        <div>
            <h1><i class="fas fa-plus me-2"></i>New customer payment</h1>
            <p>Record receive, refund, discount, or write-off against customer AR.</p>
            <span class="hero-badge"><i class="fas fa-book"></i> customer_ledger</span>
        </div>
        <div class="branch-hub-actions">
            <a href="<?= BASE_URL ?>CustomerTransaction" class="btn btn-outline-light btn-sm">
                <i class="fas fa-arrow-left me-1"></i> All payments
            </a>
        </div>
    </header>

    <div class="branch-form-layout has-aside">
        <div class="branch-form-panel">
            <form id="customerTransactionForm" class="needs-validation" novalidate>
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">

                <div class="branch-form-section">
                    <div class="branch-form-section-head">
                        <span class="icon-wrap teal"><i class="fas fa-user"></i></span>
                        Customer &amp; collector
                    </div>
                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="customer_id">Customer <span class="text-danger">*</span></label>
                            <select name="customer_id" id="customer_id" class="form-select" required>
                                <option value="">— Select customer —</option>
                                <?php
                                $preselectCustomer = (int)($_GET['customer_id'] ?? 0);
                                foreach ($customers as $c): ?>
                                <option value="<?= (int)$c['id'] ?>"<?= $preselectCustomer === (int)$c['id'] ? ' selected' : '' ?>>
                                    <?= htmlspecialchars($c['shop_name'] ?? '', ENT_QUOTES) ?>
                                    <?php if (!empty($c['mobile'])): ?> (<?= htmlspecialchars($c['mobile'], ENT_QUOTES) ?>)<?php endif; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="collected_by">Collected by <span class="text-danger">*</span></label>
                            <select name="collected_by" id="collected_by" class="form-select" required>
                                <?php foreach ($employees as $e): ?>
                                <option value="<?= (int)$e['id'] ?>"><?= htmlspecialchars($e['name'] ?? '', ENT_QUOTES) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div id="dueSummary" class="cust-txn-due-banner d-none mt-3"></div>
                </div>

                <div class="branch-form-section">
                    <div class="branch-form-section-head">
                        <span class="icon-wrap indigo"><i class="fas fa-file-invoice-dollar"></i></span>
                        Transaction
                    </div>
                    <div class="row g-3">
                        <div class="col-12 col-md-4">
                            <label class="form-label" for="transaction_type">Type <span class="text-danger">*</span></label>
                            <select name="transaction_type" id="transaction_type" class="form-select" required>
                                <option value="">— Select —</option>
                                <option value="receive">Receive (customer payment)</option>
                                <option value="payment">Payment (refund / advance)</option>
                                <option value="discount">Discount given</option>
                                <option value="write_off">Write-off (bad debt)</option>
                            </select>
                            <div id="typeHint" class="cust-txn-type-hint mt-1"></div>
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label" for="amount">Amount (Tk) <span class="text-danger">*</span></label>
                            <input type="number" name="amount" id="amount" step="0.01" min="0.01" class="form-control" required>
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label" for="transaction_date">Date</label>
                            <input type="date" name="transaction_date" id="transaction_date" value="<?= htmlspecialchars($today, ENT_QUOTES) ?>" class="form-control">
                        </div>
                    </div>
                </div>

                <div class="branch-form-section">
                    <div class="branch-form-section-head">
                        <span class="icon-wrap slate"><i class="fas fa-wallet"></i></span>
                        Payment mode
                    </div>
                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="mode">Mode</label>
                            <select name="mode" id="mode" class="form-select">
                                <option value="cash">Cash</option>
                                <option value="bank">Bank transfer</option>
                            </select>
                        </div>
                        <div class="col-12 col-md-6" id="bank_section" style="display:none;">
                            <label class="form-label" for="bank_id">Bank account</label>
                            <select name="bank_id" id="bank_id" class="form-select">
                                <?php foreach ($banks as $b): ?>
                                <option value="<?= (int)$b['id'] ?>"><?= htmlspecialchars($b['bank_name'] ?? '', ENT_QUOTES) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="branch-form-section">
                    <div class="branch-form-section-head">
                        <span class="icon-wrap teal"><i class="fas fa-comment"></i></span>
                        Remarks
                    </div>
                    <textarea name="narration" class="form-control" rows="3" placeholder="Optional narration…"></textarea>
                </div>

                <div class="branch-form-footer">
                    <button type="submit" class="btn btn-primary px-4">
                        <i class="fas fa-check me-1"></i> Save payment
                    </button>
                    <a href="<?= BASE_URL ?>CustomerTransaction" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>

        <aside class="branch-form-aside">
            <div class="aside-title">Tips</div>
            <div class="branch-aside-tip">
                <i class="fas fa-hand-holding-dollar me-1"></i>
                <strong>Receive</strong> — Dr cash/bank, Cr AR. <strong>Refund</strong> — Dr AR, Cr cash/bank. <strong>Discount / write-off</strong> — Dr expense, Cr AR (no cash movement).
            </div>
            <div class="branch-aside-tip mt-2">
                <i class="fas fa-building-columns me-1"></i>
                Bank mode updates <code>banks.balance</code> on receive (in) and refund (out). All types post GL when ledgers are configured.
            </div>
            <div class="branch-preview-card mt-3">
                <div class="aside-title mb-2">Preview</div>
                <div class="branch-avatar" id="previewAvatar">?</div>
                <div class="preview-name" id="previewCustomer">Customer</div>
                <div class="preview-code" id="previewType">Type</div>
                <div class="mt-2 cust-txn-amount receive" id="previewAmount">Tk 0</div>
            </div>
        </aside>
    </div>
</div>

<script>window.CT_BOOT = { baseUrl: <?= json_encode(BASE_URL, JSON_THROW_ON_ERROR) ?> };</script>
<script src="<?= BASE_URL ?>assets/js/CustomerTransaction.js"></script>

<?php
$content = ob_get_clean();
require_once '../app/views/layouts/main.php';