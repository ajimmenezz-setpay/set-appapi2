<?php

namespace App\Models\Modules;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $table = 'cat_modules_category';
    protected $primaryKey = 'Id';

    protected $fillable = [
        'Name',
        'Order',
        'ServiceId',
        'Flag'
    ];

    public $timestamps = false;
}
