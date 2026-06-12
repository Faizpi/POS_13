<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BiayaItem extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'biaya_id', 'kategori', 'deskripsi', 'jumlah',
    ];

    protected function casts(): array
    {
        return ['jumlah' => 'decimal:2'];
    }

    public function biaya() { return $this->belongsTo(Biaya::class); }
}
