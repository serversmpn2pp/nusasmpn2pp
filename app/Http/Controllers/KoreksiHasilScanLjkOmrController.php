<?php

namespace App\Http\Controllers;

use App\Models\BatchScanUjianOmr;
use App\Models\HasilScanLjkUjianOmr;
use App\Models\LembarJawabUjianOmr;
use App\Models\UjianOmr;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class KoreksiHasilScanLjkOmrController extends Controller
{
    public function edit(UjianOmr $ujianOmr, BatchScanUjianOmr $batchScan, HasilScanLjkUjianOmr $hasilScan)
    {
        $this->pastikanHasilMilikBatch($ujianOmr, $batchScan, $hasilScan);
        $this->pastikanBelumDiterapkan($hasilScan);
        $hasilScan->load([
            'jawaban' => fn ($query) => $query->orderBy('nomor_soal'),
            'lembarJawabUjianOmr.anggotaKelas.siswa',
            'lembarJawabUjianOmr.anggotaKelas.kelas',
            'lembarJawabUjianOmr.versiSoalUjianOmr',
        ]);

        return view('ujian-omr.scan.periksa', [
            'ujianOmr' => $ujianOmr,
            'batchScan' => $batchScan,
            'hasilScan' => $hasilScan,
            'daftarLembarJawab' => $this->daftarLembarJawab($ujianOmr),
            'lembarJawabTerpakaiIds' => HasilScanLjkUjianOmr::query()
                ->whereKeyNot($hasilScan->id)
                ->whereNotNull('lembar_jawab_ujian_omr_id')
                ->pluck('lembar_jawab_ujian_omr_id')
                ->map(fn ($id) => (int) $id),
            'daftarPilihan' => range('A', chr(ord('A') + $ujianOmr->jumlah_pilihan - 1)),
        ]);
    }

    public function update(
        Request $request,
        UjianOmr $ujianOmr,
        BatchScanUjianOmr $batchScan,
        HasilScanLjkUjianOmr $hasilScan,
    ) {
        $this->pastikanHasilMilikBatch($ujianOmr, $batchScan, $hasilScan);
        $this->pastikanBelumDiterapkan($hasilScan);
        $daftarPilihan = range('A', chr(ord('A') + $ujianOmr->jumlah_pilihan - 1));
        $data = $request->validate([
            'lembar_jawab_ujian_omr_id' => ['required', 'integer', 'exists:lembar_jawab_ujian_omr,id'],
            'jawaban' => ['required', 'array', 'size:' . $ujianOmr->jumlah_soal],
            'jawaban.*' => ['nullable', Rule::in($daftarPilihan)],
            'catatan_koreksi' => ['nullable', 'string', 'max:1000'],
        ], [
            'lembar_jawab_ujian_omr_id.required' => 'Pilih siswa pemilik LJK terlebih dahulu.',
            'jawaban.size' => 'Jumlah jawaban yang dikirim tidak sesuai dengan jumlah soal ujian.',
            'jawaban.*.in' => 'Ada pilihan jawaban yang tidak dikenali.',
        ]);
        $jawaban = collect($data['jawaban']);

        if ($jawaban->keys()->map(fn ($nomor) => (int) $nomor)->sort()->values()->all() !== range(1, $ujianOmr->jumlah_soal)) {
            throw ValidationException::withMessages([
                'jawaban' => 'Nomor jawaban tidak lengkap atau tidak sesuai dengan soal ujian.',
            ]);
        }

        $lembarJawab = LembarJawabUjianOmr::query()
            ->with(['anggotaKelas', 'versiSoalUjianOmr.kunciJawaban'])
            ->where('ujian_omr_id', $ujianOmr->id)
            ->findOrFail($data['lembar_jawab_ujian_omr_id']);
        $dipakaiHasilLain = HasilScanLjkUjianOmr::query()
            ->where('lembar_jawab_ujian_omr_id', $lembarJawab->id)
            ->whereKeyNot($hasilScan->id)
            ->exists();

        if ($dipakaiHasilLain) {
            throw ValidationException::withMessages([
                'lembar_jawab_ujian_omr_id' => 'LJK siswa ini sudah terhubung dengan hasil scan lain. Periksa hasil scan tersebut agar nilai siswa tidak tertukar.',
            ]);
        }

        $kunciJawaban = $lembarJawab->versiSoalUjianOmr->kunciJawaban->pluck('jawaban', 'nomor_soal');

        if ($kunciJawaban->count() !== $ujianOmr->jumlah_soal) {
            throw ValidationException::withMessages([
                'lembar_jawab_ujian_omr_id' => 'Kunci jawaban versi soal siswa belum lengkap. Lengkapi kunci sebelum melakukan koreksi.',
            ]);
        }

        $jumlahBenar = 0;
        $jumlahSalah = 0;
        $jumlahKosong = 0;
        $lembarJawabLamaId = $hasilScan->lembar_jawab_ujian_omr_id;

        DB::transaction(function () use (
            $request,
            $batchScan,
            $hasilScan,
            $lembarJawab,
            $lembarJawabLamaId,
            $ujianOmr,
            $jawaban,
            $kunciJawaban,
            $data,
            &$jumlahBenar,
            &$jumlahSalah,
            &$jumlahKosong,
        ) {
            foreach (range(1, $ujianOmr->jumlah_soal) as $nomorSoal) {
                $pilihan = filled($jawaban->get($nomorSoal)) ? (string) $jawaban->get($nomorSoal) : null;
                $benar = $pilihan !== null && $pilihan === $kunciJawaban->get($nomorSoal);

                if ($pilihan === null) {
                    $jumlahKosong++;
                } elseif ($benar) {
                    $jumlahBenar++;
                } else {
                    $jumlahSalah++;
                }

                $hasilScan->jawaban()->updateOrCreate(
                    ['nomor_soal' => $nomorSoal],
                    [
                        'jawaban' => $pilihan,
                        'status' => $pilihan === null ? 'kosong_dikonfirmasi' : 'dikoreksi_manual',
                        'benar' => $benar,
                    ],
                );
            }

            $hasilScan->update([
                'lembar_jawab_ujian_omr_id' => $lembarJawab->id,
                'status' => 'terbaca',
                'jumlah_benar' => $jumlahBenar,
                'jumlah_salah' => $jumlahSalah,
                'jumlah_kosong' => $jumlahKosong,
                'jumlah_ganda' => 0,
                'nilai' => round($jumlahBenar / max(1, $ujianOmr->jumlah_soal) * 100, 2),
                'catatan_koreksi' => filled($data['catatan_koreksi'] ?? null)
                    ? trim($data['catatan_koreksi'])
                    : null,
                'dikoreksi_pada' => now(),
                'dikoreksi_oleh_pengguna_id' => $request->user()?->id,
            ]);
            $lembarJawab->update(['status' => 'sudah_dipindai']);

            if ($lembarJawabLamaId && (int) $lembarJawabLamaId !== (int) $lembarJawab->id) {
                $this->perbaruiStatusLembarJawabLama((int) $lembarJawabLamaId, $hasilScan->id);
            }

            $this->perbaruiRingkasanBatch($batchScan);
        });

        return redirect()
            ->route('ujian-omr.scan.show', [$ujianOmr, $batchScan])
            ->with('berhasil', 'Hasil LJK berhasil dikoreksi. Nilai telah dihitung ulang dan siap diterapkan.');
    }

    private function daftarLembarJawab(UjianOmr $ujianOmr)
    {
        return $ujianOmr->lembarJawabUjianOmr()
            ->with(['anggotaKelas.siswa', 'anggotaKelas.kelas', 'versiSoalUjianOmr'])
            ->get()
            ->sortBy(fn (LembarJawabUjianOmr $lembar) => sprintf(
                '%s|%05d|%s',
                $lembar->anggotaKelas?->kelas?->nama ?? '',
                $lembar->anggotaKelas?->nomor_absen ?? 999,
                $lembar->anggotaKelas?->siswa?->nama_lengkap ?? '',
            ))
            ->values();
    }

    private function perbaruiRingkasanBatch(BatchScanUjianOmr $batchScan): void
    {
        $batchScan->update([
            'jumlah_berhasil' => $batchScan->hasilScan()->where('status', 'terbaca')->count(),
            'jumlah_perlu_diperiksa' => $batchScan->hasilScan()->where('status', '!=', 'terbaca')->count(),
        ]);
    }

    private function perbaruiStatusLembarJawabLama(int $lembarJawabId, int $hasilScanId): void
    {
        $masihTerbaca = HasilScanLjkUjianOmr::query()
            ->where('lembar_jawab_ujian_omr_id', $lembarJawabId)
            ->whereKeyNot($hasilScanId)
            ->where('status', 'terbaca')
            ->exists();

        LembarJawabUjianOmr::whereKey($lembarJawabId)->update([
            'status' => $masihTerbaca ? 'sudah_dipindai' : 'perlu_diperiksa',
        ]);
    }

    private function pastikanHasilMilikBatch(
        UjianOmr $ujianOmr,
        BatchScanUjianOmr $batchScan,
        HasilScanLjkUjianOmr $hasilScan,
    ): void {
        abort_unless((int) $batchScan->ujian_omr_id === (int) $ujianOmr->id, 404);
        abort_unless((int) $hasilScan->batch_scan_ujian_omr_id === (int) $batchScan->id, 404);
    }

    private function pastikanBelumDiterapkan(HasilScanLjkUjianOmr $hasilScan): void
    {
        if ($hasilScan->diterapkan_pada) {
            throw ValidationException::withMessages([
                'nilai' => 'Nilai yang sudah diterapkan tidak dapat dikoreksi dari halaman OMR. Gunakan fitur Input Nilai jika memang diperlukan.',
            ]);
        }
    }
}
