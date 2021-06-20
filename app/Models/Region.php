<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Region extends Model
{
    protected $connection = 'mysql2';
	
    protected $table='region';
    
    protected $primaryKey='regionid';
    
    public $timestamps = false;
    
    protected $guarded = ['regionid'];
}