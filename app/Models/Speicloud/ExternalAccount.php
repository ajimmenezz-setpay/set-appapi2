<?php

namespace App\Models\Speicloud;

use Illuminate\Database\Eloquent\Model;

class ExternalAccount extends Model
{
    protected $table = 't_spei_external_accounts';
    protected $fillable = [
        'Id',
        'InterbankCLABE',
        'Beneficiary',
        'Rfc',
        'Alias',
        'BankId',
        'Email',
        'Phone',
        'CreatedByUser',
        'CreateDate',
        'Active'
    ];
}
