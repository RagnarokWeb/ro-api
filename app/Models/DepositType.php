<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DepositType extends Model
{
    //
    protected $table='deposit_type';
    
    protected $primaryKey='id';
    
    public $timestamps = false;
    
    protected $guarded = ['id'];
}