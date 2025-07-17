<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'transaction_number',
        'type',
        'amount',
        'description',
        'category',
        'reference_type',
        'reference_id',
        'customer_name',
        'notes',
        'transaction_date',
        'created_by'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'transaction_date' => 'datetime'
    ];

    public static function generateTransactionNumber()
    {
        $prefix = 'TXN';
        $date = now()->format('Ymd');
        $count = self::whereDate('created_at', today())->count() + 1;
        return $prefix . $date . str_pad($count, 4, '0', STR_PAD_LEFT);
    }

    // ✅ Simple relationships without where clauses
    public function rental()
    {
        return $this->belongsTo(Rental::class, 'reference_id');
    }

    public function cafeOrder()
    {
        return $this->belongsTo(CafeOrder::class, 'reference_id');
    }

    public function cafeStock()
    {
        return $this->belongsTo(CafeStock::class, 'reference_id');
    }

    // Scopes
    public function scopeDebit($query)
    {
        return $query->where('type', 'debit');
    }

    public function scopeCredit($query)
    {
        return $query->where('type', 'credit');
    }

    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('transaction_date', [$startDate, $endDate]);
    }

    public function scopeByCategory($query, $category)
    {
        return $query->where('category', $category);
    }
}
