<?php

namespace App\Http\Controllers;

use App\Models\PenugasanGuruWaliSiswa;
use App\Models\TransaksiPoinSiswa;
use Illuminate\Http\Request;

class SiswaWaliSayaController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()?->pegawai_id, 403);

        $kataKunci = trim((string) $request->input('kata_kunci', ''));
        $penugasan = PenugasanGuruWaliSiswa::query()
            ->with([
                'siswa.anggotaKelas' => fn ($query) => $query
                    ->where('status_keanggotaan', 'aktif')
                    ->with(['kelas:id,nama,tahun_pelajaran_id', 'tahunPelajaran:id,nama,aktif']),
            ])
            ->where('guru_wali_pegawai_id', $request->user()->pegawai_id)
            ->where('aktif', true)
            ->when($kataKunci !== '', fn ($query) => $query->whereHas('siswa', fn ($query) => $query
                ->where('nama_lengkap', 'ilike', '%' . $kataKunci . '%')
                ->orWhere('nisn', 'ilike', '%' . $kataKunci . '%')))
            ->orderByDesc('tanggal_mulai')
            ->paginate(15)
            ->withQueryString();

        $tahunAktifIds = $penugasan->getCollection()
            ->flatMap(fn ($item) => $item->siswa?->anggotaKelas ?? collect())
            ->pluck('tahun_pelajaran_id')
            ->filter()
            ->unique();

        $totalPoin = TransaksiPoinSiswa::query()
            ->whereIn('siswa_id', $penugasan->getCollection()->pluck('siswa_id'))
            ->when($tahunAktifIds->isNotEmpty(), fn ($query) => $query->whereIn('tahun_pelajaran_id', $tahunAktifIds))
            ->selectRaw('siswa_id, SUM(poin) as total_poin')
            ->groupBy('siswa_id')
            ->pluck('total_poin', 'siswa_id');

        return view('siswa-wali-saya.index', compact('penugasan', 'totalPoin', 'kataKunci'));
    }
}
