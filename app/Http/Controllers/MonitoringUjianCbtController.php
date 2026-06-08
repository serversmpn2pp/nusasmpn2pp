<?php

namespace App\Http\Controllers;

use App\Models\PesertaUjianCbt;
use App\Models\SesiUjianCbt;
use App\Models\UjianCbt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class MonitoringUjianCbtController extends Controller
{
    public function index(Request $request, UjianCbt $ujianCbt)
    {
        $data = $request->validate([
            'kelas_id' => ['nullable', 'integer', 'exists:kelas,id'],
            'sesi_ujian_cbt_id' => ['nullable', 'integer', 'exists:sesi_ujian_cbt,id'],
            'status_monitor' => ['nullable', Rule::in([
                'semua',
                'belum_login',
                'sudah_login',
                'sedang_mengerjakan',
                'selesai',
                'nonaktif',
                'terblokir',
            ])],
            'auto_refresh' => ['nullable', 'boolean'],
        ]);

        $kelasId = $data['kelas_id'] ?? null;
        $sesiUjianCbtId = $data['sesi_ujian_cbt_id'] ?? null;
        $statusMonitor = $data['status_monitor'] ?? 'semua';
        $autoRefresh = filter_var($data['auto_refresh'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $ujianCbt->load([
            'jenisUjianCbt',
            'tahunPelajaran',
            'mataPelajaran',
            'kelasUjianCbt.kelas',
            'sesiUjianCbt',
        ]);
        $jumlahSoalPaket = $ujianCbt->soalUjianCbt()->count();
        $jumlahSoalTampil = min((int) $ujianCbt->jumlah_soal, $jumlahSoalPaket);

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

        $ringkasan = $this->ringkasanMonitoring($ujianCbt);

        $pesertaUjianCbt = $ujianCbt->pesertaUjianCbt()
            ->with([
                'sesiUjianCbt',
                'kelasUjianCbt.kelas',
                'anggotaKelas.siswa',
                'akunPesertaCbt',
            ])
            ->withCount([
                'jawabanPesertaUjianCbt as jumlah_jawaban_tersimpan' => fn ($query) => $query->whereNotNull('jawaban'),
                'jawabanPesertaUjianCbt as jumlah_jawaban_ragu' => fn ($query) => $query->where('ragu', true),
                'jawabanPesertaUjianCbt as jumlah_jawaban_dikoreksi' => fn ($query) => $query->whereNotNull('skor'),
                'jawabanPesertaUjianCbt as jumlah_jawaban_benar' => fn ($query) => $query->where('benar', true),
            ])
            ->when($kelasId, fn ($query) => $query->whereHas(
                'kelasUjianCbt',
                fn ($query) => $query->where('kelas_id', $kelasId),
            ))
            ->when($sesiUjianCbtId, fn ($query) => $query->where('sesi_ujian_cbt_id', $sesiUjianCbtId))
            ->when($statusMonitor !== 'semua', fn ($query) => $this->terapkanFilterStatusMonitor($query, $statusMonitor))
            ->get()
            ->sortBy(fn (PesertaUjianCbt $item) => sprintf(
                '%s|%s|%05d|%s',
                $item->sesiUjianCbt?->kode ?? '',
                $item->kelasUjianCbt?->kelas?->nama ?? '',
                $item->anggotaKelas?->nomor_absen ?? 999,
                $item->anggotaKelas?->siswa?->nama_lengkap ?? '',
            ))
            ->values();

        return view('ujian-cbt.monitoring.index', [
            'ujianCbt' => $ujianCbt,
            'kelasPeserta' => $kelasPeserta,
            'sesiUjianCbt' => $sesiUjianCbt,
            'pesertaUjianCbt' => $pesertaUjianCbt,
            'ringkasan' => $ringkasan,
            'kelasId' => $kelasId,
            'sesiUjianCbtId' => $sesiUjianCbtId,
            'statusMonitor' => $statusMonitor,
            'autoRefresh' => $autoRefresh,
            'jumlahSoalPaket' => $jumlahSoalPaket,
            'jumlahSoalTampil' => $jumlahSoalTampil,
            'waktuSekarang' => now(),
        ]);
    }

    private function ringkasanMonitoring(UjianCbt $ujianCbt): array
    {
        $baris = $ujianCbt->pesertaUjianCbt()
            ->selectRaw("
                count(*) as total,
                sum(case when status = 'aktif' and waktu_mulai is null and ip_terakhir is null then 1 else 0 end) as belum_login,
                sum(case when status = 'aktif' and waktu_mulai is null and ip_terakhir is not null then 1 else 0 end) as sudah_login,
                sum(case when status = 'sedang_mengerjakan' then 1 else 0 end) as sedang_mengerjakan,
                sum(case when status = 'selesai' then 1 else 0 end) as selesai,
                sum(case when status = 'nonaktif' then 1 else 0 end) as nonaktif,
                sum(case when status = 'terblokir' then 1 else 0 end) as terblokir
            ")
            ->first();

        $jawabanTerisi = $ujianCbt->pesertaUjianCbt()
            ->join('jawaban_peserta_ujian_cbt', 'peserta_ujian_cbt.id', '=', 'jawaban_peserta_ujian_cbt.peserta_ujian_cbt_id')
            ->whereNotNull('jawaban_peserta_ujian_cbt.jawaban')
            ->count();
        $jawabanRagu = $ujianCbt->pesertaUjianCbt()
            ->join('jawaban_peserta_ujian_cbt', 'peserta_ujian_cbt.id', '=', 'jawaban_peserta_ujian_cbt.peserta_ujian_cbt_id')
            ->where('jawaban_peserta_ujian_cbt.ragu', true)
            ->count();

        return [
            'total' => (int) ($baris->total ?? 0),
            'belum_login' => (int) ($baris->belum_login ?? 0),
            'sudah_login' => (int) ($baris->sudah_login ?? 0),
            'sedang_mengerjakan' => (int) ($baris->sedang_mengerjakan ?? 0),
            'selesai' => (int) ($baris->selesai ?? 0),
            'nonaktif' => (int) ($baris->nonaktif ?? 0),
            'terblokir' => (int) ($baris->terblokir ?? 0),
            'jawaban_terisi' => $jawabanTerisi,
            'jawaban_ragu' => $jawabanRagu,
        ];
    }

    private function terapkanFilterStatusMonitor($query, string $statusMonitor)
    {
        return match ($statusMonitor) {
            'belum_login' => $query->where('status', 'aktif')
                ->whereNull('waktu_mulai')
                ->whereNull('ip_terakhir'),
            'sudah_login' => $query->where('status', 'aktif')
                ->whereNull('waktu_mulai')
                ->whereNotNull('ip_terakhir'),
            'sedang_mengerjakan', 'selesai', 'nonaktif', 'terblokir' => $query->where('status', $statusMonitor),
            default => $query,
        };
    }
}
