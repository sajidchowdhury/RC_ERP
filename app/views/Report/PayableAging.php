<?php
// views/report/PayableAging.php

$a = new Helper();

$content = '
<div class="card shadow-sm">
    <div class="card-header bg-light d-flex justify-content-between align-items-center">
        <h5 class="mb-0">
            <i class="fas fa-clock me-2"></i> Payable Aging Report
        </h5>
        <button class="btn btn-sm btn-outline-secondary" data-toggle="collapse" href="#filterPanel">
            <i class="fas fa-chevron-down"></i>
        </button>
    </div>

    <div id="filterPanel" class="collapse show">
        <div class="card-body">
            <form method="get">
                <div class="row g-3">

                    <div class="col-md-3">
                        <label class="form-label">As Of Date</label>
                        <input type="date" name="as_of_date" class="form-control" value="' . htmlspecialchars($as_of_date) . '">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Branch</label>
                        <select name="branch_id" class="form-select select2">
                            <option value="">All Branches</option>';

foreach($branches as $b) {
    $selected = ($branch_id == $b['id']) ? 'selected' : '';
    $content .= '
                            <option value="' . $b['id'] . '" ' . $selected . '>' . htmlspecialchars($b['branch_name']) . '</option>';
}

$content .= '
                        </select>
                    </div>

                    <div class="col-12 d-flex gap-2 mt-3">
                        <button type="submit" name="search" value="1" class="btn btn-primary">
                            <i class="fas fa-search"></i> Generate Aging
                        </button>
                        <button type="submit" name="export" value="1" class="btn btn-success">
                            <i class="fas fa-file-excel"></i> Export Excel
                        </button>
                    </div>

                </div>
            </form>
        </div>
    </div>
</div>
';

if (isset($aging_data) && !empty($aging_data)) {

    $content .= '
<div class="card shadow-sm mt-4">
    <div class="card-header bg-dark text-white">
        <strong>Payable Aging as of ' . date('d-m-Y', strtotime($as_of_date)) . '</strong>
    </div>
    <div class="table-responsive">
        <table class="table table-bordered table-hover">
            <thead class="table-dark">
                <tr>
                    <th>Supplier Code</th>
                    <th>Supplier Name</th>
                    <th>Mobile</th>
                    <th>Branch</th>
                    <th class="text-end">0-30 Days</th>
                    <th class="text-end">31-60 Days</th>
                    <th class="text-end">61-90 Days</th>
                    <th class="text-end">90+ Days</th>
                    <th class="text-end">Total Payable</th>
                </tr>
            </thead>
            <tbody>';

    $grandTotal = 0;

    foreach($aging_data as $row) {
        $total = $row['total_payable'];
        $grandTotal += $total;

        $content .= '
                <tr>
                    <td>' . htmlspecialchars($row['supplier_code']) . '</td>
                    <td>' . htmlspecialchars($row['supplier_name']) . '</td>
                    <td>' . htmlspecialchars($row['mobile'] ?? '-') . '</td>
                    <td>' . htmlspecialchars($row['branch_name']) . '</td>
                    <td class="text-end">' . number_format($row['bucket_0_30'], 2) . '</td>
                    <td class="text-end">' . number_format($row['bucket_31_60'], 2) . '</td>
                    <td class="text-end">' . number_format($row['bucket_61_90'], 2) . '</td>
                    <td class="text-end">' . number_format($row['bucket_90_plus'], 2) . '</td>
                    <td class="text-end fw-bold">' . number_format($total, 2) . '</td>
                </tr>';
    }

    $content .= '
            </tbody>
            <tfoot class="table-dark">
                <tr>
                    <th colspan="8" class="text-end">GRAND TOTAL</th>
                    <th class="text-end">' . number_format($grandTotal, 2) . '</th>
                </tr>
            </tfoot>
        </table>
    </div>
</div>';
} 
elseif (isset($_GET['search'])) {
    $content .= '
<div class="alert alert-info mt-4">No outstanding payables found as of the selected date.</div>';
}

$content .= '
<script>
$(document).ready(function() {
    $(".select2").select2();
});
</script>
';

require_once '../app/views/layouts/main.php';
?>