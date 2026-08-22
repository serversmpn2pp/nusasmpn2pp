<?php

namespace App\Http\Controllers;

use App\Models\JawabanPesertaUjianCbt;
use App\Models\NilaiSiswa;
use App\Models\SoalUjianCbt;
use App\Models\UjianCbt;
use App\Services\Cbt\KoreksiOtomatisCbtService;
use App\Services\Nilai\PublikasiNilaiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TerapkanNilaiCbtController extends Controller
{
    public function __construct(private PublikasiNilaiService $publikasiNilai) {}

    public function store(Request $request, UjianCbt $ujianCbt, KoreksiOtomatisCbtService $koreksiOtomatisCbtService)
    {
        $soalUjian = $this->ambilSoalUjian($ujianCbt);
        $bobotTotal = round($soalUjian->sum(fn (SoalUjianCbt $item) => (float) $item->bobot), 2);

        if ($soalUjian->isEmpty() || $bobotTotal <= 0) {
            throw ValidationException::withMessages([
                'nilai' => $ujianCbt->asesmenKelas()
                    ? 'Nilai belum dapat dimasukkan karena asesmen belum memiliki soal berbobot.'
                    : 'Nilai belum dapat diterapkan karena paket CBT belum memiliki soal berbobot.',
            ]);
        }

        $koreksiOtomatisCbtService->koreksiUjian($ujianCbt);

        $soalOtomatisIds = $soalUjian
            ->filter(fn (SoalUjianCbt $item) => in_array($item->soalCbt?->jenis_soal, KoreksiOtomatisCbtService::JENIS_OTOMATIS, true))
            ->pluck('id')
            ->all();
        $soalManualIds = $soalUjian
            ->reject(fn (SoalUjianCbt $item) => in_array($item->soalCbt?->jenis_soal, KoreksiOtomatisCbtService::JENIS_OTOMATIS, true))
            ->pluck('id')
            ->all();
        $soalUjianIds = $soalUjian->pluck('id')->all();

        $pesertaUjian = $ujianCbt->pesertaUjianCbt()
            ->with([
                'kelasUjianCbt.komponenNilai',
                'anggotaKelas.siswa',
                'jawabanPesertaUjianCbt' => fn ($query) => $query->whereIn('soal_ujian_cbt_id', $soalUjianIds),
            ])
            ->get();

        $ringkasan = [
            'diterapkan' => 0,
            'belum_selesai' => 0,
            'perlu_koreksi_otomatis' => 0,
            'perlu_koreksi_manual' => 0,
            'tujuan_tidak_valid' => 0,
        ];
        $cakupanNilaiBerubah = collect();

        DB::transaction(function () use (
            $request,
            $ujianCbt,
            $pesertaUjian,
            $soalOtomatisIds,
            $soalManualIds,
            $soalUjianIds,
            $bobotTotal,
            &$ringkasan,
            $cakupanNilaiBerubah,
        ) {
            foreach ($pesertaUjian as $peserta) {
                $komponenNilai = $peserta->kelasUjianCbt?->komponenNilai;
                $siswaId = $peserta->anggotaKelas?->siswa_id;

                if ($peserta->status !== 'selesai') {
                    $ringkasan['belum_selesai']++;

                    continue;
                }

                if (
                    ! $komponenNilai?->aktif
                    || ! in_array($komponenNilai->jenis_komponen, ['sumatif', 'sts', 'sas_saj'], true)
                    || ! $siswaId
                ) {
                    $ringkasan['tujuan_tidak_valid']++;

                    continue;
                }

                $jawaban = $peserta->jawabanPesertaUjianCbt->keyBy('soal_ujian_cbt_id');
                $perluKoreksiOtomatis = collect($soalOtomatisIds)
                    ->filter(fn ($id) => ! $jawaban->has($id) || is_null($jawaban[$id]->skor))
                    ->count();
                $perluKoreksiManual = collect($soalManualIds)
                    ->filter(fn ($id) => $jawaban->has($id) && ! is_null($jawaban[$id]->jawaban) && is_null($jawaban[$id]->skor))
                    ->count();

                if ($perluKoreksiOtomatis > 0) {
                    $ringkasan['perlu_koreksi_otomatis']++;

                    continue;
                }

                if ($perluKoreksiManual > 0) {
                    $ringkasan['perlu_koreksi_manual']++;

                    continue;
                }

                $skorTotal = $jawaban
                    ->filter(fn (JawabanPesertaUjianCbt $item) => in_array((int) $item->soal_ujian_cbt_id, $soalUjianIds, true))
                    ->sum(fn (JawabanPesertaUjianCbt $item) => (float) ($item->skor ?? 0));
                $nilai = round(($skorTotal / $bobotTotal) * 100, 2);
                $nilai = max(0, min(100, $nilai));

                $nilaiSiswa = NilaiSiswa::query()->firstOrNew([
                    'komponen_nilai_id' => $komponenNilai->id,
                    'siswa_id' => $siswaId,
                ]);
                $nilaiSiswa->nilai = $nilai;

                if (blank($nilaiSiswa->catatan) || str_starts_with((string) $nilaiSiswa->catatan, 'Diterapkan dari CBT ')) {
                    $nilaiSiswa->catatan = "Diterapkan dari CBT {$ujianCbt->kode}.";
                }

                $nilaiSiswa->save();
                $peserta->update([
                    'nilai_siswa_id' => $nilaiSiswa->id,
                    'nilai_diterapkan_pada' => now(),
                    'nilai_diterapkan_oleh_pengguna_id' => $request->user()?->id,
                ]);
                $cakupanNilaiBerubah->push([
                    'guru_mata_pelajaran_id' => (int) $komponenNilai->guru_mata_pelajaran_id,
                    'semester' => $komponenNilai->semester,
                ]);
                $ringkasan['diterapkan']++;
            }
        });

        $cakupanNilaiBerubah
            ->unique(fn (array $item) => $item['guru_mata_pelajaran_id'].'|'.$item['semester'])
            ->each(fn (array $item) => $this->publikasiNilai->tandaiDraf(
                $item['guru_mata_pelajaran_id'],
                $item['semester'],
            ));

        $pesan = $ujianCbt->asesmenKelas()
            ? "{$ringkasan['diterapkan']} hasil asesmen berhasil dimasukkan ke nilai siswa."
            : "{$ringkasan['diterapkan']} nilai CBT berhasil diterapkan ke nilai siswa.";

        if ($ringkasan['belum_selesai']) {
            $pesan .= " {$ringkasan['belum_selesai']} peserta dilewati karena belum selesai.";
        }

        if ($ringkasan['perlu_koreksi_otomatis']) {
            $pesan .= " {$ringkasan['perlu_koreksi_otomatis']} peserta dilewati karena masih perlu koreksi otomatis.";
        }

        if ($ringkasan['perlu_koreksi_manual']) {
            $pesan .= " {$ringkasan['perlu_koreksi_manual']} peserta dilewati karena masih perlu koreksi manual.";
        }

        if ($ringkasan['tujuan_tidak_valid']) {
            $pesan .= " {$ringkasan['tujuan_tidak_valid']} peserta dilewati karena komponen nilai tujuannya tidak valid.";
        }

        return redirect()
            ->route('ujian-cbt.hasil.index', $ujianCbt)
            ->with($ringkasan['diterapkan'] ? 'berhasil' : 'gagal', $pesan);
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
