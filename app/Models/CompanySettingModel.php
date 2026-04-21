<?php

namespace App\Models;

use CodeIgniter\Model;

class CompanySettingModel extends Model
{
    protected $table         = 'company_settings';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = [
        'company_name',
        'address_line',
        'city',
        'state',
        'pincode',
        'phone',
        'email',
        'gstin',
        'logo_path',
        'issuement_suffix',
        'delivery_challan_suffix',
        'sale_bill_suffix',
        'onesignal_enabled',
        'onesignal_app_id',
        'onesignal_rest_api_key',
        'onesignal_sender_id',
        'whatsapp_enabled',
        'whatsapp_api_url',
        'whatsapp_http_method',
        'whatsapp_auth_type',
        'whatsapp_auth_header',
        'whatsapp_auth_token',
        'whatsapp_sender_id',
        'whatsapp_timeout_sec',
        'whatsapp_alert_numbers',
        'whatsapp_extra_headers_json',
        'whatsapp_body_template',
        'whatsapp_notify_order_created',
        'whatsapp_notify_order_status_changed',
        'whatsapp_notify_order_ready',
        'whatsapp_notify_order_over_budget',
        'whatsapp_notify_order_delay_daily',
        'whatsapp_template_order_created',
        'whatsapp_template_order_status_changed',
        'whatsapp_template_order_ready',
        'whatsapp_template_order_over_budget',
        'whatsapp_template_order_delay_daily',
    ];
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
}
