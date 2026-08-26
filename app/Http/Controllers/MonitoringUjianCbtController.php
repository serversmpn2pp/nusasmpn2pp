<?php

namespace App\Http\Controllers;

use App\Models\PesertaUjianCbt;
use App\Models\SesiUjianCbt;
use App\Models\UjianCbt;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MonitoringUjianCbtController extends Controller
{
    public function index(Request $request, UjianCbt $ujianCbt)
    {
        $data = $request->validate([
            'kelas_id' => ['nullable', 'integer', 'exists:kelas,id'],
            'sesi_ujian_cbt_id' => ['nullable', 'integer', 'exists:sesi_ujian_cbt,id'],
            'ruang_ujian_cbt_id' => [
                'nullable',
                'integer',
                Rule::exists('ruang_ujian_cbt', 'id')->where(
                    fn ($query) => $query->where('ujian_cbt_id', $ujianCbt->id),
                ),
            ],
            'status_monitor' => ['nullable', Rule::in([
                'semua',
                'belum_hadir',
                'hadir_belum_mulai',
                'tidak_hadir',
                'sedang_mengerjakan',
                'selesai',
                'nonaktif',
                'terblokir',
            ])],
            'auto_refresh' => ['nullable', 'boolean'],
        ]);

        $kelasId = $data['kelas_id'] ?? null;
        $sesiUjianCbtId = $data['sesi_ujian_cbt_id'] ?? null;
        $ruangUjianCbtId = $data['ruang_ujian_cbt_id'] ?? null;
        $statusMonitor = $data['status_monitor'] ?? 'semua';
        $autoRefresh = filter_var($data['auto_refresh'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $ujianCbt->load([
            'jenisUjianCbt',
            'tahunPelajaran',
            'mataPelajaran',
            'kelasUjianCbt.kelas',
            'sesiUjianCbt',
            'ruangUjianCbt',
            'jadwalUjianCbt.kegiatanUjianCbt',
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

        $ruangUjianCbt = $ujianCbt->ruangUjianCbt
            ->sortBy(fn ($ruang) => sprintf('%s|%s', $ruang->kode, $ruang->nama))
            ->values();

        $ringkasan = $this->ringkasanMonitoring($ujianCbt);

        $pesertaUjianCbt = $ujianCbt->pesertaUjianCbt()
            ->with([
                'sesiUjianCbt',
                'kelasUjianCbt.kelas',
                'ruangUjianCbt',
                'anggotaKelas.siswa',
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
            ->when($ruangUjianCbtId, fn ($query) => $query->where('ruang_ujian_cbt_id', $ruangUjianCbtId))
            ->when($statusMonitor !== 'semua', fn ($query) => $this->terapkanFilterStatusMonitor($query, $statusMonitor))
            ->get()
            ->sortBy(fn (PesertaUjianCbt $item) => sprintf(
                '%s|%05d|%s|%05d|%s',
                $item->ruangUjianCbt?->kode ?? 'ZZZ',
                $item->nomor_meja ?? 99999,
                $item->kelasUjianCbt?->kelas?->nama ?? '',
                $item->anggotaKelas?->nomor_absen ?? 999,
                $item->anggotaKelas?->siswa?->nama_lengkap ?? '',
            ))
            ->values();

        return view('ujian-cbt.monitoring.index', [
            'ujianCbt' => $ujianCbt,
            'kelasPeserta' => $kelasPeserta,
            'sesiUjianCbt' => $sesiUjianCbt,
            'ruangUjianCbt' => $ruangUjianCbt,
            'pesertaUjianCbt' => $pesertaUjianCbt,
            'ringkasan' => $ringkasan,
            'kelasId' => $kelasId,
            'sesiUjianCbtId' => $sesiUjianCbtId,
            'ruangUjianCbtId' => $ruangUjianCbtId,
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
                sum(case when status = 'aktif' and (status_kehadiran_ujian is null or status_kehadiran_ujian = 'belum_absen') then 1 else 0 end) as belum_hadir,
                sum(case when status = 'aktif' and status_kehadiran_ujian in ('hadir', 'terlambat') then 1 else 0 end) as hadir_belum_mulai,
                sum(case when status = 'aktif' and status_kehadiran_ujian in ('sakit', 'izin', 'alfa') then 1 else 0 end) as tidak_hadir,
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
            'belum_hadir' => (int) ($baris->belum_hadir ?? 0),
            'hadir_belum_mulai' => (int) ($baris->hadir_belum_mulai ?? 0),
            'tidak_hadir' => (int) ($baris->tidak_hadir ?? 0),
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
            'belum_hadir' => $query->where('status', 'aktif')
                ->where(fn ($query) => $query
                    ->whereNull('status_kehadiran_ujian')
                    ->orWhere('status_kehadiran_ujian', 'belum_absen')),
            'hadir_belum_mulai' => $query->where('status', 'aktif')
                ->whereIn('status_kehadiran_ujian', ['hadir', 'terlambat']),
            'tidak_hadir' => $query->where('status', 'aktif')
                ->whereIn('status_kehadiran_ujian', ['sakit', 'izin', 'alfa']),
            'sedang_mengerjakan', 'selesai', 'nonaktif', 'terblokir' => $query->where('status', $statusMonitor),
            default => $query,
        };
    }
}
