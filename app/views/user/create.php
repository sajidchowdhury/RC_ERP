<?php
$title = 'Create New User';

$content = '
<div class="container-fluid py-3">
    <div class="card shadow">
        <div class="card-header text-black  d-flex justify-content-between align-items-center">
            <h4 class="card-title mb-0">Create System User</h4>
        </div>
        <div class="card-body">
            <form method="POST" action="' . BASE_URL . 'user/store">
                <input type="hidden" name="csrf_token" value="' . htmlspecialchars($_SESSION['csrf_token'] ?? '') . '">
                <div class="row">
                    <div class="col-md-6">
                        <label>Employee <span class="text-danger">*</span></label>
                        <select name="employee_id" class="form-control" required>
                            <option value="">Select Employee</option>';
                            
if (!empty($preSelectedEmployee)) {
    $content .= '<option value="' . $preSelectedEmployee['id'] . '" selected>' 
                . htmlspecialchars($preSelectedEmployee['name']) . ' (' 
                . htmlspecialchars($preSelectedEmployee['employee_code']) . ')</option>';
} else {
    foreach ($employees as $emp) {
        $content .= '<option value="' . $emp['id'] . '">' 
                    . htmlspecialchars($emp['name']) . ' (' 
                    . htmlspecialchars($emp['employee_code']) . ')</option>';
    }
}

$content .= '
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label>Username <span class="text-danger">*</span></label>
                        <input type="text" name="username" class="form-control" required>
                    </div>
                </div>

                <div class="row mt-3">
                    <div class="col-md-6">
                        <label>Password <span class="text-danger">*</span></label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label>Confirm Password <span class="text-danger">*</span></label>
                        <input type="password" name="confirm_password" class="form-control" required>
                    </div>
                </div>

                <div class="text-end mt-4">
                    <a href="' . BASE_URL . 'user" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">Create User</button>
                </div>
            </form>
        </div>
    </div>
</div>';

require_once '../app/views/layouts/main.php';
?>