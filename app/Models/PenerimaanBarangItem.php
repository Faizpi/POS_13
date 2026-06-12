<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PenerimaanBarangItem extends Model
{
    protected $fillable = [
        'penerimaan_barang_id', 'produk_id',
        'qty_diterima', 'qty_reject', 'tipe_stok',
        'batch_number', 'expired_date', 'keterangan',
    ];

    protected function casts(): array
    {
        return ['expired_date' => 'date'];
    }

    public function penerimaanBarang() { return $this->belongsTo(PenerimaanBarang::class); }
    public function produk() { return $this->belongsTo(Produk::class); }
}
