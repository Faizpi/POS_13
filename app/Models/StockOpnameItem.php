<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockOpnameItem extends Model
{
    protected $fillable = [
        'stock_opname_id', 'produk_id',
        'batch_number', 'expired_date',
        'qty_system', 'qty_aktual', 'selisih', 'keterangan',
    ];

    protected function casts(): array
    {
        return [
            'expired_date' => 'date',
            'qty_system' => 'decimal:2',
            'qty_aktual' => 'decimal:2',
            'selisih' => 'decimal:2',
        ];
    }

    // Relationships
    public function stockOpname()
    {
        return $this->belongsTo(StockOpname::class);
    }

    public function produk()
    {
        return $this->belongsTo(Produk::class);
    }

    // Auto-calculate selisih before saving
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($item) {
            $item->selisih = $item->qty_aktual - $item->qty_system;
        });
    }
}
