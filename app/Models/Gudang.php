<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Gudang extends Model
{
    protected $attributes = [
        'is_active' => true,
    ];

    protected $fillable = [
        'nama_gudang',
        'alamat_gudang',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function gudangProduks()
    {
        return $this->hasMany(GudangProduk::class);
    }

    public function admins()
    {
        return $this->belongsToMany(User::class, 'admin_gudang')->withTimestamps();
    }

    public function spectators()
    {
        return $this->belongsToMany(User::class, 'spectator_gudang')->withTimestamps();
    }

    public function cashBankAccounts(): HasMany
    {
        return $this->hasMany(CashBankAccount::class);
    }
}
