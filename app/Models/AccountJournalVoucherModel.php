<?php

namespace App\Models;

use CodeIgniter\Model;

class AccountJournalVoucherModel extends Model
{
    protected $table = 'account_journal_vouchers';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'voucher_no',
        'voucher_date',
        'voucher_type',
        'from_party_type',
        'from_party_id',
        'to_party_type',
        'to_party_id',
        'expense_head',
        'amount',
        'payment_mode',
        'reference_no',
        'status',
        'notes',
        'created_by',
    ];
    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
}
