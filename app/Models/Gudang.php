<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Gudang extends Model
{
    protected $fillable = [
        'nama_gudang',
        'alamat_gudang',
    ];

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
}
