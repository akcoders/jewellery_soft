<?php

namespace App\Models;

use CodeIgniter\Model;

class ShowroomSaleModel extends Model
{
    protected $table = 'showroom_sales';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'sale_no',
        'sale_date',
        'showroom_id',
        'showroom_counter_id',
        'salesperson_employee_id',
        'customer_id',
        'reservation_id',
        'invoice_id',
        'packing_list_id',
        'gst_master_id',
        'tax_breakup_json',
        'hsn_sac',
        'total_qty',
        'total_gold_weight',
        'total_diamond_weight',
        'total_stone_weight',
        'gold_rate',
        'gold_amount',
        'diamond_rate',
        'diamond_amount',
        'stone_rate',
        'stone_amount',
        'other_amount',
        'taxable_amount',
        'gst_percent',
        'gst_amount',
        'round_off_amount',
        'total_amount',
        'received_amount',
        'payment_status',
        'sale_status',
        'notes',
        'created_by',
    ];
    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
}
