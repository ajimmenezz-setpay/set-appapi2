<?php

namespace App\Models\Speicloud\Authorization;

use Illuminate\Database\Eloquent\Model;

class AuthorizationRules extends Model
{
    protected $table = 't_speicloud_authorization_rules';
    protected $primaryKey = 'Id';
    protected $fillable = [
        'RuleType',
        'Amount',
        'DailyMovementsLimit',
        'MonthlyMovementsLimit',
        'Priority',
        'CreatedBy'
    ];
}
