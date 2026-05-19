<?php

namespace App\Http\Controllers;

use App\Models\AbsensiSiswa;
use App\Models\AnggotaKelas;
use App\Models\Kelas;
use App\Models\PengaturanAbsensi;
use App\Models\TahunPelajaran;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RekapAbsensiHarianController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'tanggal' => ['nullable', 'date'],
            'tahun_pelajaran_id' => ['nullable', 'integer', 'exists:tahun_pelajaran,id'],
            'kelas_id' => ['nullable', 'integer', 'exists:kelas,id'],
        ]);

        $tanggal = Carbon::parse($data['tanggal'] ?? now())->toDateString();
        $daftarTahunPelajaran = TahunPelajaran::query()
            ->orderByDesc('aktif')
            ->orderByDesc('tanggal_mulai')
            ->orderByDesc('id')
            ->get();

        $tahunPelajaranId = $this->ambilTahunPelajaranId($data['tahun_pelajaran_id'] ?? null, $daftarTahunPelajaran);
        $daftarKelas = $tahunPelajaranId
            ? Kelas::query()
                ->where('tahun_pelajaran_id', $tahunPelajaranId)
                ->where('aktif', true)
                ->orderBy('tingkat')
                ->orderBy('nama')
                ->get()
            : collect();

        $kelasId = $this->ambilKelasId($data['kelas_id'] ?? null, $daftarKelas);
        $rekapAbsensi = $tahunPelajaranId
            ? $this->ambilRekapAbsensi($tanggal, $tahunPelajaranId, $kelasId)
            : collect();
        $ringkasan = $this->hitungRingkasan($rekapAbsensi);

        return view('rekap-absensi-harian.index', [
            'tanggal' => $tanggal,
            'tahunPelajaranId' => $tahunPelajaranId,
            'kelasId' => $kelasId,
            'daftarTahunPelajaran' => $daftarTahunPelajaran,
            'daftarKelas' => $daftarKelas,
            'rekapAbsensi' => $rekapAbsensi,
            'ringkasan' => $ringkasan,
        ]);
    }

    public function editKoreksi(Request $request, AnggotaKelas $anggotaKelas)
    {
        $data = $request->validate([
            'tanggal' => ['nullable', 'date'],
        ]);

        $tanggal = Carbon::parse($data['tanggal'] ?? now())->toDateString();
        $anggotaKelas->load(['tahunPelajaran', 'kelas', 'siswa']);
        $absensi = $this->ambilAbsensi($tanggal, $anggotaKelas);
        $pengaturanAbsensi = $this->ambilPengaturanAbsensi($tanggal);

        return view('rekap-absensi-harian.koreksi', compact(
            'tanggal',
            'anggotaKelas',
            'absensi',
            'pengaturanAbsensi',
        ));
    }

    public function updateKoreksi(Request $request, AnggotaKelas $anggotaKelas)
    {
        $data = $request->validate([
            'tanggal' => ['required', 'date'],
            'status_kehadiran' => ['required', Rule::in(['hadir', 'izin', 'sakit', 'alfa'])],
            'jam_masuk' => ['nullable', 'date_format:H:i'],
            'jam_pulang' => ['nullable', 'date_format:H:i'],
            'catatan' => ['nullable', 'string'],
        ]);

        $tanggal = Carbon::parse($data['tanggal'])->toDateString();
        $anggotaKelas->load(['tahunPelajaran', 'kelas', 'siswa']);
        $this->pastikanDataKoreksiValid($data);

        DB::transaction(function () use ($data, $tanggal, $anggotaKelas) {
            $pengaturanAbsensi = $this->ambilPengaturanAbsensi($tanggal);
            $statusKehadiran = $data['status_kehadiran'];
            $jamMasuk = $statusKehadiran === 'hadir' ? ($data['jam_masuk'] ?? null) : null;
            $jamPulang = $statusKehadiran === 'hadir' ? ($data['jam_pulang'] ?? null) : null;
            $statusMasuk = null;
            $statusPulang = null;
            $menitTerlambat = 0;
            $menitPulangCepat = 0;

            if ($statusKehadiran === 'hadir') {
                [$statusMasuk, $menitTerlambat] = $this->hitungStatusMasuk($jamMasuk, $pengaturanAbsensi);
                [$statusPulang, $menitPulangCepat] = $this->hitungStatusPulang($jamPulang, $pengaturanAbsensi);
            }

            AbsensiSiswa::updateOrCreate(
                [
                    'tanggal' => $tanggal,
                    'siswa_id' => $anggotaKelas->siswa_id,
                ],
                [
                    'tahun_pelajaran_id' => $anggotaKelas->tahun_pelajaran_id,
                    'kelas_id' => $anggotaKelas->kelas_id,
                    'anggota_kelas_id' => $anggotaKelas->id,
                    'jam_masuk' => $jamMasuk,
                    'status_masuk' => $statusMasuk,
                    'menit_terlambat' => $menitTerlambat,
                    'jam_pulang' => $jamPulang,
                    'status_pulang' => $statusPulang,
                    'menit_pulang_cepat' => $menitPulangCepat,
                    'status_kehadiran' => $statusKehadiran,
                    'sumber' => 'manual',
                    'catatan' => $data['catatan'] ?? null,
                ],
            );
        });

        return redirect()
            ->route('rekap-absensi-harian.index', [
                'tanggal' => $tanggal,
                'tahun_pelajaran_id' => $anggotaKelas->tahun_pelajaran_id,
                'kelas_id' => $anggotaKelas->kelas_id,
            ])
            ->with('berhasil', 'Koreksi absensi berhasil disimpan.');
    }

    private function ambilTahunPelajaranId(?int $tahunPelajaranId, $daftarTahunPelajaran): ?int
    {
        if ($tahunPelajaranId && $daftarTahunPelajaran->contains('id', $tahunPelajaranId)) {
            return $tahunPelajaranId;
        }

        $tahunAktif = $daftarTahunPelajaran->firstWhere('aktif', true);

        return $tahunAktif?->id ?? $daftarTahunPelajaran->first()?->id;
    }

    private function ambilKelasId(?int $kelasId, $daftarKelas): ?int
    {
        if ($kelasId && $daftarKelas->contains('id', $kelasId)) {
            return $kelasId;
        }

        return null;
    }

    private function ambilRekapAbsensi(string $tanggal, int $tahunPelajaranId, ?int $kelasId)
    {
        $anggotaKelas = AnggotaKelas::query()
            ->with(['kelas', 'siswa'])
            ->where('tahun_pelajaran_id', $tahunPelajaranId)
            ->where('status_keanggotaan', 'aktif')
            ->whereHas('siswa', function ($query) {
                $query->where('aktif', true);
            })
            ->when($kelasId, function ($query) use ($kelasId) {
                $query->where('kelas_id', $kelasId);
            })
            ->orderBy('kelas_id')
            ->orderByRaw('nomor_absen IS NULL')
            ->orderBy('nomor_absen')
            ->orderBy('id')
            ->get();

        $absensi = AbsensiSiswa::query()
            ->whereDate('tanggal', $tanggal)
            ->where('tahun_pelajaran_id', $tahunPelajaranId)
            ->when($kelasId, function ($query) use ($kelasId) {
                $query->where('kelas_id', $kelasId);
            })
            ->get();

        $absensiPerAnggota = $absensi->whereNotNull('anggota_kelas_id')->keyBy('anggota_kelas_id');
        $absensiPerSiswa = $absensi->keyBy('siswa_id');

        return $anggotaKelas->map(function (AnggotaKelas $anggota) use ($absensiPerAnggota, $absensiPerSiswa) {
            $absen = $absensiPerAnggota->get($anggota->id) ?? $absensiPerSiswa->get($anggota->siswa_id);
            $statusKehadiran = $absen?->status_kehadiran ?? 'alfa';

            return [
                'anggota_kelas' => $anggota,
                'absensi' => $absen,
                'status_kehadiran' => $statusKehadiran,
                'status_sumber' => $absen ? 'catatan' : 'inferensi',
                'terlambat' => (int) ($absen?->menit_terlambat ?? 0),
                'pulang_cepat' => (int) ($absen?->menit_pulang_cepat ?? 0),
                'belum_pulang' => $statusKehadiran === 'hadir' && $absen?->jam_masuk && ! $absen?->jam_pulang,
            ];
        });
    }

    private function ambilAbsensi(string $tanggal, AnggotaKelas $anggotaKelas): ?AbsensiSiswa
    {
        return AbsensiSiswa::query()
            ->whereDate('tanggal', $tanggal)
            ->where('siswa_id', $anggotaKelas->siswa_id)
            ->first();
    }

    private function ambilPengaturanAbsensi(string $tanggal): ?PengaturanAbsensi
    {
        return PengaturanAbsensi::query()
            ->where('hari', $this->hariDariTanggal(Carbon::parse($tanggal)->isoWeekday()))
            ->where('aktif', true)
            ->first();
    }

    private function pastikanDataKoreksiValid(array $data): void
    {
        if ($data['status_kehadiran'] === 'hadir' && blank($data['jam_masuk'] ?? null)) {
            throw ValidationException::withMessages([
                'jam_masuk' => 'Jam masuk wajib diisi jika status kehadiran adalah hadir.',
            ]);
        }

        if (filled($data['jam_masuk'] ?? null) && filled($data['jam_pulang'] ?? null)) {
            if ($this->menitDariJam($data['jam_pulang']) < $this->menitDariJam($data['jam_masuk'])) {
                throw ValidationException::withMessages([
                    'jam_pulang' => 'Jam pulang tidak boleh lebih awal dari jam masuk.',
                ]);
            }
        }
    }

    private function hitungStatusMasuk(?string $jamMasuk, ?PengaturanAbsensi $pengaturanAbsensi): array
    {
        if (! $jamMasuk) {
            return [null, 0];
        }

        if (! $pengaturanAbsensi) {
            return ['manual', 0];
        }

        $menitTerlambat = max(0, $this->menitDariJam($jamMasuk) - $this->menitDariJam($pengaturanAbsensi->formatJam($pengaturanAbsensi->jam_masuk)));

        return [$menitTerlambat > 0 ? 'terlambat' : 'tepat_waktu', $menitTerlambat];
    }

    private function hitungStatusPulang(?string $jamPulang, ?PengaturanAbsensi $pengaturanAbsensi): array
    {
        if (! $jamPulang) {
            return [null, 0];
        }

        if (! $pengaturanAbsensi) {
            return ['manual', 0];
        }

        $menitPulangCepat = max(0, $this->menitDariJam($pengaturanAbsensi->formatJam($pengaturanAbsensi->jam_pulang)) - $this->menitDariJam($jamPulang));

        return [$menitPulangCepat > 0 ? 'pulang_cepat' : 'normal', $menitPulangCepat];
    }

    private function menitDariJam(string $jam): int
    {
        [$hour, $minute] = array_map('intval', explode(':', substr($jam, 0, 5)));

        return ($hour * 60) + $minute;
    }

    private function hariDariTanggal(int $isoWeekday): string
    {
        return [
            1 => 'senin',
            2 => 'selasa',
            3 => 'rabu',
            4 => 'kamis',
            5 => 'jumat',
            6 => 'sabtu',
            7 => 'minggu',
        ][$isoWeekday];
    }

    private function hitungRingkasan($rekapAbsensi): array
    {
        return [
            'total' => $rekapAbsensi->count(),
            'hadir' => $rekapAbsensi->where('status_kehadiran', 'hadir')->count(),
            'izin' => $rekapAbsensi->where('status_kehadiran', 'izin')->count(),
            'sakit' => $rekapAbsensi->where('status_kehadiran', 'sakit')->count(),
            'alfa' => $rekapAbsensi->where('status_kehadiran', 'alfa')->count(),
            'terlambat' => $rekapAbsensi->where('terlambat', '>', 0)->count(),
            'pulang_cepat' => $rekapAbsensi->where('pulang_cepat', '>', 0)->count(),
            'belum_pulang' => $rekapAbsensi->where('belum_pulang', true)->count(),
        ];
    }
}
