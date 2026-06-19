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

    /**
     * Auto-generate kode kontak berurutan (KT00001, KT00002, ...).
     */
    public static function generateKodeKontak(): string
    {
        $last = static::whereNotNull('kode_kontak')
            ->where('kode_kontak', 'like', 'KT%')
            ->orderByDesc('id')
            ->value('kode_kontak');

        $nextNumber = $last
            ? ((int) str_replace('KT', '', $last)) + 1
            : 1;

        return 'KT' . str_pad($nextNumber, 5, '0', STR_PAD_LEFT);
    }
}
