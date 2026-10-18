<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <div><h4 class="mb-1">Arattai User Mapping</h4><div class="text-muted">Map each ERP role to the numeric Arattai User ID used for transaction messages.</div></div>
    <a class="btn btn-outline-primary" href="<?= site_url('admin/arattai-messages') ?>">Message Audit</a>
</div>

<div class="alert alert-info">Ask the person to send <strong>Hi</strong> to the Arattai Business Number, then copy their captured User ID below. A mobile number is not an Arattai User ID.</div>

<div class="card mb-3"><div class="card-body">
    <form method="post" action="<?= site_url('admin/arattai-users') ?>" class="row g-3 align-items-end">
        <?= csrf_field() ?>
        <div class="col-md-3"><label class="form-label">Role</label><select class="form-select" id="arattai-role" name="entity_type" required>
            <option value="">Select role</option><option value="admin">Admin</option><option value="customer">Customer</option><option value="karigar">Karigar</option><option value="follower">Follower</option>
        </select></div>
        <div class="col-md-4"><label class="form-label">ERP user</label><select class="form-select" id="arattai-entity" name="entity_id" required disabled><option value="">Select role first</option></select></div>
        <div class="col-md-3"><label class="form-label">Arattai User ID</label><input class="form-control" name="arattai_user_id" list="captured-arattai-users" inputmode="numeric" placeholder="Example: 20034820986" required></div>
        <div class="col-md-2"><button class="btn btn-primary w-100">Save Mapping</button></div>
        <div class="col-12 d-none" id="approval-admin-wrap"><div class="form-check"><input class="form-check-input" type="checkbox" value="1" id="is-approval-admin" name="is_approval_admin"><label class="form-check-label" for="is-approval-admin">Send pending approval requests to this admin</label></div></div>
    </form>
    <datalist id="captured-arattai-users"><?php foreach ($capturedUsers as $user): ?><option value="<?= esc((string) $user['arattai_user_id']) ?>"><?= esc(trim((string) (($user['name'] ?? '') ?: ($user['phone'] ?? 'Captured user')))) ?></option><?php endforeach; ?></datalist>
</div></div>

<div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
    <thead><tr><th>Role</th><th>ERP user</th><th>Arattai User ID</th><th>Approval admin</th><th>Status</th><th>Updated</th><th></th></tr></thead><tbody>
    <?php if ($rows === []): ?><tr><td colspan="7" class="text-center text-muted py-5">No mappings saved yet.</td></tr><?php endif; ?>
    <?php foreach ($rows as $row): ?><tr>
        <td><span class="badge bg-light text-dark text-uppercase"><?= esc((string) $row['entity_type']) ?></span></td>
        <td><?= esc((string) $row['entity_label']) ?><div class="small text-muted">ERP ID <?= (int) $row['entity_id'] ?></div></td>
        <td><code><?= esc((string) $row['arattai_user_id']) ?></code></td>
        <td><?= (int) $row['is_approval_admin'] === 1 ? '<span class="badge bg-success">Yes</span>' : '<span class="text-muted">No</span>' ?></td>
        <td><span class="badge bg-<?= (int) $row['is_active'] === 1 ? 'success' : 'secondary' ?>"><?= (int) $row['is_active'] === 1 ? 'Active' : 'Inactive' ?></span></td>
        <td class="small text-nowrap"><?= esc((string) (($row['updated_at'] ?? '') ?: ($row['created_at'] ?? ''))) ?></td>
        <td><form method="post" action="<?= site_url('admin/arattai-users/' . (int) $row['id'] . '/toggle') ?>"><?= csrf_field() ?><button class="btn btn-sm btn-outline-secondary"><?= (int) $row['is_active'] === 1 ? 'Disable' : 'Enable' ?></button></form></td>
    </tr><?php endforeach; ?>
    </tbody></table></div></div>

<script>
(() => {
    const role = document.getElementById('arattai-role');
    const entity = document.getElementById('arattai-entity');
    const approval = document.getElementById('approval-admin-wrap');
    role.addEventListener('change', async () => {
        const value = role.value;
        approval.classList.toggle('d-none', value !== 'admin');
        entity.disabled = true;
        entity.innerHTML = '<option value="">Loading...</option>';
        if (!value) { entity.innerHTML = '<option value="">Select role first</option>'; return; }
        try {
            const response = await fetch(<?= json_encode(site_url('admin/arattai-users/entities')) ?> + '?type=' + encodeURIComponent(value), {headers: {'Accept': 'application/json'}});
            const data = await response.json();
            entity.innerHTML = '<option value="">Select ERP user</option>' + (data.entities || []).map(item => '<option value="' + item.id + '">' + String(item.label).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c])) + '</option>').join('');
            entity.disabled = false;
        } catch (error) {
            entity.innerHTML = '<option value="">Could not load users</option>';
        }
    });
})();
</script>
<?= $this->endSection() ?>
