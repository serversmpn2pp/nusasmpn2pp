<?php

namespace App\Http\Controllers;

use App\Models\PesertaUjianCbt;
use App\Models\RuangUjianCbt;
use App\Models\Siswa;
use App\Models\UjianCbt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class PresensiUjianCbtController extends Controller
{
    public function index(Request $request)
    {
        $pengguna = $request->user();
        $dapatKelolaSemua = $this->dapatKelolaSemua($pengguna);

        abort_unless($dapatKelolaSemua || filled($pengguna?->pegawai_id), 403);

        $daftarRuang = RuangUjianCbt::query()
            ->with([
                'ujianCbt.jenisUjianCbt',
                'ujianCbt.mataPelajaran',
                'jadwalUjianCbt.kegiatanUjianCbt',
                'jadwalUjianCbt.mataPelajaran',
                'sesiUjianCbt',
                'pengawasUtama',
                'pengawasPendamping',
            ])
            ->withCount([
                'pesertaUjianCbt as jumlah_peserta',
                'pesertaUjianCbt as jumlah_hadir' => fn ($query) => $query->whereIn('status_kehadiran_ujian', ['hadir', 'terlambat']),
                'pesertaUjianCbt as jumlah_belum_absen' => fn ($query) => $query->where('status_kehadiran_ujian', 'belum_absen'),
                'pesertaUjianCbt as jumlah_tidak_hadir' => fn ($query) => $query->whereIn('status_kehadiran_ujian', ['sakit', 'izin', 'alfa']),
            ])
            ->when(! $dapatKelolaSemua, fn ($query) => $query->where(function ($query) use ($pengguna) {
                $query->where('pengawas_utama_pegawai_id', $pengguna->pegawai_id)
                    ->orWhere('pengawas_pendamping_pegawai_id', $pengguna->pegawai_id);
            }))
            ->get()
            ->sortBy(fn (RuangUjianCbt $ruang) => sprintf(
                '%s|%s|%s',
                $ruang->jadwalUjianCbt?->tanggal?->format('Ymd') ?? '99999999',
                substr((string) $ruang->jadwalUjianCbt?->waktu_mulai, 0, 5),
                $ruang->kode,
            ))
            ->values();

        $hariIni = now()->toDateString();

        return view('presensi-ujian-cbt.index', [
            'ruangHariIni' => $daftarRuang
                ->filter(fn (RuangUjianCbt $ruang) => $ruang->jadwalUjianCbt?->tanggal?->toDateString() === $hariIni)
                ->values(),
            'ruangLain' => $daftarRuang
                ->reject(fn (RuangUjianCbt $ruang) => $ruang->jadwalUjianCbt?->tanggal?->toDateString() === $hariIni)
                ->values(),
            'dapatKelolaSemua' => $dapatKelolaSemua,
        ]);
    }

    public function show(Request $request, UjianCbt $ujianCbt, RuangUjianCbt $ruangUjianCbt)
    {
        $this->pastikanRuangMilikUjian($ujianCbt, $ruangUjianCbt);
        $this->pastikanDapatMengelolaRuang($request, $ruangUjianCbt);

        $ruangUjianCbt->load([
            'ujianCbt.jenisUjianCbt',
            'ujianCbt.mataPelajaran',
            'jadwalUjianCbt.kegiatanUjianCbt',
            'jadwalUjianCbt.mataPelajaran',
            'sesiUjianCbt',
            'pengawasUtama',
            'pengawasPendamping',
        ]);

        $peserta = $this->daftarPeserta($ruangUjianCbt);
        $presensiTerbaru = $peserta
            ->whereIn('status_kehadiran_ujian', ['hadir', 'terlambat'])
            ->sortByDesc(fn (PesertaUjianCbt $item) => $item->absen_ujian_pada?->getTimestamp() ?? 0)
            ->take(8)
            ->map(fn (PesertaUjianCbt $item) => $this->dataPeserta($item))
            ->values();

        return view('presensi-ujian-cbt.show', [
            'ujianCbt' => $ujianCbt,
            'ruangUjianCbt' => $ruangUjianCbt,
            'peserta' => $peserta,
            'presensiTerbaru' => $presensiTerbaru,
            'ringkasan' => $this->ringkasanRuang($ruangUjianCbt),
            'waktuServerIso' => now()->toIso8601String(),
            'daftarStatusKehadiran' => PesertaUjianCbt::DAFTAR_STATUS_KEHADIRAN,
        ]);
    }

    public function scan(Request $request, UjianCbt $ujianCbt, RuangUjianCbt $ruangUjianCbt): JsonResponse
    {
        $this->pastikanRuangMilikUjian($ujianCbt, $ruangUjianCbt);
        $this->pastikanDapatMengelolaRuang($request, $ruangUjianCbt);

        $data = $request->validate([
            'isi_scan' => ['required', 'string', 'max:120'],
        ]);
        $nisn = $this->nisnDariIsiScan($data['isi_scan']);

        if (! $nisn) {
            return $this->responsGagal('QR tidak berisi NISN yang dapat dikenali.');
        }

        $siswa = Siswa::query()->where('nisn', $nisn)->first();

        if (! $siswa) {
            return $this->responsGagal('NISN pada kartu tidak ditemukan di data siswa NUSA.');
        }

        $peserta = DB::transaction(function () use ($request, $ujianCbt, $ruangUjianCbt, $siswa) {
            $peserta = PesertaUjianCbt::query()
                ->where('ujian_cbt_id', $ujianCbt->id)
                ->where('ruang_ujian_cbt_id', $ruangUjianCbt->id)
                ->whereHas('anggotaKelas', fn ($query) => $query->where('siswa_id', $siswa->id))
                ->lockForUpdate()
                ->first();

            if (! $peserta) {
                return null;
            }

            if (! in_array($peserta->status_kehadiran_ujian, ['hadir', 'terlambat'], true)) {
                $peserta->update([
                    'status_kehadiran_ujian' => 'hadir',
                    'absen_ujian_pada' => now(),
                    'absen_ujian_oleh_pengguna_id' => $request->user()?->id,
                ]);
                $peserta->setAttribute('presensi_baru', true);
            } else {
                $peserta->setAttribute('presensi_baru', false);
            }

            return $peserta;
        });

        if (! $peserta) {
            $pesertaLain = PesertaUjianCbt::query()
                ->with(['ruangUjianCbt', 'kelasUjianCbt.kelas', 'anggotaKelas.siswa'])
                ->where('ujian_cbt_id', $ujianCbt->id)
                ->whereHas('anggotaKelas', fn ($query) => $query->where('siswa_id', $siswa->id))
                ->first();

            if ($pesertaLain?->ruangUjianCbt) {
                return response()->json([
                    'berhasil' => false,
                    'status' => 'salah_ruang',
                    'pesan' => 'Siswa terdaftar di '.$pesertaLain->ruangUjianCbt->kode.' - '.$pesertaLain->ruangUjianCbt->nama.', bukan di ruang ini.',
                    'siswa' => $this->dataSiswa($siswa, $pesertaLain),
                    'ruang_seharusnya' => $pesertaLain->ruangUjianCbt->nama,
                    'waktu_server' => now()->format('H:i:s'),
                ], 422);
            }

            return response()->json([
                'berhasil' => false,
                'status' => 'bukan_peserta',
                'pesan' => 'Siswa ini tidak terdaftar sebagai peserta paket ujian tersebut.',
                'siswa' => $this->dataSiswa($siswa),
                'waktu_server' => now()->format('H:i:s'),
            ], 422);
        }

        $peserta->load(['ruangUjianCbt', 'kelasUjianCbt.kelas', 'anggotaKelas.siswa']);
        $baru = (bool) $peserta->getAttribute('presensi_baru');

        return response()->json([
            'berhasil' => true,
            'baru' => $baru,
            'status' => $baru ? 'hadir' : 'sudah_hadir',
            'pesan' => $baru
                ? 'Presensi ujian berhasil dicatat. Silakan menuju meja nomor '.($peserta->nomor_meja ?: '-').'.'
                : 'Siswa sudah tercatat hadir di ruang ini.',
            'peserta' => $this->dataPeserta($peserta),
            'siswa' => $this->dataSiswa($siswa, $peserta),
            'ringkasan' => $this->ringkasanRuang($ruangUjianCbt),
            'waktu_server' => now()->format('H:i:s'),
        ]);
    }

    public function updateManual(
        Request $request,
        UjianCbt $ujianCbt,
        RuangUjianCbt $ruangUjianCbt,
        PesertaUjianCbt $pesertaUjianCbt
    ): JsonResponse {
        $this->pastikanRuangMilikUjian($ujianCbt, $ruangUjianCbt);
        $this->pastikanDapatMengelolaRuang($request, $ruangUjianCbt);
        abort_unless(
            (int) $pesertaUjianCbt->ujian_cbt_id === (int) $ujianCbt->id
            && (int) $pesertaUjianCbt->ruang_ujian_cbt_id === (int) $ruangUjianCbt->id,
            404,
        );

        $data = $request->validate([
            'status_kehadiran_ujian' => ['required', Rule::in(array_keys(PesertaUjianCbt::DAFTAR_STATUS_KEHADIRAN))],
            'catatan_kehadiran_ujian' => ['nullable', 'string', 'max:1000'],
        ]);

        $peserta = DB::transaction(function () use ($request, $data, $pesertaUjianCbt) {
            $peserta = PesertaUjianCbt::query()->lockForUpdate()->findOrFail($pesertaUjianCbt->id);
            $statusBerubah = $peserta->status_kehadiran_ujian !== $data['status_kehadiran_ujian'];
            $perubahan = [
                'status_kehadiran_ujian' => $data['status_kehadiran_ujian'],
                'catatan_kehadiran_ujian' => filled($data['catatan_kehadiran_ujian'] ?? null)
                    ? trim($data['catatan_kehadiran_ujian'])
                    : null,
            ];

            if ($data['status_kehadiran_ujian'] === 'belum_absen') {
                $perubahan['absen_ujian_pada'] = null;
                $perubahan['absen_ujian_oleh_pengguna_id'] = null;
            } elseif ($statusBerubah || ! $peserta->absen_ujian_pada) {
                $perubahan['absen_ujian_pada'] = now();
                $perubahan['absen_ujian_oleh_pengguna_id'] = $request->user()?->id;
            }

            $peserta->update($perubahan);

            return $peserta;
        });

        $peserta->load(['ruangUjianCbt', 'kelasUjianCbt.kelas', 'anggotaKelas.siswa']);

        return response()->json([
            'berhasil' => true,
            'pesan' => 'Presensi '.$peserta->anggotaKelas?->siswa?->nama_lengkap.' berhasil diperbarui.',
            'peserta' => $this->dataPeserta($peserta),
            'ringkasan' => $this->ringkasanRuang($ruangUjianCbt),
            'waktu_server' => now()->format('H:i:s'),
        ]);
    }

    private function daftarPeserta(RuangUjianCbt $ruangUjianCbt)
    {
        return $ruangUjianCbt->pesertaUjianCbt()
            ->with(['kelasUjianCbt.kelas', 'anggotaKelas.siswa'])
            ->get()
            ->sortBy(fn (PesertaUjianCbt $peserta) => sprintf(
                '%05d|%s',
                $peserta->nomor_meja ?? 999,
                $peserta->anggotaKelas?->siswa?->nama_lengkap ?? '',
            ))
            ->values();
    }

    private function ringkasanRuang(RuangUjianCbt $ruangUjianCbt): array
    {
        $query = $ruangUjianCbt->pesertaUjianCbt();

        return [
            'peserta' => (clone $query)->count(),
            'hadir' => (clone $query)->whereIn('status_kehadiran_ujian', ['hadir', 'terlambat'])->count(),
            'belum_absen' => (clone $query)->where('status_kehadiran_ujian', 'belum_absen')->count(),
            'tidak_hadir' => (clone $query)->whereIn('status_kehadiran_ujian', ['sakit', 'izin', 'alfa'])->count(),
        ];
    }

    private function dataPeserta(PesertaUjianCbt $peserta): array
    {
        $siswa = $peserta->anggotaKelas?->siswa;

        return [
            'id' => $peserta->id,
            'nama_lengkap' => $siswa?->nama_lengkap,
            'nisn' => $siswa?->nisn,
            'kelas' => $peserta->kelasUjianCbt?->kelas?->nama,
            'foto_url' => $this->fotoUrl($siswa),
            'nomor_meja' => $peserta->nomor_meja,
            'status' => $peserta->status_kehadiran_ujian,
            'label_status' => $peserta->labelStatusKehadiranUjian(),
            'waktu_scan' => $peserta->absen_ujian_pada?->format('H:i:s'),
            'catatan' => $peserta->catatan_kehadiran_ujian,
        ];
    }

    private function dataSiswa(?Siswa $siswa, ?PesertaUjianCbt $peserta = null): ?array
    {
        if (! $siswa) {
            return null;
        }

        return [
            'nama_lengkap' => $siswa->nama_lengkap,
            'nisn' => $siswa->nisn,
            'foto_url' => $this->fotoUrl($siswa),
            'kelas' => $peserta?->kelasUjianCbt?->kelas?->nama,
            'nomor_meja' => $peserta?->nomor_meja,
        ];
    }

    private function fotoUrl(?Siswa $siswa): ?string
    {
        return $siswa?->foto && Storage::disk('public')->exists($siswa->foto)
            ? asset('storage/'.$siswa->foto)
            : null;
    }

    private function nisnDariIsiScan(string $isiScan): ?string
    {
        $isiScan = trim($isiScan);

        if (preg_match('/^\d{8,20}$/', $isiScan)) {
            return $isiScan;
        }

        if (preg_match('/(?:NISN\s*[:=-]?\s*)(\d{8,20})/i', $isiScan, $cocok)) {
            return $cocok[1];
        }

        return null;
    }

    private function responsGagal(string $pesan): JsonResponse
    {
        return response()->json([
            'berhasil' => false,
            'status' => 'tidak_dikenali',
            'pesan' => $pesan,
            'waktu_server' => now()->format('H:i:s'),
        ], 422);
    }

    private function pastikanRuangMilikUjian(UjianCbt $ujianCbt, RuangUjianCbt $ruangUjianCbt): void
    {
        abort_unless((int) $ruangUjianCbt->ujian_cbt_id === (int) $ujianCbt->id, 404);
    }

    private function pastikanDapatMengelolaRuang(Request $request, RuangUjianCbt $ruangUjianCbt): void
    {
        $pengguna = $request->user();

        if ($this->dapatKelolaSemua($pengguna)) {
            return;
        }

        abort_unless(
            filled($pengguna?->pegawai_id)
            && in_array((int) $pengguna->pegawai_id, [
                (int) $ruangUjianCbt->pengawas_utama_pegawai_id,
                (int) $ruangUjianCbt->pengawas_pendamping_pegawai_id,
            ], true),
            403,
        );
    }

    private function dapatKelolaSemua($pengguna): bool
    {
        return (bool) ($pengguna?->administrator() || $pengguna?->memilikiIzin('cbt.kelola'));
    }
}
