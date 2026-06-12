<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StokLog extends Model
{
    protected $fillable = [
        'gudang_produk_id', 'produk_id', 'gudang_id', 'user_id',
        'produk_nama', 'gudang_nama', 'user_nama',
        'stok_sebelum', 'stok_sesudah', 'selisih', 'keterangan',
    ];

    public function gudangProduk() { return $this->belongsTo(GudangProduk::class); }
    public function produk() { return $this->belongsTo(Produk::class); }
    public function gudang() { return $this->belongsTo(Gudang::class); }
    public function user() { return $this->belongsTo(User::class); }
}
