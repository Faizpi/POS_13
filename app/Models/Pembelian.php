<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Pembelian extends Model
{
    protected $fillable = [
        'user_id', 'approver_id', 'kontak_id', 'no_urut_harian', 'nomor', 'uuid',
        'gudang_id', 'staf_penyetuju', 'email_penyetuju',
        'tgl_transaksi', 'syarat_pembayaran', 'tgl_jatuh_tempo',
        'tipe_harga', 'no_referensi', 'no_resi', 'biaya_pengiriman',
        'urgensi', 'tahun_anggaran', 'tag', 'koordinat', 'memo',
        'lampiran_path', 'lampiran_paths', 'status',
        'diskon_akhir', 'tax_percentage', 'grand_total',
    ];

    protected function casts(): array
    {
        return [
            'tgl_transaksi' => 'date',
            'tgl_jatuh_tempo' => 'date',
            'lampiran_paths' => 'array',
            'grand_total' => 'decimal:2',
            'biaya_pengiriman' => 'decimal:2',
            'diskon_akhir' => 'decimal:2',
            'tax_percentage' => 'decimal:2',
        ];
    }

    protected static function boot()
    {
        parent::boot();
        static::creating(fn($m) => $m->uuid = $m->uuid ?: (string) Str::uuid());
    }

    public function user() { return $this->belongsTo(User::class); }
    public function approver() { return $this->belongsTo(User::class, 'approver_id'); }
    public function kontak() { return $this->belongsTo(Kontak::class); }
    public function gudang() { return $this->belongsTo(Gudang::class); }
    public function items() { return $this->hasMany(PembelianItem::class); }
    public function penerimaanBarangs() { return $this->hasMany(PenerimaanBarang::class); }
    public function pembayarans() { return $this->hasMany(Pembayaran::class)->where('type', 'hutang'); }

    public function getStatusDisplayAttribute(): string
    {
        if ($this->status === 'Lunas') return 'Lunas';
        if ($this->status === 'Approved') return 'Belum Lunas';
        return $this->status;
    }

    public function getCustomNumberAttribute(): string
    {
        if ($this->nomor) return $this->nomor;
        $dateCode = $this->created_at->format('Ymd');
        $noUrutPadded = str_pad($this->no_urut_harian, 3, '0', STR_PAD_LEFT);
        return "PR-{$dateCode}-{$this->user_id}-{$noUrutPadded}";
    }

    public static function generateNomor($userId, $noUrut, $createdAt): string
    {
        $dateCode = $createdAt->format('Ymd');
        $noUrutPadded = str_pad($noUrut, 3, '0', STR_PAD_LEFT);
        return "PR-{$dateCode}-{$userId}-{$noUrutPadded}";
    }
}
