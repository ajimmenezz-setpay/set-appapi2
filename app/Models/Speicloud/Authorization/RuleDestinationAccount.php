<?php

namespace App\Models\Speicloud\Authorization;

use Illuminate\Database\Eloquent\Model;

class RuleDestinationAccount extends Model
{
    protected $table = 't_speicloud_authorization_rules_destinations';
    protected $primaryKey = 'Id';
    public $timestamps = true;
    protected $fillable = [
        'RuleId',
        'DestinationAccount',
        'DestinationAccountName'
    ];
}
