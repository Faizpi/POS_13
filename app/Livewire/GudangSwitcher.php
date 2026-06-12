<?php

namespace App\Livewire;

use App\Models\Gudang;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class GudangSwitcher extends Component
{
    public $currentGudangId;
    public $availableGudangs = [];

    public function mount()
    {
        $user = Auth::user();
        
        // Tidak perlu switcher untuk Super Admin atau User biasa (yang hanya memiliki 1 gudang statis)
        if (!$user || $user->isSuperAdmin() || $user->role === 'user') {
            return;
        }

        $gudangs = collect();

        if ($user->role === 'admin') {
            $gudangs = $user->gudangs;
            // Masukkan gudang utama jika belum ada di koleksi
            if ($user->gudang_id) {
                $mainGudang = Gudang::find($user->gudang_id);
                if ($mainGudang && !$gudangs->contains('id', $mainGudang->id)) {
                    $gudangs->push($mainGudang);
                }
            }
        } elseif ($user->role === 'spectator') {
            $gudangs = $user->spectatorGudangs;
        }

        if ($gudangs->count() > 1) {
            $this->availableGudangs = $gudangs->pluck('nama_gudang', 'id')->toArray();
            
            // Tambahkan label "Utama" untuk gudang utama admin
            if ($user->role === 'admin' && $user->gudang_id && isset($this->availableGudangs[$user->gudang_id])) {
                $this->availableGudangs[$user->gudang_id] .= ' (Utama)';
            }
            
            $this->currentGudangId = $user->current_gudang_id;
            
            if (!$this->currentGudangId && $user->role === 'admin') {
                $this->currentGudangId = $user->gudang_id;
            }
            
            // Set default aktif pertama jika belum diset
            if (!$this->currentGudangId && $gudangs->count() > 0) {
                $this->currentGudangId = $gudangs->first()->id;
                $user->update(['current_gudang_id' => $this->currentGudangId]);
            }
        }
    }

    public function switchGudang($gudangId)
    {
        $user = Auth::user();
        
        if ($gudangId && !$user->canAccessGudang($gudangId)) {
            Notification::make()
                ->title('Akses Ditolak')
                ->danger()
                ->send();
            return;
        }

        $user->update(['current_gudang_id' => $gudangId]);
        $this->currentGudangId = $gudangId;
        
        Notification::make()
            ->title('Gudang aktif berhasil diubah')
            ->success()
            ->send();

        // Refresh the page to apply changes across all queries
        return redirect(request()->header('Referer'));
    }

    public function render()
    {
        return view('livewire.gudang-switcher');
    }
}
