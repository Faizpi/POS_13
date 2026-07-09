<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PenjualanItem extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'penjualan_id', 'produk_id', 'deskripsi', 'kuantitas',
        'unit', 'harga_satuan', 'diskon', 'diskon_nominal',
        'batch_number', 'expired_date', 'jumlah_baris',
    ];

    protected function casts(): array
    {
        return [
            'expired_date' => 'date',
            'harga_satuan' => 'decimal:2',
            'diskon_nominal' => 'decimal:2',
        ];
    }

    public function penjualan()
    {
        return $this->belongsTo(Penjualan::class);
    }

    public function produk()
    {
        return $this->belongsTo(Produk::class);
    }
}
