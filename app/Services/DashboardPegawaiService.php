<?php

namespace App\Services;

use App\Models\AbsensiPegawai;
use App\Models\JadwalPelajaran;
use App\Models\KomponenNilai;
use App\Models\PengaturanAbsensiPegawai;
use App\Models\Pengguna;
use App\Models\SesiSupervisi;
use App\Models\TahunPelajaran;
use App\Services\Persuratan\PersuratanService;
use App\Services\Persuratan\TindakLanjutSuratService;
use App\Services\Supervisi\AksesSupervisi;
use App\Services\Supervisi\PascaobservasiSupervisiService;
use App\Services\Supervisi\PengisianSupervisiService;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

class DashboardPegawaiService
{
    public function __construct(
        private PersuratanService $persuratan,
        private TindakLanjutSuratService $tindakLanjut,
        private AksesSupervisi $aksesSupervisi,
        private PengisianSupervisiService $pengisian,
        private PascaobservasiSupervisiService $pascaobservasi,
    ) {}

    public function data(Pengguna $akun, CarbonInterface $hari, ?TahunPelajaran $tahun): array
    {
        $awalMinggu = $hari->copy()->subDays(6)->startOfDay();
        $awalBulan = $hari->copy()->startOfMonth();
        $catatan = AbsensiPegawai::where('pegawai_id', $akun->pegawai_id ?: 0)
            ->whereDate('tanggal', '>=', ($awalMinggu->lt($awalBulan) ? $awalMinggu : $awalBulan)->toDateString())
            ->whereDate('tanggal', '<=', $hari->toDateString())
            ->orderBy('tanggal')->get()->keyBy(fn ($a) => $a->tanggal->toDateString());
        $presensi = $catatan->get($hari->toDateString());
        $jadwal = $presensi?->pengaturanAbsensiPegawai ?? $this->jadwalPresensi($akun, $hari);
        $agenda = collect();
        $tugas = collect();
        $aksi = collect();
        $aktif = $akun->pegawai?->aktif === true;

        if ($aktif) {
            $this->persuratan($akun, $hari, $agenda, $tugas, $aksi);
            if ($tahun) {
                $this->akademik($akun, $hari, $tahun, $agenda, $tugas, $aksi);
                $this->supervisi($akun, $hari, $tahun, $agenda, $tugas, $aksi);
            }
            if ($akun->memilikiIzin('pegawai.profil')) {
                $aksi->push(['label' => 'Profil saya', 'ikon' => 'user-round', 'url' => route('profil-pegawai.edit')]);
            }
            if ($akun->memilikiIzin('poin_siswa.lapor')) {
                $aksi->push(['label' => 'Laporkan kejadian', 'ikon' => 'message-square-warning', 'url' => route('laporan-pembinaan-siswa.create')]);
            }
        }

        return [
            'presensiPegawaiHariIni' => $presensi,
            'jadwalPresensiPegawai' => $jadwal,
            'grafikKedatanganPegawai' => [
                'minggu' => $this->grafik($catatan, $awalMinggu, $hari),
                'bulan' => $this->grafik($catatan, $awalBulan, $hari),
            ],
            'agendaPegawai' => $agenda->sortBy('urut')->values(),
            'tugasPegawai' => $tugas,
            'jumlahTugasPegawai' => (int) $tugas->sum('jumlah'),
            'aksiPegawai' => $aksi->unique('url')->values(),
            'aktivitasPegawai' => $akun->notifikasiPengguna()->latest('id')->limit(5)->get(),
        ];
    }

    private function jadwalPresensi(Pengguna $akun, CarbonInterface $hari): ?PengaturanAbsensiPegawai
    {
        if (! $akun->pegawai) {
            return null;
        }
        $kodeHari = array_keys(PengaturanAbsensiPegawai::DAFTAR_HARI)[$hari->dayOfWeekIso - 1];

        return PengaturanAbsensiPegawai::where('hari', $kodeHari)->where('aktif', true)
            ->where(fn ($q) => $q->where(fn ($q) => $q->where('cakupan', 'pegawai')->where('pegawai_id', $akun->pegawai_id))
                ->orWhere(fn ($q) => $q->where('cakupan', 'jenis_pegawai')->where('jenis_pegawai', $akun->pegawai->jenis_pegawai))
                ->orWhere('cakupan', 'semua'))
            ->orderByRaw("case cakupan when 'pegawai' then 1 when 'jenis_pegawai' then 2 else 3 end")
            ->orderBy('id')->first();
    }

    private function grafik(Collection $catatan, CarbonInterface $awal, CarbonInterface $akhir): array
    {
        return collect(CarbonPeriod::create($awal->toDateString(), $akhir->toDateString()))->map(function ($tanggal) use ($catatan) {
            $a = $catatan->get($tanggal->toDateString());
            $hadir = $a?->status_kehadiran === 'hadir' && filled($a->jam_masuk);

            return ['tanggal' => $tanggal->format('d/m'), 'lengkap' => $tanggal->locale('id')->translatedFormat('l, d F Y'),
                'menit' => $hadir ? max(0, (int) $a->menit_terlambat) : null,
                'status' => $a?->labelStatusKehadiran() ?? 'Belum ada catatan',
                'jam' => $hadir ? substr($a->jam_masuk, 0, 5) : null];
        })->all();
    }

    private function tambahTugas(Collection $tugas, int $jumlah, string $label, string $detail, string $ikon, string $url): void
    {
        if ($jumlah > 0) {
            $tugas->push(compact('jumlah', 'label', 'detail', 'ikon', 'url'));
        }
    }

    private function persuratan(Pengguna $akun, CarbonInterface $hari, Collection $agenda, Collection $tugas, Collection $aksi): void
    {
        $surat = $this->persuratan->penugasanSaya($akun)->whereIn('status', ['disahkan', 'dikirim']);
        $belumDibaca = (clone $surat)->whereHas('penugasan', fn ($q) => $q->where('pegawai_id', $akun->pegawai_id)->whereNull('dibaca_pada'))->count();
        $this->tambahTugas($tugas, $belumDibaca, 'Surat tugas belum dibaca', 'Penugasan resmi untuk Anda', 'mail', route('surat-saya.index', ['belum_dibaca' => 1]));
        foreach ((clone $surat)->whereDate('mulai_tugas', '<=', $hari->toDateString())->whereDate('selesai_tugas', '>=', $hari->toDateString())->orderBy('id')->get() as $s) {
            $agenda->push(['mulai' => 'Hari ini', 'selesai' => null, 'urut' => '00:00', 'judul' => $s->perihal,
                'detail' => $s->nomor_surat, 'status' => 'Surat tugas', 'warna' => 'blue', 'url' => route('surat-saya.show', $s)]);
        }
        $disposisi = $this->tindakLanjut->daftar($akun)->whereIn('status', ['belum_mulai', 'diproses', 'perlu_perbaikan'])
            ->whereHas('surat', fn ($q) => $q->where('status', '<>', 'dibatalkan'))->count();
        $this->tambahTugas($tugas, $disposisi, 'Tindak lanjut surat', 'Disposisi atau laporan tugas belum selesai', 'clipboard-list', route('tindak-lanjut-surat.index'));

        if ($akun->memilikiIzin('surat.kelola')) {
            $draf = $this->persuratan->arsip($akun)->where('dibuat_oleh_pengguna_id', $akun->id)->whereIn('status', ['draf', 'dikembalikan'])->count();
            $this->tambahTugas($tugas, $draf, 'Draf surat saya', 'Belum diajukan atau perlu perbaikan', 'file-pen-line', route('persuratan.index'));
            $aksi->push(['label' => 'Tambah surat', 'ikon' => 'file-plus-2', 'url' => route('persuratan.create')]);
        }
        if ($akun->memilikiIzin('surat.sahkan')) {
            $jumlah = $this->persuratan->arsip($akun)->where('pengesah_pengguna_id', $akun->id)->where('status', 'diajukan')->count();
            $this->tambahTugas($tugas, $jumlah, 'Pengesahan surat', 'Menunggu pemeriksaan Anda', 'stamp', route('persuratan.pemeriksaan'));
            $aksi->push(['label' => 'Pemeriksaan surat', 'ikon' => 'stamp', 'url' => route('persuratan.pemeriksaan')]);
        }
    }

    private function akademik(Pengguna $akun, CarbonInterface $hari, TahunPelajaran $tahun, Collection $agenda, Collection $tugas, Collection $aksi): void
    {
        if ($akun->memilikiIzin('jadwal.pribadi')) {
            $kodeHari = array_keys(PengaturanAbsensiPegawai::DAFTAR_HARI)[$hari->dayOfWeekIso - 1];
            $jadwal = JadwalPelajaran::with(['kelas', 'jamPelajaran', 'mataPelajaran', 'guruMataPelajaran.mataPelajaran'])
                ->where('tahun_pelajaran_id', $tahun->id)->where('hari', $kodeHari)->where('aktif', true)
                ->whereHas('jamPelajaran', fn ($q) => $q->where('aktif', true))
                ->whereHas('kelas', fn ($q) => $q->where('aktif', true))
                ->whereHas('guruMataPelajaran', fn ($q) => $q->where('pegawai_id', $akun->pegawai_id)->where('aktif', true))->get();
            foreach ($jadwal as $j) {
                $mulai = substr($j->jamPelajaran->jam_mulai, 0, 5);
                $selesai = substr($j->jamPelajaran->jam_selesai, 0, 5);
                $status = $hari->format('H:i') > $selesai ? 'Selesai' : ($hari->format('H:i') >= $mulai ? 'Berlangsung' : 'Berikutnya');
                $agenda->push(['mulai' => $mulai, 'selesai' => $selesai, 'urut' => $mulai, 'judul' => $j->mataPelajaranTerjadwal()?->nama ?? 'Pembelajaran',
                    'detail' => $j->kelas?->nama, 'status' => $status, 'warna' => $status === 'Berlangsung' ? 'teal' : 'blue', 'url' => route('jadwal-saya.index')]);
            }
            $aksi->push(['label' => 'Jadwal saya', 'ikon' => 'calendar-days', 'url' => route('jadwal-saya.index')]);
        }
        if ($akun->memilikiIzin('nilai.input')) {
            $kosong = KomponenNilai::where('aktif', true)->whereHas('guruMataPelajaran', fn ($q) => $q
                ->where('pegawai_id', $akun->pegawai_id)->where('tahun_pelajaran_id', $tahun->id)->where('aktif', true)
                ->whereHas('kelas', fn ($q) => $q->where('aktif', true)))
                ->where(fn ($q) => $q->whereNull('tanggal_penilaian')->orWhereDate('tanggal_penilaian', '<=', $hari->toDateString()))
                ->whereHas('guruMataPelajaran.kelas.anggotaKelas', fn ($q) => $q
                    ->where('tahun_pelajaran_id', $tahun->id)->where('status_keanggotaan', 'aktif')
                    ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('nilai_siswa')
                        ->whereColumn('nilai_siswa.komponen_nilai_id', 'komponen_nilai.id')
                        ->whereColumn('nilai_siswa.siswa_id', 'anggota_kelas.siswa_id')
                        ->where(fn ($q) => $q->whereNotNull('nilai')->orWhere(fn ($q) => $q->whereNotNull('predikat')->where('predikat', '<>', '')))))
                ->count();
            $this->tambahTugas($tugas, $kosong, 'Komponen nilai belum lengkap', 'Masih ada siswa aktif yang belum memiliki nilai', 'notebook-pen', route('input-nilai.index'));
            $aksi->push(['label' => 'Input nilai', 'ikon' => 'notebook-pen', 'url' => route('input-nilai.index')]);
        }
        if ($akun->memilikiIzin('perangkat_ajar.upload')) {
            $aksi->push(['label' => 'Perangkat ajar', 'ikon' => 'folder-open', 'url' => route('perangkat-ajar-saya.index')]);
        }
    }

    private function supervisi(Pengguna $akun, CarbonInterface $hari, TahunPelajaran $tahun, Collection $agenda, Collection $tugas, Collection $aksi): void
    {
        if (! $this->aksesSupervisi->boleh($akun)) {
            return;
        }
        // A personal dashboard never inherits the manager's school-wide supervision scope.
        $sesi = $this->aksesSupervisi->sesi(SesiSupervisi::query(), $akun)
            ->whereNotNull('diterbitkan_pada')->where('status', 'terjadwal')
            ->where(fn ($q) => $q->where('guru_pegawai_id', $akun->pegawai_id)->orWhere('supervisor_pegawai_id', $akun->pegawai_id))
            ->whereHas('program', fn ($q) => $q->where('tahun_pelajaran_id', $tahun->id)->where('status', '<>', 'diarsipkan'))
            ->with(['program', 'guru', 'supervisor', 'praobservasi', 'penilaian', 'pascaobservasi', 'tindakLanjut'])->orderBy('waktu_mulai')->get();
        $tertunda = collect();
        foreach ($sesi as $s) {
            if ($s->waktu_mulai->isSameDay($hari)) {
                $agenda->push(['mulai' => $s->waktu_mulai->format('H:i'), 'selesai' => $s->waktu_selesai->format('H:i'),
                    'urut' => $s->waktu_mulai->format('H:i'), 'judul' => 'Supervisi akademik', 'detail' => $s->guru_pegawai_id === $akun->pegawai_id ? 'Supervisor: '.$s->supervisor?->nama_lengkap : 'Guru: '.$s->guru?->nama_lengkap,
                    'status' => 'Supervisi', 'warna' => 'teal', 'url' => route('supervisi.sesi.show', $s)]);
            }
            $pra = $this->pengisian->hak($akun, $s);
            $pasca = $this->pascaobservasi->hak($akun, $s);
            $tindakLanjutTertunda = $s->tindakLanjut->contains(function ($t) use ($s, $akun, $pasca) {
                $sejalan = $s->penilaian?->status === 'final' && $t->penilaian_versi === $s->penilaian->versi;
                $guru = $s->guru_pegawai_id === $akun->pegawai_id && $t->diterbitkan_pada
                    && $this->pascaobservasi->sejalan($s) && $s->pascaobservasi?->status === 'diterima'
                    && in_array($t->status, ['ditugaskan', 'dikembalikan'], true);

                return $sejalan && ($guru || ($pasca['kelola_rencana'] && $t->status === 'diajukan'));
            });
            if ($pra['isi_praobservasi'] || $pra['isi_penilaian'] || $pasca['isi_refleksi'] || $pasca['tinjau_refleksi'] || $pasca['sesuaikan_hasil'] || $tindakLanjutTertunda) {
                $tertunda->push($s);
            }
        }
        $this->tambahTugas($tugas, $tertunda->count(), 'Pengisian supervisi', 'Pengisian atau tindak lanjut sesi menunggu Anda', 'clipboard-check', route('supervisi.index'));
        if ($sesi->isNotEmpty() || $this->aksesSupervisi->global($akun)) {
            $aksi->push(['label' => 'Supervisi', 'ikon' => 'clipboard-check', 'url' => route('supervisi.index')]);
        }
    }
}
