<?php

namespace App\Http\Controllers;

use App\Models\JadwalUjianCbt;
use App\Models\KegiatanUjianCbt;
use App\Models\PesertaUjianCbt;
use App\Models\RuangUjianCbt;
use App\Models\TahunPelajaran;
use App\Models\UjianCbt;
use Illuminate\Http\Request;

class StatusKelengkapanPanitiaCbtController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'tahun_pelajaran_id' => ['nullable', 'integer', 'exists:tahun_pelajaran,id'],
            'kegiatan_ujian_cbt_id' => ['nullable', 'integer', 'exists:kegiatan_ujian_cbt,id'],
        ]);

        $tahunPelajaranId = $data['tahun_pelajaran_id'] ?? null;
        $kegiatanUjianCbtId = $data['kegiatan_ujian_cbt_id'] ?? null;

        $daftarKegiatan = KegiatanUjianCbt::query()
            ->with(['jenisUjianCbt', 'tahunPelajaran'])
            ->withCount('jadwalUjianCbt')
            ->when($tahunPelajaranId, fn ($query, $id) => $query->where('tahun_pelajaran_id', $id))
            ->where('status', '!=', 'nonaktif')
            ->orderByDesc('tanggal_mulai')
            ->orderByDesc('id')
            ->get();

        $kegiatanTerpilih = $kegiatanUjianCbtId
            ? $daftarKegiatan->firstWhere('id', (int) $kegiatanUjianCbtId)
            : $daftarKegiatan->first();

        if ($kegiatanTerpilih) {
            $kegiatanTerpilih->load([
                'jenisUjianCbt',
                'tahunPelajaran',
                'jadwalUjianCbt' => fn ($query) => $query
                    ->with(['mataPelajaran', 'kelas', 'ujianCbt.mataPelajaran'])
                    ->orderBy('tanggal')
                    ->orderBy('waktu_mulai')
                    ->orderBy('urutan')
                    ->orderBy('id'),
            ]);
        }

        $statusJadwal = $kegiatanTerpilih
            ? $kegiatanTerpilih->jadwalUjianCbt
                ->map(fn (JadwalUjianCbt $jadwal) => $this->statusJadwal($jadwal))
                ->values()
            : collect();

        $jumlahJadwal = $statusJadwal->count();
        $jumlahLengkap = $statusJadwal->where('siap_panitia', true)->count();
        $rataKelengkapan = $jumlahJadwal
            ? round((float) $statusJadwal->avg('persentase'), 1)
            : 0;

        return view('status-kelengkapan-panitia-cbt.index', [
            'daftarKegiatan' => $daftarKegiatan,
            'kegiatanTerpilih' => $kegiatanTerpilih,
            'tahunPelajaranId' => $tahunPelajaranId,
            'statusJadwal' => $statusJadwal,
            'daftarTahunPelajaran' => TahunPelajaran::query()
                ->orderByDesc('aktif')
                ->orderByDesc('tanggal_mulai')
                ->get(),
            'ringkasan' => [
                'jadwal' => $jumlahJadwal,
                'lengkap' => $jumlahLengkap,
                'perlu_dilengkapi' => max(0, $jumlahJadwal - $jumlahLengkap),
                'rata_kelengkapan' => $rataKelengkapan,
            ],
        ]);
    }

    private function statusJadwal(JadwalUjianCbt $jadwal): array
    {
        $ujianCbt = $jadwal->ujianCbt;
        $kelasJadwalCount = $jadwal->kelas->count();
        $statistik = $ujianCbt ? $this->statistikUjian($ujianCbt, $jadwal) : $this->statistikKosong();

        $pemeriksaan = collect([
            [
                'label' => 'Jadwal ujian dikunci',
                'beres' => $jadwal->terkunci(),
                'wajib' => true,
                'detail' => $jadwal->terkunci()
                    ? 'Jadwal final dan terkunci.'
                    : 'Kunci jadwal setelah tanggal, jam, mapel, dan kelas peserta final.',
                'url' => route('jadwal-ujian-cbt.index', ['kegiatan_ujian_cbt_id' => $jadwal->kegiatan_ujian_cbt_id]),
            ],
            [
                'label' => 'Paket CBT terhubung',
                'beres' => (bool) $ujianCbt,
                'wajib' => true,
                'detail' => $ujianCbt
                    ? "{$ujianCbt->kode} - {$ujianCbt->nama}"
                    : 'Hubungkan jadwal ini ke paket CBT.',
                'url' => $ujianCbt
                    ? route('ujian-cbt.show', $ujianCbt)
                    : route('jadwal-ujian-cbt.index', ['kegiatan_ujian_cbt_id' => $jadwal->kegiatan_ujian_cbt_id]),
            ],
            [
                'label' => 'Kelas peserta jadwal',
                'beres' => $kelasJadwalCount > 0,
                'wajib' => true,
                'detail' => $kelasJadwalCount > 0
                    ? $jadwal->kelas->pluck('nama')->implode(', ')
                    : 'Pilih minimal satu kelas peserta.',
                'url' => route('jadwal-ujian-cbt.index', ['kegiatan_ujian_cbt_id' => $jadwal->kegiatan_ujian_cbt_id]),
            ],
            [
                'label' => 'Soal sesuai target',
                'beres' => $statistik['soal_cukup'],
                'wajib' => true,
                'detail' => $ujianCbt
                    ? "{$statistik['soal']} / {$statistik['target_soal']} soal"
                    : 'Paket CBT belum terhubung.',
                'url' => $ujianCbt ? route('ujian-cbt.soal.edit', $ujianCbt) : null,
            ],
            [
                'label' => 'Sesi ujian tersedia',
                'beres' => $statistik['sesi'] > 0,
                'wajib' => true,
                'detail' => $ujianCbt
                    ? "{$statistik['sesi']} sesi aktif"
                    : 'Paket CBT belum terhubung.',
                'url' => $ujianCbt ? route('ujian-cbt.peserta.index', $ujianCbt) : null,
            ],
            [
                'label' => 'Peserta dan akun CBT',
                'beres' => $statistik['peserta'] > 0 && $statistik['akun'] >= $statistik['peserta'],
                'wajib' => true,
                'detail' => $ujianCbt
                    ? "{$statistik['akun']} / {$statistik['peserta']} peserta punya akun"
                    : 'Paket CBT belum terhubung.',
                'url' => $ujianCbt ? route('ujian-cbt.peserta.index', $ujianCbt) : null,
            ],
            [
                'label' => 'Ruang ujian tersedia',
                'beres' => $statistik['ruang'] > 0 && $statistik['kapasitas'] >= max(1, $statistik['peserta']),
                'wajib' => true,
                'detail' => $ujianCbt
                    ? "{$statistik['ruang']} ruang, kapasitas {$statistik['kapasitas']} untuk {$statistik['peserta']} peserta"
                    : 'Paket CBT belum terhubung.',
                'url' => $ujianCbt ? route('ujian-cbt.ruang.index', [$ujianCbt, 'jadwal_ujian_cbt_id' => $jadwal->id]) : null,
            ],
            [
                'label' => 'Nomor meja lengkap',
                'beres' => $statistik['peserta'] > 0 && $statistik['ditempatkan'] >= $statistik['peserta'],
                'wajib' => true,
                'detail' => $ujianCbt
                    ? "{$statistik['ditempatkan']} / {$statistik['peserta']} peserta sudah punya ruang dan nomor meja"
                    : 'Paket CBT belum terhubung.',
                'url' => $ujianCbt ? route('ujian-cbt.ruang.index', [$ujianCbt, 'jadwal_ujian_cbt_id' => $jadwal->id]) : null,
            ],
            [
                'label' => 'Pengawas ruang',
                'beres' => $statistik['ruang'] > 0 && $statistik['pengawas'] >= $statistik['ruang'],
                'wajib' => true,
                'detail' => $ujianCbt
                    ? "{$statistik['pengawas']} / {$statistik['ruang']} ruang punya pengawas utama"
                    : 'Paket CBT belum terhubung.',
                'url' => $ujianCbt ? route('ujian-cbt.ruang.index', [$ujianCbt, 'jadwal_ujian_cbt_id' => $jadwal->id]) : null,
            ],
            [
                'label' => 'Ruang dikunci',
                'beres' => $statistik['ruang'] > 0 && $statistik['ruang_terkunci'] >= $statistik['ruang'],
                'wajib' => true,
                'detail' => $ujianCbt
                    ? "{$statistik['ruang_terkunci']} / {$statistik['ruang']} ruang terkunci"
                    : 'Paket CBT belum terhubung.',
                'url' => $ujianCbt ? route('ujian-cbt.ruang.index', [$ujianCbt, 'jadwal_ujian_cbt_id' => $jadwal->id]) : null,
            ],
            [
                'label' => 'Bukti daftar hadir dan BA',
                'beres' => $statistik['ruang'] > 0 && $statistik['bukti'] >= $statistik['ruang'],
                'wajib' => false,
                'detail' => $ujianCbt
                    ? "{$statistik['bukti']} / {$statistik['ruang']} ruang sudah unggah bukti"
                    : 'Diunggah setelah ujian selesai.',
                'url' => $ujianCbt ? route('ujian-cbt.ruang.index', [$ujianCbt, 'jadwal_ujian_cbt_id' => $jadwal->id]) : null,
            ],
        ]);

        $wajib = $pemeriksaan->where('wajib', true);
        $jumlahWajib = $wajib->count();
        $jumlahBeres = $wajib->where('beres', true)->count();
        $persentase = $jumlahWajib
            ? round(($jumlahBeres / $jumlahWajib) * 100)
            : 0;

        return [
            'jadwal' => $jadwal,
            'ujianCbt' => $ujianCbt,
            'pemeriksaan' => $pemeriksaan,
            'statistik' => $statistik,
            'jumlah_wajib' => $jumlahWajib,
            'jumlah_beres' => $jumlahBeres,
            'persentase' => $persentase,
            'siap_panitia' => $jumlahWajib > 0 && $jumlahBeres === $jumlahWajib,
        ];
    }

    private function statistikUjian(UjianCbt $ujianCbt, JadwalUjianCbt $jadwal): array
    {
        $targetSoal = (int) $ujianCbt->jumlah_soal;
        $jumlahSoal = $ujianCbt->soalUjianCbt()->count();
        $jumlahPeserta = $ujianCbt->pesertaUjianCbt()
            ->where('status', '!=', 'nonaktif')
            ->count();
        $ruang = $ujianCbt->ruangUjianCbt()
            ->where('jadwal_ujian_cbt_id', $jadwal->id)
            ->get();
        $ruangIds = $ruang->pluck('id');
        $jumlahDitempatkan = $ruangIds->isEmpty()
            ? 0
            : PesertaUjianCbt::query()
                ->where('ujian_cbt_id', $ujianCbt->id)
                ->where('status', '!=', 'nonaktif')
                ->whereIn('ruang_ujian_cbt_id', $ruangIds)
                ->whereNotNull('nomor_meja')
                ->count();

        return [
            'target_soal' => $targetSoal,
            'soal' => $jumlahSoal,
            'soal_cukup' => $targetSoal > 0 && $jumlahSoal >= $targetSoal,
            'kelas_paket' => $ujianCbt->kelasUjianCbt()->count(),
            'sesi' => $ujianCbt->sesiUjianCbt()->where('status', 'aktif')->count(),
            'peserta' => $jumlahPeserta,
            'akun' => $ujianCbt->pesertaUjianCbt()
                ->where('status', '!=', 'nonaktif')
                ->whereNotNull('akun_peserta_cbt_id')
                ->count(),
            'ruang' => $ruang->count(),
            'kapasitas' => (int) $ruang->sum(fn (RuangUjianCbt $item) => $item->kapasitas ?: 0),
            'ditempatkan' => $jumlahDitempatkan,
            'pengawas' => $ruang->whereNotNull('pengawas_utama_pegawai_id')->count(),
            'ruang_terkunci' => $ruang->filter(fn (RuangUjianCbt $item) => $item->terkunci())->count(),
            'bukti' => $ruang
                ->filter(fn (RuangUjianCbt $item) => filled($item->bukti_daftar_hadir_lokasi_file) && filled($item->bukti_berita_acara_lokasi_file))
                ->count(),
        ];
    }

    private function statistikKosong(): array
    {
        return [
            'target_soal' => 0,
            'soal' => 0,
            'soal_cukup' => false,
            'kelas_paket' => 0,
            'sesi' => 0,
            'peserta' => 0,
            'akun' => 0,
            'ruang' => 0,
            'kapasitas' => 0,
            'ditempatkan' => 0,
            'pengawas' => 0,
            'ruang_terkunci' => 0,
            'bukti' => 0,
        ];
    }
}
