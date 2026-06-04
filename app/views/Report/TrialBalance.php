<?php
require_once __DIR__ . '/../../helpers/ReportsCatalog.php';

ob_start();
$title = 'Trial Balance';
$rpt = ReportsCatalog::get('trial_balance');
$rpt_has_run = !empty($trial_balance);
$rpt_export_url = $rpt_has_run
    ? BASE_URL . 'Report/TrialBalance?' . http_build_query(array_merge($_GET, ['export' => 1]))
    : '';
?>
<form method="GET" action="<?= BASE_URL ?>Report/TrialBalance" class="row g-3 align-items-end">
    <input type="hidden" name="search" value="1">
    <div class="col-md-3">
        <label class="form-label small fw-semibold">From date</label>
        <input type="date" name="from_date" class="form-control form-control-sm" value="<?= htmlspecialchars($from_date ?? date('Y-m-01'), ENT_QUOTES) ?>">
    </div>
    <div class="col-md-3">
        <label class="form-label small fw-semibold">To date</label>
        <input type="date" name="to_date" class="form-control form-control-sm" value="<?= htmlspecialchars($to_date ?? date('Y-m-d'), ENT_QUOTES) ?>">
    </div>
    <div class="col-md-3">
        <label class="form-label small fw-semibold">Account type</label>
        <select name="account_type" class="form-select form-select-sm">
            <option value="">All types</option>
            <?php foreach (['Asset', 'Liability', 'Equity', 'Income', 'Expense'] as $t): ?>
            <option value="<?= $t ?>" <?= ($account_type ?? '') === $t ? 'selected' : '' ?>><?= $t ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-3 d-flex gap-2">
        <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-wand-magic-sparkles me-1"></i> Generate</button>
    </div>
</form>
<?php $rpt_filters = ob_get_clean();

ob_start();
if ($rpt_has_run):
    $tb = $trial_balance;
    $balanced = !empty($tb['is_balanced']);
?>
<div class="rpt-status-banner <?= $balanced ? 'balanced' : 'unbalanced' ?>">
    <div class="fs-2">
        <i class="fas <?= $balanced ? 'fa-circle-check text-success' : 'fa-triangle-exclamation text-danger' ?>"></i>
    </div>
    <div>
        <h4 class="mb-1 fw-bold"><?= $balanced ? 'Accounting is balanced' : 'Out of balance — investigate journals' ?></h4>
        <p class="mb-0 small">
            <?= htmlspecialchars($tb['from_date'] ?? '', ENT_QUOTES) ?> → <?= htmlspecialchars($tb['to_date'] ?? '', ENT_QUOTES) ?>
            <?php if (!$balanced): ?>
            · Difference <strong>Tk <?= number_format((float)($tb['difference'] ?? 0), 2) ?></strong>
            <?php endif; ?>
        </p>
    </div>
</div>
<?php
    ob_start();
?>
<div class="rpt-kpi <?= $balanced ? 'success' : 'danger' ?>">
    <div class="kpi-label">Total debits</div>
    <div class="kpi-value">Tk <?= number_format((float)($tb['grand_debit'] ?? 0), 2) ?></div>
</div>
<div class="rpt-kpi <?= $balanced ? 'success' : 'danger' ?>">
    <div class="kpi-label">Total credits</div>
    <div class="kpi-value">Tk <?= number_format((float)($tb['grand_credit'] ?? 0), 2) ?></div>
</div>
<div class="rpt-kpi">
    <div class="kpi-label">Active accounts</div>
    <div class="kpi-value"><?= count($tb['data'] ?? []) ?></div>
</div>
<?php $rpt_kpis = ob_get_clean(); ?>

<div class="rpt-result-panel">
    <div class="rpt-result-panel-head">
        <h2><i class="fas fa-list me-1"></i> Trial balance detail</h2>
        <?php if ($balanced): ?>
        <span class="badge bg-success">Balanced ✓</span>
        <?php else: ?>
        <span class="badge bg-danger">Diff <?= number_format((float)($tb['difference'] ?? 0), 2) ?></span>
        <?php endif; ?>
    </div>
    <div class="table-responsive">
        <table class="table rpt-data-table mb-0">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Ledger</th>
                    <th>Type</th>
                    <th class="text-end">Debit</th>
                    <th class="text-end">Credit</th>
                    <th class="text-end">Balance</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($tb['data'])): ?>
                <tr><td colspan="7" class="text-center text-muted py-4">No activity in this period.</td></tr>
            <?php else: ?>
                <?php foreach ($tb['data'] as $row): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($row['ledger_code'] ?? '', ENT_QUOTES) ?></strong></td>
                    <td><?= htmlspecialchars($row['ledger_name'] ?? '', ENT_QUOTES) ?></td>
                    <td><span class="badge bg-secondary"><?= htmlspecialchars($row['account_type'] ?? '', ENT_QUOTES) ?></span></td>
                    <td class="text-end"><?= (float)($row['debit'] ?? 0) > 0 ? number_format((float)$row['debit'], 2) : '—' ?></td>
                    <td class="text-end"><?= (float)($row['credit'] ?? 0) > 0 ? number_format((float)$row['credit'], 2) : '—' ?></td>
                    <td class="text-end">
                        <strong><?= number_format((float)($row['balance'] ?? 0), 2) ?></strong>
                        <small class="text-muted">(<?= htmlspecialchars($row['balance_side'] ?? '', ENT_QUOTES) ?>)</small>
                    </td>
                    <td>
                        <a href="<?= BASE_URL ?>ledger/edit/<?= (int)($row['ledger_id'] ?? 0) ?>" class="btn btn-sm btn-outline-secondary" title="Ledger">
                            <i class="fas fa-pen"></i>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
            <?php if (!empty($tb['data'])): ?>
            <tfoot>
                <tr>
                    <td colspan="3" class="text-end">Grand total</td>
                    <td class="text-end"><?= number_format((float)($tb['grand_debit'] ?? 0), 2) ?></td>
                    <td class="text-end"><?= number_format((float)($tb['grand_credit'] ?? 0), 2) ?></td>
                    <td colspan="2" class="text-center"><?= $balanced ? 'BALANCED' : 'OUT OF BALANCE' ?></td>
                </tr>
            </tfoot>
            <?php endif; ?>
        </table>
    </div>
</div>
<?php
else:
    $rpt_kpis = '';
?>
<div class="rpt-result-panel">
    <div class="rpt-empty-state">
        <i class="fas fa-scale-balanced d-block"></i>
        <h3>Set your period</h3>
        <p>Generate the trial balance to verify debits equal credits across every active ledger.</p>
    </div>
</div>
<?php endif;
$rpt_body = ob_get_clean();

ob_start();
include __DIR__ . '/partials/frame.php';
$content = ob_get_clean();
require_once __DIR__ . '/../layouts/main.php';