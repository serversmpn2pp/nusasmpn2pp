<?php

namespace App\Services\ProfilSiswa;

use App\Models\AbsensiSiswa;
use App\Models\AlumniHumas;
use App\Models\KomponenNilai;
use App\Models\KelulusanTahfidz;
use App\Models\LaporanPembinaanSiswa;
use App\Models\PeminjamanBarang;
use App\Models\Pengguna;
use App\Models\PesertaUjianCbt;
use App\Models\PresensiKegiatanIbadah;
use App\Models\PrestasiSekolah;
use App\Models\PublikasiNilaiSiswa;
use App\Models\SetoranTahfidz;
use App\Models\Siswa;
use App\Models\SurahTahfidz;
use App\Models\TahunPelajaran;
use App\Models\TransaksiPoinSiswa;
use App\Services\Absensi\RincianPresensiHarianSiswaService;
use Illuminate\Database\Eloquent\Builder;

class DataProfilSiswaTerpadu
{
    public function __construct(
        private readonly AksesProfilSiswaTerpadu $akses,
        private readonly RincianPresensiHarianSiswaService $sumberPresensi,
    ) {}

    public function cakupan(Pengguna $user, array $lingkup, Siswa $siswa, TahunPelajaran $tahun, bool $adaKelas): array
    {
        $kelas = fn (string $bagian) => $this->akses->anggotaKelas($user, $lingkup, $tahun, $bagian)
            ->where('siswa_id', $siswa->id)->distinct()->pluck('kelas_id')->all();
        $bolehNilai = $user->memilikiIzin(['nilai.lihat', 'nilai.rekap']);
        $kelasNilai = $bolehNilai && $adaKelas ? $kelas('akademik') : [];
        $mapel = $bolehNilai && $adaKelas && $lingkup['mapel'] && $tahun->aktif ? (int) $user->pegawai_id : null;
        $kelasMapel = $mapel ? $kelas('mapel') : [];
        $poinSekolah = $this->akses->salahSatu($lingkup, ['admin', 'pimpinan', 'kesiswaan']);
        $poinBinaan = $lingkup['guru_wali'] && $tahun->aktif && $siswa->penugasanGuruWaliSiswa()
            ->where('aktif', true)->where('guru_wali_pegawai_id', $user->pegawai_id)->exists();
        $bolehPoin = $user->memilikiIzin(['poin_siswa.lihat', 'bk.lihat', 'poin_siswa.verifikasi_bk', 'poin_siswa.sahkan_wakil']);
        $bolehData = $this->akses->semuaIdentitas($lingkup)
            || ($adaKelas && $user->memilikiIzin('siswa.lihat') && $kelas('presensi') !== []);
        $kelolaData = $bolehData && $this->akses->salahSatu($lingkup, ['admin', 'tu']) && $user->memilikiIzin('siswa.kelola');

        return [
            'data' => $bolehData,
            'riwayat_kelas' => $this->akses->semuaIdentitas($lingkup),
            'identitas_lengkap' => $bolehData && $user->memilikiIzin(['siswa.lihat', 'siswa.kelola']),
            'kontak' => $kelolaData,
            'presensi' => $adaKelas && $user->memilikiIzin(['absensi.lihat', 'absensi.laporan']) ? $kelas('presensi') : [],
            'akademik' => $kelasNilai !== [] || $kelasMapel !== [],
            'kelas_nilai' => $kelasNilai,
            'kelas_mapel' => $kelasMapel,
            'pegawai_mapel' => $mapel,
            'ujian' => $adaKelas && $user->memilikiIzin(['cbt.lihat', 'cbt.kelola']) ? $kelas('ujian') : [],
            'pembinaan' => $adaKelas && $bolehPoin ? $kelas('pembinaan') : [],
            'pemeriksaan_bk' => $adaKelas && $user->memilikiIzin(['poin_siswa.verifikasi_bk', 'poin_siswa.sahkan_wakil', 'bk.kelola'])
                ? $kelas('pemeriksaan') : [],
            'poin_tahun' => $bolehPoin && ($poinSekolah || $poinBinaan),
            'ibadah' => $adaKelas && $user->memilikiIzin('ibadah.rekap') ? $kelas('ibadah') : [],
            'tahfidz' => $lingkup['tahfidz'] ? $this->akses->anggotaTahfidz($user, $tahun)->where('siswa_id', $siswa->id)
                ->orderByDesc('aktif')->orderByDesc('id')->pluck('id')->all() : [],
            'prestasi' => $this->akses->salahSatu($lingkup, ['admin', 'pimpinan', 'kesiswaan']) && $user->memilikiIzin('prestasi_sekolah.lihat'),
            'alumni' => $this->akses->salahSatu($lingkup, ['admin', 'pimpinan', 'tu']) && $user->memilikiIzin('alumni_humas.lihat'),
            'peminjaman' => $this->akses->salahSatu($lingkup, ['admin', 'pimpinan', 'tu']) && $user->memilikiIzin('barang.lihat'),
        ];
    }

    public function tabs(array $c): array
    {
        return array_filter([
            'ringkasan' => 'Ringkasan',
            'data' => $c['data'] ? 'Data & Keluarga' : null,
            'presensi' => $c['presensi'] ? 'Presensi' : null,
            'akademik' => $c['akademik'] ? 'Akademik' : null,
            'ujian' => $c['ujian'] ? 'Riwayat Ujian' : null,
            'pembinaan' => $c['pembinaan'] ? 'Pembinaan & Poin' : null,
            'ibadah' => $c['ibadah'] ? 'Ibadah' : null,
            'tahfidz' => $c['tahfidz'] ? 'Tahfidz' : null,
            'lainnya' => $c['prestasi'] || $c['alumni'] || $c['peminjaman'] ? 'Riwayat Lainnya' : null,
        ]);
    }

    public function isi(string $tab, Siswa $siswa, TahunPelajaran $tahun, string $semester, array $c, bool $kosongSaja): array
    {
        return match ($tab) {
            'ringkasan' => $this->ringkasan($siswa, $tahun, $semester, $c),
            'data' => $this->biodata($siswa, $c),
            'presensi' => $this->presensi($siswa, $tahun, $c),
            'akademik' => $this->akademik($siswa, $tahun, $semester, $c, $kosongSaja),
            'ujian' => $this->ujian($siswa, $tahun, $semester, $c),
            'pembinaan' => $this->pembinaan($siswa, $tahun, $c),
            'ibadah' => $this->ibadah($siswa, $tahun, $c),
            'tahfidz' => $this->tahfidz($siswa, $c),
            'lainnya' => $this->lainnya($siswa, $tahun, $c),
        };
    }

    private function ringkasan(Siswa $siswa, TahunPelajaran $tahun, string $semester, array $c): array
    {
        $statistik = [];
        if ($c['presensi']) {
            $statistik[] = ['Catatan hadir', $this->queryPresensi($siswa, $tahun, $c)->where('status_kehadiran', 'hadir')->count(), 'presensi'];
        }
        if ($c['akademik']) {
            $statistik[] = ['Komponen belum bernilai', $this->tanpaNilai($this->queryKomponen($siswa, $tahun, $semester, $c), $siswa)->count(), 'akademik'];
        }
        if ($c['pembinaan'] && $c['poin_tahun']) {
            $statistik[] = ['Saldo poin resmi', $this->saldoPoin($siswa, $tahun), 'pembinaan'];
        }
        if ($c['ibadah']) {
            $statistik[] = ['Kehadiran ibadah tercatat', $this->queryIbadah($siswa, $tahun, $c)->count(), 'ibadah'];
        }
        if ($c['tahfidz']) {
            $statistik[] = ['Surah sudah disetor', $this->jumlahSurah($c), 'tahfidz'];
        }

        return ['statistik' => $statistik];
    }

    private function biodata(Siswa $siswa, array $c): array
    {
        $kolom = ['nis' => 'NIS', 'nisn' => 'NISN'];
        if ($c['identitas_lengkap']) {
            $kolom += ['jenis_kelamin' => 'Jenis kelamin', 'tempat_lahir' => 'Tempat lahir',
                'tanggal_lahir' => 'Tanggal lahir', 'agama' => 'Agama', 'sekolah_asal' => 'Sekolah asal'];
        }
        if ($c['kontak']) {
            $kolom += ['nik' => 'NIK', 'alamat' => 'Alamat', 'status_dalam_keluarga' => 'Status dalam keluarga', 'anak_ke' => 'Anak ke'];
        }
        $data = Siswa::query()->select(array_keys($kolom))->findOrFail($siswa->id);
        $biodata = collect($kolom)->mapWithKeys(fn ($label, $key) => [$label => match ($key) {
            'tanggal_lahir' => $data->tanggal_lahir?->translatedFormat('d F Y'),
            'jenis_kelamin' => ['L' => 'Laki-laki', 'P' => 'Perempuan'][$data->jenis_kelamin] ?? $data->jenis_kelamin,
            default => $data->{$key},
        }]);
        $keluarga = collect();
        if ($c['kontak']) {
            $kolom = ['nama_ayah' => 'Nama ayah', 'pekerjaan_ayah' => 'Pekerjaan ayah', 'nomor_wa_ayah' => 'WhatsApp ayah',
                'nama_ibu' => 'Nama ibu', 'pekerjaan_ibu' => 'Pekerjaan ibu', 'nomor_wa_ibu' => 'WhatsApp ibu',
                'nama_wali' => 'Nama wali', 'hubungan_wali' => 'Hubungan wali', 'nomor_wa_wali' => 'WhatsApp wali'];
            $data = Siswa::query()->select(array_keys($kolom))->findOrFail($siswa->id);
            $keluarga = collect($kolom)->mapWithKeys(fn ($label, $key) => [$label => $data->{$key}]);
        }
        $tabel = [];
        if ($c['riwayat_kelas']) {
            $anggota = $siswa->anggotaKelas()->with(['kelas:id,nama', 'tahunPelajaran:id,nama'])
                ->latest('tanggal_masuk')->latest('id')->paginate(15);
            $tabel[] = $this->tabel('Riwayat kelas', ['Tahun pelajaran', 'Kelas', 'Nomor absen', 'Status', 'Masuk / keluar'],
                $anggota->through(fn ($row) => [$row->tahunPelajaran?->nama, $row->kelas?->nama, $row->nomor_absen,
                    $this->label($row->status_keanggotaan), [$row->tanggal_masuk?->format('d/m/Y'), $row->tanggal_keluar?->format('d/m/Y')]]));
        }
        if ($c['kontak']) {
            $wali = $siswa->orangTuaWali()->orderByDesc('orang_tua_wali_siswa.utama')->orderBy('orang_tua_wali.nama_lengkap')
                ->paginate(10, ['orang_tua_wali.id', 'orang_tua_wali.nama_lengkap', 'orang_tua_wali.nomor_wa'], 'wali')
                ->through(fn ($row) => [$row->nama_lengkap, $this->label($row->pivot->hubungan),
                    $row->nomor_wa, $row->pivot->utama ? 'Utama' : null]);
            $tabel[] = $this->tabel('Orang tua / wali terdaftar', ['Nama', 'Hubungan', 'WhatsApp', 'Kontak utama'], $wali);
        }

        return compact('biodata', 'keluarga', 'tabel');
    }

    private function presensi(Siswa $siswa, TahunPelajaran $tahun, array $c): array
    {
        $query = $this->queryPresensi($siswa, $tahun, $c);
        $jumlah = (clone $query)->reorder()->selectRaw('status_kehadiran, count(*) as jumlah')->groupBy('status_kehadiran')->pluck('jumlah', 'status_kehadiran');
        $baris = $query->with(['kelas:id,nama',
            'logScanAbsensi' => fn ($q) => $q->where('siswa_id', $siswa->id)->where('berhasil', true)->whereIn('jenis_scan', ['masuk', 'pulang'])
                ->select('id', 'absensi_siswa_id', 'siswa_id', 'tanggal', 'jenis_scan', 'waktu_scan'),
            'riwayatPerubahan' => fn ($q) => $q->where('siswa_id', $siswa->id)->with('dibuatOleh:id,nama')->latest('id')
                ->select('id', 'absensi_siswa_id', 'siswa_id', 'tanggal', 'sumber', 'status_sesudah', 'dibuat_oleh_pengguna_id', 'created_at'),
        ])->latest('tanggal')->latest('id')->paginate(20, ['id', 'kelas_id', 'siswa_id', 'tanggal', 'status_kehadiran', 'jam_masuk', 'jam_pulang',
            'status_masuk', 'menit_terlambat', 'menit_pulang_cepat', 'sumber', 'alasan_hadir_manual', 'sanksi_manual_menit'])->through(function (AbsensiSiswa $row) {
            $row->setRelation('logScanAbsensi', $row->logScanAbsensi->filter(fn ($log) => $log->tanggal?->isSameDay($row->tanggal)));
            $row->setRelation('riwayatPerubahan', $row->riwayatPerubahan->filter(fn ($log) => $log->tanggal?->isSameDay($row->tanggal)));
            $sumber = $this->sumberPresensi->sumberJam($row);
            $koreksi = $row->riwayatPerubahan->whereIn('sumber', ['koreksi_manual', 'guru_piket']);
            $menitMasuk = $row->menit_terlambat > 0 ? $row->menit_terlambat.($row->memilikiSanksiManual()
                ? ' menit tercatat (sanksi tanpa kartu)' : ' menit terlambat') : $row->labelDurasiTerlambat();

            return [
                [$row->tanggal->translatedFormat('d F Y'), $row->kelas?->nama],
                [$row->labelHadirManualTanpaJam() ?? $this->label($row->status_kehadiran), $row->menungguVerifikasiScan() ? 'Perlu verifikasi scan' : null],
                [$this->jam($row->jam_masuk), $sumber['sumber_masuk_label'], $menitMasuk],
                [$this->jam($row->jam_pulang), $sumber['sumber_pulang_label'], $row->menit_pulang_cepat > 0 ? $row->menit_pulang_cepat.' menit lebih awal' : null],
                $koreksi->map(fn ($log) => ($log->dibuatOleh?->nama ?? 'Akun tidak tercatat').' ('.$log->created_at?->format('d/m/Y H:i').')')->all(),
            ];
        });

        return [
            'statistik' => collect(['hadir', 'sakit', 'izin', 'alfa'])->map(fn ($key) => [$this->label($key).' tercatat', (int) ($jumlah[$key] ?? 0)])->all(),
            'tabel' => [$this->tabel('Riwayat presensi sekolah', ['Tanggal / kelas', 'Status', 'Masuk', 'Pulang', 'Riwayat koreksi'], $baris,
                'Menampilkan catatan presensi yang tersimpan, bukan perkiraan status untuk hari tanpa catatan.')],
        ];
    }

    private function queryPresensi(Siswa $siswa, TahunPelajaran $tahun, array $c): Builder
    {
        return AbsensiSiswa::query()->where('siswa_id', $siswa->id)
            ->where('tahun_pelajaran_id', $tahun->id)->whereIn('kelas_id', $c['presensi']);
    }

    private function queryKomponen(Siswa $siswa, TahunPelajaran $tahun, string $semester, array $c): Builder
    {
        return KomponenNilai::query()->where('aktif', true)->where('semester', $semester)
            ->whereHas('guruMataPelajaran', fn (Builder $q) => $q->where('tahun_pelajaran_id', $tahun->id)->where('aktif', true)
                ->where(fn (Builder $g) => $g->whereIn('kelas_id', $c['kelas_nilai'])
                    ->when($c['kelas_mapel'], fn (Builder $a) => $a->orWhere(fn (Builder $m) => $m
                        ->whereIn('kelas_id', $c['kelas_mapel'])->where('pegawai_id', $c['pegawai_mapel'])))))
            ->with(['guruMataPelajaran.mataPelajaran:id,nama', 'guruMataPelajaran.kelas:id,nama,tingkat',
                'nilaiSiswa' => fn ($q) => $q->where('siswa_id', $siswa->id)->select('id', 'komponen_nilai_id', 'nilai', 'predikat', 'catatan')]);
    }

    private function tanpaNilai(Builder $query, Siswa $siswa): Builder
    {
        return $query->whereDoesntHave('nilaiSiswa', fn (Builder $q) => $q->where('siswa_id', $siswa->id)
            ->where(fn (Builder $isi) => $isi->whereNotNull('nilai')->orWhereRaw("TRIM(COALESCE(predikat, '')) <> ''")));
    }

    private function akademik(Siswa $siswa, TahunPelajaran $tahun, string $semester, array $c, bool $kosongSaja): array
    {
        $query = $this->queryKomponen($siswa, $tahun, $semester, $c);
        $total = (clone $query)->count();
        $kosong = $this->tanpaNilai(clone $query, $siswa)->count();
        $baris = $query->when($kosongSaja, fn (Builder $q) => $this->tanpaNilai($q, $siswa))
            ->orderBy('guru_mata_pelajaran_id')
            ->orderByRaw("CASE jenis_komponen WHEN 'formatif' THEN 1 WHEN 'sumatif' THEN 2 WHEN 'sts' THEN 3 WHEN 'sas_saj' THEN 4 ELSE 5 END")
            ->orderBy('urutan')->orderBy('id')->paginate(25);
        $publikasi = PublikasiNilaiSiswa::query()->where('semester', $semester)->where('dipublikasikan', true)
            ->whereIn('guru_mata_pelajaran_id', $baris->getCollection()->pluck('guru_mata_pelajaran_id'))
            ->pluck('dipublikasikan_pada', 'guru_mata_pelajaran_id');
        $baris->through(function (KomponenNilai $komponen) use ($publikasi) {
            $nilai = $komponen->nilaiSiswa->first();
            $angka = $nilai?->nilai !== null ? $this->angka($nilai->nilai) : $nilai?->predikat;

            return [
                [$komponen->guruMataPelajaran?->mataPelajaran?->nama, $komponen->guruMataPelajaran?->kelas?->nama],
                [$komponen->nama, $komponen->labelJenis()],
                $komponen->tanggal_penilaian?->format('d/m/Y'),
                filled($angka) ? $angka : 'Belum ada nilai',
                $publikasi->has($komponen->guru_mata_pelajaran_id) ? 'Dipublikasikan' : 'Belum dipublikasikan',
                $nilai?->catatan,
            ];
        });

        return [
            'statistik' => [['Komponen aktif', $total], ['Sudah ada nilai', $total - $kosong], ['Belum ada nilai', $kosong]],
            'tabel' => [$this->tabel('Nilai per komponen', ['Mata pelajaran', 'Komponen', 'Tanggal penilaian', 'Nilai', 'Publikasi mapel', 'Catatan'],
                $baris, 'Nilai kosong belum membuktikan bahwa tugas tidak dikumpulkan atau ujian tidak diikuti.')],
        ];
    }

    private function ujian(Siswa $siswa, TahunPelajaran $tahun, string $semester, array $c): array
    {
        $baris = PesertaUjianCbt::query()->whereHas('anggotaKelas', fn (Builder $a) => $a
            ->where('siswa_id', $siswa->id)->where('tahun_pelajaran_id', $tahun->id)->whereIn('kelas_id', $c['ujian']))
            ->whereHas('ujianCbt', fn (Builder $q) => $q->where('tahun_pelajaran_id', $tahun->id)->where('semester', $semester))
            ->select('id', 'ujian_cbt_id', 'ruang_ujian_cbt_id', 'status', 'status_kehadiran_ujian', 'status_susulan',
                'waktu_mulai', 'waktu_selesai')
            ->with(['ujianCbt:id,nama,mata_pelajaran_id,tanggal_mulai', 'ujianCbt.mataPelajaran:id,nama', 'ruangUjianCbt:id,nama'])
            ->latest('id')->paginate(20)->through(fn (PesertaUjianCbt $row) => [
                [$row->ujianCbt?->nama, $row->ujianCbt?->mataPelajaran?->nama],
                [$row->ujianCbt?->tanggal_mulai?->format('d/m/Y'), $row->ruangUjianCbt?->nama],
                $row->labelStatusKehadiranUjian(), $row->labelStatusPelaksanaan(),
                $row->status_susulan ? $row->labelStatusSusulan() : null,
                $row->waktu_mulai || $row->waktu_selesai
                    ? ($row->waktu_mulai?->format('H:i') ?? '-').' / '.($row->waktu_selesai?->format('H:i') ?? '-') : null,
            ]);

        return ['tabel' => [$this->tabel('Riwayat peserta CBT', ['Ujian', 'Tanggal / ruang', 'Kehadiran', 'Pelaksanaan', 'Susulan', 'Mulai / selesai'], $baris)]];
    }

    private function pembinaan(Siswa $siswa, TahunPelajaran $tahun, array $c): array
    {
        $baris = LaporanPembinaanSiswa::query()->where('siswa_id', $siswa->id)->where('tahun_pelajaran_id', $tahun->id)
            ->whereIn('kelas_id', $c['pembinaan'])
            ->where(fn (Builder $q) => $q->where('status_verifikasi', 'disahkan')
                ->orWhereIn('kelas_id', $c['pemeriksaan_bk'])
                ->orWhere(fn (Builder $p) => $p->where('status_verifikasi', 'ditetapkan_pembinaan')->where('jenis_laporan', 'pelanggaran')))
            ->select('id', 'nomor_laporan', 'tanggal_kejadian', 'jenis_laporan', 'status', 'status_verifikasi',
                'kategori_pembinaan_siswa_id', 'kunci_presensi_otomatis', 'total_poin')
            ->with(['kategoriPembinaanSiswa:id,nama', 'butirPelanggaranLaporan' => fn ($q) => $q
                ->whereHas('laporanPembinaanSiswa', fn (Builder $laporan) => $laporan->where('status_verifikasi', 'disahkan'))
                ->select('id', 'laporan_pembinaan_siswa_id', 'nama_pelanggaran')])
            ->latest('tanggal_kejadian')->latest('id')->paginate(20)
            ->through(fn (LaporanPembinaanSiswa $row) => [
                $row->tanggal_kejadian?->format('d/m/Y'), [$row->nomor_laporan, $row->labelJenisLaporan()],
                $row->status_verifikasi === 'disahkan'
                    ? [$row->kategoriPembinaanSiswa?->nama, ...$row->butirPelanggaranLaporan->pluck('nama_pelanggaran')->all()] : null,
                $row->status_verifikasi === 'tidak_perlu' ? $row->labelStatus() : $row->labelStatusVerifikasi(),
                $row->status_verifikasi === 'disahkan' ? $row->total_poin : null,
            ]);
        $tabel = [$this->tabel('Riwayat pembinaan', ['Tanggal', 'Laporan', 'Kategori', 'Status', 'Poin disahkan'], $baris,
            'Laporan yang masih diperiksa bukan pelanggaran yang sudah terbukti. Catatan konseling dan berkas pemeriksaan tidak ditampilkan di sini.')];
        $statistik = [];
        if ($c['poin_tahun']) {
            $statistik[] = ['Saldo poin resmi', $this->saldoPoin($siswa, $tahun)];
            $transaksi = $this->queryPoin($siswa, $tahun)->latest('tercatat_pada')->latest('id')
                ->paginate(12, ['id', 'tercatat_pada', 'jenis', 'poin'], 'transaksi')
                ->through(fn ($row) => [$row->tercatat_pada?->format('d/m/Y H:i'), $this->label($row->jenis), sprintf('%+d', $row->poin)]);
            $tabel[] = $this->tabel('Mutasi poin resmi', ['Waktu', 'Jenis', 'Perubahan poin'], $transaksi);
        }

        return compact('statistik', 'tabel');
    }

    private function queryPoin(Siswa $siswa, TahunPelajaran $tahun): Builder
    {
        return TransaksiPoinSiswa::query()->where('siswa_id', $siswa->id)->where('tahun_pelajaran_id', $tahun->id);
    }

    private function saldoPoin(Siswa $siswa, TahunPelajaran $tahun): int
    {
        return max(0, (int) $this->queryPoin($siswa, $tahun)->sum('poin'));
    }

    private function queryIbadah(Siswa $siswa, TahunPelajaran $tahun, array $c): Builder
    {
        return PresensiKegiatanIbadah::query()->where('siswa_id', $siswa->id)
            ->where('tahun_pelajaran_id', $tahun->id)->whereIn('kelas_id', $c['ibadah']);
    }

    private function ibadah(Siswa $siswa, TahunPelajaran $tahun, array $c): array
    {
        $baris = $this->queryIbadah($siswa, $tahun, $c)->with('kegiatanIbadah:id,nama')->latest('tanggal')->latest('id')
            ->paginate(20, ['id', 'kegiatan_ibadah_id', 'tanggal', 'waktu_scan', 'sumber'])
            ->through(fn ($row) => [$row->tanggal?->format('d/m/Y'), $row->kegiatanIbadah?->nama,
                $this->jam($row->waktu_scan), $this->label($row->sumber)]);

        return ['tabel' => [$this->tabel('Presensi ibadah tercatat', ['Tanggal', 'Kegiatan', 'Waktu', 'Sumber'], $baris,
            'Data berhalangan dan alasan pribadi tidak ditampilkan. Tidak ada catatan scan bukan kesimpulan bahwa siswa tidak beribadah.')]];
    }

    private function jumlahSurah(array $c): int
    {
        return SetoranTahfidz::query()->whereIn('anggota_tahfidz_id', $c['tahfidz'])->distinct()->count('nomor_surah');
    }

    private function tahfidz(Siswa $siswa, array $c): array
    {
        $query = SetoranTahfidz::query()->whereIn('anggota_tahfidz_id', $c['tahfidz']);
        $sudah = (clone $query)->distinct()->pluck('nomor_surah');
        $belum = SurahTahfidz::query()->whereNotIn('nomor', $sudah)->orderBy('nomor')->pluck('nama');
        $lulus = KelulusanTahfidz::query()->where('siswa_id', $siswa->id)->whereIn('anggota_tahfidz_id', $c['tahfidz'])
            ->with('wisuda:id,nama,tanggal')->first(['id', 'wisuda_tahfidz_id', 'diverifikasi_pada', 'nomor_sertifikat', 'diterbitkan_pada']);
        $baris = $query->with(['surah:nomor,nama', 'anggota.kelompok:id,nama,pembimbing_pegawai_id', 'anggota.kelompok.pembimbing:id,nama_lengkap',
            'pertemuan:id,tanggal', 'penilai:id,nama'])
            ->latest('id')->paginate(20, ['id', 'anggota_tahfidz_id', 'nomor_surah', 'pertemuan_tahfidz_id', 'penilai_id', 'nilai'])
            ->through(fn ($row) => [$row->pertemuan?->tanggal?->format('d/m/Y'), $row->surah?->nama,
                [$row->anggota?->kelompok?->nama, $row->penilai?->nama ?? $row->anggota?->kelompok?->pembimbing?->nama_lengkap], $row->labelNilai()]);
        $keterangan = $belum->isNotEmpty() ? 'Belum tercatat: '.$belum->join(', ').'.' : 'Seluruh surah Juz 30 sudah tercatat.';
        if ($lulus?->diverifikasi_pada) {
            $keterangan .= ' Kelulusan diverifikasi '.$lulus->diverifikasi_pada->format('d/m/Y').'.';
        }
        if ($lulus?->diterbitkan_pada) {
            $keterangan .= ' Sertifikat '.$lulus->nomor_sertifikat.' diterbitkan '.$lulus->diterbitkan_pada->format('d/m/Y').'.';
        }
        if ($lulus?->wisuda) {
            $keterangan .= ' '.$lulus->wisuda->nama.' ('.$lulus->wisuda->tanggal?->format('d/m/Y').').';
        }

        return [
            'statistik' => [['Surah disetor', $sudah->count()], ['Surah belum tercatat', $belum->count()],
                ['Kelulusan', $lulus?->diverifikasi_pada ? 'Terverifikasi' : 'Belum diverifikasi']],
            'tabel' => [$this->tabel('Riwayat setoran Tahfidz', ['Pertemuan', 'Surah', 'Kelompok / penilai', 'Nilai'], $baris, $keterangan)],
        ];
    }

    private function lainnya(Siswa $siswa, TahunPelajaran $tahun, array $c): array
    {
        $tabel = [];
        if ($c['prestasi']) {
            $prestasi = PrestasiSekolah::query()->where('tahun_pelajaran_id', $tahun->id)->where('status', 'terverifikasi')
                ->whereHas('peserta', fn (Builder $q) => $q->where('siswa_id', $siswa->id))->latest('tanggal_prestasi')->latest('id')
                ->paginate(15, ['id', 'nama_kegiatan', 'tingkat', 'perolehan', 'capaian', 'tanggal_prestasi'], 'prestasi')
                ->through(fn ($row) => [$row->tanggal_prestasi?->format('d/m/Y'), $row->nama_kegiatan,
                    PrestasiSekolah::TINGKAT[$row->tingkat] ?? null, $row->capaian ?: (PrestasiSekolah::PEROLEHAN[$row->perolehan] ?? null)]);
            $tabel[] = $this->tabel('Prestasi terverifikasi', ['Tanggal', 'Kegiatan', 'Tingkat', 'Capaian'], $prestasi);
        }
        if ($c['alumni']) {
            $alumni = AlumniHumas::query()->where('siswa_id', $siswa->id)->whereHas('anggotaKelas', fn (Builder $q) => $q
                ->where('tahun_pelajaran_id', $tahun->id))->orderByDesc('tanggal_lulus')->orderByDesc('id')
                ->paginate(15, ['id', 'tanggal_lulus', 'tahun_lulus', 'kelas_terakhir', 'status', 'nama_sekolah', 'kota_sekolah'], 'alumni')
                ->through(fn ($row) => [$row->tanggal_lulus?->format('d/m/Y') ?? $row->tahun_lulus, $row->kelas_terakhir,
                    $this->label($row->status), [$row->nama_sekolah, $row->kota_sekolah]]);
            $tabel[] = $this->tabel('Riwayat alumni', ['Kelulusan', 'Kelas terakhir', 'Status', 'Sekolah lanjutan'], $alumni);
        }
        if ($c['peminjaman']) {
            $pinjam = PeminjamanBarang::query()->where('siswa_id', $siswa->id)
                ->whereBetween('tanggal_peminjaman', [$tahun->tanggal_mulai->toDateString(), $tahun->tanggal_selesai->toDateString()])
                ->with('detailPeminjamanBarang.barang:id,nama')->latest('tanggal_peminjaman')->latest('id')
                ->paginate(15, ['id', 'nomor_peminjaman', 'tanggal_peminjaman', 'rencana_kembali', 'status'], 'peminjaman')
                ->through(fn ($row) => [$row->tanggal_peminjaman?->format('d/m/Y'), $row->nomor_peminjaman,
                    $row->detailPeminjamanBarang->map(fn ($detail) => $detail->barang?->nama)->filter()->all(),
                    $row->rencana_kembali?->format('d/m/Y'), $row->labelStatus()]);
            $tabel[] = $this->tabel('Peminjaman barang', ['Tanggal', 'Nomor', 'Barang', 'Rencana kembali', 'Status'], $pinjam);
        }

        return compact('tabel');
    }

    private function tabel(string $judul, array $kolom, $baris, ?string $keterangan = null): array
    {
        return compact('judul', 'kolom', 'baris', 'keterangan');
    }

    private function label(?string $nilai): ?string
    {
        return filled($nilai) ? str($nilai)->headline()->toString() : null;
    }

    private function jam(?string $waktu): ?string
    {
        return $waktu ? substr($waktu, 0, 5) : null;
    }

    private function angka(string $angka): string
    {
        return rtrim(rtrim(number_format((float) $angka, 2, ',', ''), '0'), ',');
    }
}
