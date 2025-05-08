<?php

namespace App\Models\Speicloud\Authorization;

use Illuminate\Database\Eloquent\Model;

class RuleProcessors extends Model
{
    protected $table = 't_speicloud_authorization_rules_processors';
    protected $primaryKey = 'Id';
    public $timestamps = true;
    protected $fillable = [
        'RuleId',
        'UserId'
    ];
}
