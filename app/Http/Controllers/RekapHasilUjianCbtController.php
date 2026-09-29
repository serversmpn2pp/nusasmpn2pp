<?php

namespace App\Http\Controllers;

use App\Models\UjianCbt;
use App\Services\Cbt\CakupanHasilUjianCbtService;
use App\Services\Cbt\FinalisasiHasilUjianTerpusatService;
use App\Services\Cbt\RekapHasilUjianCbtService;
use App\Services\Cbt\SelesaikanPengerjaanKedaluwarsaCbtService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RekapHasilUjianCbtController extends Controller
{
    public function index(
        Request $request,
        UjianCbt $ujianCbt,
        FinalisasiHasilUjianTerpusatService $finalisasiHasil,
        SelesaikanPengerjaanKedaluwarsaCbtService $penyelesaianKedaluwarsa,
        CakupanHasilUjianCbtService $cakupanHasil,
        RekapHasilUjianCbtService $rekapHasil,
    ) {
        $data = $request->validate([
            'kelas_id' => ['nullable', 'integer', 'exists:kelas,id'],
            'sesi_ujian_cbt_id' => ['nullable', 'integer', 'exists:sesi_ujian_cbt,id'],
            'status_hasil' => ['nullable', Rule::in([
                'semua',
                'tuntas',
                'belum_tuntas',
                'perlu_koreksi_otomatis',
                'perlu_koreksi_manual',
                'belum_mengikuti',
                'belum_selesai',
            ])],
        ]);

        $kelasId = isset($data['kelas_id']) ? (int) $data['kelas_id'] : null;
        $sesiUjianCbtId = isset($data['sesi_ujian_cbt_id']) ? (int) $data['sesi_ujian_cbt_id'] : null;
        $statusHasil = $data['status_hasil'] ?? 'semua';

        if ($ujianCbt->ujianTerpusat()) {
            $penyelesaianKedaluwarsa->selesaikanUjian($ujianCbt);
            $finalisasiHasil->sinkronkanAlfaOtomatis($request->user(), $ujianCbt);
        }

        $kelasDapatDilihat = $cakupanHasil->kelasDapatDilihat($request->user(), $ujianCbt);
        abort_if($kelasDapatDilihat->isEmpty(), 403);

        if ($kelasId) {
            abort_unless($kelasDapatDilihat->contains(
                fn ($kelasUjian) => (int) $kelasUjian->kelas_id === $kelasId,
            ), 403);
        }

        $dataTampilan = $rekapHasil->bangun(
            $ujianCbt,
            $kelasDapatDilihat,
            $kelasId,
            $sesiUjianCbtId,
            $statusHasil,
        );
        $dataTampilan['kelasDapatDiekspor'] = $cakupanHasil->kelasDapatDiekspor($request->user(), $ujianCbt);
        $dataTampilan['finalisasiHasil'] = $ujianCbt->ujianTerpusat()
            && $ujianCbt->jadwalUjianCbt->contains(fn ($item) => $item->kegiatanUjianCbt)
                ? $finalisasiHasil->ringkasan($request->user(), $ujianCbt)
                : null;
        $dalamJadwal = $ujianCbt->tanggal_mulai
            && $ujianCbt->tanggal_selesai
            && now()->between($ujianCbt->tanggal_mulai, $ujianCbt->tanggal_selesai, true);
        $pesertaMasihAktif = (int) data_get(
            $dataTampilan,
            'finalisasiHasil.kesiapan.peserta_masih_aktif',
            0,
        ) > 0;
        $dataTampilan['ujianSedangBerlangsung'] = ! $ujianCbt->hasil_difinalisasi_pada
            && ($dalamJadwal || $pesertaMasihAktif);

        if ($ujianCbt->asesmenKelas()) {
            return view('asesmen-kelas-cbt.hasil', $dataTampilan);
        }

        return view('ujian-cbt.hasil.index', $dataTampilan);
    }
}
