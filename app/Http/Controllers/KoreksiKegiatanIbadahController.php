<?php

namespace App\Http\Controllers;

use App\Models\AnggotaKelas;
use App\Models\JadwalKegiatanIbadah;
use App\Models\KegiatanIbadah;
use App\Models\PresensiKegiatanIbadah;
use App\Models\RiwayatKoreksiKegiatanIbadah;
use App\Models\TahunPelajaran;
use App\Services\Ibadah\AksesScanKegiatanIbadah;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class KoreksiKegiatanIbadahController extends Controller
{
    public function edit(Request $request, AnggotaKelas $anggotaKelas, AksesScanKegiatanIbadah $akses)
    {
        $data = $request->validate([
            'tanggal' => ['required', 'date', 'before_or_equal:today'],
            'kegiatan_ibadah_id' => ['required', 'integer', 'exists:kegiatan_ibadah,id'],
        ]);
        $tanggal = Carbon::parse($data['tanggal'])->startOfDay();
        [$tahunPelajaran, $kegiatan, $jadwal] = $this->pastikanDataDapatDikoreksi(
            $request,
            $anggotaKelas,
            (int) $data['kegiatan_ibadah_id'],
            $tanggal,
            $akses,
        );
        $anggotaKelas->load(['kelas:id,nama', 'siswa:id,nama_lengkap,nis,nisn,foto', 'tahunPelajaran:id,nama']);
        $presensi = $this->ambilPresensi($anggotaKelas, $kegiatan, $tanggal);
        $riwayat = RiwayatKoreksiKegiatanIbadah::query()
            ->with('diubahOleh:id,nama')
            ->where('kegiatan_ibadah_id', $kegiatan->id)
            ->where('siswa_id', $anggotaKelas->siswa_id)
            ->whereDate('tanggal', $tanggal->toDateString())
            ->latest('id')
            ->limit(5)
            ->get();

        return view('rekap-kegiatan-ibadah.koreksi', [
            'anggotaKelas' => $anggotaKelas,
            'tahunPelajaran' => $tahunPelajaran,
            'kegiatan' => $kegiatan,
            'jadwal' => $jadwal,
            'tanggal' => $tanggal->toDateString(),
            'tanggalLabel' => $tanggal->locale('id')->translatedFormat('l, d F Y'),
            'presensi' => $presensi,
            'riwayat' => $riwayat,
        ]);
    }

    public function update(Request $request, AnggotaKelas $anggotaKelas, AksesScanKegiatanIbadah $akses)
    {
        $data = $request->validate([
            'tanggal' => ['required', 'date', 'before_or_equal:today'],
            'kegiatan_ibadah_id' => ['required', 'integer', 'exists:kegiatan_ibadah,id'],
            'status_presensi' => ['required', Rule::in(['sudah', 'belum'])],
            'waktu_presensi' => ['nullable', 'required_if:status_presensi,sudah', 'date_format:H:i'],
            'alasan' => ['required', 'string', 'min:5', 'max:1000'],
        ]);
        $tanggal = Carbon::parse($data['tanggal'])->startOfDay();
        [$tahunPelajaran, $kegiatan, $jadwal] = $this->pastikanDataDapatDikoreksi(
            $request,
            $anggotaKelas,
            (int) $data['kegiatan_ibadah_id'],
            $tanggal,
            $akses,
        );

        DB::transaction(function () use ($request, $data, $tanggal, $tahunPelajaran, $kegiatan, $jadwal, $anggotaKelas) {
            AnggotaKelas::query()->whereKey($anggotaKelas->id)->lockForUpdate()->firstOrFail();
            $presensi = PresensiKegiatanIbadah::query()
                ->where('kegiatan_ibadah_id', $kegiatan->id)
                ->where('siswa_id', $anggotaKelas->siswa_id)
                ->whereDate('tanggal', $tanggal->toDateString())
                ->lockForUpdate()
                ->first();
            $hadirSebelum = (bool) $presensi;
            $waktuSebelum = $presensi?->waktu_scan;
            $sumberSebelum = $presensi?->sumber;

            if ($data['status_presensi'] === 'belum') {
                if (! $presensi) {
                    throw ValidationException::withMessages([
                        'status_presensi' => 'Siswa ini memang belum memiliki catatan presensi.',
                    ]);
                }

                $riwayat = $this->buatRiwayatDasar($request, $anggotaKelas, $kegiatan, $tahunPelajaran, $tanggal, $data['alasan']);
                $riwayat->fill([
                    'presensi_kegiatan_ibadah_id' => $presensi->id,
                    'tindakan' => 'hapus',
                    'hadir_sebelum' => true,
                    'hadir_sesudah' => false,
                    'waktu_sebelum' => $waktuSebelum,
                    'waktu_sesudah' => null,
                    'sumber_sebelum' => $sumberSebelum,
                    'sumber_sesudah' => null,
                ])->save();
                $presensi->delete();

                return;
            }

            if (! $presensi && ! $jadwal) {
                throw ValidationException::withMessages([
                    'status_presensi' => 'Input manual tidak dapat dibuat karena kegiatan ini tidak memiliki jadwal pada tanggal tersebut.',
                ]);
            }

            $waktuSesudah = $data['waktu_presensi'].':00';

            if (! $presensi) {
                $presensi = new PresensiKegiatanIbadah([
                    'jadwal_kegiatan_ibadah_id' => $jadwal->id,
                    'kegiatan_ibadah_id' => $kegiatan->id,
                    'tahun_pelajaran_id' => $tahunPelajaran->id,
                    'kelas_id' => $anggotaKelas->kelas_id,
                    'anggota_kelas_id' => $anggotaKelas->id,
                    'siswa_id' => $anggotaKelas->siswa_id,
                    'dipindai_oleh_pengguna_id' => $request->user()?->id,
                    'tanggal' => $tanggal->toDateString(),
                    'sumber' => 'manual',
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);
            }

            $presensi->fill([
                'waktu_scan' => $waktuSesudah,
                'dikoreksi_oleh_pengguna_id' => $request->user()?->id,
                'dikoreksi_pada' => now(),
                'catatan_koreksi' => $data['alasan'],
            ])->save();

            $this->buatRiwayatDasar($request, $anggotaKelas, $kegiatan, $tahunPelajaran, $tanggal, $data['alasan'])
                ->fill([
                    'presensi_kegiatan_ibadah_id' => $presensi->id,
                    'tindakan' => $hadirSebelum ? 'ubah' : 'tambah',
                    'hadir_sebelum' => $hadirSebelum,
                    'hadir_sesudah' => true,
                    'waktu_sebelum' => $waktuSebelum,
                    'waktu_sesudah' => $waktuSesudah,
                    'sumber_sebelum' => $sumberSebelum,
                    'sumber_sesudah' => $presensi->sumber,
                ])->save();
        });

        $pesan = $data['status_presensi'] === 'sudah'
            ? 'Presensi manual/koreksi berhasil disimpan.'
            : 'Catatan presensi berhasil dibatalkan dan riwayat perubahan tetap tersimpan.';

        return redirect()
            ->route('rekap-kegiatan-ibadah.index', [
                'tanggal' => $tanggal->toDateString(),
                'kegiatan_ibadah_id' => $kegiatan->id,
                'kelas_id' => $anggotaKelas->kelas_id,
            ])
            ->with('berhasil', $pesan);
    }

    private function pastikanDataDapatDikoreksi(
        Request $request,
        AnggotaKelas $anggotaKelas,
        int $kegiatanId,
        Carbon $tanggal,
        AksesScanKegiatanIbadah $akses,
    ): array {
        $tahunPelajaran = TahunPelajaran::query()->where('aktif', true)->orderByDesc('tanggal_mulai')->firstOrFail();
        abort_unless((int) $anggotaKelas->tahun_pelajaran_id === (int) $tahunPelajaran->id, 404);
        abort_unless($anggotaKelas->status_keanggotaan === 'aktif', 404);
        abort_unless($akses->dapatMengoreksi($request->user(), $tahunPelajaran, $tanggal), 403);

        $kegiatan = KegiatanIbadah::query()->findOrFail($kegiatanId);
        $hari = array_keys(JadwalKegiatanIbadah::DAFTAR_HARI)[$tanggal->dayOfWeekIso - 1] ?? 'minggu';
        $jadwal = $hari === 'minggu' ? null : JadwalKegiatanIbadah::query()
            ->where('tahun_pelajaran_id', $tahunPelajaran->id)
            ->where('kegiatan_ibadah_id', $kegiatan->id)
            ->where('hari', $hari)
            ->first();

        return [$tahunPelajaran, $kegiatan, $jadwal];
    }

    private function ambilPresensi(AnggotaKelas $anggotaKelas, KegiatanIbadah $kegiatan, Carbon $tanggal): ?PresensiKegiatanIbadah
    {
        return PresensiKegiatanIbadah::query()
            ->with(['dipindaiOleh:id,nama', 'dikoreksiOleh:id,nama'])
            ->where('kegiatan_ibadah_id', $kegiatan->id)
            ->where('siswa_id', $anggotaKelas->siswa_id)
            ->whereDate('tanggal', $tanggal->toDateString())
            ->first();
    }

    private function buatRiwayatDasar(
        Request $request,
        AnggotaKelas $anggotaKelas,
        KegiatanIbadah $kegiatan,
        TahunPelajaran $tahunPelajaran,
        Carbon $tanggal,
        string $alasan,
    ): RiwayatKoreksiKegiatanIbadah {
        return new RiwayatKoreksiKegiatanIbadah([
            'kegiatan_ibadah_id' => $kegiatan->id,
            'tahun_pelajaran_id' => $tahunPelajaran->id,
            'kelas_id' => $anggotaKelas->kelas_id,
            'anggota_kelas_id' => $anggotaKelas->id,
            'siswa_id' => $anggotaKelas->siswa_id,
            'diubah_oleh_pengguna_id' => $request->user()?->id,
            'tanggal' => $tanggal->toDateString(),
            'alasan' => $alasan,
        ]);
    }
}
