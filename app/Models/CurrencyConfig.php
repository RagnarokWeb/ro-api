<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CurrencyConfig extends Model
{
    //
    protected $table='currency_config';
    
    protected $primaryKey='id';
    
    public $timestamps = false;
    
    protected $guarded = ['id'];
}