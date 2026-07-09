<?php

namespace App\Models;

use App\Models\Concerns\GeneratesNomorSafely;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class StockOpname extends Model
{
    use GeneratesNomorSafely;

    protected $fillable = [
        'user_id', 'approver_id', 'gudang_id',
        'no_urut_harian', 'nomor', 'uuid',
        'tgl_opname', 'status', 'memo', 'lampiran_paths',
    ];

    protected function casts(): array
    {
        return [
            'tgl_opname' => 'date',
            'lampiran_paths' => 'array',
        ];
    }

    protected static function boot()
    {
        parent::boot();
        static::creating(fn ($m) => $m->uuid = $m->uuid ?: (string) Str::uuid());
    }

    // Relationships
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

    public function items()
    {
        return $this->hasMany(StockOpnameItem::class);
    }

    // Accessors
    public function getCustomNumberAttribute(): string
    {
        if ($this->nomor) {
            return $this->nomor;
        }
        $dateCode = $this->created_at->format('Ymd');
        $noUrutPadded = str_pad($this->no_urut_harian, 3, '0', STR_PAD_LEFT);

        return "SOP-{$dateCode}-{$this->user_id}-{$noUrutPadded}";
    }

    public static function generateNomor($userId, $noUrut, $createdAt): string
    {
        $dateCode = $createdAt->format('Ymd');
        $noUrutPadded = str_pad($noUrut, 3, '0', STR_PAD_LEFT);

        return "SOP-{$dateCode}-{$userId}-{$noUrutPadded}";
    }

    /**
     * Generate nomor transaksi dengan retry-on-duplicate strategy (race-safe).
     */
    public static function generateNomorSafe(int $userId, Carbon $createdAt): string
    {
        return static::generateNomorWithRetry('SOP', $userId, $createdAt);
    }

    // Status helpers
    public function isDraft(): bool
    {
        return $this->status === 'Draft';
    }

    public function isSubmitted(): bool
    {
        return $this->status === 'Submitted';
    }

    public function isApplied(): bool
    {
        return $this->status === 'Applied';
    }
}
