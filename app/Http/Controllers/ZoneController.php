<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Controllers\APIController;
// use App\Models\User;
use App\Models\Zone;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;

class ZoneController extends APIController
{
    /**
     * Show the profile for a given user.
     *
     * @param  int  $id
     * @return \Illuminate\View\View
     */
    public function getList(Request $request)
    {
        $column = [
            'regionid',
            'nickname'
        ];
        
        return Zone::get($column);
    }
}