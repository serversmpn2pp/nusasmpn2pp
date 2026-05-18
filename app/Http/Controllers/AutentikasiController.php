<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AutentikasiController extends Controller
{
    public function createLogin()
    {
        if (Auth::check()) {
            return redirect()->route('pegawai.index');
        }

        return view('auth.login');
    }

    public function storeLogin(Request $request)
    {
        $data = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $berhasil = Auth::attempt([
            'username' => $data['username'],
            'password' => $data['password'],
            'aktif' => true,
        ], $request->boolean('ingat'));

        if (! $berhasil) {
            throw ValidationException::withMessages([
                'username' => 'Username atau kata sandi tidak sesuai.',
            ]);
        }

        $request->session()->regenerate();
        $request->user()->forceFill([
            'terakhir_login_pada' => now(),
        ])->save();

        return redirect()
            ->intended(route('pegawai.index'))
            ->with('berhasil', 'Selamat datang di NUSA.');
    }

    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function editKataSandi()
    {
        return view('auth.ganti-kata-sandi');
    }

    public function updateKataSandi(Request $request)
    {
        $data = $request->validate([
            'kata_sandi_lama' => ['required', 'current_password:web'],
            'kata_sandi_baru' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'kata_sandi_lama.current_password' => 'Kata sandi lama tidak sesuai.',
            'kata_sandi_baru.confirmed' => 'Konfirmasi kata sandi baru tidak sama.',
            'kata_sandi_baru.min' => 'Kata sandi baru minimal 8 karakter.',
        ]);

        $request->user()->forceFill([
            'kata_sandi' => Hash::make($data['kata_sandi_baru']),
        ])->save();

        return back()->with('berhasil', 'Kata sandi berhasil diganti.');
    }
}
