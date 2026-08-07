<?php

namespace App\Http\Controllers;

use App\Models\LaporanPembinaanSiswa;
use App\Models\Pengguna;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ProgressKasusSiswaController extends Controller
{
    public function index(Request $request)
    {
        $siswa = $this->siswaDariPengguna($request->user());
        $tahunPelajaranAktif = TahunPelajaran::query()
            ->where('aktif', true)
            ->orderByDesc('tanggal_mulai')
            ->first();

        $query = LaporanPembinaanSiswa::query()
            ->with([
                'tahunPelajaran:id,nama',
                'kelas:id,nama',
                'kategoriPembinaanSiswa:id,nama',
            ])
            ->where('siswa_id', $siswa?->id ?: 0);

        $ringkasan = [
            'semua' => (clone $query)->count(),
            'diproses' => (clone $query)
                ->where(function ($query) {
                    $query->whereIn('status_verifikasi', [
                        'diajukan',
                        'pemeriksaan_bk',
                        'perlu_klarifikasi',
                        'dikembalikan_bk',
                        'menunggu_pengesahan_wakil',
                        'menunggu_persetujuan',
                        'disetujui_sebagian',
                        'perlu_musyawarah',
                    ])->orWhere(function ($query) {
                        $query->where('status_verifikasi', 'tidak_perlu')
                            ->whereNotIn('status', ['selesai', 'dibatalkan']);
                    });
                })
                ->count(),
            'pembinaan' => (clone $query)
                ->where('status_verifikasi', 'ditetapkan_pembinaan')
                ->count(),
            'poin_resmi' => (int) (clone $query)
                ->where('status_verifikasi', 'disahkan')
                ->sum('total_poin'),
        ];

        $laporan = $query
            ->latest('tanggal_kejadian')
            ->latest('id')
            ->paginate(10);
        $presentasiStatus = $laporan->getCollection()
            ->mapWithKeys(fn (LaporanPembinaanSiswa $item) => [
                $item->id => $this->presentasiStatus($item),
            ]);

        return view('progress-kasus-siswa.index', compact(
            'siswa',
            'tahunPelajaranAktif',
            'ringkasan',
            'laporan',
            'presentasiStatus',
        ));
    }

    public function show(Request $request, LaporanPembinaanSiswa $laporanPembinaanSiswa)
    {
        $siswa = $this->siswaDariPengguna($request->user());
        abort_unless($siswa && (int) $laporanPembinaanSiswa->siswa_id === (int) $siswa->id, 404);

        $laporanPembinaanSiswa->load([
            'tahunPelajaran:id,nama',
            'kelas:id,nama',
            'kategoriPembinaanSiswa:id,nama',
            'butirPelanggaranLaporan' => fn ($query) => $query->orderBy('id'),
            'riwayatProsesPembinaanSiswa' => fn ($query) => $query
                ->oldest('terjadi_pada')
                ->oldest('id'),
            'tindakLanjutPembinaanSiswa' => fn ($query) => $query
                ->oldest('tanggal_tindak_lanjut')
                ->oldest('waktu_tindak_lanjut')
                ->oldest('id'),
        ]);

        return view('progress-kasus-siswa.show', [
            'siswa' => $siswa,
            'laporan' => $laporanPembinaanSiswa,
            'presentasiStatus' => $this->presentasiStatus($laporanPembinaanSiswa),
            'linimasa' => $this->linimasaPublik($laporanPembinaanSiswa),
        ]);
    }

    private function siswaDariPengguna(?Pengguna $pengguna): ?Siswa
    {
        abort_unless($pengguna?->akunSiswa() || $pengguna?->memilikiPeran('siswa'), 403);

        return $pengguna->siswa()->first();
    }

    private function presentasiStatus(LaporanPembinaanSiswa $laporan): array
    {
        $presentasi = match ($laporan->status_verifikasi) {
            'diajukan' => [
                'label' => 'Laporan diterima sekolah',
                'deskripsi' => 'Laporan telah tercatat dan menunggu pemeriksaan BK.',
                'warna' => 'warning',
                'langkah' => 1,
                'final' => false,
            ],
            'pemeriksaan_bk' => [
                'label' => 'Sedang diperiksa BK',
                'deskripsi' => 'BK sedang memeriksa keterangan dan informasi yang tersedia.',
                'warna' => 'info',
                'langkah' => 2,
                'final' => false,
            ],
            'perlu_klarifikasi' => [
                'label' => 'Klarifikasi sedang dilengkapi',
                'deskripsi' => 'BK memerlukan informasi tambahan sebelum memberikan keputusan.',
                'warna' => 'warning',
                'langkah' => 2,
                'final' => false,
            ],
            'dikembalikan_bk' => [
                'label' => 'Sedang diperiksa kembali oleh BK',
                'deskripsi' => 'Rekomendasi sedang diperbaiki atau dilengkapi oleh BK.',
                'warna' => 'info',
                'langkah' => 2,
                'final' => false,
            ],
            'menunggu_pengesahan_wakil', 'menunggu_persetujuan', 'disetujui_sebagian', 'perlu_musyawarah' => [
                'label' => 'Menunggu pengesahan Wakil Kesiswaan',
                'deskripsi' => 'Rekomendasi poin belum menjadi keputusan resmi.',
                'warna' => 'warning',
                'langkah' => 3,
                'final' => false,
            ],
            'ditetapkan_pembinaan' => [
                'label' => 'Pembinaan ditetapkan',
                'deskripsi' => 'BK menetapkan pembinaan tanpa penambahan poin.',
                'warna' => 'success',
                'langkah' => 4,
                'final' => true,
            ],
            'disahkan' => [
                'label' => 'Pelanggaran berpoin disahkan',
                'deskripsi' => 'Poin telah disahkan oleh Wakil Kesiswaan dan resmi tercatat.',
                'warna' => 'danger',
                'langkah' => 4,
                'final' => true,
            ],
            'tidak_terbukti' => [
                'label' => 'Selesai: tidak terbukti',
                'deskripsi' => 'Pemeriksaan selesai dan laporan tidak menambah poin.',
                'warna' => 'success',
                'langkah' => 4,
                'final' => true,
            ],
            'dibatalkan' => [
                'label' => 'Laporan dibatalkan',
                'deskripsi' => 'Laporan ditutup dan tidak menambah poin.',
                'warna' => 'neutral',
                'langkah' => 4,
                'final' => true,
            ],
            'tidak_perlu' => [
                'label' => $laporan->jenis_laporan === 'pembinaan' ? 'Pembinaan dicatat' : 'Sedang ditangani',
                'deskripsi' => 'Penanganan dicatat oleh sekolah tanpa proses pengesahan poin.',
                'warna' => 'info',
                'langkah' => $laporan->status === 'selesai' ? 4 : 3,
                'final' => $laporan->status === 'selesai',
            ],
            default => [
                'label' => 'Sedang ditangani sekolah',
                'deskripsi' => 'Sekolah sedang melanjutkan penanganan laporan ini.',
                'warna' => 'info',
                'langkah' => 2,
                'final' => false,
            ],
        };

        $presentasi['status_penanganan'] = $laporan->labelStatus();

        return $presentasi;
    }

    private function linimasaPublik(LaporanPembinaanSiswa $laporan): Collection
    {
        $linimasa = collect();

        foreach ($laporan->riwayatProsesPembinaanSiswa as $riwayat) {
            $isi = $this->presentasiRiwayat($riwayat->kode_kegiatan, $riwayat->data ?? []);
            if (! $isi) {
                continue;
            }

            $linimasa->push([
                'judul' => $isi['judul'],
                'deskripsi' => $isi['deskripsi'],
                'tanggal' => $riwayat->terjadi_pada ?: $riwayat->created_at,
            ]);
        }

        if ($linimasa->isEmpty()) {
            $linimasa->push([
                'judul' => 'Laporan diterima sekolah',
                'deskripsi' => 'Laporan telah tercatat dalam sistem NUSA.',
                'tanggal' => $laporan->created_at,
            ]);
        }

        foreach ($laporan->tindakLanjutPembinaanSiswa as $tindakLanjut) {
            $linimasa->push([
                'judul' => 'Tindak lanjut: '.$tindakLanjut->labelJenis(),
                'deskripsi' => 'Status penanganan: '.$tindakLanjut->labelStatusLaporan().'.',
                'tanggal' => $tindakLanjut->tanggal_tindak_lanjut,
            ]);
        }

        return $linimasa
            ->sortBy(fn (array $item) => $item['tanggal']?->timestamp ?? 0)
            ->unique(fn (array $item) => $item['judul'].'|'.($item['tanggal']?->toDateString() ?? '-'))
            ->values();
    }

    private function presentasiRiwayat(string $kode, array $data): ?array
    {
        if ($kode === 'keputusan_bk') {
            return match ($data['hasil'] ?? null) {
                'sanksi_poin' => [
                    'judul' => 'BK merekomendasikan pelanggaran berpoin',
                    'deskripsi' => 'Rekomendasi diteruskan kepada Wakil Kesiswaan untuk diperiksa.',
                ],
                'pembinaan' => [
                    'judul' => 'BK menetapkan pembinaan',
                    'deskripsi' => 'Kejadian ditangani melalui pembinaan tanpa penambahan poin.',
                ],
                'tidak_terbukti' => [
                    'judul' => 'Pemeriksaan BK selesai',
                    'deskripsi' => 'Laporan dinyatakan tidak terbukti dan tidak menambah poin.',
                ],
                default => [
                    'judul' => 'BK meminta klarifikasi tambahan',
                    'deskripsi' => 'Informasi tambahan diperlukan sebelum keputusan diberikan.',
                ],
            };
        }

        return match ($kode) {
            'laporan_dibuat', 'laporan_otomatis_absensi' => [
                'judul' => 'Laporan diterima sekolah',
                'deskripsi' => 'Laporan telah tercatat dan masuk ke proses pemeriksaan.',
            ],
            'klarifikasi_siswa' => [
                'judul' => 'Klarifikasi siswa dicatat',
                'deskripsi' => 'Keterangan siswa telah masuk sebagai bagian dari pemeriksaan.',
            ],
            'pembinaan_ditetapkan' => [
                'judul' => 'Pembinaan tanpa poin ditetapkan',
                'deskripsi' => 'BK menetapkan kejadian ditangani melalui pembinaan.',
            ],
            'poin_disahkan', 'poin_disahkan_wakil' => [
                'judul' => 'Poin disahkan Wakil Kesiswaan',
                'deskripsi' => 'Poin telah menjadi catatan resmi siswa.',
            ],
            'dikembalikan_wakil' => [
                'judul' => 'Rekomendasi dikembalikan kepada BK',
                'deskripsi' => 'BK melanjutkan pemeriksaan dan melengkapi rekomendasi.',
            ],
            'sinkronisasi_koreksi_absensi' => [
                'judul' => 'Data keterlambatan diperbarui',
                'deskripsi' => 'Laporan disesuaikan dengan koreksi data absensi.',
            ],
            'laporan_dibatalkan', 'laporan_otomatis_dibatalkan' => [
                'judul' => 'Laporan ditutup',
                'deskripsi' => 'Laporan tidak dilanjutkan dan tidak menambah poin.',
            ],
            default => null,
        };
    }
}
