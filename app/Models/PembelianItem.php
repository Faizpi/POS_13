<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PembelianItem extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'pembelian_id', 'produk_id', 'deskripsi', 'kuantitas',
        'unit', 'harga_satuan', 'diskon', 'jumlah_baris',
    ];

    protected function casts(): array
    {
        return ['harga_satuan' => 'decimal:2'];
    }

    public function pembelian() { return $this->belongsTo(Pembelian::class); }
    public function produk() { return $this->belongsTo(Produk::class); }
}
