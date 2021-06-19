<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CharBase extends Model
{
    protected $connection = 'mysql_game';
	
    protected $table='charbase';
    
    protected $primaryKey='charid';
    
    public $timestamps = false;
    
    protected $guarded = ['charid'];
}