<?php

namespace App\Models\CardCloud;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CreditWallet extends Model
{
    use HasFactory;

    protected $table = 'credit_wallets';
    protected $primaryKey = 'Id';

    protected $fillable = [
        'UUID',
        'AccountId',
        'CreditLimit',
        'UsedCredit',
        'AvailableCredit',
        'MinimumPayment',
        'InterestRate',
        'YearlyFee',
        'LateInterestRate',
        'CreditStartDate',
        'NextFeeDate',
    ];
}
