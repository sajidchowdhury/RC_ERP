<?php
ob_start();
$title = $title ?? 'GL Reconciliation';
$report = $report ?? [];
$from = $from ?? date('Y-m-01');
$to = $to ?? date('Y-m-d');
$ar = $report['ar'] ?? [];
$inv = $report['inventory'] ?? [];
$cogs = $report['cogs'] ?? [];
$mismatches = $ar['ledger_mismatches'] ?? [];
$tolerance = (float)($report['tolerance'] ?? 0.02);
$hasIssues = !empty($report['has_issues']);
?>
<div class="container-fluid py-3">
    <div class="card shadow-sm border-0 mb-3">
        <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center py-3 gap-2">
            <div>
                <h4 class="card-title mb-0 fw-semibold">
                    <i class="fas fa-scale-balanced text-primary me-2"></i>
                    GL reconciliation
                </h4>
                <small class="text-muted">
                    Phase 5 — <?= htmlspecialchars($report['branch_name'] ?? '', ENT_QUOTES) ?>
                </small>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="<?= BASE_URL ?>sales/reconcile" class="btn btn-outline-primary btn-sm">
                    <i class="fas fa-sync me-1"></i> Refresh
                </a>
                <a href="<?= BASE_URL ?>SalesAudit/checklist" class="btn btn-outline-secondary btn-sm">Audit checklist</a>
            </div>
        </div>
        <div class="card-body">
            <form method="get" action="<?= BASE_URL ?>sales/reconcile" class="row g-2 align-items-end mb-4">
                <div class="col-auto">
                    <label class="form-label small mb-0">COGS period from</label>
                    <input type="date" name="from" class="form-control form-control-sm" value="<?= htmlspecialchars($from, ENT_QUOTES) ?>">
                </div>
                <div class="col-auto">
                    <label class="form-label small mb-0">To</label>
                    <input type="date" name="to" class="form-control form-control-sm" value="<?= htmlspecialchars($to, ENT_QUOTES) ?>">
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary btn-sm">Apply</button>
                </div>
            </form>

            <p class="text-muted small mb-3">
                Last run: <strong><?= htmlspecialchars($report['ran_at'] ?? '', ENT_QUOTES) ?></strong>
                · Tolerance: <?= number_format($tolerance, 2) ?>
                <?php if ($hasIssues): ?>
                    <span class="badge bg-danger ms-1">Issues detected</span>
                <?php else: ?>
                    <span class="badge bg-success ms-1">Within tolerance</span>
                <?php endif; ?>
            </p>

            <?php if (!empty($report['issues'])): ?>
                <div class="alert alert-warning">
                    <ul class="mb-0">
                        <?php foreach ($report['issues'] as $issue): ?>
                            <li><?= htmlspecialchars($issue, ENT_QUOTES) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <h5 class="fw-semibold mb-2">Accounts receivable</h5>
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <div class="border rounded p-3 h-100">
                        <div class="text-muted small">Customer ledger net</div>
                        <div class="fs-4 fw-bold"><?= number_format((float)($ar['customer_ledger_net'] ?? 0), 2) ?></div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="border rounded p-3 h-100">
                        <div class="text-muted small">GL AR control</div>
                        <div class="fs-4 fw-bold"><?= number_format((float)($ar['gl_ar_net'] ?? 0), 2) ?></div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="border rounded p-3 h-100 <?= !empty($ar['within_tolerance']) ? 'border-success' : 'border-danger' ?>">
                        <div class="text-muted small">Difference</div>
                        <div class="fs-4 fw-bold <?= !empty($ar['within_tolerance']) ? 'text-success' : 'text-danger' ?>">
                            <?= number_format((float)($ar['difference'] ?? 0), 2) ?>
                        </div>
                    </div>
                </div>
            </div>

            <?php
            $integrityOk = ((int)($ar['ledger_mismatch_count'] ?? 0)) === 0;
            $nullBranch = (int)($ar['null_branch_ledger_rows'] ?? 0);
            ?>
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <div class="alert <?= $integrityOk ? 'alert-success' : 'alert-warning' ?> mb-0">
                        <strong>Running balance integrity:</strong>
                        <?= $integrityOk ? 'OK' : (int)($ar['ledger_mismatch_count'] ?? 0) . ' mismatch(es)' ?>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="alert <?= $nullBranch === 0 ? 'alert-success' : 'alert-warning' ?> mb-0">
                        <strong>branch_id on ledger:</strong>
                        <?= $nullBranch === 0 ? 'OK' : "{$nullBranch} NULL row(s)" ?>
                    </div>
                </div>
            </div>

            <?php if (!$integrityOk && $mismatches !== []): ?>
                <div class="table-responsive mb-4">
                    <table class="table table-sm table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Customer</th>
                                <th class="text-end">Last balance</th>
                                <th class="text-end">Computed</th>
                                <th class="text-end">Diff</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($mismatches, 0, 50) as $row): ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['shop_name'] ?? $row['customer_name'] ?? ('#' . ($row['customer_id'] ?? '')), ENT_QUOTES) ?></td>
                                    <td class="text-end"><?= number_format((float)($row['last_balance'] ?? 0), 2) ?></td>
                                    <td class="text-end"><?= number_format((float)($row['computed_balance'] ?? 0), 2) ?></td>
                                    <td class="text-end text-danger"><?= number_format((float)($row['difference'] ?? 0), 2) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <h5 class="fw-semibold mb-2">Inventory</h5>
            <p class="text-muted small"><?= htmlspecialchars($inv['note'] ?? '', ENT_QUOTES) ?></p>
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <div class="border rounded p-3">
                        <div class="text-muted small">Warehouse stock value</div>
                        <div class="fs-5 fw-bold"><?= number_format((float)($inv['warehouse_stock_value'] ?? 0), 2) ?></div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="border rounded p-3">
                        <div class="text-muted small">GL inventory net</div>
                        <div class="fs-5 fw-bold"><?= number_format((float)($inv['gl_inventory_net'] ?? 0), 2) ?></div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="border rounded p-3 <?= !empty($inv['within_tolerance']) ? 'border-success' : 'border-warning' ?>">
                        <div class="text-muted small">Difference</div>
                        <div class="fs-5 fw-bold"><?= number_format((float)($inv['difference'] ?? 0), 2) ?></div>
                    </div>
                </div>
            </div>

            <h5 class="fw-semibold mb-2">COGS tie-out</h5>
            <p class="text-muted small"><?= htmlspecialchars($cogs['timing_note'] ?? '', ENT_QUOTES) ?></p>
            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <div class="border rounded p-3">
                        <div class="text-muted small">Stock OUT (challans)</div>
                        <div class="fs-5 fw-bold"><?= number_format((float)($cogs['stock_cogs_amount'] ?? 0), 2) ?></div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="border rounded p-3">
                        <div class="text-muted small">GL COGS debits</div>
                        <div class="fs-5 fw-bold"><?= number_format((float)($cogs['gl_cogs_amount'] ?? 0), 2) ?></div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="border rounded p-3 <?= !empty($cogs['within_tolerance']) ? 'border-success' : 'border-warning' ?>">
                        <div class="text-muted small">Difference</div>
                        <div class="fs-5 fw-bold"><?= number_format((float)($cogs['difference'] ?? 0), 2) ?></div>
                    </div>
                </div>
            </div>

            <p class="text-muted small mb-0">
                Scheduled job: <code>php database/scripts/run_gl_reconciliation.php</code>
                · Alerts: <code>logs/reconciliation_alerts.log</code>
                · Optional email: set <code>RECON_ALERT_EMAIL</code> in config/local.php
            </p>
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();
require_once '../app/views/layouts/main.php';
echo $content;