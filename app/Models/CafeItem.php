<?php
// app/Models/CafeItem.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CafeItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'category',
        'price',
        'image',
        'is_available',
        'preparation_time'
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'is_available' => 'boolean',
        'preparation_time' => 'integer'
    ];

    public function stock()
    {
        return $this->hasOne(CafeStock::class);
    }

    public function orderItems()
    {
        return $this->hasMany(CafeOrderItem::class);
    }

    public function getIsInStockAttribute()
    {
        return $this->stock && $this->stock->quantity > 0;
    }

    public function getStockQuantityAttribute()
    {
        return $this->stock ? $this->stock->quantity : 0;
    }
}
