<?php

namespace App\Http\Controllers;

use App\Models\PengaturanInventaris;
use Illuminate\Http\Request;

class PengaturanInventarisController extends Controller
{
    public function index()
    {
        $pengaturan = PengaturanInventaris::utama()->load('diperbaruiOleh');

        return view('pengaturan-inventaris.index', compact('pengaturan'));
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'awalan_nomor_aset' => ['required', 'string', 'max:80', 'regex:/^\d{2}(?:\.\d{2})*$/'],
            'akhiran_nomor_aset' => ['required', 'string', 'max:20', 'regex:/^\d{2}$/'],
            'nama_pemilik' => ['required', 'string', 'max:160'],
            'jumlah_digit_id_internal' => ['required', 'integer', 'min:4', 'max:10'],
        ], [
            'awalan_nomor_aset.regex' => 'Awalan nomor aset harus berupa kelompok dua angka yang dipisahkan titik, misalnya 12.03.15.08.10.',
            'akhiran_nomor_aset.regex' => 'Akhiran nomor aset harus terdiri dari dua angka, misalnya 08.',
        ]);

        $pengaturan = PengaturanInventaris::utama();
        $pengaturan->update([
            'awalan_nomor_aset' => trim($data['awalan_nomor_aset'], '.'),
            'akhiran_nomor_aset' => trim($data['akhiran_nomor_aset'], '.'),
            'nama_pemilik' => trim($data['nama_pemilik']),
            'jumlah_digit_id_internal' => (int) $data['jumlah_digit_id_internal'],
            'diperbarui_oleh_pengguna_id' => $request->user()?->id,
        ]);

        return redirect()
            ->route('pengaturan-inventaris.index')
            ->with('berhasil', 'Pengaturan identitas inventaris berhasil disimpan.');
    }
}
