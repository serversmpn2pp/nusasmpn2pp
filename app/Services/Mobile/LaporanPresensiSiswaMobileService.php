<?php

namespace App\Services\Mobile;

use App\Models\AbsensiSiswa;
use App\Models\AnggotaKelas;
use App\Models\Pengguna;
use App\Models\Siswa;
use App\Services\Absensi\LaporanPresensiSiswaService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class LaporanPresensiSiswaMobileService
{
    public function __construct(private readonly LaporanPresensiSiswaService $laporan) {}

    public function daftar(Pengguna $pengguna, Request $request, array $opsi): array
    {
        $laporan = $this->laporan->bangun($request);
        $cari = trim((string) ($opsi['cari'] ?? ''));
        $halaman = max(1, (int) ($opsi['halaman'] ?? 1));
        $perHalaman = min(50, max(10, (int) ($opsi['per_halaman'] ?? 20)));
        $semuaItem = $laporan['laporanAbsensi'];
        $hasil = $cari === '' ? $semuaItem : $semuaItem->filter(function (array $item) use ($cari) {
            $siswa = $item['anggota_kelas']->siswa;
            $teks = mb_strtolower(implode(' ', [$siswa?->nama_lengkap, $siswa?->nis, $siswa?->nisn]));

            return str_contains($teks, mb_strtolower($cari));
        })->values();
        $total = $hasil->count();
        $items = $hasil->forPage($halaman, $perHalaman)->values();

        return [
            'periode' => [
                'jenis' => $laporan['periode'],
                'label' => $laporan['labelPeriode'],
                'tanggal_mulai' => $laporan['tanggalMulai'],
                'tanggal_selesai' => $laporan['tanggalSelesai'],
                'jumlah_hari_efektif' => $laporan['jumlahHariEfektif'],
            ],
            'ringkasan' => $laporan['ringkasan'],
            'items' => $items->map(fn (array $item) => $this->item($item))->values(),
            'tahun_pelajaran' => $laporan['daftarTahunPelajaran']->map(fn ($item) => [
                'id' => (int) $item->id, 'nama' => $item->nama, 'aktif' => (bool) $item->aktif,
            ])->values(),
            'kelas' => $laporan['daftarKelas']->map(fn ($item) => [
                'id' => (int) $item->id, 'nama' => $item->nama, 'tingkat' => (int) $item->tingkat,
            ])->values(),
            'filter' => [
                'tahun_pelajaran_id' => $laporan['tahunPelajaranId'], 'kelas_id' => $laporan['kelasId'],
                'periode' => $laporan['periode'], 'tanggal' => $laporan['tanggal'], 'bulan' => $laporan['bulan'],
                'semester' => $laporan['semester'], 'tanggal_mulai' => $laporan['tanggalMulai'],
                'tanggal_selesai' => $laporan['tanggalSelesai'], 'cari' => $cari,
            ],
            'paginasi' => [
                'halaman' => $halaman, 'per_halaman' => $perHalaman, 'total' => $total,
                'halaman_terakhir' => max(1, (int) ceil($total / $perHalaman)),
                'ada_halaman_berikutnya' => $halaman * $perHalaman < $total,
            ],
            'hak_akses' => [
                'cakupan_wali_kelas' => $laporan['cakupanWaliKelas'],
                'dapat_export' => $pengguna->memilikiIzin('laporan.export'),
            ],
        ];
    }

    public function detail(Pengguna $pengguna, Request $request, AnggotaKelas $anggotaKelas): array
    {
        $laporan = $this->laporan->bangun($request);
        $item = $laporan['laporanAbsensi']->first(
            fn (array $item) => (int) $item['anggota_kelas']->id === (int) $anggotaKelas->id,
        );
        abort_unless($item, 404, 'Siswa tidak ditemukan pada cakupan laporan ini.');
        $anggotaKelas = $item['anggota_kelas'];
        $absensi = AbsensiSiswa::query()
            ->where('siswa_id', $anggotaKelas->siswa_id)
            ->where('tahun_pelajaran_id', $laporan['tahunPelajaranId'])
            ->when(! empty($laporan['tanggalEfektif']), fn ($query) => $query
                ->whereDate('tanggal', '>=', reset($laporan['tanggalEfektif']))
                ->whereDate('tanggal', '<=', end($laporan['tanggalEfektif'])))
            ->when(empty($laporan['tanggalEfektif']), fn ($query) => $query->whereRaw('1 = 0'))
            ->get()
            ->filter(fn (AbsensiSiswa $item) => in_array($item->tanggal->toDateString(), $laporan['tanggalEfektif'], true))
            ->keyBy(fn (AbsensiSiswa $presensi) => $presensi->tanggal->toDateString());

        return [
            'siswa' => $this->identitas($anggotaKelas),
            'periode' => [
                'jenis' => $laporan['periode'], 'label' => $laporan['labelPeriode'],
                'tanggal_mulai' => $laporan['tanggalMulai'], 'tanggal_selesai' => $laporan['tanggalSelesai'],
                'jumlah_hari_efektif' => $laporan['jumlahHariEfektif'],
            ],
            'ringkasan' => $this->ringkasanItem($item),
            'rincian' => collect($laporan['tanggalEfektif'])->map(function (string $tanggal) use ($absensi) {
                $presensi = $absensi->get($tanggal);
                $status = $presensi?->status_kehadiran ?? 'alfa';

                return [
                    'tanggal' => $tanggal,
                    'tanggal_label' => Carbon::parse($tanggal)->locale('id')->translatedFormat('D, d M Y'),
                    'status' => $status,
                    'status_label' => $this->labelStatus($status),
                    'inferensi' => $presensi === null,
                    'jam_masuk' => $this->formatJam($presensi?->jam_masuk),
                    'jam_pulang' => $this->formatJam($presensi?->jam_pulang),
                    'menit_terlambat' => (int) ($presensi?->menit_terlambat ?? 0),
                    'menit_pulang_cepat' => (int) ($presensi?->menit_pulang_cepat ?? 0),
                    'sumber' => $presensi?->sumber,
                    'catatan' => $presensi?->catatan,
                ];
            })->values(),
            'hak_akses' => [
                'cakupan_wali_kelas' => $laporan['cakupanWaliKelas'],
                'dapat_export' => $pengguna->memilikiIzin('laporan.export'),
            ],
        ];
    }

    public function bangunLaporan(Request $request): array
    {
        return $this->laporan->bangun($request);
    }

    public function namaBerkas(array $laporan): string
    {
        return $this->laporan->namaBerkas($laporan);
    }

    private function item(array $item): array
    {
        return [
            ...$this->identitas($item['anggota_kelas']),
            'ringkasan' => $this->ringkasanItem($item),
        ];
    }

    private function identitas(AnggotaKelas $anggota): array
    {
        $siswa = $anggota->siswa;
        $foto = $siswa && filled($siswa->foto) && Storage::disk('public')->exists($siswa->foto);

        return [
            'anggota_kelas_id' => (int) $anggota->id,
            'nomor_absen' => $anggota->nomor_absen,
            'nama' => $siswa?->nama_lengkap ?? '-',
            'nis' => $siswa?->nis,
            'nisn' => $siswa?->nisn,
            'inisial' => $siswa ? $this->inisial($siswa) : 'S',
            'foto_url' => $foto ? asset('storage/'.$siswa->foto) : null,
            'kelas' => $anggota->kelas?->nama ?? '-',
        ];
    }

    private function ringkasanItem(array $item): array
    {
        return collect($item)->except('anggota_kelas')->all();
    }

    private function inisial(Siswa $siswa): string
    {
        $hasil = collect(preg_split('/\s+/', trim($siswa->nama_lengkap)) ?: [])->filter()->take(2)
            ->map(fn (string $kata) => mb_substr($kata, 0, 1))->implode('');

        return mb_strtoupper($hasil ?: 'S');
    }

    private function labelStatus(string $status): string
    {
        return match ($status) {
            'hadir' => 'Hadir', 'izin' => 'Izin', 'sakit' => 'Sakit', default => 'Alfa'
        };
    }

    private function formatJam(?string $jam): ?string
    {
        return $jam ? mb_substr($jam, 0, 5) : null;
    }
}
