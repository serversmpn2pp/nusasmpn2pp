<?php

namespace App\Http\Controllers;

use App\Models\PesertaUjianCbt;
use App\Models\SesiUjianCbt;
use App\Models\SoalUjianCbt;
use App\Models\UjianCbt;
use App\Services\Cbt\KoreksiOtomatisCbtService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RekapHasilUjianCbtController extends Controller
{
    public function index(Request $request, UjianCbt $ujianCbt)
    {
        $data = $request->validate([
            'kelas_id' => ['nullable', 'integer', 'exists:kelas,id'],
            'sesi_ujian_cbt_id' => ['nullable', 'integer', 'exists:sesi_ujian_cbt,id'],
            'status_hasil' => ['nullable', Rule::in([
                'semua',
                'tuntas',
                'belum_tuntas',
                'perlu_koreksi_otomatis',
                'perlu_koreksi_manual',
                'belum_selesai',
            ])],
        ]);

        $kelasId = $data['kelas_id'] ?? null;
        $sesiUjianCbtId = $data['sesi_ujian_cbt_id'] ?? null;
        $statusHasil = $data['status_hasil'] ?? 'semua';

        $ujianCbt->load([
            'jenisUjianCbt',
            'tahunPelajaran',
            'mataPelajaran',
            'kelasUjianCbt.kelas',
            'kelasUjianCbt.komponenNilai',
            'sesiUjianCbt',
        ]);

        $soalUjian = $this->ambilSoalUjian($ujianCbt);
        $jumlahSoalTampil = $soalUjian->count();
        $bobotTotal = round($soalUjian->sum(fn ($item) => (float) $item->bobot), 2);
        $soalOtomatisIds = $soalUjian
            ->filter(fn ($item) => in_array($item->soalCbt?->jenis_soal, KoreksiOtomatisCbtService::JENIS_OTOMATIS, true))
            ->pluck('id')
            ->all();
        $soalManualIds = $soalUjian
            ->reject(fn ($item) => in_array($item->soalCbt?->jenis_soal, KoreksiOtomatisCbtService::JENIS_OTOMATIS, true))
            ->pluck('id')
            ->all();

        $kelasPeserta = $ujianCbt->kelasUjianCbt
            ->sortBy(fn ($item) => $item->kelas?->nama)
            ->values();
        $sesiUjianCbt = $ujianCbt->sesiUjianCbt
            ->sortBy(fn (SesiUjianCbt $sesi) => sprintf(
                '%s|%s',
                $sesi->waktu_mulai?->format('YmdHis') ?? '99999999999999',
                $sesi->kode,
            ))
            ->values();

        $peserta = $ujianCbt->pesertaUjianCbt()
            ->with([
                'sesiUjianCbt',
                'kelasUjianCbt.kelas',
                'anggotaKelas.siswa',
                'jawabanPesertaUjianCbt',
            ])
            ->when($kelasId, fn ($query) => $query->whereHas(
                'kelasUjianCbt',
                fn ($query) => $query->where('kelas_id', $kelasId),
            ))
            ->when($sesiUjianCbtId, fn ($query) => $query->where('sesi_ujian_cbt_id', $sesiUjianCbtId))
            ->get()
            ->sortBy(fn (PesertaUjianCbt $item) => sprintf(
                '%s|%s|%05d|%s',
                $item->sesiUjianCbt?->kode ?? '',
                $item->kelasUjianCbt?->kelas?->nama ?? '',
                $item->anggotaKelas?->nomor_absen ?? 999,
                $item->anggotaKelas?->siswa?->nama_lengkap ?? '',
            ))
            ->values();

        $rekapSemua = $peserta
            ->map(fn (PesertaUjianCbt $item) => $this->susunRekapPeserta(
                $item,
                $soalUjian,
                $soalOtomatisIds,
                $soalManualIds,
                $bobotTotal,
                $ujianCbt->kkm,
            ))
            ->values();
        $rekapHasil = $rekapSemua
            ->filter(fn ($item) => $statusHasil === 'semua' || $item['kode_status_hasil'] === $statusHasil)
            ->values();

        $dataTampilan = [
            'ujianCbt' => $ujianCbt,
            'kelasPeserta' => $kelasPeserta,
            'sesiUjianCbt' => $sesiUjianCbt,
            'rekapHasil' => $rekapHasil,
            'ringkasan' => $this->ringkasan($rekapSemua),
            'kelasId' => $kelasId,
            'sesiUjianCbtId' => $sesiUjianCbtId,
            'statusHasil' => $statusHasil,
            'jumlahSoalTampil' => $jumlahSoalTampil,
            'jumlahSoalOtomatis' => count($soalOtomatisIds),
            'jumlahSoalManual' => count($soalManualIds),
            'bobotTotal' => $bobotTotal,
        ];

        if ($ujianCbt->asesmenKelas()) {
            return view('asesmen-kelas-cbt.hasil', $dataTampilan);
        }

        return view('ujian-cbt.hasil.index', $dataTampilan);
    }

    private function susunRekapPeserta(
        PesertaUjianCbt $peserta,
        $soalUjian,
        array $soalOtomatisIds,
        array $soalManualIds,
        float $bobotTotal,
        ?int $kkm,
    ): array {
        $jawaban = $peserta->jawabanPesertaUjianCbt->keyBy('soal_ujian_cbt_id');
        $jawabanTersimpan = $jawaban
            ->filter(fn ($item) => ! is_null($item->jawaban))
            ->count();
        $jawabanDikoreksi = $jawaban
            ->filter(fn ($item) => ! is_null($item->skor))
            ->count();
        $benar = $jawaban
            ->filter(fn ($item) => $item->benar === true)
            ->count();
        $skorTotal = round($jawaban->sum(fn ($item) => (float) ($item->skor ?? 0)), 2);
        $nilai = $bobotTotal > 0 ? round(($skorTotal / $bobotTotal) * 100, 2) : 0.0;

        $belumDikoreksiOtomatis = collect($soalOtomatisIds)
            ->filter(fn ($id) => ! $jawaban->has($id) || is_null($jawaban[$id]->skor))
            ->count();
        $perluKoreksiManual = collect($soalManualIds)
            ->filter(fn ($id) => $jawaban->has($id) && ! is_null($jawaban[$id]->jawaban) && is_null($jawaban[$id]->skor))
            ->count();
        $belumJawab = max(0, $soalUjian->count() - $jawabanTersimpan);
        $status = $this->statusHasil(
            $peserta,
            $nilai,
            $kkm,
            $belumDikoreksiOtomatis,
            $perluKoreksiManual,
        );

        return [
            'peserta' => $peserta,
            'jawaban_tersimpan' => $jawabanTersimpan,
            'jawaban_dikoreksi' => $jawabanDikoreksi,
            'benar' => $benar,
            'salah' => max(0, $jawabanDikoreksi - $benar),
            'belum_jawab' => $belumJawab,
            'belum_dikoreksi_otomatis' => $belumDikoreksiOtomatis,
            'perlu_koreksi_manual' => $perluKoreksiManual,
            'skor_total' => $skorTotal,
            'nilai' => $nilai,
            ...$status,
        ];
    }

    private function statusHasil(
        PesertaUjianCbt $peserta,
        float $nilai,
        ?int $kkm,
        int $belumDikoreksiOtomatis,
        int $perluKoreksiManual,
    ): array {
        if ($peserta->status !== 'selesai') {
            return [
                'kode_status_hasil' => 'belum_selesai',
                'label_status_hasil' => 'Belum selesai',
                'badge_status_hasil' => 'badge-muted',
            ];
        }

        if ($belumDikoreksiOtomatis > 0) {
            return [
                'kode_status_hasil' => 'perlu_koreksi_otomatis',
                'label_status_hasil' => 'Perlu koreksi otomatis',
                'badge_status_hasil' => 'badge-warning',
            ];
        }

        if ($perluKoreksiManual > 0) {
            return [
                'kode_status_hasil' => 'perlu_koreksi_manual',
                'label_status_hasil' => 'Perlu koreksi manual',
                'badge_status_hasil' => 'badge-warning',
            ];
        }

        if (! is_null($kkm) && $nilai >= $kkm) {
            return [
                'kode_status_hasil' => 'tuntas',
                'label_status_hasil' => 'Tuntas',
                'badge_status_hasil' => 'badge-active',
            ];
        }

        return [
            'kode_status_hasil' => 'belum_tuntas',
            'label_status_hasil' => is_null($kkm) ? 'Selesai' : 'Belum tuntas',
            'badge_status_hasil' => is_null($kkm) ? 'badge-active' : 'badge-inactive',
        ];
    }

    private function ringkasan($rekapSemua): array
    {
        $total = $rekapSemua->count();
        $nilaiAkhir = $rekapSemua->pluck('nilai');
        $hasilFinal = $rekapSemua->filter(fn ($item) => in_array(
            $item['kode_status_hasil'],
            ['tuntas', 'belum_tuntas'],
            true,
        ));
        $nilaiFinal = $hasilFinal->pluck('nilai');

        return [
            'total_peserta' => $total,
            'rata_rata' => $total > 0 ? round($nilaiAkhir->avg(), 2) : 0,
            'nilai_tertinggi' => $total > 0 ? round($nilaiAkhir->max(), 2) : 0,
            'nilai_terendah' => $total > 0 ? round($nilaiAkhir->min(), 2) : 0,
            'hasil_final' => $hasilFinal->count(),
            'rata_rata_final' => $hasilFinal->isNotEmpty() ? round($nilaiFinal->avg(), 2) : null,
            'nilai_tertinggi_final' => $hasilFinal->isNotEmpty() ? round($nilaiFinal->max(), 2) : null,
            'tuntas' => $rekapSemua->where('kode_status_hasil', 'tuntas')->count(),
            'belum_tuntas' => $rekapSemua->where('kode_status_hasil', 'belum_tuntas')->count(),
            'perlu_koreksi' => $rekapSemua
                ->filter(fn ($item) => in_array($item['kode_status_hasil'], ['perlu_koreksi_otomatis', 'perlu_koreksi_manual'], true))
                ->count(),
            'belum_selesai' => $rekapSemua->where('kode_status_hasil', 'belum_selesai')->count(),
        ];
    }

    private function ambilSoalUjian(UjianCbt $ujianCbt)
    {
        return $ujianCbt->soalUjianCbt()
            ->with('soalCbt')
            ->get()
            ->sortBy(fn (SoalUjianCbt $item) => sprintf('%05d|%08d', $item->nomor_urut ?? 9999, $item->id))
            ->values()
            ->take($ujianCbt->jumlah_soal);
    }
}
