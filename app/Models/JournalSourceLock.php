<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class JournalSourceLock extends Model
{
    protected $table = 'journal_source_locks';

    protected $fillable = [
        'source_type',
        'source_id',
        'journal_type',
        'source_version',
        'locked_at',
    ];

    protected function casts(): array
    {
        return [
            'source_id' => 'integer',
            'source_version' => 'integer',
            'locked_at' => 'immutable_datetime',
        ];
    }

    // No relationships - this is a serialization lock, not a domain entity
}
