<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Rental extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_name',
        'phone', 
        'ps_station',
        'ip_address',
        'start_time',
        'end_time',
        'duration_minutes',
        'price',
        'status'
    ];

    // Cast these fields to Carbon instances
    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'duration_minutes' => 'integer',
        'price' => 'float',
    ];

    // Add a computed attribute for remaining time
    public function getRemainingTimeAttribute()
    {
        return $this->end_time->diffInMinutes(now(), false);
    }
    public function cafeOrders()
    {
        return $this->hasMany(CafeOrder::class);
    }
}