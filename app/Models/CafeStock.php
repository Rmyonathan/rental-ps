<?php
// app/Models/CafeStock.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CafeStock extends Model
{
    use HasFactory;

    protected $fillable = [
        'cafe_item_id',
        'quantity',
        'minimum_stock',
        'cost_price',
        'expiry_date'
    ];

    protected $casts = [
        'quantity' => 'integer',
        'minimum_stock' => 'integer',
        'cost_price' => 'decimal:2',
        'expiry_date' => 'date'
    ];

    public function cafeItem()
    {
        return $this->belongsTo(CafeItem::class);
    }

    public function getIsLowStockAttribute()
    {
        return $this->quantity <= $this->minimum_stock;
    }

    public function getIsExpiredAttribute()
    {
        return $this->expiry_date && $this->expiry_date->isPast();
    }
}
