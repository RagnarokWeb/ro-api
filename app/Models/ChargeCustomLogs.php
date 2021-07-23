<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChargeCustomLogs extends Model
{
    //
    protected $table='chargecustom_logs';
    
    protected $primaryKey='id';
    
    public $timestamps = false;
    
    protected $guarded = ['id'];
}
?>