<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class AccountMappingKeyLock extends Model
{
    protected $fillable = [
        'mapping_key',
    ];
}
