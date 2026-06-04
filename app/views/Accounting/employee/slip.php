<?php
$transaction = $transaction ?? [];
$t = $transaction;
$id = (int)($t['id'] ?? 0);
$isReversed = !empty($t['is_reversed']);

$formatDate = static function ($d) {
    if (!$d) return '—';
    $ts = strtotime($d);
    return $ts ? date('d-m-Y', $ts) : $d;
};
$formatMoney = static fn ($n) => 'Tk ' . number_format((float)$n, 2);

$type = (string)($t['transaction_type'] ?? 'advance');
$typeLabel = match ($type) {
    'advance' => 'Advance',
    'loan' => 'Loan',
    'repayment' => 'Repayment',
    'salary' => 'Salary Payment',
    'deduction' => 'Deduction',
    'adjustment' => 'Adjustment',
    default => ucfirst($type),
};
$typeCls = preg_replace('/[^a-z_]/', '', strtolower($type)) ?: 'advance';

$amountLabel = match ($type) {
    'repayment', 'deduction' => 'Amount received / প্রাপ্ত টাকা',
    default => 'Amount paid / প্রদত্ত টাকা',
};

$mode = strtolower(trim((string)($t['payment_mode'] ?? 'cash')));
$branchId = (int)($t['branch_id'] ?? $_SESSION['branch_id'] ?? 1);
$invoiceStub = [
    'branch_id'      => $branchId,
    'branch_name'    => $t['branch_name'] ?? ($_SESSION['branch_name'] ?? 'Remote Center'),
    'branch_address' => trim((string)($t['branch_address'] ?? '')),
    'branch_phone'   => trim((string)($t['branch_phone'] ?? '')),
];
$title = $title ?? ('Employee Transaction Slip — ' . ($t['transaction_code'] ?? ''));
?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title, ENT_QUOTES) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Bengali:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/invoice-print.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/customer-payment-slip-print.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/employee-payment-slip-print.css">
</head>
<body class="invoice-print-body customer-payment-slip-body emp-payment-slip-body">

<div class="customer-payment-slip-toolbar no-print">
    <button type="button" class="btn btn-light" onclick="window.print()"><i class="fas fa-print"></i> Print</button>
    <a href="<?= BASE_URL ?>EmployeeTransaction/details/<?= $id ?>" class="btn btn-outline-light"><i class="fas fa-eye"></i> Details</a>
    <button type="button" class="btn btn-outline-light" onclick="window.close()">Close</button>
</div>

<div class="invoice-print-stack">
    <article class="invoice-print-page customer-payment-slip-page">
        <div class="cps-slip-page-inner">
            <?php if ($isReversed): ?><div class="cps-reversed-watermark" aria-hidden="true">REVERSED</div><?php endif; ?>
            <header class="invoice-print-header">
                <?php
                $branch_id = $branchId;
                $invoice = $invoiceStub;
                $doc_label_bn = 'কর্মচারী লেনদেন রসিদ';
                $doc_label_en = 'EMPLOYEE TRANSACTION';
                require __DIR__ . '/../../sales/partials/invoice_branch_header.php';
                ?>
                <div class="cps-banner">
                    <h2>কর্মচারী লেনদেন রসিদ / EMPLOYEE TRANSACTION SLIP</h2>
                    <span class="cps-voucher-code"><?= htmlspecialchars($t['transaction_code'] ?? '', ENT_QUOTES) ?></span>
                    <span class="cps-status-pill <?= $isReversed ? 'reversed' : 'active' ?>"><?= $isReversed ? 'Reversed' : 'Active' ?></span>
                </div>
                <div class="invoice-meta-grid">
                    <div><div class="meta-label">Date</div><div class="meta-value"><?= $formatDate($t['transaction_date'] ?? '') ?></div></div>
                    <div class="text-end"><div class="meta-label">Branch</div><div class="meta-value"><?= htmlspecialchars($invoiceStub['branch_name'], ENT_QUOTES) ?></div></div>
                </div>
            </header>
            <div class="invoice-print-body-main">
                <?php if ($isReversed): ?>
                <div class="cps-reversed-note">
                    <strong>Reversed.</strong>
                    <?php if (!empty($t['reverse_reason'])): ?><div><?= htmlspecialchars($t['reverse_reason'], ENT_QUOTES) ?></div><?php endif; ?>
                </div>
                <?php endif; ?>
                <div class="cps-amount-hero <?= htmlspecialchars($typeCls, ENT_QUOTES) ?>">
                    <div class="cps-label"><?= htmlspecialchars($amountLabel, ENT_QUOTES) ?></div>
                    <div class="cps-amount"><?= $formatMoney($t['amount'] ?? 0) ?></div>
                    <span class="cps-type-badge <?= htmlspecialchars($typeCls, ENT_QUOTES) ?>"><?= htmlspecialchars($typeLabel, ENT_QUOTES) ?></span>
                </div>
                <table class="cps-details-table">
                    <tr><th>Employee</th><td><strong><?= htmlspecialchars($t['employee_name'] ?? '', ENT_QUOTES) ?></strong>
                        <?php if (!empty($t['employee_code'])): ?><br><small><?= htmlspecialchars($t['employee_code'], ENT_QUOTES) ?></small><?php endif; ?>
                    </td></tr>
                    <tr><th>Mode</th><td><?= strtoupper($mode) ?><?php if ($mode === 'bank' && !empty($t['bank_name'])): ?><br><small><?= htmlspecialchars($t['bank_name'], ENT_QUOTES) ?></small><?php endif; ?></td></tr>
                    <?php if (!empty($t['journal_entry_id'])): ?><tr><th>GL</th><td>JE #<?= (int)$t['journal_entry_id'] ?></td></tr><?php endif; ?>
                    <?php if (!empty($t['remarks'])): ?><tr><th>Narration</th><td><?= nl2br(htmlspecialchars($t['remarks'], ENT_QUOTES)) ?></td></tr><?php endif; ?>
                </table>
            </div>
        </div>
    </article>
</div>
</body>
</html>