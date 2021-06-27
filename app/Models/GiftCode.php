<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GiftCode extends Model
{
    //
    protected $table='giftcode';
    
    protected $primaryKey='id';
    
    public $timestamps = false;
    
    protected $guarded = ['id'];
}