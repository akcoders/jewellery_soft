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
    ];
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
}
