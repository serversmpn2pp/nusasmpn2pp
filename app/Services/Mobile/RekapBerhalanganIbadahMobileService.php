<?php

namespace App\Services\Mobile;

use App\Models\KonfirmasiBerhalanganIbadah;
use App\Models\Pengguna;
use App\Models\PeriodeBerhalanganIbadah;
use App\Models\TahunPelajaran;
use App\Services\Ibadah\AksesBerhalanganIbadah;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class RekapBerhalanganIbadahMobileService
{
    public function __construct(private readonly AksesBerhalanganIbadah $akses) {}

    public function tampilkan(Pengguna $pengguna, array $filter, bool $semua = false): array
    {
        $tahunPelajaran = TahunPelajaran::query()
            ->where('aktif', true)
            ->orderByDesc('tanggal_mulai')
            ->firstOrFail();
        abort_unless(
            $this->akses->dapatMengonfirmasi($pengguna, $tahunPelajaran),
            403,
            'Rekap privat hanya dapat dibuka oleh petugas yang berwenang.',
        );

        $bulan = Carbon::createFromFormat('Y-m', $filter['bulan'] ?? now()->format('Y-m'))->startOfMonth();
        $bulanMinimum = $tahunPelajaran->tanggal_mulai->copy()->startOfMonth();
        $bulanMaksimum = now()->startOfMonth()->min($tahunPelajaran->tanggal_selesai->copy()->startOfMonth());

        if ($bulan->lt($bulanMinimum) || $bulan->gt($bulanMaksimum)) {
            throw ValidationException::withMessages([
                'bulan' => 'Pilih bulan dalam tahun pelajaran aktif dan tidak melewati bulan berjalan.',
            ]);
        }

        $tanggalMulai = $bulan->copy()->startOfMonth()->max($tahunPelajaran->tanggal_mulai->copy()->startOfDay());
        $tanggalSelesai = $bulan->copy()->endOfMonth()
            ->min($tahunPelajaran->tanggal_selesai->copy()->endOfDay())
            ->min(now()->endOfDay());
        $daftarKelas = $this->akses->kelasTercakup($pengguna, $tahunPelajaran);
        $kelasId = filled($filter['kelas_id'] ?? null) ? (int) $filter['kelas_id'] : null;

        if ($kelasId && ! $daftarKelas->contains('id', $kelasId)) {
            abort(403, 'Kelas berada di luar cakupan pendampingan Anda.');
        }

        $status = $filter['status'] ?? 'semua';
        $kataKunci = trim((string) ($filter['cari'] ?? ''));
        $dasar = PeriodeBerhalanganIbadah::query()
            ->where('tahun_pelajaran_id', $tahunPelajaran->id)
            ->whereDate('tanggal_mulai', '<=', $tanggalSelesai->toDateString())
            ->where(function (Builder $query) use ($tanggalMulai) {
                $query->whereNull('tanggal_selesai')
                    ->orWhereDate('tanggal_selesai', '>=', $tanggalMulai->toDateString());
            })
            ->when($kelasId, fn (Builder $query) => $query->where('kelas_id', $kelasId))
            ->when($kataKunci !== '', function (Builder $query) use ($kataKunci) {
                $pola = '%'.mb_strtolower($kataKunci).'%';
                $query->whereHas('siswa', fn (Builder $query) => $query
                    ->whereRaw('LOWER(nama_lengkap) LIKE ?', [$pola])
                    ->orWhereRaw('LOWER(nisn) LIKE ?', [$pola]));
            });
        $this->akses->batasiPeriodeSesuaiCakupan($dasar, $pengguna, $tahunPelajaran);

        $ringkasan = [
            'periode' => (clone $dasar)->count(),
            'siswi' => (clone $dasar)->distinct()->count('siswa_id'),
            'aktif' => (clone $dasar)->where('status', PeriodeBerhalanganIbadah::STATUS_AKTIF)->count(),
            'perlu_konfirmasi' => (clone $dasar)->where('status', PeriodeBerhalanganIbadah::STATUS_PERLU_KONFIRMASI)->count(),
            'selesai' => (clone $dasar)->where('status', PeriodeBerhalanganIbadah::STATUS_SELESAI)->count(),
        ];
        $query = (clone $dasar)
            ->with([
                'siswa:id,nama_lengkap,nisn,foto',
                'kelas:id,nama',
                'konfirmasiTerakhir.dikonfirmasiOlehPengguna:id,nama',
            ])
            ->withCount([
                'presensiHarian as presensi_bulan_count' => fn (Builder $query) => $query
                    ->whereDate('tanggal', '>=', $tanggalMulai->toDateString())
                    ->whereDate('tanggal', '<=', $tanggalSelesai->toDateString()),
                'riwayatKonfirmasi as konfirmasi_bulan_count' => fn (Builder $query) => $query
                    ->whereBetween('dikonfirmasi_pada', [$tanggalMulai, $tanggalSelesai]),
            ])
            ->when($status !== 'semua', fn (Builder $query) => $query->where('status', $status))
            ->orderByDesc('tanggal_mulai')
            ->orderByDesc('id');

        if ($semua) {
            $items = $query->get();
            $paginasi = $this->paginasiSemua($items);
        } else {
            $paginator = $query->paginate(
                (int) ($filter['per_halaman'] ?? 15),
                ['*'],
                'halaman',
                (int) ($filter['halaman'] ?? 1),
            );
            $items = collect($paginator->items());
            $paginasi = $this->paginasi($paginator);
        }

        return [
            'mode_privat' => true,
            'pesan_privasi' => 'Dokumen internal dan privat. Catatan percakapan tidak ditampilkan dan tidak digabungkan ke laporan ibadah umum.',
            'tahun_pelajaran' => [
                'id' => (int) $tahunPelajaran->id,
                'nama' => $tahunPelajaran->nama,
            ],
            'bulan' => $bulan->format('Y-m'),
            'bulan_label' => $bulan->locale('id')->translatedFormat('F Y'),
            'bulan_minimum' => $bulanMinimum->format('Y-m'),
            'bulan_maksimum' => $bulanMaksimum->format('Y-m'),
            'tanggal_mulai' => $tanggalMulai->toDateString(),
            'tanggal_selesai' => $tanggalSelesai->toDateString(),
            'tanggal_cetak' => now()->locale('id')->translatedFormat('d F Y H:i'),
            'filter' => [
                'kelas_id' => $kelasId,
                'status' => $status,
                'cari' => $kataKunci,
            ],
            'referensi' => [
                'kelas' => $daftarKelas->map(fn ($kelas) => [
                    'id' => (int) $kelas->id,
                    'nama' => $kelas->nama,
                ])->values(),
                'status' => collect($this->daftarStatus())->map(fn (string $label, string $kode) => [
                    'kode' => $kode,
                    'label' => $label,
                ])->values(),
            ],
            'ringkasan' => $ringkasan,
            'items' => $items->map(fn (PeriodeBerhalanganIbadah $item) => $this->ringkasPeriode($item))->values(),
            'paginasi' => $paginasi,
        ];
    }

    private function ringkasPeriode(PeriodeBerhalanganIbadah $periode): array
    {
        $akhirDurasi = $periode->tanggal_selesai ?: now();
        $konfirmasi = $periode->konfirmasiTerakhir;

        return [
            'id' => (int) $periode->id,
            'siswa' => [
                'id' => (int) $periode->siswa_id,
                'nama' => $periode->siswa?->nama_lengkap ?? '-',
                'nisn' => $periode->siswa?->nisn,
                'foto_url' => $this->fotoUrl($periode->siswa?->foto),
            ],
            'kelas' => [
                'id' => $periode->kelas?->id ? (int) $periode->kelas->id : null,
                'nama' => $periode->kelas?->nama ?? '-',
            ],
            'tanggal_mulai' => $periode->tanggal_mulai?->toDateString(),
            'tanggal_mulai_label' => $periode->tanggal_mulai?->locale('id')->translatedFormat('d M Y'),
            'tanggal_selesai' => $periode->tanggal_selesai?->toDateString(),
            'tanggal_selesai_label' => $periode->tanggal_selesai?->locale('id')->translatedFormat('d M Y'),
            'durasi_hari' => $periode->tanggal_mulai
                ? (int) $periode->tanggal_mulai->copy()->startOfDay()->diffInDays($akhirDurasi->copy()->startOfDay()) + 1
                : 0,
            'presensi_bulan' => (int) ($periode->presensi_bulan_count ?? 0),
            'konfirmasi_bulan' => (int) ($periode->konfirmasi_bulan_count ?? 0),
            'konfirmasi_terakhir' => $konfirmasi ? [
                'tanggal' => $konfirmasi->dikonfirmasi_pada?->toIso8601String(),
                'tanggal_label' => $konfirmasi->dikonfirmasi_pada?->locale('id')->translatedFormat('d M Y'),
                'hasil' => $konfirmasi->hasil,
                'hasil_label' => KonfirmasiBerhalanganIbadah::DAFTAR_HASIL[$konfirmasi->hasil] ?? '-',
                'oleh' => $konfirmasi->dikonfirmasiOlehPengguna?->nama,
            ] : null,
            'status' => $periode->status,
            'status_label' => $this->daftarStatus()[$periode->status] ?? str($periode->status)->headline()->toString(),
            'cara_selesai' => $periode->cara_selesai,
            'cara_selesai_label' => match ($periode->cara_selesai) {
                'scan_ibadah' => 'Scan ibadah biasa',
                'konfirmasi_privat' => 'Konfirmasi privat',
                default => null,
            },
        ];
    }

    private function daftarStatus(): array
    {
        return [
            'semua' => 'Semua status',
            PeriodeBerhalanganIbadah::STATUS_AKTIF => 'Sedang dipantau',
            PeriodeBerhalanganIbadah::STATUS_PERLU_KONFIRMASI => 'Perlu konfirmasi',
            PeriodeBerhalanganIbadah::STATUS_SELESAI => 'Selesai',
        ];
    }

    private function paginasi(LengthAwarePaginator $paginator): array
    {
        return [
            'halaman' => $paginator->currentPage(),
            'halaman_terakhir' => $paginator->lastPage(),
            'per_halaman' => $paginator->perPage(),
            'total' => $paginator->total(),
            'ada_halaman_berikutnya' => $paginator->hasMorePages(),
        ];
    }

    private function paginasiSemua(Collection $items): array
    {
        return [
            'halaman' => 1,
            'halaman_terakhir' => 1,
            'per_halaman' => $items->count(),
            'total' => $items->count(),
            'ada_halaman_berikutnya' => false,
        ];
    }

    private function fotoUrl(?string $foto): ?string
    {
        return $foto && Storage::disk('public')->exists($foto)
            ? asset('storage/'.$foto)
            : null;
    }
}
