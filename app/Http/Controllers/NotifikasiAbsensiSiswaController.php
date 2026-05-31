<?php

namespace App\Http\Controllers;

use App\Models\NotifikasiAbsensiSiswa;
use Illuminate\Http\Request;

class NotifikasiAbsensiSiswaController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->input('status', 'semua');

        if (! array_key_exists($status, NotifikasiAbsensiSiswa::DAFTAR_STATUS) && $status !== 'semua') {
            $status = 'semua';
        }

        $notifikasi = NotifikasiAbsensiSiswa::query()
            ->with([
                'siswa:id,nama_lengkap,nisn',
                'absensiSiswa:id,kelas_id,jam_masuk,status_masuk,menit_terlambat',
                'absensiSiswa.kelas:id,nama',
            ])
            ->when($status !== 'semua', fn ($query) => $query->where('status', $status))
            ->latest('created_at')
            ->paginate(15)
            ->withQueryString();

        $ringkasan = collect(NotifikasiAbsensiSiswa::DAFTAR_STATUS)
            ->mapWithKeys(fn (string $label, string $kode) => [
                $kode => NotifikasiAbsensiSiswa::where('status', $kode)->count(),
            ])
            ->all();

        return view('notifikasi-absensi-siswa.index', [
            'notifikasi' => $notifikasi,
            'status' => $status,
            'daftarStatus' => NotifikasiAbsensiSiswa::DAFTAR_STATUS,
            'ringkasan' => $ringkasan,
        ]);
    }
}
