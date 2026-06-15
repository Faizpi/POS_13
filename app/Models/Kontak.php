<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

class Kontak extends Authenticatable
{
    protected $fillable = [
        'kode_kontak',
        'nama',
        'email',
        'no_telp',
        'pin',
        'alamat',
        'diskon_persen',
        'gudang_id',
        'created_by',
    ];

    protected $hidden = [
        'pin',
    ];

    public function getAuthPassword()
    {
        return $this->pin;
    }

    protected function casts(): array
    {
        return [
            'diskon_persen' => 'decimal:2',
        ];
    }

    public function gudang()
    {
        return $this->belongsTo(Gudang::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function kunjungans()
    {
        return $this->hasMany(Kunjungan::class);
    }

    public function pembelians()
    {
        return $this->hasMany(Pembelian::class);
    }
}
