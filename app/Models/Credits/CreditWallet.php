<?php

namespace App\Models\Credits;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CreditWallet extends Model
{
    use HasFactory;

    protected $connection = 'card_cloud';
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
        'DaysUntilDue',
        'CLABE'
    ];
    protected $hidden = [
        'created_at',
        'updated_at'
    ];

    public $timestamps = true;
}
