<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('content') ?>
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">Company Settings</h5>
    </div>
    <div class="card-body">
        <form method="post" action="<?= site_url('admin/company-settings') ?>" enctype="multipart/form-data">
            <?= csrf_field() ?>

            <div class="row g-3">
                <div class="col-12">
                    <h6 class="mb-1">Main Company Details</h6>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Company Name</label>
                    <input type="text" name="company_name" class="form-control" value="<?= esc((string) old('company_name', (string) ($setting['company_name'] ?? ''))) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Phone</label>
                    <input type="text" name="phone" class="form-control" value="<?= esc((string) old('phone', (string) ($setting['phone'] ?? ''))) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Email</label>
                    <input type="text" name="email" class="form-control" value="<?= esc((string) old('email', (string) ($setting['email'] ?? ''))) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">GSTIN</label>
                    <input type="text" name="gstin" class="form-control" value="<?= esc((string) old('gstin', (string) ($setting['gstin'] ?? ''))) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">City</label>
                    <input type="text" name="city" class="form-control" value="<?= esc((string) old('city', (string) ($setting['city'] ?? ''))) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">State</label>
                    <input type="text" name="state" class="form-control" value="<?= esc((string) old('state', (string) ($setting['state'] ?? ''))) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Pincode</label>
                    <input type="text" name="pincode" class="form-control" value="<?= esc((string) old('pincode', (string) ($setting['pincode'] ?? ''))) ?>">
                </div>
                <div class="col-md-8">
                    <label class="form-label">Address</label>
                    <input type="text" name="address_line" class="form-control" value="<?= esc((string) old('address_line', (string) ($setting['address_line'] ?? ''))) ?>">
                </div>

                <div class="col-md-3">
                    <label class="form-label">Issuement Prefix</label>
                    <input type="text" name="issuement_suffix" class="form-control" value="<?= esc((string) old('issuement_suffix', (string) ($setting['issuement_suffix'] ?? 'ISS'))) ?>">
                    <small class="text-muted">Slash is allowed, for example ISS/ or AB/ISS/.</small>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Issuement Minimum Start</label>
                    <input type="number" min="1" max="999999999" step="1" name="issuement_start_count" class="form-control" required value="<?= esc((string) old('issuement_start_count', (string) ($setting['issuement_start_count'] ?? '1'))) ?>">
                    <small class="text-muted">Minimum serial used for the next auto voucher.</small>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Last Issuement Number</label>
                    <input type="number" min="0" max="999999999" step="1" name="issuement_last_number" class="form-control" value="<?= esc((string) old('issuement_last_number', (string) ($setting['issuement_last_number'] ?? '0'))) ?>">
                    <small class="text-muted">The next issuement will use this number + 1.</small>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Delivery Challan Prefix</label>
                    <input type="text" name="delivery_challan_suffix" class="form-control" value="<?= esc((string) old('delivery_challan_suffix', (string) ($setting['delivery_challan_suffix'] ?? 'DC'))) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Last Delivery Challan Number</label>
                    <input type="number" min="0" step="1" name="delivery_challan_last_number" class="form-control" value="<?= esc((string) old('delivery_challan_last_number', (string) ($setting['delivery_challan_last_number'] ?? '0'))) ?>">
                    <small class="text-muted">Next challan automatically uses this number + 1.</small>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Sale Bill Prefix</label>
                    <input type="text" name="sale_bill_suffix" class="form-control" value="<?= esc((string) old('sale_bill_suffix', (string) ($setting['sale_bill_suffix'] ?? 'SB'))) ?>">
                </div>

                <div class="col-12"><div class="border rounded-3 p-3 mt-2"><h6 class="mb-3">Delivery Challan Branch Addresses</h6><div class="row g-3">
                    <div class="col-md-6"><label class="form-label">Mumbai Address</label><textarea name="mumbai_branch_address" class="form-control" rows="4" placeholder="Full Mumbai dispatch address"><?= esc((string) old('mumbai_branch_address', (string) ($setting['mumbai_branch_address'] ?? ''))) ?></textarea></div>
                    <div class="col-md-6"><label class="form-label">Hyderabad Address</label><textarea name="hyderabad_branch_address" class="form-control" rows="4" placeholder="Full Hyderabad dispatch address"><?= esc((string) old('hyderabad_branch_address', (string) ($setting['hyderabad_branch_address'] ?? ''))) ?></textarea></div>
                </div></div></div>

                <div class="col-md-6">
                    <label class="form-label">Company Logo</label>
                    <input type="file" name="logo" class="form-control" accept=".jpg,.jpeg,.png,.webp">
                </div>
                <div class="col-md-6 d-flex align-items-end">
                    <?php if (! empty($setting['logo_path'])): ?>
                        <div class="border rounded p-2">
                            <img src="<?= base_url((string) $setting['logo_path']) ?>" alt="Company Logo" style="max-height:60px;">
                        </div>
                    <?php endif; ?>
                </div>

                <div class="col-12">
                    <div class="border rounded-3 p-3 mt-2">
                        <h6 class="mb-3">OneSignal Push Settings</h6>
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Enable Push</label>
                                <div class="form-check mt-2">
                                    <input class="form-check-input" type="checkbox" name="onesignal_enabled" id="onesignal_enabled" value="1" <?= ! empty($setting['onesignal_enabled']) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="onesignal_enabled">Enable OneSignal</label>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">OneSignal App ID</label>
                                <input type="text" name="onesignal_app_id" class="form-control" value="<?= esc((string) old('onesignal_app_id', (string) ($setting['onesignal_app_id'] ?? ''))) ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">REST API Key</label>
                                <input type="text" name="onesignal_rest_api_key" class="form-control" value="<?= esc((string) old('onesignal_rest_api_key', (string) ($setting['onesignal_rest_api_key'] ?? ''))) ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Sender ID</label>
                                <input type="text" name="onesignal_sender_id" class="form-control" value="<?= esc((string) old('onesignal_sender_id', (string) ($setting['onesignal_sender_id'] ?? ''))) ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12">
                    <div class="border rounded-3 p-3 mt-2">
                        <h6 class="mb-1">WhatsApp API &amp; Shared Order Message Settings</h6>
                        <p class="small text-muted mb-3">Order-created, status, ready, over-budget and delay toggles/templates below are shared. When ATTRAI is enabled, those order messages use the ATTRAI connection.</p>
                        <div class="row g-3">
                            <div class="col-md-2">
                                <label class="form-label">Enable</label>
                                <div class="form-check mt-2">
                                    <input class="form-check-input" type="checkbox" name="whatsapp_enabled" id="whatsapp_enabled" value="1" <?= ! empty($setting['whatsapp_enabled']) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="whatsapp_enabled">Enable WhatsApp</label>
                                </div>
                            </div>
                            <div class="col-md-5">
                                <label class="form-label">API URL</label>
                                <input type="text" name="whatsapp_api_url" class="form-control" value="<?= esc((string) old('whatsapp_api_url', (string) ($setting['whatsapp_api_url'] ?? ''))) ?>" placeholder="https://your-provider.example/send">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Method</label>
                                <?php $whatsappMethod = (string) old('whatsapp_http_method', (string) ($setting['whatsapp_http_method'] ?? 'POST')); ?>
                                <select name="whatsapp_http_method" class="form-select">
                                    <?php foreach (['POST', 'PUT', 'PATCH'] as $method): ?>
                                        <option value="<?= esc($method) ?>" <?= $whatsappMethod === $method ? 'selected' : '' ?>><?= esc($method) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Timeout (sec)</label>
                                <input type="number" min="5" max="120" name="whatsapp_timeout_sec" class="form-control" value="<?= esc((string) old('whatsapp_timeout_sec', (string) ($setting['whatsapp_timeout_sec'] ?? '20'))) ?>">
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">Auth Type</label>
                                <?php $whatsappAuthType = (string) old('whatsapp_auth_type', (string) ($setting['whatsapp_auth_type'] ?? 'none')); ?>
                                <select name="whatsapp_auth_type" class="form-select">
                                    <?php foreach (['none', 'bearer', 'basic', 'custom'] as $authType): ?>
                                        <option value="<?= esc($authType) ?>" <?= $whatsappAuthType === $authType ? 'selected' : '' ?>><?= esc(strtoupper($authType)) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Auth Header</label>
                                <input type="text" name="whatsapp_auth_header" class="form-control" value="<?= esc((string) old('whatsapp_auth_header', (string) ($setting['whatsapp_auth_header'] ?? 'Authorization'))) ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Auth Token</label>
                                <input type="text" name="whatsapp_auth_token" class="form-control" value="<?= esc((string) old('whatsapp_auth_token', (string) ($setting['whatsapp_auth_token'] ?? ''))) ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Sender ID</label>
                                <input type="text" name="whatsapp_sender_id" class="form-control" value="<?= esc((string) old('whatsapp_sender_id', (string) ($setting['whatsapp_sender_id'] ?? ''))) ?>">
                            </div>

                            <div class="col-md-12">
                                <label class="form-label">Internal Alert Numbers</label>
                                <textarea name="whatsapp_alert_numbers" class="form-control" rows="2" placeholder="919999999999,918888888888"><?= esc((string) old('whatsapp_alert_numbers', (string) ($setting['whatsapp_alert_numbers'] ?? ''))) ?></textarea>
                                <small class="text-muted">Used for over-budget and daily delay alerts. Separate multiple numbers with comma, space, or new line.</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Extra Headers JSON</label>
                                <textarea name="whatsapp_extra_headers_json" class="form-control font-monospace" rows="4" placeholder='{"X-API-KEY":"value"}'><?= esc((string) old('whatsapp_extra_headers_json', (string) ($setting['whatsapp_extra_headers_json'] ?? ''))) ?></textarea>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Request Body Template</label>
                                <textarea name="whatsapp_body_template" class="form-control font-monospace" rows="4" placeholder='{"to":{{to_json}},"message":{{message_json}},"sender":{{sender_id_json}}}'><?= esc((string) old('whatsapp_body_template', (string) ($setting['whatsapp_body_template'] ?? ''))) ?></textarea>
                            </div>

                            <div class="col-12">
                                <div class="row g-2">
                                    <div class="col-md-2">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="whatsapp_notify_order_created" id="whatsapp_notify_order_created" value="1" <?= (int) old('whatsapp_notify_order_created', (string) ($setting['whatsapp_notify_order_created'] ?? '1')) === 1 ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="whatsapp_notify_order_created">Order Created</label>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="whatsapp_notify_order_status_changed" id="whatsapp_notify_order_status_changed" value="1" <?= (int) old('whatsapp_notify_order_status_changed', (string) ($setting['whatsapp_notify_order_status_changed'] ?? '1')) === 1 ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="whatsapp_notify_order_status_changed">Status Update</label>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="whatsapp_notify_order_ready" id="whatsapp_notify_order_ready" value="1" <?= (int) old('whatsapp_notify_order_ready', (string) ($setting['whatsapp_notify_order_ready'] ?? '1')) === 1 ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="whatsapp_notify_order_ready">Order Ready</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="whatsapp_notify_order_over_budget" id="whatsapp_notify_order_over_budget" value="1" <?= (int) old('whatsapp_notify_order_over_budget', (string) ($setting['whatsapp_notify_order_over_budget'] ?? '1')) === 1 ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="whatsapp_notify_order_over_budget">Over Budget Alert</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="whatsapp_notify_order_delay_daily" id="whatsapp_notify_order_delay_daily" value="1" <?= (int) old('whatsapp_notify_order_delay_daily', (string) ($setting['whatsapp_notify_order_delay_daily'] ?? '1')) === 1 ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="whatsapp_notify_order_delay_daily">Daily Delay Alert</label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Order Created Template</label>
                                <textarea name="whatsapp_template_order_created" class="form-control" rows="4"><?= esc((string) old('whatsapp_template_order_created', (string) ($setting['whatsapp_template_order_created'] ?? ''))) ?></textarea>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Status Update Template</label>
                                <textarea name="whatsapp_template_order_status_changed" class="form-control" rows="4"><?= esc((string) old('whatsapp_template_order_status_changed', (string) ($setting['whatsapp_template_order_status_changed'] ?? ''))) ?></textarea>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Order Ready Template</label>
                                <textarea name="whatsapp_template_order_ready" class="form-control" rows="4"><?= esc((string) old('whatsapp_template_order_ready', (string) ($setting['whatsapp_template_order_ready'] ?? ''))) ?></textarea>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Over Budget Template</label>
                                <textarea name="whatsapp_template_order_over_budget" class="form-control" rows="4"><?= esc((string) old('whatsapp_template_order_over_budget', (string) ($setting['whatsapp_template_order_over_budget'] ?? ''))) ?></textarea>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">Daily Delay Template</label>
                                <textarea name="whatsapp_template_order_delay_daily" class="form-control" rows="4"><?= esc((string) old('whatsapp_template_order_delay_daily', (string) ($setting['whatsapp_template_order_delay_daily'] ?? ''))) ?></textarea>
                                <small class="text-muted">Available placeholders: {{to}}, {{message}}, {{sender_id}}, {{event_key}}, {{order_no}}, {{customer_display_name}}, {{status}}, {{due_date_display}}, {{gold_budget}}, {{diamond_budget}}, {{gold_over_budget}}, {{diamond_over_budget}}, {{delay_days}}, {{from_status}}, {{to_status}}, {{remarks}}, {{karigar_name}}, {{priority}}. For JSON request bodies use *_json placeholders like {{message_json}} and {{to_json}}.</small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12">
                    <div class="border rounded-3 p-3 mt-2">
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                            <div>
                                <h6 class="mb-1">Arattai Approval &amp; Transaction Integration</h6>
                                <small class="text-muted">Enter the OAuth client credentials once. The ERP obtains and refreshes Bearer tokens automatically.</small>
                            </div>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="attrai_enabled" id="attrai_enabled" value="1" <?= ! empty($setting['attrai_enabled']) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="attrai_enabled">Enable ATTRAI</label>
                            </div>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Arattai Client ID</label>
                                <input type="text" name="attrai_client_id" class="form-control font-monospace" maxlength="500" value="<?= esc((string) old('attrai_client_id', (string) ($setting['attrai_client_id'] ?? ''))) ?>" autocomplete="off">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Arattai Client Secret</label>
                                <input type="password" name="attrai_client_secret" class="form-control font-monospace" maxlength="1000" value="" autocomplete="new-password" placeholder="<?= ! empty($setting['attrai_client_secret']) ? 'Saved — leave blank to keep it' : 'Paste Client Secret' ?>">
                                <small class="text-muted">The secret is never displayed again after saving.</small>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Authorized Redirect URI</label>
                                <input type="text" class="form-control font-monospace" readonly value="<?= esc((string) ($attraiRedirectUri ?? '')) ?>">
                                <small class="text-muted">Add this exact URL in the Arattai API Console client settings.</small>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Company ID</label>
                                <input type="text" name="attrai_company_id" class="form-control font-monospace" readonly value="<?= esc((string) old('attrai_company_id', (string) ($setting['attrai_company_id'] ?? ''))) ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Business ID</label>
                                <input type="text" name="attrai_business_id" class="form-control font-monospace" readonly value="<?= esc((string) old('attrai_business_id', (string) ($setting['attrai_business_id'] ?? ''))) ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Business Number ID</label>
                                <input type="text" name="attrai_business_number_id" class="form-control font-monospace" readonly value="<?= esc((string) old('attrai_business_number_id', (string) ($setting['attrai_business_number_id'] ?? ''))) ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Approval Admin Arattai User IDs</label>
                                <textarea name="attrai_approval_numbers" class="form-control font-monospace" rows="2" placeholder="53817425,53817426"><?= esc((string) old('attrai_approval_numbers', (string) ($setting['attrai_approval_numbers'] ?? ''))) ?></textarea>
                                <small class="text-muted">Use Arattai user IDs, not mobile numbers. Multiple IDs can be comma-separated.</small>
                            </div>
                            <div class="col-12">
                                <div class="border rounded p-3">
                                    <div class="d-flex flex-wrap justify-content-between gap-2 mb-2">
                                        <strong>Recent Arattai Users</strong>
                                        <small class="text-muted">Ask the admin to send <code>Hi</code> to your Arattai Business Number, then refresh this page.</small>
                                    </div>
                                    <?php if (empty($recentArattaiUsers)): ?>
                                        <div class="small text-muted">No incoming Arattai user captured yet.</div>
                                    <?php else: ?>
                                        <div class="table-responsive">
                                            <table class="table table-sm align-middle mb-0">
                                                <thead><tr><th>Name</th><th>Phone</th><th>Arattai User ID</th><th>Last seen</th></tr></thead>
                                                <tbody>
                                                <?php foreach ($recentArattaiUsers as $arattaiUser): ?>
                                                    <tr>
                                                        <td><?= esc((string) (($arattaiUser['name'] ?? '') ?: 'Unknown')) ?></td>
                                                        <td><?= esc((string) (($arattaiUser['phone'] ?? '') ?: '-')) ?></td>
                                                        <td><code class="user-select-all"><?= esc((string) ($arattaiUser['id'] ?? '')) ?></code></td>
                                                        <td><?= esc((string) ($arattaiUser['last_seen_at'] ?? '')) ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Webhook URL</label>
                                <input type="text" class="form-control" readonly value="<?= esc(site_url('integrations/attrai/webhook')) ?>">
                                <small class="text-muted">Conversation webhook: select only <code>chat.message.received</code>.</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Webhook Secret</label>
                                <input type="password" name="attrai_webhook_secret" class="form-control font-monospace" maxlength="255" value="" autocomplete="new-password" placeholder="<?= ! empty($setting['attrai_webhook_secret']) ? 'Saved — leave blank to keep it' : 'Paste the Arattai webhook Shared Secret' ?>">
                                <small class="text-muted">Paste the Shared Secret generated in Arattai Webhooks. It validates <code>X-Hub-Signature-256</code>.</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Approval Audit Reviewer</label>
                                <?php $selectedAttraiReviewer = (int) old('attrai_approval_reviewer_id', (string) ($setting['attrai_approval_reviewer_id'] ?? '0')); ?>
                                <select name="attrai_approval_reviewer_id" class="form-select">
                                    <option value="">Select admin / owner</option>
                                    <?php foreach (($attraiReviewers ?? []) as $reviewer): ?>
                                        <option value="<?= (int) ($reviewer['id'] ?? 0) ?>" <?= $selectedAttraiReviewer === (int) ($reviewer['id'] ?? 0) ? 'selected' : '' ?>>
                                            <?= esc((string) (($reviewer['name'] ?? '') ?: ($reviewer['email'] ?? 'Admin'))) ?><?= ! empty($reviewer['email']) ? ' · ' . esc((string) $reviewer['email']) : '' ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="text-muted">ATTRAI approvals are recorded in this administrator's name.</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label d-block">Events</label>
                                <div class="form-check form-check-inline mt-2">
                                    <input class="form-check-input" type="checkbox" name="attrai_notify_approval_requested" id="attrai_notify_approval_requested" value="1" <?= (int) old('attrai_notify_approval_requested', (string) ($setting['attrai_notify_approval_requested'] ?? '1')) === 1 ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="attrai_notify_approval_requested">Approval requests</label>
                                </div>
                                <div class="form-check form-check-inline mt-2">
                                    <input class="form-check-input" type="checkbox" name="attrai_notify_karigar_assigned" id="attrai_notify_karigar_assigned" value="1" <?= (int) old('attrai_notify_karigar_assigned', (string) ($setting['attrai_notify_karigar_assigned'] ?? '1')) === 1 ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="attrai_notify_karigar_assigned">Karigar assignment</label>
                                </div>
                            </div>

                            <div class="col-12">
                                <div class="border rounded p-3 bg-light">
                                    <div><strong>OAuth:</strong> <?= ! empty($setting['attrai_refresh_token']) ? '<span class="text-success">Connected</span>' : '<span class="text-danger">Not connected</span>' ?></div>
                                    <div><strong>Incoming webhook:</strong> <?= esc((string) (($setting['attrai_webhook_status'] ?? '') ?: 'not configured')) ?><?= ! empty($setting['attrai_webhook_id']) ? ' · ' . esc((string) $setting['attrai_webhook_id']) : '' ?></div>
                                    <div><strong>Approval template:</strong> <?= esc((string) (($setting['attrai_approval_template_status'] ?? '') ?: 'not created')) ?><?= ! empty($setting['attrai_approval_template_id']) ? ' · ' . esc((string) $setting['attrai_approval_template_id']) : '' ?></div>
                                    <div><strong>Order-update template:</strong> <?= esc((string) (($setting['attrai_update_template_status'] ?? '') ?: 'not created')) ?><?= ! empty($setting['attrai_update_template_id']) ? ' · ' . esc((string) $setting['attrai_update_template_id']) : '' ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-12">
                    <button type="submit" class="btn btn-primary">Save Settings</button>
                </div>
            </div>
        </form>

        <div class="border rounded-3 p-3 mt-4">
            <h6 class="mb-2">Arattai One-time Setup</h6>
            <p class="small text-muted">Save Client ID and Client Secret, connect once, then configure the incoming conversation webhook. Conversational previews do not need approved templates.</p>
            <div class="d-flex flex-wrap gap-2">
                <a href="<?= site_url('admin/arattai-messages') ?>" class="btn btn-outline-dark">Message Audit &amp; Failure Reasons</a>
                <a href="<?= site_url('admin/arattai-users') ?>" class="btn btn-outline-dark">Map Arattai Users</a>
                <a href="<?= site_url('admin/company-settings/arattai/connect') ?>" class="btn btn-outline-primary">Connect Arattai</a>
                <form method="post" action="<?= site_url('admin/company-settings/arattai/templates') ?>">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-outline-success" <?= empty($setting['attrai_refresh_token']) ? 'disabled' : '' ?>>Create &amp; Submit ERP Templates</button>
                </form>
                <form method="post" action="<?= site_url('admin/company-settings/arattai/webhook') ?>">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-outline-warning" <?= empty($setting['attrai_refresh_token']) ? 'disabled' : '' ?>>Configure Incoming Webhook</button>
                </form>
            </div>
        </div>

        <form id="arattai-test" method="post" action="<?= site_url('admin/company-settings/attrai-test') ?>" class="border rounded-3 p-3 mt-4">
            <?= csrf_field() ?>
            <h6 class="mb-2">Send ATTRAI Demo</h6>
            <div class="row g-2 align-items-end">
                <div class="col-md-6">
                    <label class="form-label">Arattai User ID</label>
                    <input type="text" name="test_number" class="form-control" required placeholder="Example: 53817425">
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-outline-primary">Send Demo Now</button>
                </div>
            </div>
            <small class="text-muted d-block mt-2">The user must first message your Business Number so the conversation window is active.</small>
        </form>

        <form id="arattai-workflow-preview" method="post" action="<?= site_url('admin/company-settings/arattai/preview') ?>" class="border rounded-3 p-3 mt-4">
            <?= csrf_field() ?>
            <h6 class="mb-2">Send One Arattai Workflow Preview</h6>
            <div class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label">Arattai User ID</label>
                    <input type="text" name="arattai_user_id" class="form-control" required placeholder="Paste a captured Arattai User ID">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Preview scenario</label>
                    <select name="preview_scenario" class="form-select">
                        <option value="order_status">Order status + order image</option>
                        <option value="followup_approval">Follow-up approval + buttons</option>
                        <option value="issuement_approval">Issuement approval + buttons</option>
                        <option value="karigar_assignment">Karigar assignment</option>
                        <option value="karigar_issuement">Karigar issuement</option>
                        <option value="payment">Payment</option>
                        <option value="followup_due">Follow-up due</option>
                        <option value="points">Points (no image)</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-outline-primary">Send Selected Preview</button>
                </div>
            </div>
            <small class="text-muted d-block mt-2">Sends only one selected preview to avoid Arattai's conversational rate limit. All previews except points include a relevant image when available.</small>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
