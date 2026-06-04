<?php
ob_start();
$title = $title ?? 'Bank Audit Logs';
$logs = $logs ?? [];
$countCreated = $countUpdated = $countStatus = 0;
foreach ($logs as $log) {
    $a = (string)($log['action'] ?? '');
    if (str_contains($a, 'created')) $countCreated++;
    elseif (str_contains($a, 'updated')) $countUpdated++;
    else $countStatus++;
}
function bankAuditClass(string $action): string {
    if (str_contains($action, 'created')) return 'created';
    if (str_contains($action, 'updated')) return 'updated';
    if (str_contains($action, 'status') || str_contains($action, 'deactivated') || str_contains($action, 'restored')) return 'status';
    return 'other';
}
function bankAuditLabel(string $action): string {
    return match (true) {
        str_contains($action, 'created') => 'Created',
        str_contains($action, 'updated') => 'Updated',
        str_contains($action, 'status_changed') => 'Status changed',
        str_contains($action, 'deactivated') => 'Deactivated',
        str_contains($action, 'restored') => 'Restored',
        default => $action,
    };
}
function bankAuditDetails($details): string {
    if (empty($details) || !is_array($details)) return '<span class="text-muted">—</span>';
    $parts = [];
    if (!empty($details['bank_name'])) $parts[] = '<strong>Bank:</strong> ' . htmlspecialchars((string)$details['bank_name'], ENT_QUOTES);
    if (empty($parts)) return '<span class="branch-audit-details">' . htmlspecialchars(json_encode($details, JSON_UNESCAPED_UNICODE), ENT_QUOTES) . '</span>';
    return '<div class="branch-audit-details">' . implode(' · ', $parts) . '</div>';
}
?>
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/branch-index.css">
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/bank-theme.css">

<div class="branch-hub bank-theme container-fluid py-2">
    <header class="branch-hub-hero">
        <div>
            <h1><i class="fas fa-clock-rotate-left me-2"></i>Bank audit trail</h1>
            <p>Creates, updates, status changes, deactivations, and restores — last 300 bank events.</p>
        </div>
        <div class="branch-hub-actions">
            <a href="<?= BASE_URL ?>bank/create" class="btn btn-outline-light btn-sm"><i class="fas fa-plus me-1"></i> New account</a>
            <a href="<?= BASE_URL ?>bank" class="btn btn-light btn-sm"><i class="fas fa-arrow-left me-1"></i> Accounts</a>
        </div>
    </header>

    <div class="branch-audit-summary">
        <span class="branch-audit-chip"><i class="fas fa-list"></i> <?= count($logs) ?> entries</span>
        <span class="branch-audit-chip created"><i class="fas fa-plus"></i> <?= $countCreated ?> created</span>
        <span class="branch-audit-chip updated"><i class="fas fa-pen"></i> <?= $countUpdated ?> updated</span>
        <span class="branch-audit-chip status"><i class="fas fa-toggle-on"></i> <?= $countStatus ?> status</span>
    </div>

    <div class="branch-hub-panel branch-audit-panel">
        <div class="table-responsive">
            <table class="table table-borderless mb-0 w-100" id="auditTable">
                <thead>
                    <tr>
                        <th>When</th>
                        <th>User</th>
                        <th>Action</th>
                        <th>Bank ID</th>
                        <th>Details</th>
                        <th>IP</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                    <tr>
                        <td colspan="6" class="text-center text-muted py-5">
                            <i class="fas fa-inbox fa-2x mb-2 opacity-50 d-block"></i>
                            No bank audit logs yet.
                        </td>
                    </tr>
                    <?php else: foreach ($logs as $log):
                        $action = (string)($log['action'] ?? '');
                        $cls = bankAuditClass($action);
                    ?>
                    <tr>
                        <td><small class="text-nowrap"><?= htmlspecialchars($log['timestamp'] ?? '', ENT_QUOTES) ?></small></td>
                        <td><span class="badge rounded-pill bg-light text-dark border">#<?= (int)($log['performed_by'] ?? 0) ?></span></td>
                        <td><span class="branch-audit-action <?= $cls ?>"><?= htmlspecialchars(bankAuditLabel($action), ENT_QUOTES) ?></span></td>
                        <td><strong><?= htmlspecialchars((string)($log['target_user_id'] ?? '—'), ENT_QUOTES) ?></strong></td>
                        <td><?= bankAuditDetails($log['details'] ?? []) ?></td>
                        <td><small class="text-muted"><?= htmlspecialchars($log['ip'] ?? 'unknown', ENT_QUOTES) ?></small></td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <div class="branch-audit-meta-foot">
            <i class="fas fa-file-lines me-1"></i>
            Stored in <code>logs/user_audit.log</code> · Filter prefix <code>bank_</code>
        </div>
    </div>
</div>

<script>
$(function() {
    const hasRows = $('#auditTable tbody tr').length > 0 && $('#auditTable tbody tr td').length > 1;
    if (!hasRows) return;
    $('#auditTable').DataTable({
        pageLength: 50,
        order: [[0, 'desc']],
        dom: '<"branch-dt-toolbar"lf>Brt<"branch-dt-footer"ip>',
        buttons: ['copy', 'excel', 'pdf'],
        language: { emptyTable: 'No bank audit logs found yet.', search: 'Filter logs:' }
    });
});
</script>

<?php
$content = ob_get_clean();
require_once '../app/views/layouts/main.php';