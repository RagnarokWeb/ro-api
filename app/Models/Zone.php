<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Zone extends Model
{
    protected $connection = 'mysql2';
	
    protected $table='zone';
    
    protected $primaryKey='zoneid';
    
    public $timestamps = false;
    
    protected $guarded = ['zoneid'];
}