<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        if ($user->role !== 'super_admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $query = User::with('gudang:id,nama_gudang', 'gudangs:id,nama_gudang', 'spectatorGudangs:id,nama_gudang');

        if ($request->filled('role')) {
            $query->where('role', $request->role);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        return response()->json(
            $query->orderByRaw("FIELD(role, 'super_admin','spectator','admin','user')")
                ->orderBy('name')
                ->paginate($request->per_page ?? 20)
        );
    }

    public function show($id)
    {
        $user = auth()->user();
        if ($user->role !== 'super_admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json(
            User::with('gudang:id,nama_gudang', 'gudangs:id,nama_gudang', 'spectatorGudangs:id,nama_gudang')->findOrFail($id)
        );
    }

    public function store(Request $request)
    {
        $user = auth()->user();
        if ($user->role !== 'super_admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $rules = [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'role' => ['required', Rule::in(['super_admin', 'admin', 'spectator', 'user'])],
            'alamat' => 'nullable|string',
            'no_telp' => 'nullable|string|max:20',
        ];

        if (in_array($request->role, ['admin', 'spectator'])) {
            $rules['gudangs'] = 'required|array|min:1';
            $rules['gudangs.*'] = 'exists:gudangs,id';
        } elseif ($request->role === 'user') {
            $rules['gudang_id'] = 'required|exists:gudangs,id';
        }

        if ($request->role === 'admin') {
            $rules['can_export_pdf'] = 'nullable|boolean';
            $rules['can_export_excel'] = 'nullable|boolean';
        }

        $request->validate($rules);

        $newUser = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $request->role,
            'alamat' => $request->alamat,
            'no_telp' => $request->no_telp,
            'gudang_id' => ($request->role === 'user') ? $request->gudang_id : null,
            'can_export_pdf' => $request->role === 'admin' ? (bool) $request->input('can_export_pdf', false) : false,
            'can_export_excel' => $request->role === 'admin' ? (bool) $request->input('can_export_excel', false) : false,
            'receives_transaction_whatsapp' => $request->input('receives_transaction_whatsapp', true),
        ]);

        if ($request->role === 'admin' && $request->gudangs) {
            $newUser->gudangs()->sync($request->gudangs);
            $newUser->update(['current_gudang_id' => $request->gudangs[0]]);
        } elseif ($request->role === 'spectator' && $request->gudangs) {
            $newUser->spectatorGudangs()->sync($request->gudangs);
            $newUser->update(['current_gudang_id' => $request->gudangs[0]]);
        }

        return response()->json(['message' => 'User berhasil dibuat.', 'data' => $newUser->load('gudang', 'gudangs', 'spectatorGudangs')], 201);
    }

    public function update(Request $request, $id)
    {
        $user = auth()->user();
        if ($user->role !== 'super_admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $targetUser = User::findOrFail($id);

        $rules = [
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($id)],
            'role' => ['required', Rule::in(['super_admin', 'admin', 'spectator', 'user'])],
            'alamat' => 'nullable|string',
            'no_telp' => 'nullable|string|max:20',
            'password' => 'nullable|string|min:8',
        ];

        if (in_array($request->role, ['admin', 'spectator'])) {
            $rules['gudangs'] = 'required|array|min:1';
            $rules['gudangs.*'] = 'exists:gudangs,id';
        } elseif ($request->role === 'user') {
            $rules['gudang_id'] = 'required|exists:gudangs,id';
        }

        $request->validate($rules);

        $data = $request->only('name', 'email', 'alamat', 'no_telp', 'role');
        $data['gudang_id'] = ($request->role === 'user') ? $request->gudang_id : null;
        $data['can_export_pdf'] = $request->role === 'admin' ? (bool) $request->input('can_export_pdf', false) : false;
        $data['can_export_excel'] = $request->role === 'admin' ? (bool) $request->input('can_export_excel', false) : false;
        $data['receives_transaction_whatsapp'] = (bool) $request->input('receives_transaction_whatsapp');

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $oldRole = $targetUser->role;
        $targetUser->update($data);

        if ($oldRole !== $request->role) {
            $targetUser->gudangs()->detach();
            $targetUser->spectatorGudangs()->detach();
        }

        if ($request->role === 'admin' && $request->gudangs) {
            $targetUser->gudangs()->sync($request->gudangs);
            if (!$targetUser->current_gudang_id || !in_array($targetUser->current_gudang_id, $request->gudangs)) {
                $targetUser->update(['current_gudang_id' => $request->gudangs[0]]);
            }
        } elseif ($request->role === 'spectator' && $request->gudangs) {
            $targetUser->spectatorGudangs()->sync($request->gudangs);
            if (!$targetUser->current_gudang_id || !in_array($targetUser->current_gudang_id, $request->gudangs)) {
                $targetUser->update(['current_gudang_id' => $request->gudangs[0]]);
            }
        } elseif ($request->role === 'user') {
            $targetUser->gudangs()->detach();
            $targetUser->spectatorGudangs()->detach();
            $targetUser->update(['current_gudang_id' => null]);
        }

        return response()->json(['message' => 'User berhasil diupdate.', 'data' => $targetUser->fresh()->load('gudang', 'gudangs', 'spectatorGudangs')]);
    }

    public function destroy($id)
    {
        $user = auth()->user();
        if ($user->role !== 'super_admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($user->id == $id) {
            return response()->json(['message' => 'Tidak bisa menghapus akun sendiri.'], 422);
        }

        $targetUser = User::findOrFail($id);

        $penjualanCount = \App\Models\Penjualan::where('user_id', $id)->count();
        $pembelianCount = \App\Models\Pembelian::where('user_id', $id)->count();
        $kunjunganCount = \App\Models\Kunjungan::where('user_id', $id)->count();

        if ($penjualanCount > 0 || $pembelianCount > 0 || $kunjunganCount > 0) {
            return response()->json([
                'message' => 'Tidak dapat menghapus user karena masih memiliki transaksi.',
                'transaksi' => compact('penjualanCount', 'pembelianCount', 'kunjunganCount'),
            ], 422);
        }

        $targetUser->delete();
        return response()->json(['message' => 'User berhasil dihapus.']);
    }
}
