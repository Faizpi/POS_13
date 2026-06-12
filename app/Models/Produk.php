<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Produk extends Model
{
    protected $fillable = [
        'nama_produk',
        'item_code',
        'harga',
        'harga_grosir',
        'satuan',
        'deskripsi',
    ];

    protected function casts(): array
    {
        return [
            'harga' => 'decimal:2',
            'harga_grosir' => 'decimal:2',
        ];
    }

    public function stokDiGudang()
    {
        return $this->hasMany(GudangProduk::class);
    }
}
