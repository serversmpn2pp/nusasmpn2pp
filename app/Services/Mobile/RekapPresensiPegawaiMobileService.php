<?php

namespace App\Services\Mobile;

use App\Models\AbsensiPegawai;
use App\Models\Pegawai;
use App\Models\PengaturanAbsensiPegawai;
use App\Models\Pengguna;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class RekapPresensiPegawaiMobileService
{
    private const PER_HALAMAN = 30;

    public function daftar(Pengguna $pengguna, array $filter): array
    {
        $tanggal = Carbon::parse($filter['tanggal'] ?? now())->toDateString();
        $pribadi = $pengguna->membatasiCakupanAbsensiPegawai();
        $this->pastikanAkunPegawai($pengguna, $pribadi);
        $jenis = $pribadi ? '' : trim((string) ($filter['jenis_pegawai'] ?? ''));
        $pegawaiId = $pribadi ? (int) $pengguna->pegawai_id : ($filter['pegawai_id'] ?? null);
        $statusPegawai = $pribadi ? 'semua' : ($filter['status_pegawai'] ?? 'aktif');
        $status = $pribadi ? 'semua' : ($filter['status'] ?? 'semua');
        $cari = trim((string) ($filter['cari'] ?? ''));
        $halaman = max((int) ($filter['halaman'] ?? 1), 1);

        $pegawai = $this->queryPegawai($jenis, $pegawaiId ? (int) $pegawaiId : null, $statusPegawai, $cari)->get();
        $rekapSemua = $this->rekap($tanggal, $pegawai);
        $ringkasan = $this->ringkasan($rekapSemua);
        $rekap = $this->filterStatus($rekapSemua, $status);
        $total = $rekap->count();
        $halamanTerakhir = max((int) ceil($total / self::PER_HALAMAN), 1);
        $items = $rekap->slice(($halaman - 1) * self::PER_HALAMAN, self::PER_HALAMAN)->values();

        return [
            'tanggal' => $tanggal,
            'tanggal_label' => Carbon::parse($tanggal)->locale('id')->translatedFormat('l, d F Y'),
            'waktu_server' => now()->toIso8601String(),
            'items' => $items->map(fn (array $item) => $this->item($pengguna, $item))->values(),
            'ringkasan' => $ringkasan,
            'jenis_pegawai' => $pribadi ? [] : $this->pilihanJenisPegawai(),
            'pegawai' => $pribadi ? [] : $this->pilihanPegawai($statusPegawai),
            'filter' => [
                'tanggal' => $tanggal,
                'jenis_pegawai' => $jenis !== '' ? $jenis : null,
                'pegawai_id' => $pegawaiId ? (int) $pegawaiId : null,
                'status_pegawai' => $statusPegawai,
                'status' => $status,
                'cari' => $cari,
            ],
            'paginasi' => [
                'halaman' => $halaman,
                'halaman_terakhir' => $halamanTerakhir,
                'total' => $total,
                'ada_halaman_berikutnya' => $halaman < $halamanTerakhir,
            ],
            'hak_akses' => [
                'cakupan_pribadi' => $pribadi,
                'dapat_koreksi' => ! $pribadi && $pengguna->memilikiIzin('absensi.koreksi'),
            ],
        ];
    }

    public function detail(Pengguna $pengguna, Pegawai $pegawai, string $tanggal): array
    {
        $tanggal = Carbon::parse($tanggal)->toDateString();
        $this->pastikanBolehAkses($pengguna, $pegawai);
        $absensi = $this->ambilAbsensi($pegawai, $tanggal);
        $jadwal = $this->ambilJadwal($pegawai, $tanggal);
        $rekap = [
            'pegawai' => $pegawai,
            'absensi' => $absensi,
            'status_kehadiran' => $absensi?->status_kehadiran ?? 'alfa',
            'status_sumber' => $absensi ? 'catatan' : 'inferensi',
            'terlambat' => (int) ($absensi?->menit_terlambat ?? 0),
            'pulang_cepat' => (int) ($absensi?->menit_pulang_cepat ?? 0),
            'belum_pulang' => $absensi?->status_kehadiran === 'hadir' && filled($absensi?->jam_masuk) && blank($absensi?->jam_pulang),
        ];

        return [
            'tanggal' => $tanggal,
            'tanggal_label' => Carbon::parse($tanggal)->locale('id')->translatedFormat('l, d F Y'),
            'item' => $this->item($pengguna, $rekap),
            'jadwal_presensi' => $jadwal ? [
                'tersedia' => true,
                'id' => (int) $jadwal->id,
                'nama' => $jadwal->nama_jadwal,
                'jam_masuk' => $this->formatJam($jadwal->jam_masuk),
                'jam_pulang' => $this->formatJam($jadwal->jam_pulang),
            ] : [
                'tersedia' => false,
                'id' => null,
                'nama' => null,
                'jam_masuk' => null,
                'jam_pulang' => null,
            ],
            'hak_akses' => [
                'dapat_koreksi' => ! $pengguna->membatasiCakupanAbsensiPegawai()
                    && $pengguna->memilikiIzin('absensi.koreksi'),
                'cakupan_pribadi' => $pengguna->membatasiCakupanAbsensiPegawai(),
            ],
        ];
    }

    public function koreksi(Pengguna $pengguna, Pegawai $pegawai, array $data): AbsensiPegawai
    {
        $this->pastikanBolehAkses($pengguna, $pegawai);
        abort_unless(
            ! $pengguna->membatasiCakupanAbsensiPegawai() && $pengguna->memilikiIzin('absensi.koreksi'),
            403,
        );
        $this->pastikanKoreksiValid($data);
        $tanggal = Carbon::parse($data['tanggal'])->toDateString();

        return DB::transaction(function () use ($pegawai, $tanggal, $data) {
            $jadwal = $this->ambilJadwal($pegawai, $tanggal);
            $hadir = $data['status_kehadiran'] === 'hadir';
            $jamMasuk = $hadir ? ($data['jam_masuk'] ?? null) : null;
            $jamPulang = $hadir ? ($data['jam_pulang'] ?? null) : null;
            [$statusMasuk, $terlambat] = $hadir ? $this->statusMasuk($jamMasuk, $jadwal) : [null, 0];
            [$statusPulang, $pulangCepat] = $hadir ? $this->statusPulang($jamPulang, $jadwal) : [null, 0];

            return AbsensiPegawai::updateOrCreate(
                ['tanggal' => $tanggal, 'pegawai_id' => $pegawai->id],
                [
                    'pengaturan_absensi_pegawai_id' => $jadwal?->id,
                    'jam_masuk' => $jamMasuk,
                    'status_masuk' => $statusMasuk,
                    'menit_terlambat' => $terlambat,
                    'jam_pulang' => $jamPulang,
                    'status_pulang' => $statusPulang,
                    'menit_pulang_cepat' => $pulangCepat,
                    'status_kehadiran' => $data['status_kehadiran'],
                    'sumber' => 'manual',
                    'catatan' => filled($data['catatan'] ?? null) ? trim($data['catatan']) : null,
                ],
            );
        });
    }

    private function queryPegawai(string $jenis, ?int $pegawaiId, string $status, string $cari): Builder
    {
        return Pegawai::query()
            ->select(['id', 'nama_lengkap', 'nip', 'foto', 'jenis_kelamin', 'jenis_pegawai', 'jabatan_utama', 'status_kepegawaian', 'aktif'])
            ->when($status === 'aktif', fn (Builder $query) => $query->where('aktif', true))
            ->when($status === 'nonaktif', fn (Builder $query) => $query->where('aktif', false))
            ->when($jenis !== '', fn (Builder $query) => $query->where('jenis_pegawai', $jenis))
            ->when($pegawaiId, fn (Builder $query) => $query->whereKey($pegawaiId))
            ->when($cari !== '', function (Builder $query) use ($cari) {
                $pola = '%'.mb_strtolower($cari).'%';
                $query->where(function (Builder $query) use ($pola) {
                    $query->whereRaw('LOWER(nama_lengkap) LIKE ?', [$pola])
                        ->orWhereRaw("LOWER(COALESCE(nip, '')) LIKE ?", [$pola])
                        ->orWhereRaw("LOWER(COALESCE(jabatan_utama, '')) LIKE ?", [$pola]);
                });
            })
            ->orderBy('nama_lengkap');
    }

    private function rekap(string $tanggal, Collection $pegawai): Collection
    {
        $absensi = AbsensiPegawai::query()
            ->with('pengaturanAbsensiPegawai:id,nama_jadwal')
            ->whereDate('tanggal', $tanggal)
            ->whereIn('pegawai_id', $pegawai->pluck('id'))
            ->get()
            ->keyBy('pegawai_id');

        return $pegawai->map(function (Pegawai $pegawai) use ($absensi) {
            $item = $absensi->get($pegawai->id);
            $status = $item?->status_kehadiran ?? 'alfa';

            return [
                'pegawai' => $pegawai,
                'absensi' => $item,
                'status_kehadiran' => $status,
                'status_sumber' => $item ? 'catatan' : 'inferensi',
                'terlambat' => (int) ($item?->menit_terlambat ?? 0),
                'pulang_cepat' => (int) ($item?->menit_pulang_cepat ?? 0),
                'belum_pulang' => $status === 'hadir' && filled($item?->jam_masuk) && blank($item?->jam_pulang),
            ];
        });
    }

    private function filterStatus(Collection $rekap, string $status): Collection
    {
        return match ($status) {
            'terlambat' => $rekap->where('terlambat', '>', 0)->values(),
            'pulang_cepat' => $rekap->where('pulang_cepat', '>', 0)->values(),
            'belum_pulang' => $rekap->where('belum_pulang', true)->values(),
            'semua' => $rekap->values(),
            default => $rekap->where('status_kehadiran', $status)->values(),
        };
    }

    private function item(Pengguna $pengguna, array $rekap): array
    {
        /** @var Pegawai $pegawai */
        $pegawai = $rekap['pegawai'];
        /** @var AbsensiPegawai|null $absensi */
        $absensi = $rekap['absensi'];
        $foto = filled($pegawai->foto) && Storage::disk('public')->exists($pegawai->foto);
        $status = $rekap['status_kehadiran'];

        return [
            'pegawai' => [
                'id' => (int) $pegawai->id,
                'nama' => $pegawai->nama_lengkap,
                'nip' => $pegawai->nip,
                'inisial' => $this->inisial($pegawai->nama_lengkap),
                'foto_url' => $foto ? asset('storage/'.$pegawai->foto) : null,
                'jenis_pegawai' => $pegawai->jenis_pegawai,
                'jabatan' => $pegawai->jabatan_utama,
                'status_kepegawaian' => $pegawai->status_kepegawaian,
                'aktif' => (bool) $pegawai->aktif,
            ],
            'presensi' => [
                'id' => $absensi?->id ? (int) $absensi->id : null,
                'status' => $status,
                'status_label' => AbsensiPegawai::DAFTAR_STATUS_KEHADIRAN[$status] ?? str($status)->headline()->toString(),
                'sumber' => $rekap['status_sumber'],
                'sumber_label' => $absensi ? $this->labelSumber($absensi->sumber) : 'Inferensi belum tercatat',
                'jam_masuk' => $this->formatJam($absensi?->jam_masuk),
                'jam_pulang' => $this->formatJam($absensi?->jam_pulang),
                'status_masuk' => $absensi?->status_masuk,
                'status_pulang' => $absensi?->status_pulang,
                'menit_terlambat' => (int) $rekap['terlambat'],
                'menit_pulang_cepat' => (int) $rekap['pulang_cepat'],
                'belum_pulang' => (bool) $rekap['belum_pulang'],
                'catatan' => $absensi?->catatan,
                'nama_jadwal' => $absensi?->pengaturanAbsensiPegawai?->nama_jadwal,
            ],
            'koreksi' => [
                'dapat' => ! $pengguna->membatasiCakupanAbsensiPegawai()
                    && $pengguna->memilikiIzin('absensi.koreksi'),
            ],
        ];
    }

    private function ringkasan(Collection $rekap): array
    {
        return [
            'total' => $rekap->count(),
            'hadir' => $rekap->where('status_kehadiran', 'hadir')->count(),
            'izin' => $rekap->where('status_kehadiran', 'izin')->count(),
            'sakit' => $rekap->where('status_kehadiran', 'sakit')->count(),
            'dinas_luar' => $rekap->where('status_kehadiran', 'dinas_luar')->count(),
            'cuti' => $rekap->where('status_kehadiran', 'cuti')->count(),
            'alfa' => $rekap->where('status_kehadiran', 'alfa')->count(),
            'terlambat' => $rekap->where('terlambat', '>', 0)->count(),
            'pulang_cepat' => $rekap->where('pulang_cepat', '>', 0)->count(),
            'belum_pulang' => $rekap->where('belum_pulang', true)->count(),
        ];
    }

    private function pilihanJenisPegawai(): Collection
    {
        return Pegawai::query()->whereNotNull('jenis_pegawai')->where('jenis_pegawai', '!=', '')
            ->distinct()->orderBy('jenis_pegawai')->pluck('jenis_pegawai')->values();
    }

    private function pilihanPegawai(string $status): Collection
    {
        return Pegawai::query()
            ->when($status === 'aktif', fn (Builder $query) => $query->where('aktif', true))
            ->when($status === 'nonaktif', fn (Builder $query) => $query->where('aktif', false))
            ->orderBy('nama_lengkap')->get(['id', 'nama_lengkap', 'nip'])
            ->map(fn (Pegawai $pegawai) => [
                'id' => (int) $pegawai->id,
                'nama' => $pegawai->nama_lengkap,
                'nip' => $pegawai->nip,
            ])->values();
    }

    private function ambilAbsensi(Pegawai $pegawai, string $tanggal): ?AbsensiPegawai
    {
        return AbsensiPegawai::query()->with('pengaturanAbsensiPegawai:id,nama_jadwal')
            ->whereDate('tanggal', $tanggal)->where('pegawai_id', $pegawai->id)->first();
    }

    private function ambilJadwal(Pegawai $pegawai, string $tanggal): ?PengaturanAbsensiPegawai
    {
        $hari = array_keys(PengaturanAbsensiPegawai::DAFTAR_HARI)[Carbon::parse($tanggal)->isoWeekday() - 1];
        $dasar = fn () => PengaturanAbsensiPegawai::query()->where('hari', $hari)->where('aktif', true);

        return $dasar()->where('cakupan', 'pegawai')->where('pegawai_id', $pegawai->id)->first()
            ?? (filled($pegawai->jenis_pegawai)
                ? $dasar()->where('cakupan', 'jenis_pegawai')->where('jenis_pegawai', $pegawai->jenis_pegawai)->first()
                : null)
            ?? $dasar()->where('cakupan', 'semua')->first();
    }

    private function pastikanAkunPegawai(Pengguna $pengguna, bool $pribadi): void
    {
        if ($pribadi) {
            abort_unless($pengguna->pegawai_id, 403, 'Akun belum terhubung dengan data pegawai.');
        }
    }

    private function pastikanBolehAkses(Pengguna $pengguna, Pegawai $pegawai): void
    {
        abort_unless($pengguna->dapatMengaksesAbsensiPegawai($pegawai->id), 403);
    }

    private function pastikanKoreksiValid(array $data): void
    {
        if ($data['status_kehadiran'] === 'hadir' && blank($data['jam_masuk'] ?? null)) {
            throw ValidationException::withMessages(['jam_masuk' => 'Jam masuk wajib diisi jika status hadir.']);
        }
        if (filled($data['jam_masuk'] ?? null) && filled($data['jam_pulang'] ?? null)
            && $this->menit($data['jam_pulang']) < $this->menit($data['jam_masuk'])) {
            throw ValidationException::withMessages(['jam_pulang' => 'Jam pulang tidak boleh lebih awal dari jam masuk.']);
        }
    }

    private function statusMasuk(?string $jam, ?PengaturanAbsensiPegawai $jadwal): array
    {
        if (! $jam) {
            return [null, 0];
        }
        if (! $jadwal) {
            return ['manual', 0];
        }
        $terlambat = max(0, $this->menit($jam) - $this->menit($jadwal->jam_masuk));

        return [$terlambat > 0 ? 'terlambat' : 'tepat_waktu', $terlambat];
    }

    private function statusPulang(?string $jam, ?PengaturanAbsensiPegawai $jadwal): array
    {
        if (! $jam) {
            return [null, 0];
        }
        if (! $jadwal) {
            return ['manual', 0];
        }
        $cepat = max(0, $this->menit($jadwal->jam_pulang) - $this->menit($jam));

        return [$cepat > 0 ? 'pulang_cepat' : 'normal', $cepat];
    }

    private function menit(string $jam): int
    {
        [$hour, $minute] = array_map('intval', explode(':', substr($jam, 0, 5)));

        return ($hour * 60) + $minute;
    }

    private function formatJam(?string $jam): ?string
    {
        return $jam ? substr($jam, 0, 5) : null;
    }

    private function labelSumber(?string $sumber): string
    {
        return match ($sumber) {
            'scan' => 'Mesin scanner',
            'manual', 'koreksi_manual' => 'Koreksi manual',
            default => str($sumber ?: 'Tidak diketahui')->headline()->toString(),
        };
    }

    private function inisial(string $nama): string
    {
        return mb_strtoupper(collect(preg_split('/\s+/', trim($nama)) ?: [])->take(2)
            ->map(fn (string $kata) => mb_substr($kata, 0, 1))->join('') ?: 'P');
    }
}
