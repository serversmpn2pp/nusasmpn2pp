<?php

namespace App\Services\Humas;

use App\Models\AgendaHumas;
use App\Models\AlumniHumas;
use App\Models\DokumenHumas;
use App\Models\KerjaSamaHumas;
use App\Models\KlipingBeritaHumas;
use App\Models\KunjunganTamu;
use App\Models\LaporanPelaksanaanHumas;
use App\Models\PengaduanHumas;
use App\Models\Pengguna;
use App\Models\PrestasiSekolah;
use App\Models\ProgramKerjaHumas;
use App\Models\PublikasiHumas;
use App\Models\TindakLanjutAgendaHumas;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

class DashboardHumasService
{
    public function ringkasan(Pengguna $akun, CarbonImmutable $mulai, CarbonImmutable $selesai, ?int $tahunId): array
    {
        $akun->loadMissing('daftarPeran.izin');
        $metrik = $perhatian = $distribusi = $sumberBulanan = [];
        $program = $agenda = collect();
        $jumlahProgram = 0;
        $tambah = function (string $kode, string $label, int $jumlah, string $dasar, string $url, string $warna = 'blue') use (&$metrik) {
            $metrik[$kode] = compact('label', 'jumlah', 'dasar', 'url', 'warna');
        };
        $ingat = function (string $label, int $jumlah, string $url, string $warna = 'warning') use (&$perhatian) {
            if ($jumlah > 0) {
                $perhatian[] = compact('label', 'jumlah', 'url', 'warna');
            }
        };

        if ($akun->memilikiIzin(['program_kerja_humas.lihat', 'program_kerja_humas.kelola'])) {
            $query = ProgramKerjaHumas::whereDate('tanggal_mulai', '<=', $selesai)->whereDate('tanggal_selesai', '>=', $mulai)
                ->when($tahunId, fn ($q) => $q->where('tahun_pelajaran_id', $tahunId));
            $status = $this->status($query, ProgramKerjaHumas::STATUS);
            $jumlahProgram = array_sum($status);
            $tambah('program', 'Program selesai', $status['selesai'], 'Dari '.$jumlahProgram.' program yang beririsan dengan periode', route('program-kerja-humas.index'), 'green');
            $distribusi['program'] = ['judul' => 'Status program kerja', 'label' => ProgramKerjaHumas::STATUS, 'jumlah' => $status];
            $laporan = $this->periode(LaporanPelaksanaanHumas::whereHas('program', fn ($q) => $q->where('status', '!=', 'dibatalkan')
                ->when($tahunId, fn ($q) => $q->where('tahun_pelajaran_id', $tahunId))), 'tanggal_selesai', $mulai, $selesai)->where('status', 'final');
            $tambah('laporan', 'Laporan kegiatan final', (clone $laporan)->count(), 'Tanggal selesai kegiatan; program tidak dibatalkan', route('program-kerja-humas.index'), 'teal');
            $sumberBulanan['laporan'] = ['label' => 'Laporan final', 'query' => $laporan, 'kolom' => 'tanggal_selesai'];
            $queryProgram = (clone $query)->withCount(['laporanFinal as realisasi_periode' => fn ($q) => $this->periode($q, 'tanggal_selesai', $mulai, $selesai)]);
            $program = $queryProgram->orderByRaw("CASE WHEN status IN ('rencana', 'berjalan') THEN 0 ELSE 1 END")
                ->orderBy('tanggal_selesai')->orderBy('id')->limit(8)->get(['id', 'nama', 'status', 'target_kegiatan', 'tanggal_selesai']);
            $ingat('Program melewati target penyelesaian', ProgramKerjaHumas::whereIn('status', ['rencana', 'berjalan'])->whereDate('tanggal_selesai', '<', today())->count(),
                route('program-kerja-humas.index', ['terlambat' => 1]), 'danger');
            $ingat('Laporan kegiatan masih draf', LaporanPelaksanaanHumas::where('status', 'draf')->whereHas('program', fn ($q) => $q->where('status', '!=', 'dibatalkan'))->count(), route('program-kerja-humas.index'));
        }
        if ($akun->memilikiIzin(['agenda_humas.lihat', 'agenda_humas.kelola'])) {
            $query = $this->periode(AgendaHumas::query(), 'waktu_mulai', $mulai, $selesai);
            $status = $this->status($query, AgendaHumas::STATUS);
            $tambah('agenda', 'Pertemuan selesai', $status['selesai'], 'Tanggal mulai agenda; status selesai', route('agenda-humas.index'), 'green');
            $distribusi['agenda'] = ['judul' => 'Status agenda', 'label' => AgendaHumas::STATUS, 'jumlah' => $status];
            $sumberBulanan['agenda'] = ['label' => 'Pertemuan selesai', 'query' => (clone $query)->where('status', 'selesai'), 'kolom' => 'waktu_mulai'];
            $agenda = AgendaHumas::where('status', 'terjadwal')->where('waktu_selesai', '>=', now())->where('waktu_mulai', '<=', now()->addDays(14))
                ->orderBy('waktu_mulai')->orderBy('id')->limit(6)->get(['id', 'judul', 'jenis', 'waktu_mulai', 'waktu_selesai', 'tempat']);
            $ingat('Agenda lewat jadwal belum ditutup', AgendaHumas::where('status', 'terjadwal')->where('waktu_selesai', '<', now())->count(), route('agenda-humas.index'), 'danger');
            $ingat('Tindak lanjut rapat melewati tenggat', TindakLanjutAgendaHumas::where('status', '!=', 'selesai')->whereDate('batas_tanggal', '<', today())
                ->whereHas('agenda', fn ($q) => $q->where('status', '!=', 'dibatalkan'))->count(), route('agenda-humas.index'), 'danger');
        }
        if ($akun->memilikiIzin(['kemitraan_humas.lihat', 'kemitraan_humas.kelola'])) {
            $query = $this->periode(KerjaSamaHumas::whereIn('status', ['aktif', 'diakhiri']), 'tanggal_mulai', $mulai, $selesai);
            $tambah('kemitraan', 'Kerja sama dimulai', (clone $query)->count(), 'Tanggal mulai kerja sama; draf tidak dihitung', route('kemitraan-humas.index', ['tab' => 'mou']), 'teal');
            $ingat('MoU segera berakhir', KerjaSamaHumas::denganStatusBerlaku('segera_berakhir')->count(), route('kemitraan-humas.index', ['tab' => 'mou', 'masa_berlaku' => 'segera_berakhir']));
            $ingat('MoU kedaluwarsa belum diakhiri', KerjaSamaHumas::denganStatusBerlaku('kedaluwarsa')->count(), route('kemitraan-humas.index', ['tab' => 'mou', 'masa_berlaku' => 'kedaluwarsa']), 'danger');
        }
        if ($akun->memilikiIzin(PublikasiHumas::IZIN_LIHAT)) {
            $query = $this->periode(PublikasiHumas::where('status', 'tayang'), 'waktu_tayang', $mulai, $selesai);
            $tambah('publikasi', 'Publikasi tayang', (clone $query)->count(), 'Tanggal tayang; persetujuan saja belum dihitung', route('publikasi-humas.index', ['status' => 'tayang']), 'blue');
            $sumberBulanan['publikasi'] = ['label' => 'Publikasi tayang', 'query' => $query, 'kolom' => 'waktu_tayang'];
            $ingat('Konten menunggu pemeriksaan', PublikasiHumas::where('status', 'diajukan')->count(), route('publikasi-humas.index', ['status' => 'diajukan']));
            $ingat('Konten perlu revisi', PublikasiHumas::where('status', 'revisi')->count(), route('publikasi-humas.index', ['status' => 'revisi']));
        }
        if ($akun->memilikiIzin(['prestasi_sekolah.lihat', 'prestasi_sekolah.kelola'])) {
            $query = $this->periode(PrestasiSekolah::where('status', 'terverifikasi'), 'tanggal_prestasi', $mulai, $selesai);
            $tambah('prestasi', 'Prestasi terverifikasi', (clone $query)->count(), 'Tanggal prestasi; draf dan arsip tidak dihitung', route('prestasi-sekolah.index', ['status' => 'terverifikasi']), 'violet');
            $sumberBulanan['prestasi'] = ['label' => 'Prestasi', 'query' => $query, 'kolom' => 'tanggal_prestasi'];
            $ingat('Prestasi masih draf', PrestasiSekolah::where('status', 'draf')->count(), route('prestasi-sekolah.index', ['status' => 'draf']));
        }
        if ($akun->memilikiIzin(['buku_tamu.lihat', 'buku_tamu.kelola'])) {
            $query = $this->periode(KunjunganTamu::where('status', '!=', 'dibatalkan'), 'waktu_datang', $mulai, $selesai);
            $tambah('tamu', 'Kunjungan tamu', (clone $query)->count(), 'Tanggal kedatangan; kunjungan batal tidak dihitung', route('buku-tamu.index', ['tab' => 'rekap']), 'blue');
            $sumberBulanan['tamu'] = ['label' => 'Kunjungan', 'query' => $query, 'kolom' => 'waktu_datang'];
        }
        if ($akun->memilikiIzin(PengaduanHumas::IZIN)) {
            $query = PengaduanHumas::untukPengguna($akun);
            $diterima = $this->periode(clone $query, 'tanggal_diterima', $mulai, $selesai);
            $ditangani = $this->periode((clone $query)->where('status', 'selesai'), 'diselesaikan_pada', $mulai, $selesai);
            $tambah('pengaduan_masuk', 'Aspirasi / aduan diterima', (clone $diterima)->count(), 'Tanggal diterima; sesuai cakupan akun', route('pengaduan-humas.index'), 'amber');
            $tambah('pengaduan_selesai', 'Aspirasi / aduan selesai', $ditangani->count(), 'Tanggal penyelesaian; tiket ditutup tidak dihitung', route('pengaduan-humas.index', ['status' => 'selesai']), 'green');
            $sumberBulanan['pengaduan'] = ['label' => 'Tiket diterima', 'query' => $diterima, 'kolom' => 'tanggal_diterima'];
            // The dashboard exposes ticket counts only, never titles, messages, or reporter identities.
            $ingat('Aspirasi / aduan melewati tenggat', (clone $query)->whereIn('status', PengaduanHumas::AKTIF)->whereDate('batas_tanggal', '<', today())->count(), route('pengaduan-humas.index', ['batas' => 'lewat']), 'danger');
            $ingat('Aspirasi / aduan baru belum ditugaskan', (clone $query)->where('status', 'baru')->count(), route('pengaduan-humas.index', ['status' => 'baru']));
        }
        if ($akun->memilikiIzin(KlipingBeritaHumas::IZIN_LIHAT)) {
            $query = $this->periode(KlipingBeritaHumas::where('status', 'aktif'), 'tanggal_terbit', $mulai, $selesai);
            $tambah('kliping', 'Pemberitaan media luar', $query->count(), 'Tanggal terbit; kliping aktif', route('kliping-berita-humas.index'), 'violet');
        }
        if ($akun->memilikiIzin(['alumni_humas.lihat', 'alumni_humas.kelola'])) {
            $query = $this->periode(AlumniHumas::where('status', 'aktif'), 'tanggal_lulus', $mulai, $selesai);
            $tambah('alumni', 'Alumni lulus', $query->count(), 'Tanggal kelulusan yang tercatat; alumni aktif', route('alumni-humas.index'), 'teal');
            $ingat('Alumni belum memiliki tanggal kelulusan', AlumniHumas::where('status', 'aktif')->whereNull('tanggal_lulus')->count(), route('alumni-humas.index'));
        }
        if ($akun->memilikiIzin(['dokumen_humas.lihat', 'dokumen_humas.kelola'])) {
            $ingat('Dokumen aktif sudah kedaluwarsa', DokumenHumas::where('status', 'aktif')->whereDate('berlaku_sampai', '<', today())->count(), route('dokumen-humas.index'), 'danger');
        }

        $bulan = [];
        for ($tanggal = $mulai->startOfMonth(); $tanggal->lte($selesai); $tanggal = $tanggal->addMonth()) {
            $bulan[$tanggal->format('Y-m')] = ['label' => $tanggal->locale('id')->translatedFormat('M Y'), 'jumlah' => []];
        }
        foreach ($sumberBulanan as $kode => $sumber) {
            $ekspresi = 'SUBSTR(CAST('.$sumber['kolom'].' AS TEXT), 1, 7)';
            $jumlah = (clone $sumber['query'])->reorder()->selectRaw($ekspresi.' AS bulan, COUNT(*) AS jumlah')
                ->groupByRaw($ekspresi)->pluck('jumlah', 'bulan');
            foreach ($bulan as $key => &$baris) {
                $baris['jumlah'][$kode] = (int) ($jumlah[$key] ?? 0);
            }
            unset($baris);
        }

        return ['metrik' => $metrik, 'perhatian' => $perhatian, 'distribusi' => $distribusi,
            'program' => $program, 'jumlahProgram' => $jumlahProgram, 'agenda' => $agenda,
            'kolomBulanan' => array_map(fn ($s) => $s['label'], $sumberBulanan), 'bulan' => $bulan];
    }

    private function periode(Builder $query, string $kolom, CarbonImmutable $mulai, CarbonImmutable $selesai): Builder
    {
        if (str_starts_with($kolom, 'tanggal_')) {
            return $query->whereDate($kolom, '>=', $mulai->toDateString())->whereDate($kolom, '<=', $selesai->toDateString());
        }

        return $query->whereBetween($kolom, [$mulai->startOfDay()->toDateTimeString(), $selesai->endOfDay()->toDateTimeString()]);
    }

    private function status(Builder $query, array $label): array
    {
        $jumlah = (clone $query)->reorder()->selectRaw('status, COUNT(*) AS jumlah')->groupBy('status')->pluck('jumlah', 'status');

        return collect($label)->map(fn ($nama, $kode) => (int) ($jumlah[$kode] ?? 0))->all();
    }
}
