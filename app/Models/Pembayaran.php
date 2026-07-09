<?php

namespace App\Models;

use App\Models\Concerns\GeneratesNomorSafely;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Pembayaran extends Model
{
    use GeneratesNomorSafely;

    protected $fillable = [
        'user_id', 'approver_id', 'gudang_id',
        'type', 'penjualan_id', 'pembelian_id',
        'no_urut_harian', 'nomor', 'uuid',
        'tgl_pembayaran', 'metode_pembayaran', 'jumlah_bayar',
        'bukti_bayar', 'lampiran_paths', 'keterangan', 'status',
    ];

    protected function casts(): array
    {
        return [
            'tgl_pembayaran' => 'date',
            'lampiran_paths' => 'array',
            'jumlah_bayar' => 'decimal:2',
            'penjualan_id' => 'integer',
            'pembelian_id' => 'integer',
        ];
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($m): void {
            $m->uuid = $m->uuid ?: (string) Str::uuid();
            $m->type = $m->type ?: 'piutang';
        });

        static::saving(function (self $pembayaran): void {
            $pembayaran->validateTypedRelation();
        });
    }

    public function validateTypedRelation(): void
    {
        if ($this->type === 'hutang' && empty($this->pembelian_id)) {
            throw new \DomainException('Pembayaran hutang wajib memiliki pembelian_id.');
        }

        if (($this->type ?? 'piutang') === 'piutang' && empty($this->penjualan_id)) {
            throw new \DomainException('Pembayaran piutang wajib memiliki penjualan_id.');
        }
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    public function gudang()
    {
        return $this->belongsTo(Gudang::class);
    }

    public function penjualan()
    {
        return $this->belongsTo(Penjualan::class);
    }

    public function pembelian()
    {
        return $this->belongsTo(Pembelian::class);
    }

    // Scopes
    public function scopePiutang($query)
    {
        return $query->where('type', 'piutang');
    }

    public function scopeHutang($query)
    {
        return $query->where('type', 'hutang');
    }

    public function getCustomNumberAttribute(): string
    {
        if ($this->nomor) {
            return $this->nomor;
        }
        $dateCode = $this->created_at->format('Ymd');
        $noUrutPadded = str_pad($this->no_urut_harian, 3, '0', STR_PAD_LEFT);

        return "PAY-{$dateCode}-{$this->user_id}-{$noUrutPadded}";
    }

    public static function generateNomor($userId, $noUrut, $createdAt): string
    {
        $dateCode = $createdAt->format('Ymd');
        $noUrutPadded = str_pad($noUrut, 3, '0', STR_PAD_LEFT);

        return "PAY-{$dateCode}-{$userId}-{$noUrutPadded}";
    }

    /**
     * Generate nomor transaksi dengan retry-on-duplicate strategy (race-safe).
     */
    public static function generateNomorSafe(int $userId, Carbon $createdAt): string
    {
        return static::generateNomorWithRetry('PAY', $userId, $createdAt);
    }
}
