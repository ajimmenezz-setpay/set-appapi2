<?php

namespace App\Models\Speicloud\Authorization;

use Illuminate\Database\Eloquent\Model;

class AuthorizationRules extends Model
{
    protected $table = 't_speicloud_authorization_rules';
    protected $primaryKey = 'Id';
    protected $fillable = [
        'BusinessId',
        'RuleType',
        'Amount',
        'DailyMovementsLimit',
        'MonthlyMovementsLimit',
        'Priority',
        'CreatedBy',
        'Active'
    ];
}
