<?php
// app/views/dashboard/index.php
$title = 'Dashboard - Remote Center ERP';
$content = '
<div class="container-fluid p-4">

    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold text-primary">
            <i class="fas fa-tachometer-alt"></i> Dashboard
        </h2>
        <div>
            <span class="badge bg-success fs-6">Today: <strong>' . date('d M, Y') . '</strong></span>
            <button onclick="location.reload()" class="btn btn-outline-primary ms-3">
                <i class="fas fa-sync"></i> Refresh
            </button>
        </div>
    </div>

    <!-- Quick Stats -->
    <div class="row g-4 mb-5">
        <div class="col-xl-3 col-md-6">
            <div class="card dashboard-card bg-primary text-white h-100">
                <div class="card-body">
                    <h5>Today\'s Sales</h5>
                    <h2 class="stat-number">৳ ' . number_format($today_sales) . '</h2>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card dashboard-card bg-success text-white h-100">
                <div class="card-body">
                    <h5>Today\'s Collection</h5>
                    <h2 class="stat-number">৳ ' . number_format($today_collection) . '</h2>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card dashboard-card bg-warning text-dark h-100">
                <div class="card-body">
                    <h5>Today\'s Purchase</h5>
                    <h2 class="stat-number">৳ ' . number_format($today_purchase) . '</h2>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card dashboard-card bg-info text-white h-100">
                <div class="card-body">
                    <h5>Total Cash</h5>
                    <h2 class="stat-number">৳ ' . number_format($total_cash) . '</h2>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Left Column -->
        <div class="col-lg-8">

            <!-- Branch Performance -->
            <div class="card dashboard-card mb-4">
                <div class="card-header bg-light">
                    <h5 class="section-header">Branch Performance Today</h5>
                </div>
                <div class="card-body">
                    <div class="row text-center">';
                    
                    if (!empty($branch_performance)) {
                        foreach ($branch_performance as $b) {
                            $content .= '
                        <div class="col-md-3 border-end">
                            <h6>' . htmlspecialchars($b["branch_name"]) . '</h6>
                            <h4 class="text-success">৳ ' . number_format($b["sales"]) . '</h4>
                        </div>';
                        }
                    }

$content .= '
                    </div>
                </div>
            </div>

        </div>

        <!-- Right Column -->
        <div class="col-lg-4">

            <!-- Critical Alerts -->
            <div class="card dashboard-card mb-4">
                <div class="card-header bg-danger text-white">
                    <h5><i class="fas fa-bell"></i> Critical Alerts</h5>
                </div>
                <div class="card-body">';

                if (!empty($low_stock)) {
                    foreach ($low_stock as $item) {
                        $content .= '
                    <div class="alert alert-warning py-2">
                        <strong>Low Stock:</strong> ' . htmlspecialchars($item["product_name"]) . ' 
                        (' . $item["qty"] . ' left)
                    </div>';
                    }
                }

                if (!empty($pending_demands)) {
                    foreach ($pending_demands as $d) {
                        $content .= '
                    <div class="alert alert-info py-2">
                        Pending Demand: ' . $d["demand_code"] . ' 
                        (' . $d["from_branch"] . ' → ' . $d["to_branch"] . ')
                    </div>';
                    }
                }

$content .= '
                </div>
            </div>

            <!-- Performance Scale -->
            <div class="card dashboard-card mb-4">
                <div class="card-header">
                    <h5 class="section-header">Your Today\'s Performance</h5>
                </div>
                <div class="card-body text-center">
                    <h1 class="display-4 fw-bold text-success">92%</h1>
                    <p class="text-muted">Excellent Performance</p>
                    <div class="progress" style="height: 15px;">
                        <div class="progress-bar bg-success" style="width: 92%"></div>
                    </div>
                    <small class="text-muted mt-2 d-block">Target Achieved</small>
                </div>
            </div>

        </div>
    </div>
</div>

<style>
    .dashboard-card { 
        transition: all 0.3s; 
        border: none; 
        box-shadow: 0 4px 15px rgba(0,0,0,0.08); 
    }
    .dashboard-card:hover { 
        transform: translateY(-5px); 
        box-shadow: 0 8px 25px rgba(0,0,0,0.12); 
    }
    .stat-number { 
        font-size: 2.2rem; 
        font-weight: 700; 
    }
    .section-header { 
        border-left: 5px solid #0d6efd; 
        padding-left: 15px; 
    }
</style>
';

require_once '../app/views/layouts/main.php';
?>