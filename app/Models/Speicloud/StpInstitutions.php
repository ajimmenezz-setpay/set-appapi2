<?php

namespace App\Models\Speicloud;

use Illuminate\Database\Eloquent\Model;

class StpInstitutions extends Model
{
    protected $table = 'cat_spei_banks';
    protected $primaryKey = 'Id';
    protected $fillable = ['Id', 'Code', 'ShortName', 'Name', 'Active'];

    public $timestamps = false;

}
