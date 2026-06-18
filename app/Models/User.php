<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements FilamentUser
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'alamat',
        'no_telp',
        'avatar',
        'gudang_id',
        'current_gudang_id',
        'receives_transaction_email',
        'receives_transaction_whatsapp',
        'can_export_pdf',
        'can_export_excel',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $appends = ['avatar_url'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'receives_transaction_email' => 'boolean',
            'receives_transaction_whatsapp' => 'boolean',
            'can_export_pdf' => 'boolean',
            'can_export_excel' => 'boolean',
        ];
    }

    // ===== Filament =====

    public function canAccessPanel(Panel $panel): bool
    {
        // Semua user yang login bisa akses panel (bukan hanya admin)
        return true;
    }

    // ===== Accessors =====

    public function getAvatarUrlAttribute(): ?string
    {
        if (!$this->avatar) {
            return null;
        }
        return asset('storage/' . $this->avatar);
    }

    // ===== Relationships =====

    public function gudang()
    {
        return $this->belongsTo(Gudang::class);
    }

    public function gudangs()
    {
        return $this->belongsToMany(Gudang::class, 'admin_gudang')
            ->withTimestamps();
    }

    public function spectatorGudangs()
    {
        return $this->belongsToMany(Gudang::class, 'spectator_gudang')
            ->withTimestamps();
    }

    public function penjualans()
    {
        return $this->hasMany(Penjualan::class);
    }

    public function pembelians()
    {
        return $this->hasMany(Pembelian::class);
    }

    public function biayas()
    {
        return $this->hasMany(Biaya::class);
    }

    // ===== Gudang Helpers =====

    public function getCurrentGudang()
    {
        if ($this->role === 'user') {
            return $this->gudang;
        }

        if ($this->current_gudang_id) {
            return Gudang::find($this->current_gudang_id);
        }

        if ($this->role === 'admin') {
            return $this->gudangs()->first();
        } elseif ($this->role === 'spectator') {
            return $this->spectatorGudangs()->first();
        }

        return null;
    }

    public function canAccessGudang($gudangId): bool
    {
        if ($this->role === 'super_admin') {
            return true;
        }

        if ($this->role === 'admin') {
            return $this->gudangs()->where('gudangs.id', $gudangId)->exists();
        }

        if ($this->role === 'spectator') {
            return $this->spectatorGudangs()->where('gudangs.id', $gudangId)->exists();
        }

        return $this->gudang_id == $gudangId;
    }

    // ===== Role Helpers =====

    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    public function isAdmin(): bool
    {
        return in_array($this->role, ['admin', 'super_admin']);
    }

    public function isSpectator(): bool
    {
        return $this->role === 'spectator';
    }

    public function canExportPdf(): bool
    {
        if ($this->role === 'super_admin') return true;
        if ($this->role === 'admin') return (bool) $this->can_export_pdf;
        return false;
    }

    public function canExportExcel(): bool
    {
        if ($this->role === 'super_admin') return true;
        if ($this->role === 'admin') return (bool) $this->can_export_excel;
        return false;
    }

    public function canExportReport(): bool
    {
        return $this->canExportPdf() || $this->canExportExcel();
    }

    public static function getAvailableRoles(): array
    {
        if (auth()->check() && auth()->user()->isSuperAdmin()) {
            return [
                'user' => 'User (Sales)',
                'admin' => 'Admin',
                'spectator' => 'Spectator (Read-Only)',
                'super_admin' => 'Super Admin',
            ];
        }
        return [
            'user' => 'User (Sales)',
            'admin' => 'Admin',
        ];
    }
}
