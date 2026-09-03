<?php

namespace App\Http\Controllers;

use App\Models\AnggotaKelas;
use App\Models\JadwalKegiatanIbadah;
use App\Models\KegiatanIbadah;
use App\Models\Kelas;
use App\Models\PresensiKegiatanIbadah;
use App\Models\TahunPelajaran;
use App\Services\Ibadah\AksesScanKegiatanIbadah;
use App\Services\Ibadah\RekapHarianKegiatanIbadah;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RekapKegiatanIbadahController extends Controller
{
    public function index(
        Request $request,
        AksesScanKegiatanIbadah $akses,
        RekapHarianKegiatanIbadah $rekapHarian,
    ) {
        $data = $request->validate([
            'tanggal' => ['nullable', 'date', 'before_or_equal:today'],
            'kegiatan_ibadah_id' => ['nullable', 'integer', 'exists:kegiatan_ibadah,id'],
            'kelas_id' => ['nullable', 'integer', 'exists:kelas,id'],
            'status' => ['nullable', Rule::in(['semua', 'sudah', 'belum', 'berhalangan', 'tidak_hadir'])],
            'cari' => ['nullable', 'string', 'max:100'],
        ]);
        $tanggal = Carbon::parse($data['tanggal'] ?? now())->startOfDay();
        $tahunPelajaran = TahunPelajaran::query()
            ->where('aktif', true)
            ->orderByDesc('tanggal_mulai')
            ->first();

        abort_unless(
            $akses->dapatMelihatRekap($request->user(), $tahunPelajaran, $tanggal),
            403,
            'Rekap hanya dapat dibuka oleh wali kelas untuk kelasnya, guru PAI, guru piket pada hari terkait, atau pengelola kesiswaan.',
        );
        $cakupanKelasIds = $akses->cakupanKelasRekap($request->user(), $tahunPelajaran, $tanggal);

        $daftarKegiatan = KegiatanIbadah::query()
            ->orderByDesc('aktif')
            ->orderBy('nama')
            ->get();
        $kegiatanId = isset($data['kegiatan_ibadah_id']) && $daftarKegiatan->contains('id', (int) $data['kegiatan_ibadah_id'])
            ? (int) $data['kegiatan_ibadah_id']
            : $daftarKegiatan->firstWhere('aktif', true)?->id;
        $kegiatanDipilih = $daftarKegiatan->firstWhere('id', $kegiatanId);

        $daftarKelas = $tahunPelajaran
            ? Kelas::query()
                ->where('tahun_pelajaran_id', $tahunPelajaran->id)
                ->where('aktif', true)
                ->when($cakupanKelasIds !== null, fn ($query) => $query->whereIn('id', $cakupanKelasIds))
                ->withCount([
                    'anggotaKelas as jumlah_siswa' => fn ($query) => $query
                        ->where('status_keanggotaan', 'aktif')
                        ->whereHas('siswa', fn ($query) => $query->where('aktif', true)),
                ])
                ->orderBy('tingkat')
                ->orderBy('nama')
                ->get()
            : collect();
        $kelasDimintaId = isset($data['kelas_id']) ? (int) $data['kelas_id'] : null;
        abort_if(
            $kelasDimintaId && $cakupanKelasIds !== null && ! in_array($kelasDimintaId, $cakupanKelasIds, true),
            403,
            'Wali kelas hanya dapat membuka rekap kelas yang diampunya.',
        );
        $kelasId = isset($data['kelas_id']) && $daftarKelas->contains('id', (int) $data['kelas_id'])
            ? (int) $data['kelas_id']
            : null;
        $kelasDipilih = $daftarKelas->firstWhere('id', $kelasId);
        $status = $data['status'] ?? 'semua';
        $cari = trim((string) ($data['cari'] ?? ''));
        $tanggalString = $tanggal->toDateString();

        $hasilHarian = ($tahunPelajaran && $kegiatanId)
            ? $rekapHarian->hitung($tahunPelajaran, $daftarKelas, $kegiatanId, $tanggal)
            : ['status_per_siswa' => collect(), 'ringkasan_per_kelas' => collect()];
        $statusPerSiswa = $hasilHarian['status_per_siswa'];
        $ringkasanKelas = $daftarKelas->map(fn (Kelas $kelas) => [
            'kelas' => $kelas,
            ...($hasilHarian['ringkasan_per_kelas']->get((int) $kelas->id)
                ?? RekapHarianKegiatanIbadah::ringkasanKosong()),
        ]);
        $cakupan = $kelasId
            ? $ringkasanKelas->first(fn (array $item) => (int) $item['kelas']->id === $kelasId)
            : $this->jumlahkanRingkasan($ringkasanKelas);

        $anggotaKelas = null;
        $presensiPerSiswa = collect();

        if ($tahunPelajaran && $kegiatanId && $kelasId) {
            $anggotaKelas = AnggotaKelas::query()
                ->with(['siswa:id,nama_lengkap,nis,nisn,foto', 'kelas:id,nama'])
                ->where('tahun_pelajaran_id', $tahunPelajaran->id)
                ->where('kelas_id', $kelasId)
                ->where('status_keanggotaan', 'aktif')
                ->whereHas('siswa', function ($query) use ($cari) {
                    $query->where('aktif', true)
                        ->when($cari !== '', function ($query) use ($cari) {
                            $kataKunci = '%'.mb_strtolower($cari).'%';
                            $query->where(function ($query) use ($kataKunci) {
                                $query->whereRaw('LOWER(nama_lengkap) LIKE ?', [$kataKunci])
                                    ->orWhereRaw('LOWER(nis) LIKE ?', [$kataKunci])
                                    ->orWhereRaw('LOWER(nisn) LIKE ?', [$kataKunci]);
                            });
                        });
                })
                ->when($status !== 'semua', fn ($query) => $query->whereIn(
                    'siswa_id',
                    $statusPerSiswa->where('status', $status)->keys(),
                ))
                ->orderByRaw('nomor_absen IS NULL')
                ->orderBy('nomor_absen')
                ->orderBy('id')
                ->paginate(40)
                ->withQueryString();
            $presensiPerSiswa = PresensiKegiatanIbadah::query()
                ->with(['dipindaiOleh:id,nama', 'dikoreksiOleh:id,nama'])
                ->where('tahun_pelajaran_id', $tahunPelajaran->id)
                ->where('kegiatan_ibadah_id', $kegiatanId)
                ->whereDate('tanggal', $tanggalString)
                ->whereIn('siswa_id', $anggotaKelas->getCollection()->pluck('siswa_id'))
                ->get()
                ->keyBy('siswa_id');
        }

        $hari = array_keys(JadwalKegiatanIbadah::DAFTAR_HARI)[$tanggal->dayOfWeekIso - 1] ?? 'minggu';
        $jadwal = ($tahunPelajaran && $kegiatanId && $hari !== 'minggu')
            ? JadwalKegiatanIbadah::query()
                ->where('tahun_pelajaran_id', $tahunPelajaran->id)
                ->where('kegiatan_ibadah_id', $kegiatanId)
                ->where('hari', $hari)
                ->first()
            : null;

        return view('rekap-kegiatan-ibadah.index', [
            'tanggal' => $tanggalString,
            'tanggalLabel' => $tanggal->locale('id')->translatedFormat('l, d F Y'),
            'tahunPelajaran' => $tahunPelajaran,
            'daftarKegiatan' => $daftarKegiatan,
            'kegiatanId' => $kegiatanId,
            'kegiatanDipilih' => $kegiatanDipilih,
            'daftarKelas' => $daftarKelas,
            'kelasId' => $kelasId,
            'kelasDipilih' => $kelasDipilih,
            'status' => $status,
            'cari' => $cari,
            'ringkasanKelas' => $ringkasanKelas,
            'ringkasan' => $cakupan,
            'anggotaKelas' => $anggotaKelas,
            'presensiPerSiswa' => $presensiPerSiswa,
            'statusPerSiswa' => $statusPerSiswa,
            'jadwal' => $jadwal,
            'dapatScanSekarang' => $tanggal->isToday() && $akses->dapatMemindai($request->user(), $tahunPelajaran, now()),
            'dapatKoreksi' => $akses->dapatMengoreksi($request->user(), $tahunPelajaran, $tanggal),
        ]);
    }

    private function jumlahkanRingkasan($items): array
    {
        $ringkasan = RekapHarianKegiatanIbadah::ringkasanKosong();

        foreach (array_keys($ringkasan) as $kunci) {
            if ($kunci !== 'persentase') {
                $ringkasan[$kunci] = (int) $items->sum($kunci);
            }
        }

        $ringkasan['persentase'] = $ringkasan['wajib'] > 0
            ? (int) round(($ringkasan['sudah'] / $ringkasan['wajib']) * 100)
            : 0;

        return $ringkasan;
    }
}
