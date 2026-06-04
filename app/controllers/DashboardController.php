<?php
// app/controllers/DashboardController.php

require_once '../core/BaseController.php';
require_once '../app/models/DashboardModel.php';

class DashboardController extends BaseController {

    private $dashboardModel;

    public function __construct() {
        $this->requireLogin();
        $this->dashboardModel = new DashboardModel();
    }

    public function index() {
        $data = [
            'title' => 'Dashboard - Remote Center ERP',
            'today_sales'      => $this->dashboardModel->getTodaySales(),
            'today_collection' => $this->dashboardModel->getTodayCollection(),
            'today_purchase'   => $this->dashboardModel->getTodayPurchase(),
            'total_cash'       => $this->dashboardModel->getTotalCash(),
            'branch_performance' => $this->dashboardModel->getBranchPerformanceToday(),
            'low_stock'        => $this->dashboardModel->getLowStockItems(),
            'pending_demands'  => $this->dashboardModel->getPendingDemands(),
            'today_date'       => date('d M, Y')
        ];

        $this->view('dashboard/index', $data);
    }
}