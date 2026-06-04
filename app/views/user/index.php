<?php
ob_start();
$title = $title ?? 'System users';
$branches = $branches ?? [];
$stats = $stats ?? ['active' => 0, 'inactive' => 0, 'total' => 0];
?>
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/branch-index.css">
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/user-theme.css">

<div class="branch-hub user-theme container-fluid py-2">
    <header class="branch-hub-hero">
        <div>
            <h1><i class="fas fa-users-cog me-2"></i>System users</h1>
            <p>Login accounts linked to employees — permissions control menu access.</p>
        </div>
        <div class="branch-hub-actions">
            <a href="<?= BASE_URL ?>user/audit" class="btn btn-outline-light btn-sm"><i class="fas fa-clock-rotate-left me-1"></i> Audit</a>
            <a href="<?= BASE_URL ?>user/create" class="btn btn-light btn-sm"><i class="fas fa-plus me-1"></i> New user</a>
        </div>
    </header>

    <div class="branch-hub-stats">
        <div class="branch-stat-card">
            <div class="branch-stat-icon teal"><i class="fas fa-user-check"></i></div>
            <div><div class="stat-value"><?= (int)$stats['active'] ?></div><div class="stat-label">Active logins</div></div>
        </div>
        <div class="branch-stat-card">
            <div class="branch-stat-icon slate"><i class="fas fa-user-slash"></i></div>
            <div><div class="stat-value"><?= (int)$stats['inactive'] ?></div><div class="stat-label">Inactive</div></div>
        </div>
        <div class="branch-stat-card">
            <div class="branch-stat-icon indigo"><i class="fas fa-users"></i></div>
            <div><div class="stat-value"><?= (int)$stats['total'] ?></div><div class="stat-label">Total accounts</div></div>
        </div>
    </div>

    <nav class="branch-hub-quick">
        <a href="<?= BASE_URL ?>employee"><i class="fas fa-id-badge"></i> Employees</a>
        <a href="<?= BASE_URL ?>user/change_password"><i class="fas fa-key"></i> Change my password</a>
        <a href="<?= BASE_URL ?>branch"><i class="fas fa-sitemap"></i> Branches</a>
    </nav>

    <div class="branch-hub-panel">
        <div class="branch-hub-filters">
            <div class="row g-3 align-items-end">
                <div class="col-sm-4 col-md-3">
                    <div class="filter-label">Branch</div>
                    <select id="filterBranch" class="form-select form-select-sm">
                        <option value="">All</option>
                        <?php foreach ($branches as $b): ?>
                            <option value="<?= htmlspecialchars($b['branch_name'] ?? '', ENT_QUOTES) ?>"><?= htmlspecialchars($b['branch_name'] ?? '', ENT_QUOTES) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-sm-4 col-md-2">
                    <div class="filter-label">Status</div>
                    <select id="filterStatus" class="form-select form-select-sm">
                        <option value="">All</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
                <div class="col-sm-auto">
                    <button type="button" id="clearFilters" class="btn btn-outline-secondary btn-sm btn-clear"><i class="fas fa-rotate-left me-1"></i> Reset</button>
                </div>
            </div>
        </div>

        <div class="branch-hub-table-wrap">
            <table class="table table-borderless mb-0 w-100" id="userTable">
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Username</th>
                        <th>Branch</th>
                        <th>Status</th>
                        <th>Last login</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<script>
const USR_BASE = "<?= BASE_URL ?>user";
function usrEsc(s) { return s ? String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;') : ''; }
function usrStatus(v) {
    return parseInt(v,10)===1 ? '<span class="branch-status-pill active"><span class="dot"></span> Active</span>' : '<span class="branch-status-pill inactive"><span class="dot"></span> Inactive</span>';
}
function usrActions(row) {
    const id = row.id, un = (row.username||'').replace(/'/g,"\\'");
    let h = '<div class="branch-action-bar">';
    h += '<a href="'+USR_BASE+'/edit/'+id+'" class="btn-action edit" title="Edit"><i class="fas fa-pen"></i></a>';
    h += '<a href="'+USR_BASE+'/permission/'+id+'" class="btn-action" title="Permissions"><i class="fas fa-shield-halved"></i></a>';
    h += '<button type="button" class="btn-action toggle-off" onclick="toggleUserStatus('+id+','+row.is_active+')"><i class="fas fa-power-off"></i></button>';
    h += '<button type="button" class="btn-action toggle-off" onclick="deleteUser('+id+',\''+un+'\')"><i class="fas fa-trash"></i></button>';
    return h + '</div>';
}
$(function() {
    $('#userTable').DataTable({
        processing: true, serverSide: true,
        ajax: { url: USR_BASE, data: d => { d.filterBranch = $('#filterBranch').val(); d.filterStatus = $('#filterStatus').val(); }},
        pageLength: 25, order: [[0,'asc']],
        dom: '<"branch-dt-toolbar"lf>Brt<"branch-dt-footer"ip>',
        buttons: ['copy','excel','pdf'],
        columns: [
            { data: 'employee_name', render: (d,t,r) => '<div class="branch-name-cell"><div class="branch-avatar">'+usrEsc((d||'?').charAt(0).toUpperCase())+'</div><div><div class="name">'+usrEsc(d)+'</div><small class="text-muted">'+usrEsc(r.employee_code)+'</small></div></div>' },
            { data: 'username', render: d => '<span class="branch-code-pill">'+usrEsc(d)+'</span>' },
            { data: 'branch_name', defaultContent: '—' },
            { data: 'is_active', render: usrStatus },
            { data: 'last_login', render: d => d ? '<small>'+usrEsc(d)+'</small>' : '<span class="text-muted">—</span>' },
            { data: 'id', orderable: false, className: 'text-center', render: (d,t,r) => usrActions(r) }
        ]
    });
    $('#filterBranch,#filterStatus').on('change', () => $('#userTable').DataTable().ajax.reload());
    $('#clearFilters').on('click', () => { $('#filterBranch,#filterStatus').val(''); $('#userTable').DataTable().ajax.reload(); });
});
function toggleUserStatus(id, isActive) {
    Swal.fire({ title: isActive ? 'Deactivate user?' : 'Activate user?', icon: 'warning', showCancelButton: true }).then(r => {
        if (r.isConfirmed) location.href = USR_BASE + '/toggle/' + id;
    });
}
function deleteUser(id, username) {
    Swal.fire({ title: 'Delete user?', html: 'Soft delete <strong>'+username+'</strong>', icon: 'warning', showCancelButton: true, confirmButtonColor: '#d33' }).then(r => {
        if (r.isConfirmed) location.href = USR_BASE + '/delete/' + id;
    });
}
</script>
<?php
$content = ob_get_clean();
require_once '../app/views/layouts/main.php';