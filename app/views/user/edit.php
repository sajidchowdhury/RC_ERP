<?php
ob_start();
$title = $title ?? 'Edit User';
$user = $user ?? [];
$userId = (int)($user['id'] ?? 0);
$isActive = !empty($user['is_active']);
?>
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/branch-index.css">
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/user-theme.css">

<div class="branch-hub user-theme container-fluid py-2">
    <header class="branch-hub-hero">
        <div>
            <h1><i class="fas fa-user-pen me-2"></i>Edit user</h1>
            <p>Account <strong><?= htmlspecialchars($user['username'] ?? '', ENT_QUOTES) ?></strong> · <?= htmlspecialchars($user['employee_name'] ?? '', ENT_QUOTES) ?></p>
            <span class="hero-badge"><?= $isActive ? '<i class="fas fa-circle-check"></i> Active' : '<i class="fas fa-circle-xmark"></i> Inactive' ?></span>
        </div>
        <div class="branch-hub-actions">
            <a href="<?= BASE_URL ?>user/permission/<?= $userId ?>" class="btn btn-outline-light btn-sm"><i class="fas fa-shield-halved me-1"></i> Permissions</a>
            <a href="<?= BASE_URL ?>user" class="btn btn-outline-light btn-sm"><i class="fas fa-arrow-left me-1"></i> Back</a>
        </div>
    </header>

    <div class="branch-form-layout has-aside">
        <div class="branch-form-panel">
            <form method="POST" action="<?= BASE_URL ?>user/update/<?= $userId ?>">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES) ?>">
                <input type="hidden" name="employee_id" value="<?= (int)($user['employee_id'] ?? 0) ?>">

                <div class="branch-form-section">
                    <div class="branch-form-section-head">
                        <span class="icon-wrap indigo"><i class="fas fa-user"></i></span>
                        Account
                    </div>
                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label">Employee</label>
                            <input type="text" class="form-control bg-light" readonly
                                   value="<?= htmlspecialchars($user['employee_name'] ?? '', ENT_QUOTES) ?>">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="username">Username <span class="text-danger">*</span></label>
                            <input type="text" id="username" name="username" class="form-control" required
                                   value="<?= htmlspecialchars($user['username'] ?? '', ENT_QUOTES) ?>">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="password">New password</label>
                            <input type="password" id="password" name="password" class="form-control" autocomplete="new-password"
                                   placeholder="Leave blank to keep current">
                        </div>
                    </div>
                </div>

                <div class="branch-form-section">
                    <div class="branch-form-section-head">
                        <span class="icon-wrap teal"><i class="fas fa-power-off"></i></span>
                        Status
                    </div>
                    <div class="branch-status-toggle">
                        <div class="status-option">
                            <input type="radio" name="is_active" id="usrActive" value="1" <?= $isActive ? 'checked' : '' ?>>
                            <label for="usrActive" class="active-opt"><i class="fas fa-circle-check"></i> Active</label>
                        </div>
                        <div class="status-option">
                            <input type="radio" name="is_active" id="usrInactive" value="0" <?= !$isActive ? 'checked' : '' ?>>
                            <label for="usrInactive" class="inactive-opt"><i class="fas fa-circle-xmark"></i> Inactive</label>
                        </div>
                    </div>
                    <p class="small text-muted mb-0 mt-2">Cannot deactivate the last active user in the system.</p>
                </div>

                <div class="branch-form-footer">
                    <button type="submit" class="btn btn-primary px-4"><i class="fas fa-save me-1"></i> Save changes</button>
                    <a href="<?= BASE_URL ?>user" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>

        <aside class="branch-form-aside">
            <div class="aside-title">Quick links</div>
            <div class="d-grid gap-2">
                <a href="<?= BASE_URL ?>user/permission/<?= $userId ?>" class="btn btn-sm btn-outline-primary">
                    <i class="fas fa-shield-halved me-1"></i> Menu permissions
                </a>
                <a href="<?= BASE_URL ?>employee/edit/<?= (int)($user['employee_id'] ?? 0) ?>" class="btn btn-sm btn-outline-secondary">
                    <i class="fas fa-id-badge me-1"></i> Edit employee
                </a>
            </div>
            <div class="branch-aside-tip mt-3">
                <i class="fas fa-lightbulb me-1"></i>
                Permissions apply immediately after save on the permission screen.
            </div>
        </aside>
    </div>
</div>
<?php
$content = ob_get_clean();
require_once '../app/views/layouts/main.php';