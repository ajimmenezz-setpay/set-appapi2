<?php

namespace App\Models\Speicloud\Authorization;

use Illuminate\Database\Eloquent\Model;

class RuleAuthorizers extends Model
{
    protected $table = 't_speicloud_authorization_rules_authorizers';
    protected $primaryKey = 'Id';
    public $timestamps = true;
    protected $fillable = [
        'RuleId',
        'UserId'
    ];
}
