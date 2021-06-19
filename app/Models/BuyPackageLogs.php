<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BuyPackageLogs extends Model
{
    //
    protected $table='buy_package_logs';
    
    protected $primaryKey='id';
    
    public $timestamps = false;
    
    protected $guarded = ['id'];
}