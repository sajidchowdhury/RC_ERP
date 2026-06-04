<?php
ob_start();
$title = 'GRN Details #' . htmlspecialchars($receive['receive_code']);
?>
<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-lg-11 col-xl-10">

            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h3 class="mb-1 fw-bold">
                        <i class="fas fa-truck-loading text-success me-2"></i> Purchase Receive
                        <span class="text-muted">#<?= htmlspecialchars($receive['receive_code']) ?></span>
                    </h3>
                    <p class="text-muted mb-0">Goods Received Note (GRN) Details</p>
                </div>
                <div class="d-flex gap-2">
                    <button onclick="window.print()" class="btn btn-outline-primary btn-sm">
                        <i class="fas fa-print me-1"></i> Print
                    </button>
                    <a href="<?= BASE_URL ?>PurchaseReceive" class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-arrow-left me-1"></i> Back to List
                    </a>
                </div>
            </div>

            <div class="row g-4">

                <!-- Supplier Info -->
                <div class="col-lg-6">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-header bg-light py-3">
                            <h6 class="mb-0 fw-semibold"><i class="fas fa-user-tie me-2"></i> Supplier Information</h6>
                        </div>
                        <div class="card-body">
                            <p class="mb-1"><strong><?= htmlspecialchars($receive['supplier_name']) ?></strong></p>
                            <p class="mb-1 text-muted"><?= htmlspecialchars($receive['mobile'] ?? '—') ?></p>
                            <p class="mb-0 text-muted"><?= nl2br(htmlspecialchars($receive['address'] ?? '—')) ?></p>
                        </div>
                    </div>
                </div>

                <!-- Receive Info -->
                <div class="col-lg-6">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-header bg-light py-3">
                            <h6 class="mb-0 fw-semibold"><i class="fas fa-info-circle me-2"></i> Receive Information</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-6">
                                    <small class="text-muted">Receive Date</small><br>
                                    <strong><?= date('d M Y', strtotime($receive['receive_date'])) ?></strong>
                                </div>
                                <div class="col-6">
                                    <small class="text-muted">Branch</small><br>
                                    <strong><?= htmlspecialchars($receive['branch_name']) ?></strong>
                                </div>
                            </div>
                            <hr class="my-2">
                            <div>
                                <small class="text-muted">PO Reference</small><br>
                                <strong><?= htmlspecialchars($receive['po_code'] ?? 'Direct Purchase') ?></strong>
                            </div>
                            <?php if (!empty($receive['journal_entry_id'])): ?>
                            <hr class="my-2">
                            <div>
                                <small class="text-muted">Journal Entry</small><br>
                                <a href="<?= BASE_URL ?>Report/ledger?journal_id=<?= (int)$receive['journal_entry_id'] ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-book me-1"></i> JE #<?= (int)$receive['journal_entry_id'] ?>
                                </a>
                                <small class="text-success">(Posted)</small>
                            </div>
                            <?php else: ?>
                            <hr class="my-2">
                            <div>
                                <small class="text-muted">Journal Entry</small><br>
                                <span class="badge bg-secondary">Not posted yet (or pre-GL data)</span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Items Table -->
            <div class="card shadow-sm border-0 mt-4">
                <div class="card-header bg-light py-3">
                    <h6 class="mb-0 fw-semibold"><i class="fas fa-boxes me-2"></i> Received Items</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-bordered mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Product</th>
                                    <th>Warehouse</th>
                                    <th class="text-center">Qty</th>
                                    <th class="text-end">Rate</th>
                                    <th class="text-end">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($receive['items'] as $item): ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($item['product_name']) ?></strong><br>
                                            <small class="text-muted"><?= htmlspecialchars($item['product_code']) ?></small>
                                        </td>
                                        <td><?= htmlspecialchars($item['warehouse_name']) ?></td>
                                        <td class="text-center"><?= number_format($item['qty'], 2) ?></td>
                                        <td class="text-end"><?= number_format($item['rate'], 2) ?></td>
                                        <td class="text-end"><?= number_format($item['amount'], 2) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer bg-light text-end py-3">
                    <h5 class="mb-0"><strong>Total: <?= number_format($receive['total_amount'], 2) ?> Tk</strong></h5>
                </div>
            </div>

            <!-- Remarks -->
            <?php if (!empty($receive['remarks'])): ?>
                <div class="card shadow-sm border-0 mt-4">
                    <div class="card-header bg-light py-3">
                        <h6 class="mb-0 fw-semibold"><i class="fas fa-comment-dots me-2"></i> Remarks</h6>
                    </div>
                    <div class="card-body">
                        <?= nl2br(htmlspecialchars($receive['remarks'])) ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Actions -->
            <div class="text-center mt-4">
                <button onclick="window.print()" class="btn btn-primary px-4">
                    <i class="fas fa-print me-2"></i> Print GRN
                </button>
            </div>

        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
require_once '../app/views/layouts/main.php';
?>