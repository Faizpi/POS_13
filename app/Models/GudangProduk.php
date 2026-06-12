<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GudangProduk extends Model
{
    protected $table = 'gudang_produk';

    public $timestamps = false;

    protected $fillable = [
        'gudang_id',
        'produk_id',
        'stok',
        'stok_penjualan',
        'stok_gratis',
        'stok_sample',
    ];

    public function gudang()
    {
        return $this->belongsTo(Gudang::class);
    }

    public function produk()
    {
        return $this->belongsTo(Produk::class);
    }
}
