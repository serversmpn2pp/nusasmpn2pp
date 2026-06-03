<?php

namespace App\Http\Controllers;

use App\Models\BatchScanUjianOmr;
use App\Models\HasilScanLjkUjianOmr;
use App\Models\NilaiSiswa;
use App\Models\UjianOmr;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TerapkanNilaiOmrController extends Controller
{
    public function store(Request $request, UjianOmr $ujianOmr, BatchScanUjianOmr $batchScan)
    {
        $this->pastikanBatchMilikUjian($ujianOmr, $batchScan);

        if ($batchScan->status !== 'selesai') {
            throw ValidationException::withMessages([
                'nilai' => 'Nilai baru dapat diterapkan setelah pemrosesan PDF selesai.',
            ]);
        }

        $batchScan->load([
            'hasilScan' => fn ($query) => $query->orderByDesc('id'),
            'hasilScan.lembarJawabUjianOmr.anggotaKelas',
            'hasilScan.lembarJawabUjianOmr.kelasUjianOmr.komponenNilai',
        ]);
        $hasilBersih = $batchScan->hasilScan
            ->where('status', 'terbaca')
            ->whereNull('diterapkan_pada')
            ->filter(fn (HasilScanLjkUjianOmr $hasil) => $hasil->lembar_jawab_ujian_omr_id && $hasil->nilai !== null)
            ->unique('lembar_jawab_ujian_omr_id');
        $lembarSudahDiterapkan = HasilScanLjkUjianOmr::query()
            ->whereIn('lembar_jawab_ujian_omr_id', $hasilBersih->pluck('lembar_jawab_ujian_omr_id'))
            ->whereNotNull('diterapkan_pada')
            ->pluck('lembar_jawab_ujian_omr_id')
            ->map(fn ($id) => (int) $id);
        $jumlahDiterapkan = 0;
        $jumlahTujuanTidakValid = 0;
        $jumlahSudahDiterapkan = 0;

        DB::transaction(function () use (
            $request,
            $hasilBersih,
            $lembarSudahDiterapkan,
            &$jumlahDiterapkan,
            &$jumlahTujuanTidakValid,
            &$jumlahSudahDiterapkan,
        ) {
            foreach ($hasilBersih as $hasil) {
                if ($lembarSudahDiterapkan->contains((int) $hasil->lembar_jawab_ujian_omr_id)) {
                    $jumlahSudahDiterapkan++;

                    continue;
                }

                $lembarJawab = $hasil->lembarJawabUjianOmr;
                $komponenNilai = $lembarJawab?->kelasUjianOmr?->komponenNilai;
                $anggotaKelas = $lembarJawab?->anggotaKelas;

                if (
                    ! $komponenNilai?->aktif
                    || ! in_array($komponenNilai->jenis_komponen, ['sts', 'sas_saj'], true)
                    || ! $anggotaKelas?->siswa_id
                ) {
                    $jumlahTujuanTidakValid++;

                    continue;
                }

                $nilaiSiswa = NilaiSiswa::updateOrCreate(
                    [
                        'komponen_nilai_id' => $komponenNilai->id,
                        'siswa_id' => $anggotaKelas->siswa_id,
                    ],
                    [
                        'nilai' => $hasil->nilai,
                    ],
                );
                $hasil->update([
                    'nilai_siswa_id' => $nilaiSiswa->id,
                    'diterapkan_pada' => now(),
                    'diterapkan_oleh_pengguna_id' => $request->user()?->id,
                ]);
                $jumlahDiterapkan++;
            }
        });

        $pesan = "{$jumlahDiterapkan} nilai hasil OMR berhasil diterapkan ke nilai siswa.";

        if ($jumlahSudahDiterapkan) {
            $pesan .= " {$jumlahSudahDiterapkan} LJK dilewati karena nilainya sudah pernah diterapkan.";
        }

        if ($jumlahTujuanTidakValid) {
            $pesan .= " {$jumlahTujuanTidakValid} LJK dilewati karena komponen nilai tujuannya tidak valid.";
        }

        return redirect()
            ->route('ujian-omr.scan.show', [$ujianOmr, $batchScan])
            ->with($jumlahDiterapkan ? 'berhasil' : 'gagal', $pesan);
    }

    private function pastikanBatchMilikUjian(UjianOmr $ujianOmr, BatchScanUjianOmr $batchScan): void
    {
        abort_unless((int) $batchScan->ujian_omr_id === (int) $ujianOmr->id, 404);
    }
}
