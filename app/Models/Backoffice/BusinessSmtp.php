<?php

namespace App\Models\Backoffice;

use Illuminate\Database\Eloquent\Model;

class BusinessSmtp extends Model
{
    protected $table = 't_backoffice_business_smtp_credentials';
    protected $primaryKey = 'Id';
    public $timestamps = true;

    protected $fillable = [
        'BusinessId',
        'SmtpHost',
        'SmtpPort',
        'SmtpUser',
        'SmtpPassword',
        'SmtpEncryption'
    ];

    protected $casts = [
        'BusinessId' => 'string',
        'SmtpHost' => 'string',
        'SmtpPort' => 'string',
        'SmtpUser' => 'string',
        'SmtpPassword' => 'string',
        'SmtpEncryption' => 'string'
    ];
    protected $hidden = [
        'SmtpPassword'
    ];
}
