<?php
// app/Models/CafeOrderItem.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CafeOrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'cafe_order_id',
        'cafe_item_id',
        'quantity',
        'unit_price',
        'total_price',
        'special_instructions'
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'total_price' => 'decimal:2'
    ];

    public function cafeOrder()
    {
        return $this->belongsTo(CafeOrder::class);
    }

    public function cafeItem()
    {
        return $this->belongsTo(CafeItem::class);
    }
}
