<?php

namespace App\Services\Mobile;

use App\Models\AksesCepatPengguna;
use App\Models\PengawasRuangUjianTerpusat;
use App\Models\Pengguna;
use App\Services\Ibadah\AksesBerhalanganIbadah;
use App\Services\Ibadah\AksesScanKegiatanIbadah;
use App\Services\Pembinaan\AksesRekapPoinSiswaService;
use App\Services\Pembinaan\AksesSanksiPoinService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MenuMobileService
{
    public function __construct(
        private AksesBerhalanganIbadah $aksesBerhalangan,
        private AksesScanKegiatanIbadah $aksesIbadah,
        private AksesRekapPoinSiswaService $aksesRekapPoin,
        private AksesSanksiPoinService $aksesSanksi,
    ) {}

    public function siapkan(Pengguna $pengguna): array
    {
        $pengguna->loadMissing('daftarPeran.izin');
        $kelompok = $this->kelompokTersedia($pengguna);

        return [
            'dihasilkan_pada' => now()->toISOString(),
            'jumlah_menu' => $kelompok->sum(fn (array $item) => count($item['items'])),
            'kelompok' => $kelompok->all(),
            'akses_cepat' => $this->siapkanAksesCepat($pengguna, $kelompok),
        ];
    }

    public function kodeMenuTersedia(Pengguna $pengguna): array
    {
        abort_unless($pengguna->akunPegawai(), 403);
        $pengguna->loadMissing('daftarPeran.izin');

        return $this->itemTersedia($this->kelompokTersedia($pengguna))
            ->keys()
            ->values()
            ->all();
    }

    public function simpanAksesCepat(Pengguna $pengguna, array $kodeMenu): array
    {
        abort_unless($pengguna->akunPegawai(), 403);

        DB::transaction(function () use ($pengguna, $kodeMenu) {
            AksesCepatPengguna::query()
                ->where('pengguna_id', $pengguna->id)
                ->delete();

            foreach (array_values($kodeMenu) as $index => $kode) {
                AksesCepatPengguna::create([
                    'pengguna_id' => $pengguna->id,
                    'kode_menu' => $kode,
                    'urutan' => $index + 1,
                ]);
            }
        });

        return $this->siapkan($pengguna);
    }

    public function resetAksesCepat(Pengguna $pengguna): array
    {
        abort_unless($pengguna->akunPegawai(), 403);

        AksesCepatPengguna::query()
            ->where('pengguna_id', $pengguna->id)
            ->delete();

        return $this->siapkan($pengguna);
    }

    private function kelompokTersedia(Pengguna $pengguna): Collection
    {
        return collect(config('menu_mobile', []))
            ->map(fn (array $kelompok) => $this->siapkanKelompok($kelompok, $pengguna))
            ->filter(fn (array $kelompok) => count($kelompok['items']) > 0)
            ->values();
    }

    private function siapkanAksesCepat(Pengguna $pengguna, Collection $kelompok): array
    {
        $dapatDiatur = $pengguna->akunPegawai();
        if (! $dapatDiatur) {
            return [
                'dapat_diatur' => false,
                'dikustomisasi' => false,
                'maksimal' => 4,
                'sumber' => 'bawaan',
                'kode_menu' => [],
            ];
        }

        $tersedia = $this->itemTersedia($kelompok);
        $tersimpan = AksesCepatPengguna::query()
            ->where('pengguna_id', $pengguna->id)
            ->orderBy('urutan')
            ->pluck('kode_menu');
        $dikustomisasi = $tersimpan->isNotEmpty();
        $kode = $dikustomisasi
            ? $tersimpan->filter(fn (string $item) => $tersedia->has($item))->values()
            : $this->rekomendasiAksesCepat($pengguna, $tersedia);

        if ($kode->isEmpty()) {
            $kode = $this->rekomendasiAksesCepat($pengguna, $tersedia);
        }

        return [
            'dapat_diatur' => true,
            'dikustomisasi' => $dikustomisasi,
            'maksimal' => 4,
            'sumber' => $dikustomisasi ? 'pilihan_pengguna' : 'rekomendasi_peran',
            'kode_menu' => $kode->take(4)->values()->all(),
        ];
    }

    private function itemTersedia(Collection $kelompok): Collection
    {
        return $kelompok
            ->flatMap(fn (array $group) => $group['items'])
            ->filter(fn (array $item) => $item['status'] === 'tersedia' && filled($item['rute']))
            ->keyBy('kode');
    }

    private function rekomendasiAksesCepat(Pengguna $pengguna, Collection $tersedia): Collection
    {
        $peta = [
            'guru_mapel' => ['jadwal-mengajar-saya', 'input-nilai', 'perangkat-ajar-saya', 'pusat-cbt'],
            'wali_kelas' => ['kelas', 'rekap-presensi-siswa', 'rekap-nilai-rapor', 'laporan-siswa-kelas'],
            'guru_wali' => ['siswa-wali-saya', 'laporan-siswa-wali', 'rekap-poin-siswa', 'pendampingan-siswa'],
            'bk' => ['pemeriksaan-pengesahan', 'pendampingan-siswa', 'peringatan-dini-siswa', 'rekap-poin-siswa'],
            'pimpinan' => ['rekap-presensi-siswa', 'rekap-presensi-pegawai', 'rekap-nilai-rapor', 'dashboard-sarpras'],
            'wakil_pimpinan_kurikulum' => ['guru-mata-pelajaran', 'jadwal-pelajaran', 'rekap-nilai-rapor', 'pemeriksaan-perangkat-ajar'],
            'wakil_pimpinan_kesiswaan' => ['rekap-presensi-siswa', 'pemeriksaan-pengesahan', 'peringatan-dini-siswa', 'rekap-poin-siswa'],
            'wakil_pimpinan_sarana_prasarana' => ['dashboard-sarpras', 'inventaris-barang', 'peminjaman-barang', 'barang-datang'],
            'petugas_inventaris' => ['dashboard-sarpras', 'inventaris-barang', 'peminjaman-barang', 'barang-datang'],
            'satpam' => ['scan-presensi-siswa', 'scan-presensi-pegawai', 'laporkan-kejadian'],
        ];
        $peran = $pengguna->daftarPeran
            ->where('aktif', true)
            ->pluck('kode');
        $antreanPeran = $peran
            ->filter(fn (string $kode) => isset($peta[$kode]))
            ->map(fn (string $kode) => $peta[$kode])
            ->values();
        $prioritas = collect();

        for ($posisi = 0; $posisi < 4; $posisi++) {
            foreach ($antreanPeran as $antrean) {
                if (isset($antrean[$posisi])) {
                    $prioritas->push($antrean[$posisi]);
                }
            }
        }

        return $prioritas
            ->merge([
                'jadwal-mengajar-saya',
                'piket-saya',
                'laporkan-kejadian',
                'pengajuan-saya',
                'katalog-barang',
                'rekap-presensi-pegawai',
            ])
            ->merge($tersedia->keys())
            ->unique()
            ->filter(fn (string $kode) => $tersedia->has($kode))
            ->take(4)
            ->values();
    }

    private function siapkanKelompok(array $kelompok, Pengguna $pengguna): array
    {
        $items = collect($kelompok['items'] ?? [])
            ->filter(fn (array $item) => $this->bolehDilihat($item, $pengguna))
            ->map(fn (array $item) => $this->siapkanItem($item, $pengguna))
            ->values()
            ->all();

        return [
            'kode' => $kelompok['kode'],
            'label' => $kelompok['label'],
            'deskripsi' => $kelompok['deskripsi'],
            'ikon' => $kelompok['ikon'],
            'items' => $items,
        ];
    }

    private function bolehDilihat(array $item, Pengguna $pengguna): bool
    {
        if (($item['pegawai_only'] ?? false)
            && ! $pengguna->pegawai_id
            && ! (($item['administrator_allowed'] ?? false) && $pengguna->administrator())) {
            return false;
        }

        if (($item['administrator_only'] ?? false) && ! $pengguna->administrator()) {
            return false;
        }

        if (($item['siswa_only'] ?? false)
            && ! $pengguna->akunSiswa()) {
            return false;
        }

        if (($item['parent_only'] ?? false)
            && ! $pengguna->akunOrangTua()) {
            return false;
        }

        if (($item['siswa_or_parent_only'] ?? false)
            && ! ($pengguna->akunSiswa() || $pengguna->akunOrangTua())) {
            return false;
        }

        if (filled($item['peran_only'] ?? null)
            && ! $pengguna->memilikiPeran((array) $item['peran_only'])
            && ! (($item['administrator_allowed'] ?? false) && $pengguna->administrator())) {
            return false;
        }

        if (($item['scan_berhalangan_only'] ?? false)
            && ! $this->aksesBerhalangan->dapatMemindai($pengguna)) {
            return false;
        }

        if (($item['scan_ibadah_only'] ?? false)
            && ! $this->aksesIbadah->dapatMemindai($pengguna)) {
            return false;
        }

        if (($item['konfirmasi_berhalangan_only'] ?? false)
            && ! $this->aksesBerhalangan->dapatMengonfirmasi($pengguna)) {
            return false;
        }

        if (($item['rekap_ibadah_only'] ?? false)
            && ! $this->aksesIbadah->dapatMelihatRekap($pengguna)) {
            return false;
        }

        if (($item['ringkasan_ibadah_only'] ?? false)
            && ! $this->aksesIbadah->dapatMelihatRingkasanBulanan($pengguna)) {
            return false;
        }

        if (($item['pengawas_ujian_only'] ?? false)
            && ! $this->dapatMengawasiUjian($pengguna)) {
            return false;
        }

        if (($item['pelaksanaan_sanksi_only'] ?? false)
            && ! $this->aksesSanksi->dapatMembuka($pengguna)) {
            return false;
        }

        if (($item['peringatan_dini_only'] ?? false)
            && ! $this->aksesRekapPoin->dapatMembuka($pengguna)) {
            return false;
        }

        $izin = $item['izin'] ?? null;

        return blank($izin) || $pengguna->memilikiIzin($izin);
    }

    private function dapatMengawasiUjian(Pengguna $pengguna): bool
    {
        if (! $pengguna->pegawai_id) {
            return false;
        }

        return PengawasRuangUjianTerpusat::query()
            ->where(function ($query) use ($pengguna) {
                $query->where('pengawas_utama_pegawai_id', $pengguna->pegawai_id)
                    ->orWhere('pengawas_pendamping_pegawai_id', $pengguna->pegawai_id);
            })
            ->exists();
    }

    private function siapkanItem(array $item, Pengguna $pengguna): array
    {
        if ($pengguna->akunPegawai() && $pengguna->membatasiCakupanAbsensiPegawai()) {
            $item = match ($item['kode']) {
                'rekap-presensi-pegawai' => array_replace($item, [
                    'label' => 'Rekap Presensi Saya',
                    'deskripsi' => 'Lihat rekap dan rincian presensi pribadi Anda.',
                ]),
                'laporan-presensi-pegawai' => array_replace($item, [
                    'label' => 'Laporan Presensi Saya',
                    'deskripsi' => 'Lihat laporan presensi bulanan pribadi Anda.',
                ]),
                default => $item,
            };
        }

        return [
            'kode' => $item['kode'],
            'label' => $item['label'],
            'deskripsi' => $item['deskripsi'] ?? 'Modul '.$item['label'].' NUSA.',
            'inisial' => $item['inisial'],
            'subkelompok' => $item['subkelompok'] ?? null,
            'ikon' => $item['ikon'] ?? null,
            'status' => $item['status'] ?? 'segera_hadir',
            'rute' => $item['rute'] ?? null,
        ];
    }
}
