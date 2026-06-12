<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Kontak;
use App\Models\Penjualan;
use Illuminate\Http\Request;

class KontakController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $query = Kontak::query();

        if (in_array($user->role, ['super_admin', 'spectator'])) {
            // see all
        } elseif ($user->role === 'admin') {
            $gudangIds = $this->getAccessibleGudangIds($user);
            $query->where(function ($q) use ($gudangIds) {
                $q->whereIn('gudang_id', $gudangIds)
                    ->orWhereNull('gudang_id');
            });
        } else {
            $userId = $user->id;
            $query->where(function ($q) use ($userId) {
                $q->where('created_by', $userId)
                    ->orWhere(function ($sub) use ($userId) {
                        $sub->whereNull('created_by')
                            ->whereIn('nama', function ($pq) use ($userId) {
                                $pq->select('pelanggan')
                                    ->from('penjualans')
                                    ->where('user_id', $userId)
                                    ->whereNotNull('pelanggan');
                            });
                    });
            });
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                    ->orWhere('kode_kontak', 'like', "%{$search}%")
                    ->orWhere('no_telp', 'like', "%{$search}%");
            });
        }

        return response()->json($query->orderBy('nama')->paginate($request->per_page ?? 50));
    }

    public function show($id)
    {
        $user = auth()->user();
        $kontak = Kontak::findOrFail($id);

        if (!$this->canAccessKontak($user, $kontak)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json($kontak);
    }

    public function store(Request $request)
    {
        $request->validate([
            'nama' => 'required|string|max:255',
            'no_telp' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'alamat' => 'nullable|string',
            'kode_kontak' => 'nullable|string|max:50',
            'pin' => 'nullable|string|size:6',
        ]);

        $user = auth()->user();
        $currentGudang = $user->getCurrentGudang();

        $kontak = Kontak::create([
            'kode_kontak'   => $request->kode_kontak,
            'nama'          => $request->nama,
            'email'         => $request->email,
            'no_telp'       => $request->no_telp,
            'pin'           => $request->pin,
            'alamat'        => $request->alamat,
            'diskon_persen' => $request->diskon_persen ?? 0,
            'gudang_id'     => $request->gudang_id ?? ($currentGudang ? $currentGudang->id : null),
            'created_by'    => $user->id,
        ]);

        return response()->json(['message' => 'Kontak berhasil dibuat.', 'data' => $kontak], 201);
    }

    public function update(Request $request, $id)
    {
        $user = auth()->user();
        $kontak = Kontak::findOrFail($id);

        if (!$this->canAccessKontak($user, $kontak)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'nama'        => 'required|string|max:255',
            'no_telp'     => 'nullable|string|max:20',
            'email'       => 'nullable|email|max:255',
            'kode_kontak' => 'nullable|string|max:50',
            'pin'         => 'nullable|string|size:6',
        ]);

        $kontak->update($request->only(['nama', 'email', 'no_telp', 'alamat', 'diskon_persen', 'kode_kontak', 'pin']));

        return response()->json(['message' => 'Kontak berhasil diupdate.', 'data' => $kontak]);
    }

    public function destroy($id)
    {
        $user = auth()->user();
        $kontak = Kontak::findOrFail($id);

        if (!$this->canAccessKontak($user, $kontak)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $kontak->delete();

        return response()->json(['message' => 'Kontak berhasil dihapus.']);
    }

    private function getAccessibleGudangIds($user): array
    {
        $ids = $user->gudangs->pluck('id')->toArray();
        if ($user->current_gudang_id) {
            $ids[] = $user->current_gudang_id;
        }
        if ($user->gudang_id) {
            $ids[] = $user->gudang_id;
        }
        return array_unique($ids);
    }

    private function canAccessKontak($user, $kontak): bool
    {
        if (in_array($user->role, ['super_admin', 'spectator'])) {
            return true;
        }

        if ($user->role === 'admin') {
            if ($kontak->gudang_id === null) {
                return true;
            }
            return in_array($kontak->gudang_id, $this->getAccessibleGudangIds($user));
        }

        // user/sales
        if ((int) $kontak->created_by === (int) $user->id) {
            return true;
        }

        // Legacy fallback
        if ($kontak->created_by === null) {
            return Penjualan::where('user_id', $user->id)
                ->where('pelanggan', $kontak->nama)
                ->exists();
        }

        return false;
    }
}
