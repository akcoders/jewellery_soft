<?php

namespace App\Models;

use CodeIgniter\Model;

class AccountPaymentModel extends Model
{
    protected $table = 'account_payments';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'payment_no',
        'payment_date',
        'party_type',
        'karigar_id',
        'vendor_id',
        'amount',
        'payment_mode',
        'reference_no',
        'reference_file_path',
        'reference_file_name',
        'bill_type',
        'bill_source_type',
        'bill_source_id',
        'labour_bill_id',
        'notes',
        'created_by',
    ];
    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
}
