<?php

namespace App\Services\Mobile;

use App\Models\BuktiRuangUjianCbt;
use App\Models\JadwalUjianCbt;
use App\Models\KegiatanUjianCbt;
use App\Models\PengawasRuangUjianTerpusat;
use App\Models\Pengguna;
use App\Models\RuangUjianCbt;
use App\Models\SoalCbt;
use App\Models\UjianCbt;
use App\Services\Cbt\DaftarUjianSiswaService;
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

    public function __construct(private DaftarUjianSiswaService $daftarUjianSiswa) {}

    public function siapkan(Pengguna $pengguna): array
    {
        $pengguna->loadMissing('daftarPeran.izin', 'siswa');

        $dapatMengelola = $pengguna->memilikiIzin(self::IZIN_PENGELOLAAN);
        $tugasPengawas = $this->tugasPengawas($pengguna);
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
        return [
            'ringkasan' => [
                'soal_siap' => SoalCbt::query()->where('aktif', true)->where('status', 'siap')->count(),
                'kegiatan_terpusat' => KegiatanUjianCbt::query()->where('status', '!=', 'nonaktif')->count(),
                'asesmen_kelas' => UjianCbt::query()->where('alur', 'kelas')->where('status', '!=', 'nonaktif')->count(),
                'paket_terjadwal' => JadwalUjianCbt::query()
                    ->whereHas('ujianCbt', fn ($query) => $query->whereIn('status', ['terjadwal', 'berlangsung', 'selesai']))
                    ->count(),
            ],
            'alur' => [
                [
                    'kode' => 'asesmen-kelas',
                    'judul' => 'Asesmen Kelas',
                    'deskripsi' => 'Asesmen harian yang disiapkan guru untuk kelas dan mata pelajarannya.',
                    'warna' => 'biru',
                ],
                [
                    'kode' => 'ujian-terpusat',
                    'judul' => 'Ujian Terpusat',
                    'deskripsi' => 'Kegiatan ujian sekolah dengan jadwal, ruang, peserta, dan pengawas terpusat.',
                    'warna' => 'kuning',
                ],
            ],
            'alat' => collect([
                ['kode' => 'asesmen-kelas', 'label' => 'Asesmen Kelas', 'izin' => ['cbt.asesmen_kelola', 'cbt.kelola'], 'status' => 'tersedia', 'rute' => '/asesmen-kelas'],
                ['kode' => 'bank-soal', 'label' => 'Bank Soal', 'izin' => ['cbt.lihat', 'cbt.kelola', 'cbt.soal_kelola'], 'status' => 'tersedia', 'rute' => '/bank-soal'],
                ['kode' => 'ujian-terpusat', 'label' => 'Ujian Terpusat', 'izin' => ['cbt.panitia', 'cbt.terpusat_lihat', 'cbt.kelola']],
                ['kode' => 'paket-soal', 'label' => 'Paket Soal', 'izin' => ['cbt.soal_kelola', 'cbt.panitia', 'cbt.terpusat_lihat', 'cbt.kelola'], 'status' => 'tersedia', 'rute' => '/paket-soal'],
                ['kode' => 'presensi-ujian', 'label' => 'Presensi Ujian', 'izin' => ['cbt.presensi', 'cbt.kelola']],
            ])->filter(fn (array $alat) => $pengguna->memilikiIzin($alat['izin']))
                ->map(fn (array $alat) => [
                    'kode' => $alat['kode'],
                    'label' => $alat['label'],
                    'status' => $alat['status'] ?? 'fondasi',
                    'rute' => $alat['rute'] ?? null,
                ])->values(),
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
            ];
        });

        $perluBukti = ['belum_diunggah', 'sebagian', 'siap_dikirim', 'perlu_diulang'];

        return [
            'ringkasan' => [
                'jumlah' => $items->count(),
                'hari_ini' => $items->where('tanggal', now()->toDateString())->count(),
                'perlu_bukti' => $items->whereIn('status_bukti', $perluBukti)->count(),
            ],
            'items' => $items->take(8)->values(),
            'operasional_native' => false,
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
            'items' => collect($data['daftarUjian'])->take(8)->map(function (array $item) {
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
                    'waktu_mulai' => $item['waktu_mulai']?->toISOString(),
                    'waktu_selesai' => $item['waktu_selesai']?->toISOString(),
                    'waktu' => $jadwal?->labelWaktu(),
                    'tanggal' => $jadwal?->tanggal?->toDateString(),
                    'durasi_menit' => (int) $ujian->durasi_menit,
                    'nomor_peserta' => $peserta->nomor_peserta,
                ];
            })->values(),
            'pengerjaan_native' => false,
        ];
    }
}
