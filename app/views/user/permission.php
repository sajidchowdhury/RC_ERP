<?php
ob_start();
$title = $title ?? 'Menu Permissions';
$user = $user ?? [];
$menus = $menus ?? [];
$currentPermissions = $currentPermissions ?? [];
$userId = (int)($user['id'] ?? 0);

function buildMenuRowsPermission(array $menu, array $currentPermissions, int $level = 0): string
{
    $hasView = !empty($currentPermissions[$menu['id']]['view']);
    $hasEdit = !empty($currentPermissions[$menu['id']]['edit']);
    $indent = str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', $level);

    $html = '<tr class="menu-row" data-menu-id="' . (int)$menu['id'] . '" data-level="' . $level . '">';
    $html .= '<td>' . $indent . '<strong>' . htmlspecialchars($menu['menu_name'] ?? '', ENT_QUOTES) . '</strong></td>';
    $html .= '<td class="text-center"><input type="checkbox" class="form-check-input view-check" name="permissions[' . (int)$menu['id'] . '][view]" value="1"' . ($hasView ? ' checked' : '') . '></td>';
    $html .= '<td class="text-center"><input type="checkbox" class="form-check-input edit-check" name="permissions[' . (int)$menu['id'] . '][edit]" value="1"' . ($hasEdit ? ' checked' : '') . '></td>';
    $html .= '</tr>';

    if (!empty($menu['children'])) {
        foreach ($menu['children'] as $child) {
            $html .= buildMenuRowsPermission($child, $currentPermissions, $level + 1);
        }
    }

    return $html;
}

$menuRowsHtml = '';
foreach ($menus as $menu) {
    $menuRowsHtml .= buildMenuRowsPermission($menu, $currentPermissions);
}
?>
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/branch-index.css">
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/user-theme.css">

<div class="branch-hub user-theme container-fluid py-2">
    <header class="branch-hub-hero">
        <div>
            <h1><i class="fas fa-shield-halved me-2"></i>Menu permissions</h1>
            <p>
                <strong><?= htmlspecialchars($user['employee_name'] ?? '', ENT_QUOTES) ?></strong>
                · <?= htmlspecialchars($user['username'] ?? '', ENT_QUOTES) ?>
            </p>
            <span class="hero-badge"><i class="fas fa-list"></i> View &amp; edit per menu</span>
        </div>
        <div class="branch-hub-actions">
            <a href="<?= BASE_URL ?>user/edit/<?= $userId ?>" class="btn btn-outline-light btn-sm">
                <i class="fas fa-user-pen me-1"></i> Edit user
            </a>
            <a href="<?= BASE_URL ?>user" class="btn btn-light btn-sm">
                <i class="fas fa-arrow-left me-1"></i> Users
            </a>
        </div>
    </header>

    <div class="branch-hub-panel">
        <form method="POST" action="<?= BASE_URL ?>user/save_permissions" id="permissionForm">
            <input type="hidden" name="user_id" value="<?= $userId ?>">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES) ?>">

            <div class="user-perm-toolbar">
                <button type="button" id="checkAllViewBtn" class="btn btn-sm btn-outline-primary">
                    <i class="fas fa-eye me-1"></i> All view
                </button>
                <button type="button" id="checkAllEditBtn" class="btn btn-sm btn-outline-secondary">
                    <i class="fas fa-pen me-1"></i> All edit
                </button>
                <button type="button" id="uncheckAllBtn" class="btn btn-sm btn-outline-danger">
                    <i class="fas fa-times me-1"></i> Clear all
                </button>
            </div>

            <div class="table-responsive">
                <table class="table table-hover mb-0 user-perm-table" id="permissionTable">
                    <thead>
                        <tr>
                            <th style="width:55%;">Menu</th>
                            <th class="text-center" style="width:22.5%;">View</th>
                            <th class="text-center" style="width:22.5%;">Edit</th>
                        </tr>
                    </thead>
                    <tbody><?= $menuRowsHtml ?></tbody>
                </table>
            </div>

            <div class="branch-form-footer m-3">
                <button type="button" id="savePermissionsBtn" class="btn btn-primary px-4">
                    <i class="fas fa-save me-1"></i> Save permissions
                </button>
                <a href="<?= BASE_URL ?>user" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>

    <div class="branch-aside-tip mx-0 mt-2" style="max-width:100%;">
        <i class="fas fa-lightbulb me-1"></i>
        Checking a parent can cascade to child menus of the same type (view or edit). Changes apply on save.
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const table = document.getElementById('permissionTable');
    if (!table) return;

    document.getElementById('checkAllViewBtn')?.addEventListener('click', () => {
        table.querySelectorAll('.view-check').forEach(cb => { cb.checked = true; });
    });
    document.getElementById('checkAllEditBtn')?.addEventListener('click', () => {
        table.querySelectorAll('.edit-check').forEach(cb => { cb.checked = true; });
    });
    document.getElementById('uncheckAllBtn')?.addEventListener('click', () => {
        table.querySelectorAll('input[type=checkbox]').forEach(cb => { cb.checked = false; });
    });

    table.addEventListener('change', function(e) {
        const checkbox = e.target;
        const row = checkbox.closest('tr');
        if (!row) return;
        const level = parseInt(row.dataset.level || '0', 10);
        const isView = checkbox.classList.contains('view-check');
        if (!checkbox.checked) return;
        let nextRow = row.nextElementSibling;
        while (nextRow && parseInt(nextRow.dataset.level || '0', 10) > level) {
            const target = nextRow.querySelector(isView ? '.view-check' : '.edit-check');
            if (target) target.checked = true;
            nextRow = nextRow.nextElementSibling;
        }
    });

    const saveBtn = document.getElementById('savePermissionsBtn');
    const form = document.getElementById('permissionForm');
    if (saveBtn && form) {
        saveBtn.addEventListener('click', function() {
            Swal.fire({
                title: 'Save permissions?',
                text: 'This will immediately update this user\'s access rights.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Yes, save'
            }).then((result) => {
                if (result.isConfirmed) form.submit();
            });
        });
    }
});
</script>

<?php
$content = ob_get_clean();
require_once '../app/views/layouts/main.php';