<?php

namespace App\Http\Controllers\SpeiCloud\Lists;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class Institutions extends Controller
{
    public function index()
    {
        try {
            $institutions = \App\Models\Speicloud\StpInstitutions::where('Active', 1)->orderBy('ShortName')->get(['Code as code', 'ShortName as name']);
            return response()->json($institutions);
        } catch (\Exception $e) {
            return response($e->getMessage(), $e->getCode() ?: 400);
        }
    }
}
