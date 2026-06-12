<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TutupBuku extends Model
{
    protected $table = 'tutup_buku';

    protected $fillable = [
        'tahun',
        'status',
        'closed_by',
        'closed_at',
        'metadata',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'tahun' => 'integer',
            'closed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    // Relationships
    public function closedBy()
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    // Scopes
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    // Helpers
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isProcessing(): bool
    {
        return $this->status === 'processing';
    }

    public static function isYearClosed(int $year): bool
    {
        return static::where('tahun', $year)
            ->where('status', 'completed')
            ->exists();
    }

    public static function getLastClosedYear(): ?int
    {
        $record = static::completed()
            ->orderBy('tahun', 'desc')
            ->first();

        return $record?->tahun;
    }
}
