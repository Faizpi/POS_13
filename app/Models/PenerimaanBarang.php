<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PenerimaanBarang extends Model
{
    protected $fillable = [
        'user_id', 'approver_id', 'gudang_id', 'pembelian_id',
        'no_urut_harian', 'nomor', 'uuid',
        'tgl_penerimaan', 'no_surat_jalan',
        'lampiran_paths', 'keterangan', 'status',
    ];

    protected function casts(): array
    {
        return [
            'tgl_penerimaan' => 'date',
            'lampiran_paths' => 'array',
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
    public function pembelian() { return $this->belongsTo(Pembelian::class); }
    public function items() { return $this->hasMany(PenerimaanBarangItem::class); }

    public function getCustomNumberAttribute(): string
    {
        if ($this->nomor) return $this->nomor;
        $dateCode = $this->created_at->format('Ymd');
        $noUrutPadded = str_pad($this->no_urut_harian, 3, '0', STR_PAD_LEFT);
        return "RCV-{$dateCode}-{$this->user_id}-{$noUrutPadded}";
    }

    public static function generateNomor($userId, $noUrut, $createdAt): string
    {
        $dateCode = $createdAt->format('Ymd');
        $noUrutPadded = str_pad($noUrut, 3, '0', STR_PAD_LEFT);
        return "RCV-{$dateCode}-{$userId}-{$noUrutPadded}";
    }
}
