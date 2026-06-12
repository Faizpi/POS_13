<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Biaya extends Model
{
    protected $fillable = [
        'user_id', 'approver_id', 'no_urut_harian', 'nomor', 'uuid',
        'gudang_id', 'jenis_biaya', 'bayar_dari', 'penerima', 'no_telepon',
        'alamat_penagihan', 'tgl_transaksi', 'cara_pembayaran',
        'tag', 'koordinat', 'memo',
        'lampiran_path', 'lampiran_paths', 'status',
        'tax_percentage', 'grand_total',
    ];

    protected function casts(): array
    {
        return [
            'tgl_transaksi' => 'date',
            'lampiran_paths' => 'array',
            'grand_total' => 'decimal:2',
        ];
    }

    protected static function boot()
    {
        parent::boot();
        static::creating(fn($m) => $m->uuid = $m->uuid ?: (string) Str::uuid());
    }

    public function user() { return $this->belongsTo(User::class); }
    public function approver() { return $this->belongsTo(User::class, 'approver_id'); }
    public function gudang() { return $this->belongsTo(Gudang::class); }
    public function items() { return $this->hasMany(BiayaItem::class); }

    public function getCustomNumberAttribute(): string
    {
        if ($this->nomor) return $this->nomor;
        $dateCode = $this->created_at->format('Ymd');
        $noUrutPadded = str_pad($this->no_urut_harian, 3, '0', STR_PAD_LEFT);
        return "EXP-{$dateCode}-{$this->user_id}-{$noUrutPadded}";
    }

    public static function generateNomor($userId, $noUrut, $createdAt): string
    {
        $dateCode = $createdAt->format('Ymd');
        $noUrutPadded = str_pad($noUrut, 3, '0', STR_PAD_LEFT);
        return "EXP-{$dateCode}-{$userId}-{$noUrutPadded}";
    }
}
