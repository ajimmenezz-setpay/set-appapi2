<?php

namespace App\Models\Backoffice;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Business extends Model
{
    use HasFactory;

    protected $table = 't_backoffice_business';

    protected $fillable = [
        'Id',
        'Name',
        'TemplateFile',
        'Domain',
        'Active'
    ];

    public $timestamps = false;
}
