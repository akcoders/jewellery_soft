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

                <div class="col-md-4">
                    <label class="form-label">Issuement Prefix</label>
                    <input type="text" name="issuement_suffix" class="form-control" value="<?= esc((string) old('issuement_suffix', (string) ($setting['issuement_suffix'] ?? 'ISS'))) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Delivery Challan Prefix</label>
                    <input type="text" name="delivery_challan_suffix" class="form-control" value="<?= esc((string) old('delivery_challan_suffix', (string) ($setting['delivery_challan_suffix'] ?? 'DC'))) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Sale Bill Prefix</label>
                    <input type="text" name="sale_bill_suffix" class="form-control" value="<?= esc((string) old('sale_bill_suffix', (string) ($setting['sale_bill_suffix'] ?? 'SB'))) ?>">
                </div>

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
                        <h6 class="mb-3">WhatsApp API Settings</h6>
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

                <div class="col-md-12">
                    <button type="submit" class="btn btn-primary">Save Settings</button>
                </div>
            </div>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
