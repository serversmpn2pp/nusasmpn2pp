<?php

namespace App\Http\Controllers;

use App\Models\BatchScanUjianOmr;
use App\Models\HasilScanLjkUjianOmr;
use App\Models\LembarJawabUjianOmr;
use App\Models\UjianOmr;
use App\Services\Omr\PembacaPdfLjkOmr;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class ScanLjkUjianOmrController extends Controller
{
    public function index(UjianOmr $ujianOmr)
    {
        $ujianOmr->load(['tahunPelajaran', 'mataPelajaran'])->loadCount('lembarJawabUjianOmr');
        $batchScan = $ujianOmr->batchScanUjianOmr()
            ->with('dibuatOleh')
            ->latest()
            ->paginate(10);

        return view('ujian-omr.scan.index', compact('ujianOmr', 'batchScan'));
    }

    public function store(Request $request, UjianOmr $ujianOmr, PembacaPdfLjkOmr $pembacaPdf)
    {
        $data = $request->validate([
            'file_pdf' => ['required', 'file', 'mimes:pdf', 'max:51200'],
        ]);

        if ($ujianOmr->status !== 'siap' || ! $ujianOmr->lembarJawabUjianOmr()->exists()) {
            throw ValidationException::withMessages([
                'file_pdf' => 'Generate LJK dan pastikan ujian berstatus siap digunakan sebelum memproses hasil scan.',
            ]);
        }

        $file = $data['file_pdf'];
        $lokasiFile = $file->store("omr/{$ujianOmr->id}/unggahan", 'local');
        $batchScan = BatchScanUjianOmr::create([
            'ujian_omr_id' => $ujianOmr->id,
            'nama_file_asli' => $file->getClientOriginalName(),
            'lokasi_file' => $lokasiFile,
            'status' => 'diproses',
            'dibuat_oleh_pengguna_id' => $request->user()?->id,
        ]);
        $direktoriRelatif = "omr/{$ujianOmr->id}/batch-{$batchScan->id}/pratinjau";
        Storage::disk('local')->makeDirectory($direktoriRelatif);

        try {
            $hasilMesin = $pembacaPdf->baca(
                Storage::disk('local')->path($lokasiFile),
                Storage::disk('local')->path($direktoriRelatif),
                $ujianOmr->jumlah_soal,
            );
            $this->simpanHasil($batchScan, $ujianOmr, $hasilMesin, $direktoriRelatif);
        } catch (Throwable $exception) {
            $batchScan->update([
                'status' => 'gagal',
                'pesan_error' => $exception->getMessage(),
            ]);

            throw ValidationException::withMessages([
                'file_pdf' => 'PDF belum dapat diproses. ' . $exception->getMessage(),
            ]);
        }

        return redirect()
            ->route('ujian-omr.scan.show', [$ujianOmr, $batchScan])
            ->with('berhasil', 'PDF selesai diproses. Periksa hasil scan sebelum nilai diterapkan.');
    }

    public function show(UjianOmr $ujianOmr, BatchScanUjianOmr $batchScan)
    {
        $this->pastikanBatchMilikUjian($ujianOmr, $batchScan);
        $batchScan->load([
            'dibuatOleh',
            'hasilScan.lembarJawabUjianOmr.anggotaKelas.siswa',
            'hasilScan.lembarJawabUjianOmr.anggotaKelas.kelas',
            'hasilScan.lembarJawabUjianOmr.kelasUjianOmr.komponenNilai',
            'hasilScan.lembarJawabUjianOmr.versiSoalUjianOmr',
            'hasilScan.diterapkanOleh',
            'hasilScan.dikoreksiOleh',
        ]);
        $lembarSudahDiterapkan = HasilScanLjkUjianOmr::query()
            ->whereIn(
                'lembar_jawab_ujian_omr_id',
                $batchScan->hasilScan->pluck('lembar_jawab_ujian_omr_id')->filter()->unique(),
            )
            ->whereNotNull('diterapkan_pada')
            ->pluck('lembar_jawab_ujian_omr_id')
            ->map(fn ($id) => (int) $id);
        $jumlahDiterapkan = $batchScan->hasilScan->whereNotNull('diterapkan_pada')->count();
        $jumlahDapatDiterapkan = $batchScan->hasilScan
            ->where('status', 'terbaca')
            ->whereNull('diterapkan_pada')
            ->filter(function (HasilScanLjkUjianOmr $hasil) use ($lembarSudahDiterapkan) {
                $komponenNilai = $hasil->lembarJawabUjianOmr?->kelasUjianOmr?->komponenNilai;

                return $hasil->lembar_jawab_ujian_omr_id
                    && $hasil->nilai !== null
                    && ! $lembarSudahDiterapkan->contains((int) $hasil->lembar_jawab_ujian_omr_id)
                    && $komponenNilai?->aktif
                    && in_array($komponenNilai->jenis_komponen, ['sts', 'sas_saj'], true);
            })
            ->unique('lembar_jawab_ujian_omr_id')
            ->count();

        return view('ujian-omr.scan.show', compact(
            'ujianOmr',
            'batchScan',
            'jumlahDiterapkan',
            'jumlahDapatDiterapkan',
        ));
    }

    public function pratinjau(UjianOmr $ujianOmr, BatchScanUjianOmr $batchScan, HasilScanLjkUjianOmr $hasilScan)
    {
        $this->pastikanBatchMilikUjian($ujianOmr, $batchScan);
        abort_unless((int) $hasilScan->batch_scan_ujian_omr_id === (int) $batchScan->id, 404);
        abort_unless($hasilScan->lokasi_pratinjau && Storage::disk('local')->exists($hasilScan->lokasi_pratinjau), 404);

        return Storage::disk('local')->response($hasilScan->lokasi_pratinjau);
    }

    private function simpanHasil(BatchScanUjianOmr $batchScan, UjianOmr $ujianOmr, array $hasilMesin, string $direktoriRelatif): void
    {
        DB::transaction(function () use ($batchScan, $ujianOmr, $hasilMesin, $direktoriRelatif) {
            $jumlahBerhasil = 0;
            $jumlahPerluDiperiksa = 0;

            foreach ($hasilMesin['sheets'] as $hasilLembar) {
                $token = filled($hasilLembar['token'] ?? null) ? (string) $hasilLembar['token'] : null;
                $lembarJawab = $token
                    ? LembarJawabUjianOmr::query()
                        ->where('ujian_omr_id', $ujianOmr->id)
                        ->where('token', $token)
                        ->with('versiSoalUjianOmr.kunciJawaban')
                        ->first()
                    : null;
                $kunciJawaban = $lembarJawab?->versiSoalUjianOmr?->kunciJawaban
                    ?->pluck('jawaban', 'nomor_soal') ?? collect();
                $jawabanMesin = collect($hasilLembar['answers'] ?? []);
                $jumlahKosong = $jawabanMesin->where('status', 'kosong')->count();
                $jumlahGanda = $jawabanMesin->where('status', 'ganda')->count();
                $jumlahBenar = $lembarJawab
                    ? $jawabanMesin->filter(fn ($jawaban) => ($jawaban['status'] ?? null) === 'terbaca'
                        && ($jawaban['answer'] ?? null) === $kunciJawaban->get($jawaban['number'] ?? 0))->count()
                    : 0;
                $jumlahSalah = $lembarJawab
                    ? $jawabanMesin->where('status', 'terbaca')->count() - $jumlahBenar
                    : 0;
                $status = $lembarJawab && ($hasilLembar['status'] ?? null) === 'terbaca'
                    ? 'terbaca'
                    : ($lembarJawab ? 'perlu_diperiksa' : 'token_tidak_dikenali');
                $catatan = collect($hasilLembar['warnings'] ?? [])
                    ->when(! $lembarJawab, fn ($catatan) => $catatan->push('Token QR tidak ditemukan pada daftar LJK ujian ini.'))
                    ->unique()
                    ->join(' ');
                $hasilScan = HasilScanLjkUjianOmr::create([
                    'batch_scan_ujian_omr_id' => $batchScan->id,
                    'lembar_jawab_ujian_omr_id' => $lembarJawab?->id,
                    'halaman_pdf' => (int) ($hasilLembar['page'] ?? 0),
                    'urutan_ljk' => (int) ($hasilLembar['slot'] ?? 1),
                    'token_terbaca' => $token,
                    'lokasi_pratinjau' => $direktoriRelatif . '/' . ($hasilLembar['preview'] ?? ''),
                    'status' => $status,
                    'jumlah_benar' => $jumlahBenar,
                    'jumlah_salah' => $jumlahSalah,
                    'jumlah_kosong' => $jumlahKosong,
                    'jumlah_ganda' => $jumlahGanda,
                    'nilai' => $lembarJawab ? round($jumlahBenar / max(1, $ujianOmr->jumlah_soal) * 100, 2) : null,
                    'catatan' => $catatan ?: null,
                ]);

                foreach ($jawabanMesin as $jawaban) {
                    $jawabanTerbaca = $jawaban['answer'] ?? null;
                    $hasilScan->jawaban()->create([
                        'nomor_soal' => (int) ($jawaban['number'] ?? 0),
                        'jawaban' => $jawabanTerbaca,
                        'status' => $jawaban['status'] ?? 'kosong',
                        'tingkat_kehitaman' => $jawaban['darkness'] ?? null,
                        'benar' => $lembarJawab && ($jawaban['status'] ?? null) === 'terbaca'
                            ? $jawabanTerbaca === $kunciJawaban->get($jawaban['number'] ?? 0)
                            : null,
                    ]);
                }

                if ($lembarJawab) {
                    $lembarJawab->update(['status' => $status === 'terbaca' ? 'sudah_dipindai' : 'perlu_diperiksa']);
                }

                $status === 'terbaca' ? $jumlahBerhasil++ : $jumlahPerluDiperiksa++;
            }

            $batchScan->update([
                'jumlah_halaman_pdf' => (int) $hasilMesin['pages'],
                'jumlah_ljk_terdeteksi' => count($hasilMesin['sheets']),
                'jumlah_berhasil' => $jumlahBerhasil,
                'jumlah_perlu_diperiksa' => $jumlahPerluDiperiksa,
                'status' => 'selesai',
            ]);
        });
    }

    private function pastikanBatchMilikUjian(UjianOmr $ujianOmr, BatchScanUjianOmr $batchScan): void
    {
        abort_unless((int) $batchScan->ujian_omr_id === (int) $ujianOmr->id, 404);
    }
}
