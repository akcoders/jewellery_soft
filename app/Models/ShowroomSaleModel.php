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
        'total_qty',
        'taxable_amount',
        'gst_percent',
        'gst_amount',
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
