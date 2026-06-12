<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Kunjungan extends Model
{
    protected $fillable = [
        'user_id', 'approver_id', 'no_urut_harian', 'nomor', 'uuid',
        'gudang_id', 'kontak_id', 'sales_nama', 'sales_no_telepon',
        'sales_alamat', 'tgl_kunjungan', 'tujuan', 'koordinat', 'memo',
        'lampiran_path', 'lampiran_paths', 'status',
    ];

    protected function casts(): array
    {
        return [
            'tgl_kunjungan' => 'date',
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
    public function kontak() { return $this->belongsTo(Kontak::class); }
    public function items() { return $this->hasMany(KunjunganItem::class); }

    public function getCustomNumberAttribute(): string
    {
        if ($this->nomor) return $this->nomor;
        $dateCode = $this->created_at->format('Ymd');
        $noUrutPadded = str_pad($this->no_urut_harian, 3, '0', STR_PAD_LEFT);
        return "VST-{$dateCode}-{$this->user_id}-{$noUrutPadded}";
    }

    public static function generateNomor($userId, $noUrut, $createdAt): string
    {
        $dateCode = $createdAt->format('Ymd');
        $noUrutPadded = str_pad($noUrut, 3, '0', STR_PAD_LEFT);
        return "VST-{$dateCode}-{$userId}-{$noUrutPadded}";
    }

     /**
      * Format tujuan kunjungan sebagai badge HTML.
      */
     public function getTujuanBadgeAttribute(): string
     {
         $badges = [
             'Pemeriksaan Stock' => '<span class="badge badge-info">Pemeriksaan Stock</span>',
             'Penagihan'         => '<span class="badge badge-warning">Penagihan</span>',
             'Penawaran'         => '<span class="badge badge-primary">Penawaran</span>',
             'Promo Gratis'      => '<span class="badge badge-success">Promo Gratis</span>',
             'Promo Sample'      => '<span class="badge" style="background:#6f42c1;color:#fff;">Promo Sample</span>',
         ];
         return $badges[$this->tujuan] ?? '<span class="badge badge-secondary">' . e($this->tujuan) . '</span>';
     }

    const TUJUAN_OPTIONS = [
        'Pemeriksaan Stock',
        'Penagihan',
        'Penawaran',
        'Promo Gratis',
        'Promo Sample',
    ];
}
