<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KunjunganItem extends Model
{
    protected $fillable = [
        'kunjungan_id', 'produk_id', 'jumlah',
        'batch_number', 'expired_date', 'keterangan',
    ];

    protected function casts(): array
    {
        return ['expired_date' => 'date'];
    }

    public function kunjungan() { return $this->belongsTo(Kunjungan::class); }
    public function produk() { return $this->belongsTo(Produk::class); }
}
