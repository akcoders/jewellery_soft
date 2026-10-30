<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<?php
$keys = is_array($keys ?? null) ? $keys : [];
$users = is_array($users ?? null) ? $users : [];
$flashError = session()->getFlashdata('error');
$flashSuccess = session()->getFlashdata('success');
$newKey = session()->getFlashdata('mcp_new_key');
$oldScopes = (array) old('scopes', ['read']);
$oldExpiry = (string) old('expires_days', '365');
$mcpUrl = (string) ($mcpUrl ?? '');
?>

<div class="erp-page-toolbar mb-3">
    <div>
        <span class="erp-eyebrow">Integrations</span>
        <h4 class="mb-1">MCP API Keys</h4>
        <p class="mb-0">Give each user a separate key for Claude and other AI clients. A key works with the same permissions as its ERP user. Apps that connect with OAuth (ChatGPT, claude.ai) appear here as OAuth connections after the user allows them.</p>
    </div>
</div>

<?php if (! empty($flashError)): ?>
    <div class="alert alert-danger"><?= esc((string) $flashError) ?></div>
<?php endif; ?>
<?php if (! empty($flashSuccess)): ?>
    <div class="alert alert-success"><?= esc((string) $flashSuccess) ?></div>
<?php endif; ?>

<?php if (! empty($newKey)): ?>
    <div class="card border-success mb-3">
        <div class="card-body">
            <h5 class="card-title"><i class="fe fe-key me-2"></i>New API key</h5>
            <p class="text-muted mb-2">Copy this key now and give it to the user. Only its hash is stored, so it cannot be shown again.</p>
            <div class="input-group">
                <input class="form-control font-monospace" type="text" id="mcp-new-key" value="<?= esc((string) $newKey) ?>" readonly>
                <button class="btn btn-success" type="button" onclick="(function(b){var i=document.getElementById('mcp-new-key');i.select();(navigator.clipboard?navigator.clipboard.writeText(i.value):Promise.reject()).then(function(){b.textContent='Copied';},function(){document.execCommand('copy');b.textContent='Copied';});})(this)">Copy</button>
            </div>
            <div class="small text-muted mt-2">
                Use it as the header <code>Authorization: Bearer &lt;key&gt;</code><?php if ($mcpUrl !== ''): ?> on <code><?= esc($mcpUrl) ?></code><?php endif; ?>.
            </div>
        </div>
    </div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-xl-4">
        <div class="card h-100">
            <div class="card-header"><h5 class="card-title mb-0"><i class="fe fe-plus-circle me-2"></i>Generate key</h5></div>
            <div class="card-body">
                <form action="<?= site_url('admin/mcp-keys') ?>" method="post">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label" for="mcp-user">ERP user <span class="text-danger">*</span></label>
                        <select class="form-select" id="mcp-user" name="admin_user_id" required>
                            <option value="">Select user</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?= (int) $user['id'] ?>" <?= (string) old('admin_user_id') === (string) $user['id'] ? 'selected' : '' ?>>
                                    <?= esc((string) $user['name']) ?><?= ! empty($user['email']) ? ' (' . esc((string) $user['email']) . ')' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="mcp-name">Key name <span class="text-danger">*</span></label>
                        <input class="form-control" type="text" id="mcp-name" name="name" maxlength="40" placeholder="e.g. Claude Desktop" value="<?= esc((string) old('name', 'Claude')) ?>" required>
                        <div class="form-text">Must be unique among the user's active keys.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label d-block">Scopes <span class="text-danger">*</span></label>
                        <?php foreach (($scopes ?? []) as $scope): ?>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="checkbox" id="scope-<?= esc($scope) ?>" name="scopes[]" value="<?= esc($scope) ?>" <?= in_array($scope, $oldScopes, true) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="scope-<?= esc($scope) ?>"><?= esc(ucfirst($scope)) ?></label>
                            </div>
                        <?php endforeach; ?>
                        <div class="form-text">Read = view data. Write = create and update records. SQL = read-only SQL tools (only if enabled on the MCP server).</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="mcp-expiry">Expires after</label>
                        <select class="form-select" id="mcp-expiry" name="expires_days">
                            <?php foreach (($expiryDays ?? []) as $days): ?>
                                <option value="<?= (int) $days ?>" <?= $oldExpiry === (string) $days ? 'selected' : '' ?>><?= (int) $days === 0 ? 'Never' : (int) $days . ' days' ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button class="btn btn-primary w-100" type="submit"><i class="fe fe-key me-1"></i> Generate key</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-xl-8">
        <div class="card h-100">
            <div class="card-header"><h5 class="card-title mb-0"><i class="fe fe-list me-2"></i>Issued keys</h5></div>
            <div class="card-body p-0">
                <?php if ($keys === []): ?>
                    <div class="p-3 text-muted">No keys issued yet.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 align-middle">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Key</th>
                                    <th>Scopes</th>
                                    <th>Expires</th>
                                    <th>Last used</th>
                                    <th>Status</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($keys as $key): ?>
                                    <?php
                                    $expired = ! empty($key['expires_at']) && strtotime((string) $key['expires_at']) <= time();
                                    if (! empty($key['revoked_at'])) {
                                        $badge = ['bg-secondary', 'Revoked'];
                                    } elseif ($expired) {
                                        $badge = ['bg-warning text-dark', 'Expired'];
                                    } elseif ((int) ($key['user_active'] ?? 0) !== 1) {
                                        $badge = ['bg-warning text-dark', 'User inactive'];
                                    } else {
                                        $badge = ['bg-success', 'Active'];
                                    }
                                    ?>
                                    <tr>
                                        <td>
                                            <div class="fw-semibold"><?= esc((string) ($key['user_name'] ?? ('#' . $key['admin_user_id']))) ?></div>
                                            <div class="small text-muted"><?= esc((string) ($key['user_email'] ?? '')) ?></div>
                                        </td>
                                        <td>
                                            <div><?= esc((string) $key['name']) ?></div>
                                            <?php if ((string) $key['key_prefix'] === \App\Services\McpApiKeyService::OAUTH_PREFIX): ?>
                                                <span class="badge bg-info text-dark">OAuth connection</span>
                                            <?php else: ?>
                                                <code class="small"><?= esc((string) $key['key_prefix']) ?>…</code>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= esc(str_replace(',', ', ', (string) $key['scopes'])) ?></td>
                                        <td class="small"><?= empty($key['expires_at']) ? 'Never' : esc(date('d M Y', strtotime((string) $key['expires_at']))) ?></td>
                                        <td class="small"><?= empty($key['last_used_at']) ? '—' : esc(date('d M Y, h:i A', strtotime((string) $key['last_used_at']))) ?></td>
                                        <td><span class="badge <?= esc($badge[0]) ?>"><?= esc($badge[1]) ?></span></td>
                                        <td class="text-end">
                                            <?php if (empty($key['revoked_at'])): ?>
                                                <form action="<?= site_url('admin/mcp-keys/' . (int) $key['id'] . '/revoke') ?>" method="post" onsubmit="return confirm('Revoke this API key? Any AI client using it will stop working.');">
                                                    <?= csrf_field() ?>
                                                    <button class="btn btn-sm btn-outline-danger" type="submit">Revoke</button>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
