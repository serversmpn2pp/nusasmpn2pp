<?php

namespace App\Services\Mobile;

use App\Models\KegiatanUjianCbt;
use App\Models\Kelas;
use App\Models\Pengguna;
use App\Services\Nilai\LampiranPerilakuStsService;
use App\Services\Nilai\LegerStsService;
use App\Services\Nilai\MapelRaporStsService;
use App\Services\Nilai\RaporStsService;

class StsMobileService
{
    public function __construct(private RaporStsService $rapor, private LegerStsService $leger) {}

    public function pilihan(Pengguna $pengguna, array $filter): array
    {
        abort_unless(($pengguna->akunPegawai() || $pengguna->administrator()) && RaporStsService::dapatMengakses($pengguna), 403);
        $kegiatanList = KegiatanUjianCbt::whereHas('jenisUjianCbt', fn ($q) => $q->where('kode', 'STS'))
            ->whereIn('tahun_pelajaran_id', $this->rapor->kelasDalamCakupan($pengguna)->select('tahun_pelajaran_id'))
            ->with('tahunPelajaran')->orderByDesc('tanggal_mulai')->orderByDesc('id')->get();
        $kegiatan = isset($filter['kegiatan_id']) ? $kegiatanList->firstWhere('id', $filter['kegiatan_id']) : $kegiatanList->first();
        abort_if(isset($filter['kegiatan_id']) && ! $kegiatan, 404);
        $kelasList = $kegiatan ? $this->rapor->kelasDalamCakupan($pengguna)
            ->where('tahun_pelajaran_id', $kegiatan->tahun_pelajaran_id)->orderBy('tingkat')->orderBy('nama')->get() : collect();
        $kelas = isset($filter['kelas_id']) ? $kelasList->firstWhere('id', $filter['kelas_id']) : $kelasList->first();
        abort_if(isset($filter['kelas_id']) && ! $kelas, 404);
        $tingkatList = $kelasList->pluck('tingkat')->filter()->unique()->sort()->values();
        $tingkat = isset($filter['tingkat']) ? $tingkatList->first(fn ($t) => (int) $t === (int) $filter['tingkat'])
            : ($kelas?->tingkat ?? $tingkatList->first());
        abort_if(isset($filter['tingkat']) && $tingkat === null, 404);

        return [
            'kegiatan' => $kegiatan, 'kelas' => $kelas, 'kelas_list' => $kelasList, 'tingkat' => $tingkat,
            'opsi' => [
                'kegiatan' => $kegiatanList->map(fn ($k) => ['id' => $k->id, 'label' => $k->nama.' - '.$k->tahunPelajaran?->nama, 'semester' => $k->semester])->values(),
                'kelas' => $kelasList->map(fn ($k) => ['id' => $k->id, 'label' => $k->nama, 'tingkat' => $k->tingkat])->values(),
                'tingkat' => $tingkatList->map(fn ($t) => ['id' => (int) $t, 'label' => 'Tingkat '.$t])->values(),
            ],
        ];
    }

    public function rapor(Pengguna $pengguna, array $filter): array
    {
        $pilihan = $this->pilihan($pengguna, $filter);
        $kegiatan = $pilihan['kegiatan'];
        $kelas = $pilihan['kelas'];
        $data = ['filter' => ['kegiatan_id' => $kegiatan?->id, 'kelas_id' => $kelas?->id], 'opsi' => $pilihan['opsi'], 'konteks' => null];
        if (! $kegiatan || ! $kelas) {
            abort_if(isset($filter['anggota_id']), 404);

            return $data;
        }
        $laporan = $this->rapor->bangun($kegiatan, $kelas);
        $baris = $laporan['baris'];
        if (isset($filter['anggota_id'])) {
            $baris = $baris->where('anggota.id', (int) $filter['anggota_id'])->values();
            abort_if($baris->isEmpty(), 404);
            $data['filter']['anggota_id'] = (int) $filter['anggota_id'];
        }
        $p = $laporan['pengaturan'];
        $lampiran = app(LampiranPerilakuStsService::class)->konteks($kegiatan, $kelas)['baris']->keyBy('anggota.id');
        $data['konteks'] = [
            'kegiatan' => $this->kegiatan($kegiatan), 'kelas' => $this->kelas($kelas),
            'wali_kelas' => $kelas->waliKelas ? ['id' => $kelas->waliKelas->id, 'nama' => $kelas->waliKelas->nama_lengkap] : null,
            'periode' => [
                'tersimpan' => $p->exists, 'versi' => (int) $p->versi,
                'awal' => $p->tanggal_awal_presensi->toDateString(), 'akhir' => $p->tanggal_akhir_presensi->toDateString(),
                'tanggal_rapor' => $p->tanggal_rapor->toDateString(), 'sudah_berakhir' => $p->tanggal_akhir_presensi->copy()->endOfDay()->isPast(),
            ],
            'mapel' => $laporan['mapel']->map(fn ($m) => ['id' => $m->id, 'nama' => $m->nama])->values(),
            'dapat_mengatur_mapel' => MapelRaporStsService::dapatMengatur($pengguna),
            'pengaturan_mapel' => $this->pengaturanMapel($pengguna, $kegiatan, $kelas),
            'ringkasan' => ['jumlah_siswa' => $baris->count(), 'nilai_lengkap' => $baris->where('nilai_lengkap', true)->count(),
                'siap_cetak' => $baris->where('siap', true)->count(), 'kehadiran_diperiksa' => $baris->where('diperiksa', true)->count()],
            'siswa' => $baris->map(fn ($b) => [
                ...$this->identitas($b, $kelas), 'nilai' => $this->nilai($b['nilai']),
                'nilai_lengkap' => $b['nilai_lengkap'], 'nilai_tuntas' => $b['nilai_tuntas'],
                'jumlah_bernilai' => $b['jumlah_bernilai'], 'jumlah_pengecualian' => $b['jumlah_pengecualian'],
                'jumlah' => $b['jumlah'], 'rata' => $b['rata'], 'kehadiran' => $b['kehadiran'],
                'keterlambatan' => $b['keterlambatan'], 'rekap_awal' => $b['sumber'], 'sidik_sumber' => $b['sidik_sumber'],
                'catatan_koreksi' => $b['koreksi']?->catatan_koreksi, 'diperiksa' => $b['diperiksa'],
                'sumber_berubah' => (bool) $b['sumber_berubah'], 'siap_cetak' => $b['siap'],
                'lampiran_perilaku_siap' => (bool) ($lampiran->get($b['anggota']->id)['siap'] ?? false),
            ])->values(),
        ];

        return $data;
    }

    public function bangunLeger(Pengguna $pengguna, array $filter): array
    {
        $pilihan = $this->pilihan($pengguna, $filter);
        $mode = $filter['mode'] ?? 'kelas';
        $kegiatan = $pilihan['kegiatan'];
        $kelas = $pilihan['kelas'];
        $tingkat = $mode === 'kelas' ? $kelas?->tingkat : $pilihan['tingkat'];
        $laporan = ! $kegiatan ? null : ($mode === 'tingkat' && $tingkat !== null
            ? $this->leger->bangunTingkat($kegiatan, $pilihan['kelas_list'], (int) $tingkat)
            : ($kelas ? $this->leger->bangun($kegiatan, $kelas) : null));

        return [...$pilihan, 'tingkat' => $tingkat, 'mode' => $mode, 'laporan' => $laporan];
    }

    public function leger(Pengguna $pengguna, array $filter): array
    {
        $k = $this->bangunLeger($pengguna, $filter);
        $l = $k['laporan'];
        $data = ['filter' => ['mode' => $k['mode'], 'kegiatan_id' => $k['kegiatan']?->id,
            'kelas_id' => $k['kelas']?->id, 'tingkat' => $k['tingkat']], 'opsi' => $k['opsi'], 'konteks' => null];
        if (! $l) {
            return $data;
        }
        $ringkasan = $l['ringkasan'];
        if (array_key_exists('kelas_tertinggi', $ringkasan)) {
            $ringkasan['kelas_tertinggi'] = $ringkasan['kelas_tertinggi'] ? $this->statistikKelas($ringkasan['kelas_tertinggi']) : null;
        }
        $data['konteks'] = [
            'kegiatan' => $this->kegiatan($k['kegiatan']), 'kelas' => $k['mode'] === 'kelas' ? $this->kelas($k['kelas']) : null,
            'tingkat' => $k['tingkat'], 'ranking_sementara' => $l['ranking_sementara'],
            // A wali kelas sees only their authorized classes, never a school-wide parallel ranking.
            'cakupan_tingkat_lengkap' => $k['mode'] === 'tingkat' ? $k['kelas_list']->where('tingkat', $k['tingkat'])->count()
                === Kelas::where('tahun_pelajaran_id', $k['kegiatan']->tahun_pelajaran_id)->where('tingkat', $k['tingkat'])->count() : null,
            'jumlah_mapel_pembagi' => $l['mapel']->count(), 'ringkasan' => $ringkasan, 'distribusi' => $l['distribusi']->values(),
            'mapel' => $l['mapel']->map(fn ($m) => ['id' => $m->id, 'nama' => $m->nama])->values(),
            'statistik_mapel' => $l['statistik_mapel']->map(fn ($s) => $this->statistikMapel($s))->values(),
            'mapel_tertinggi' => $l['mapel_tertinggi'] ? $this->statistikMapel($l['mapel_tertinggi']) : null,
            'statistik_kelas' => collect($l['statistik_kelas'] ?? [])->map(fn ($s) => $this->statistikKelas($s))->values(),
            'siswa' => $l['baris']->map(fn ($b) => [
                ...$this->identitas($b, $k['mode'] === 'kelas' ? $k['kelas'] : null), 'nilai' => $this->nilai($b['nilai']),
                ...collect($b)->only(['jumlah_mapel', 'jumlah_nilai_tersedia', 'jumlah_nilai_final', 'jumlah_nilai_draf',
                    'nilai_lengkap', 'nilai_final_lengkap', 'layak_ranking', 'jumlah_leger', 'rata_leger', 'ranking', 'status_ranking'])->all(),
            ])->values(),
        ];

        return $data;
    }

    public function penghargaan(Pengguna $pengguna, array $filter): array
    {
        $k = $this->bangunLeger($pengguna, $filter);
        $l = $k['laporan'];
        $kategori = $filter['kategori'] ?? 'keseluruhan';
        $mapelId = $filter['mapel_id'] ?? ($l ? $l['mapel']->first()?->id : null);
        abort_if(isset($filter['mapel_id']) && (! $l || ! $l['mapel']->contains('id', (int) $mapelId)), 404);
        $batas = (int) ($filter['batas'] ?? 10);
        $data = ['filter' => ['mode' => $k['mode'], 'kegiatan_id' => $k['kegiatan']?->id, 'kelas_id' => $k['kelas']?->id,
            'tingkat' => $k['tingkat'], 'kategori' => $kategori, 'mapel_id' => $mapelId, 'batas' => $batas], 'opsi' => $k['opsi'], 'konteks' => null];
        if ($l) {
            $p = $this->leger->kandidatPenghargaan($l, $kategori, $mapelId === null ? null : (int) $mapelId, $batas);
            $data['opsi']['mapel'] = $l['mapel']->map(fn ($m) => ['id' => $m->id, 'label' => $m->nama])->values();
            $data['konteks'] = [
                'hanya_nilai_final' => true, 'ringkasan' => $p['ringkasan'],
                'kandidat' => $p['kandidat']->map(fn ($b) => $this->kandidat($b))->values(),
                'ringkasan_mapel' => $p['ringkasan_mapel']->map(fn ($s) => [
                    'mapel' => ['id' => $s['mapel']->id, 'nama' => $s['mapel']->nama],
                    'jumlah_nilai_final' => $s['jumlah_nilai_final'], 'nilai_tertinggi' => $s['nilai_tertinggi'],
                    'juara' => $s['juara']->map(fn ($b) => $this->kandidat($b))->values(),
                ])->values(),
            ];
        }

        return $data;
    }

    private function kandidat(array $b): array
    {
        return [...$this->identitas($b), ...collect($b)->only(['ranking', 'nilai', 'jumlah_nilai_final', 'jumlah_mapel', 'label_penghargaan'])->all()];
    }

    private function identitas(array $b, ?Kelas $kelas = null): array
    {
        $anggota = $b['anggota'];

        return ['id' => $anggota->id, 'siswa_id' => $anggota->siswa_id, 'nama' => $anggota->siswa->nama_lengkap,
            'nomor_absen' => $anggota->nomor_absen, 'kelas' => $this->kelas($b['kelas'] ?? $kelas)];
    }

    private function nilai($nilai): array
    {
        return $nilai->map(fn ($n) => [
            'mapel_id' => $n['mapel']->id, 'nilai' => $n['nilai'], 'status' => $n['status'], 'keterangan' => $n['keterangan'],
            'draf' => (bool) ($n['draf'] ?? false), 'sumber_nilai' => $n['sumber_nilai'] ?? null,
            'dapat_dikecualikan' => $n['dapat_dikecualikan'], 'dikecualikan' => $n['dikecualikan'],
            'sidik_kondisi' => $n['sidik_kondisi'], 'alasan' => $n['dikecualikan'] ? $n['pengecualian']?->alasan : null,
        ])->values()->all();
    }

    private function pengaturanMapel(Pengguna $pengguna, KegiatanUjianCbt $kegiatan, Kelas $kelas): ?array
    {
        if (! MapelRaporStsService::dapatMengatur($pengguna)) {
            return null;
        }
        $p = app(MapelRaporStsService::class)->konteks($kegiatan, (int) $kelas->tingkat);

        return ['versi_mapel' => (int) ($p['pengaturan']?->versi ?? 0), 'sidik_mapel' => $p['sidik'],
            'mapel' => $p['mapel']->map(fn ($m) => ['id' => $m['mapel']->id, 'nama' => $m['mapel']->nama,
                'dipilih' => $m['dipilih'], 'wajib' => $m['wajib'], 'sumber' => $m['sumber'], 'dipulihkan' => $m['dipulihkan']])->values()];
    }

    private function statistikMapel(array $s): array
    {
        return ['mapel' => ['id' => $s['mapel']->id, 'nama' => $s['mapel']->nama],
            ...collect($s)->only(['jumlah_nilai', 'jumlah_draf', 'jumlah_final', 'jumlah_siswa', 'cakupan', 'rata', 'tertinggi_nilai', 'terendah_nilai', 'tertinggi'])->all(),
            'per_kelas' => collect($s['per_kelas'] ?? [])->map(fn ($k) => ['kelas' => $this->kelas($k['kelas']),
                'jumlah_nilai' => $k['jumlah_nilai'], 'jumlah_siswa' => $k['jumlah_siswa'], 'rata' => $k['rata']])->values()];
    }

    private function statistikKelas(array $s): array
    {
        return ['kelas' => $this->kelas($s['kelas']), ...collect($s)->only(['jumlah_siswa', 'masuk_ranking', 'belum_masuk_ranking',
            'kelengkapan', 'rata', 'tertinggi', 'terendah', 'ranking'])->all()];
    }

    private function kegiatan(KegiatanUjianCbt $k): array
    {
        return ['id' => $k->id, 'nama' => $k->nama, 'semester' => $k->semester, 'tahun_pelajaran' => $k->tahunPelajaran?->nama];
    }

    private function kelas(?Kelas $kelas): ?array
    {
        return $kelas ? ['id' => $kelas->id, 'nama' => $kelas->nama, 'tingkat' => $kelas->tingkat] : null;
    }
}
