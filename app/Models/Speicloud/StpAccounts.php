<?php

namespace App\Models\Speicloud;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StpAccounts extends Model
{
    use HasFactory;

    protected $table = 't_spei_stp_accounts';

    protected $fillable = [
        'Id',
        'StpAccountId',
        'BusinessId',
        'Number',
        'Acronym',
        'Company',
        'Key',
        'Url',
        'PendingCharges',
        'Commissions',
        'Balance',
        'BalanceDate',
        'Active'
    ];

    public $timestamps = false;

}
