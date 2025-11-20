<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApiLog extends Model
{
    protected $table = 'api_logs';

    protected $primaryKey = 'Id';

    protected $fillable = [
        'user_id',
        'method',
        'url',
        'ip',
        'request_headers',
        'request_body',
        'response_code',
        'response_body',
    ];
}
