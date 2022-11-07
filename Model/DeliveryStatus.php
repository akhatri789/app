<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class DeliveryStatus extends Model
{
    protected $table = 'delivery_status';
    protected $fillable = ['zip_code', 'no_of_day', 'status']; 
}
