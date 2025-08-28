<?php

namespace App\Models\CardCloud;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Credit extends Model
{
    use HasFactory;

    protected $table = 't_card_cloud_credits';
    protected $primaryKey = 'Id';
    protected $fillable = [
        'UUID',
        'ExternalId',
        'CompanyId',
        'UserId'
    ];
    protected $hidden = [
        'created_at',
        'updated_at'
    ];

    public $timestamps = true;
}
