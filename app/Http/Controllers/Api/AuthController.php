<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PersonalAccessToken;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Email atau password salah.'], 401);
        }

        // Generate token
        $plainToken = Str::random(64);
        $hashedToken = hash('sha256', $plainToken);

        PersonalAccessToken::create([
            'user_id' => $user->id,
            'name' => $request->device_name ?? 'mobile',
            'token' => $hashedToken,
            'expires_at' => now()->addDays(30),
        ]);

        return response()->json([
            'message' => 'Login berhasil.',
            'token' => $plainToken,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'alamat' => $user->alamat,
                'no_telp' => $user->no_telp,
                'avatar_url' => $user->avatar_url,
                'gudang_id' => $user->gudang_id,
                'current_gudang_id' => $user->current_gudang_id,
            ],
        ]);
    }

    public function logout(Request $request)
    {
        PersonalAccessToken::where('id', $request->api_token_id)->delete();

        return response()->json(['message' => 'Logout berhasil.']);
    }

    public function profile(Request $request)
    {
        $user = auth()->user();
        $gudang = $user->getCurrentGudang();

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'alamat' => $user->alamat,
                'no_telp' => $user->no_telp,
                'avatar_url' => $user->avatar_url,
                'gudang_id' => $user->gudang_id,
                'current_gudang_id' => $user->current_gudang_id,
            ],
            'gudang' => $gudang ? [
                'id' => $gudang->id,
                'nama_gudang' => $gudang->nama_gudang,
                'alamat_gudang' => $gudang->alamat_gudang,
            ] : null,
        ]);
    }

    public function updateProfile(Request $request)
    {
        $request->validate([
            'name' => 'sometimes|string|max:255',
            'alamat' => 'nullable|string',
            'no_telp' => 'nullable|string|max:20',
        ]);

        $user = auth()->user();
        $user->update($request->only(['name', 'alamat', 'no_telp']));

        return response()->json([
            'message' => 'Profil berhasil diperbarui.',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'alamat' => $user->alamat,
                'no_telp' => $user->no_telp,
                'avatar_url' => $user->avatar_url,
                'gudang_id' => $user->gudang_id,
                'current_gudang_id' => $user->current_gudang_id,
            ],
        ]);
    }

    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        $user = auth()->user();

        if (! Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'message' => 'Password lama salah.',
            ], 422);
        }

        $user->update(['password' => $request->new_password]);

        return response()->json(['message' => 'Password berhasil diubah.']);
    }

    public function uploadAvatar(Request $request)
    {
        $user = auth()->user();

        if ($request->hasFile('avatar')) {
            $request->validate(['avatar' => 'image|max:2048']);
            $path = $request->file('avatar')->store('avatars', 'public');
        } elseif ($request->avatar_base64) {
            $request->validate(['avatar_base64' => 'string']);
            $imageData = base64_decode($request->avatar_base64);
            $filename = 'avatars/'.Str::uuid().'.jpg';
            \Storage::disk('public')->put($filename, $imageData);
            $path = $filename;
        } else {
            return response()->json(['message' => 'File avatar diperlukan.'], 422);
        }

        // Delete old avatar
        if ($user->avatar) {
            \Storage::disk('public')->delete($user->avatar);
        }

        $user->update(['avatar' => $path]);

        return response()->json([
            'message' => 'Avatar berhasil diupload.',
            'avatar_url' => $user->avatar_url,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'avatar_url' => $user->avatar_url,
            ],
        ]);
    }

    public function deleteAvatar(Request $request)
    {
        $user = auth()->user();

        if ($user->avatar) {
            \Storage::disk('public')->delete($user->avatar);
            $user->update(['avatar' => null]);
        }

        return response()->json([
            'message' => 'Avatar berhasil dihapus.',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'avatar_url' => null,
            ],
        ]);
    }
}
