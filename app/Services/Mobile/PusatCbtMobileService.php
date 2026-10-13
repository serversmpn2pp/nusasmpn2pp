<?php

namespace App\Services\Mobile;

use App\Models\BuktiRuangUjianCbt;
use App\Models\PengawasRuangUjianTerpusat;
use App\Models\Pengguna;
use App\Models\RuangUjianCbt;
use App\Services\Cbt\DaftarUjianSiswaService;
use App\Services\Cbt\RingkasanPusatCbtService;
use Illuminate\Support\Collection;

class PusatCbtMobileService
{
    private const IZIN_PENGELOLAAN = [
        'cbt.lihat',
        'cbt.kelola',
        'cbt.soal_kelola',
        'cbt.presensi',
        'cbt.asesmen_kelola',
        'cbt.panitia',
        'cbt.terpusat_lihat',
    ];

    public function __construct(
        private DaftarUjianSiswaService $daftarUjianSiswa,
        private RingkasanPusatCbtService $ringkasanPusatCbt,
    ) {}

    public function siapkan(Pengguna $pengguna): array
    {
        $pengguna->loadMissing('daftarPeran.izin', 'siswa');

        $akunStaf = ! $pengguna->akunSiswa() && ! $pengguna->akunOrangTua();
        $dapatMengelola = $akunStaf && $pengguna->memilikiIzin(self::IZIN_PENGELOLAAN);
        $tugasPengawas = $akunStaf ? $this->tugasPengawas($pengguna) : collect();
        $akunSiswa = $pengguna->akunSiswa() && $pengguna->siswa;

        abort_unless($dapatMengelola || $tugasPengawas->isNotEmpty() || $akunSiswa, 403);

        return [
            'dihasilkan_pada' => now()->toISOString(),
            'akses' => [
                'dapat_mengelola' => $dapatMengelola,
                'memiliki_tugas_pengawas' => $tugasPengawas->isNotEmpty(),
                'akun_siswa' => (bool) $akunSiswa,
            ],
            'pengelolaan' => $dapatMengelola ? $this->pengelolaan($pengguna) : null,
            'pengawas' => $tugasPengawas->isNotEmpty()
                ? $this->ringkasTugasPengawas($pengguna, $tugasPengawas)
                : null,
            'siswa' => $akunSiswa ? $this->ringkasUjianSiswa($pengguna) : null,
        ];
    }

    private function pengelolaan(Pengguna $pengguna): array
    {
        $alat = collect(config('cbt_mobile', []))
            ->filter(fn (array $item) => $pengguna->memilikiIzin($item['izin']))
            ->map(fn (array $item) => [
                'kode' => $item['kode'],
                'label' => $item['label'],
                'deskripsi' => $item['deskripsi'],
                'kelompok' => $item['kelompok'],
                'status' => $item['status'],
                'rute' => $item['rute'],
                'api' => $item['api'],
            ])->values();

        $langkah = function (array $kode) use ($alat): array {
            $tersedia = $alat->keyBy('kode');

            return collect($kode)->map(fn (string $item, int $index) => $tersedia->has($item)
                ? ['nomor' => $index + 1, ...$tersedia->get($item)]
                : null)->filter()->values()->all();
        };

        return [
            'ringkasan' => $this->ringkasanPusatCbt->untuk($pengguna),
            'alur' => collect([
                [
                    'kode' => 'ujian-terpusat',
                    'judul' => 'Ujian Terpusat',
                    'deskripsi' => 'Persiapan, pelaksanaan, hingga pengolahan hasil ujian sekolah.',
                    'warna' => 'kuning',
                    'langkah' => $langkah(['ujian-terpusat', 'pelaksanaan-ujian-terpusat', 'hasil-ujian-terpusat']),
                ],
                [
                    'kode' => 'asesmen-kelas',
                    'judul' => 'Asesmen Kelas',
                    'deskripsi' => 'Bank soal dan asesmen harian untuk kelas yang Anda ajar.',
                    'warna' => 'biru',
                    'langkah' => $langkah(['bank-soal', 'asesmen-kelas']),
                ],
            ])->filter(fn (array $alur) => count($alur['langkah']) > 0)->values(),
            'alat' => $alat,
        ];
    }

    private function tugasPengawas(Pengguna $pengguna): Collection
    {
        if (! $pengguna->pegawai_id) {
            return collect();
        }

        return PengawasRuangUjianTerpusat::query()
            ->where(function ($query) use ($pengguna) {
                $query->where('pengawas_utama_pegawai_id', $pengguna->pegawai_id)
                    ->orWhere('pengawas_pendamping_pegawai_id', $pengguna->pegawai_id);
            })
            ->with([
                'jadwalUjianCbt.kegiatanUjianCbt.jenisUjianCbt',
                'jadwalUjianCbt.mataPelajaran',
                'ruangKegiatanUjianCbt',
                'pengawasUtama',
                'pengawasPendamping',
            ])
            ->get()
            ->sortBy(fn (PengawasRuangUjianTerpusat $tugas) => sprintf(
                '%s %s %03d',
                $tugas->jadwalUjianCbt?->tanggal?->format('Y-m-d') ?? '9999-12-31',
                substr((string) $tugas->jadwalUjianCbt?->waktu_mulai, 0, 5),
                $tugas->ruangKegiatanUjianCbt?->urutan ?? 999,
            ))
            ->values();
    }

    private function ringkasTugasPengawas(Pengguna $pengguna, Collection $tugas): array
    {
        $ruangOperasional = RuangUjianCbt::query()
            ->whereIn('jadwal_ujian_cbt_id', $tugas->pluck('jadwal_ujian_cbt_id')->filter())
            ->whereIn('ruang_kegiatan_ujian_cbt_id', $tugas->pluck('ruang_kegiatan_ujian_cbt_id')->filter())
            ->withCount([
                'pesertaUjianCbt',
                'buktiRuangUjianCbt as bukti_daftar_hadir_count' => fn ($query) => $query->where('jenis', BuktiRuangUjianCbt::JENIS_DAFTAR_HADIR),
                'buktiRuangUjianCbt as bukti_berita_acara_count' => fn ($query) => $query->where('jenis', BuktiRuangUjianCbt::JENIS_BERITA_ACARA),
            ])
            ->get()
            ->keyBy(fn (RuangUjianCbt $ruang) => $ruang->jadwal_ujian_cbt_id.'-'.$ruang->ruang_kegiatan_ujian_cbt_id);

        $items = $tugas->map(function (PengawasRuangUjianTerpusat $tugas) use ($pengguna, $ruangOperasional) {
            $jadwal = $tugas->jadwalUjianCbt;
            $ruang = $ruangOperasional->get($tugas->jadwal_ujian_cbt_id.'-'.$tugas->ruang_kegiatan_ujian_cbt_id);

            return [
                'id' => (int) $tugas->id,
                'ruang_id' => $ruang ? (int) $ruang->id : null,
                'dapat_dibuka' => (bool) $ruang,
                'kegiatan' => $jadwal?->kegiatanUjianCbt?->nama ?? 'Ujian Terpusat',
                'jenis_ujian' => $jadwal?->kegiatanUjianCbt?->jenisUjianCbt?->nama,
                'mata_pelajaran' => $jadwal?->mataPelajaran?->nama ?? 'Mata pelajaran belum ditentukan',
                'tanggal' => $jadwal?->tanggal?->toDateString(),
                'waktu' => $jadwal?->labelWaktu(),
                'ruang' => $tugas->ruangKegiatanUjianCbt?->nama ?? 'Ruang belum ditentukan',
                'peran' => (int) $tugas->pengawas_utama_pegawai_id === (int) $pengguna->pegawai_id
                    ? 'Pengawas utama'
                    : 'Pengawas pendamping',
                'status_bukti' => $ruang?->status_bukti,
                'label_status_bukti' => $ruang?->labelStatusBukti() ?? 'Ruang belum disiapkan',
                'jumlah_peserta' => (int) ($ruang?->peserta_ujian_cbt_count ?? 0),
                'status' => $ruang?->status,
                'label_status' => $ruang?->labelStatus() ?? 'Paket belum diterbitkan',
            ];
        });

        $perluBukti = ['belum_diunggah', 'sebagian', 'siap_dikirim', 'perlu_diulang'];

        return [
            'ringkasan' => [
                'jumlah' => $items->count(),
                'hari_ini' => $items->where('tanggal', now()->toDateString())->count(),
                'perlu_bukti' => $items->whereIn('status_bukti', $perluBukti)->count(),
            ],
            'items' => $items->values(),
            'operasional_native' => true,
        ];
    }

    private function ringkasUjianSiswa(Pengguna $pengguna): array
    {
        $data = $this->daftarUjianSiswa->siapkan($pengguna->siswa);

        return [
            'ringkasan' => [
                'aktif' => (int) $data['ringkasanUjian']['aktif'],
                'akan_datang' => (int) $data['ringkasanUjian']['akan_datang'],
                'selesai' => (int) $data['ringkasanUjian']['selesai'],
                'total' => (int) $data['ringkasanUjian']['total'],
            ],
            'items' => collect($data['daftarUjian'])->map(function (array $item) {
                $peserta = $item['peserta'];
                $ujian = $item['ujian'];
                $jadwal = $item['jadwal'];

                return [
                    'id' => (int) $peserta->id,
                    'ujian_id' => (int) $ujian->id,
                    'nama' => $ujian->nama,
                    'kode' => $ujian->kode,
                    'jenis_ujian' => $ujian->jenisUjianCbt?->nama,
                    'mata_pelajaran' => $ujian->mataPelajaran?->nama ?? 'Mata pelajaran belum ditentukan',
                    'kelompok' => $item['kelompok'],
                    'label_status' => $item['label_status'],
                    'nada_status' => $item['nada_status'],
                    'status_pengerjaan' => $peserta->status,
                    'waktu_mulai' => $item['waktu_mulai']?->toISOString(),
                    'waktu_selesai' => $item['waktu_selesai']?->toISOString(),
                    'waktu' => $jadwal?->labelWaktu(),
                    'tanggal' => $jadwal?->tanggal?->toDateString(),
                    'durasi_menit' => (int) $ujian->durasi_menit,
                    'nomor_peserta' => $peserta->nomor_peserta,
                    'memerlukan_token' => (bool) $ujian->jenisUjianCbt?->memerlukan_token
                        && $peserta->status !== 'sedang_mengerjakan',
                    'dapat_dibuka' => in_array($peserta->status, ['aktif', 'sedang_mengerjakan', 'selesai'], true),
                ];
            })->values(),
            'pengerjaan_native' => true,
        ];
    }
}
