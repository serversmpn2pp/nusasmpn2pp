<?php

namespace App\Http\Controllers;

use App\Models\AbsensiPegawai;
use App\Models\Pegawai;
use App\Models\PengaturanAbsensiPegawai;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

class LaporanAbsensiPegawaiBulananController extends Controller
{
    public function index(Request $request)
    {
        return view('laporan-absensi-pegawai-bulanan.index', $this->bangunDataLaporan($request));
    }

    public function cetak(Request $request)
    {
        return view('laporan-absensi-pegawai-bulanan.cetak', $this->bangunDataLaporan($request));
    }

    public function cetakPegawai(Request $request, Pegawai $pegawai)
    {
        return view('laporan-absensi-pegawai-bulanan.cetak', $this->bangunDataLaporan($request, $pegawai));
    }

    private function bangunDataLaporan(Request $request, ?Pegawai $pegawaiCetak = null): array
    {
        $pengguna = $request->user();
        $cakupanAbsensiPegawaiPribadi = $pengguna?->membatasiCakupanAbsensiPegawai() ?? false;
        $pegawaiIdsTerjangkau = $cakupanAbsensiPegawaiPribadi ? $this->pegawaiIdsPribadi($request) : null;

        if ($pegawaiCetak) {
            $this->pastikanBolehAksesPegawai($request, $pegawaiCetak);
        }

        $data = $request->validate($this->aturanFilter());
        $bulan = $data['bulan'] ?? now()->format('Y-m');
        $bulanCarbon = Carbon::createFromFormat('Y-m', $bulan)->startOfMonth();
        $tanggalMulai = $bulanCarbon->copy()->startOfMonth();
        $tanggalSelesai = $bulanCarbon->copy()->endOfMonth();
        $kataKunci = $cakupanAbsensiPegawaiPribadi ? '' : trim((string) ($data['kata_kunci'] ?? ''));
        $jenisPegawai = $cakupanAbsensiPegawaiPribadi ? '' : ($data['jenis_pegawai'] ?? '');
        $pegawaiId = $cakupanAbsensiPegawaiPribadi ? $request->user()?->pegawai_id : ($data['pegawai_id'] ?? null);
        $statusPegawai = $cakupanAbsensiPegawaiPribadi ? 'semua' : ($data['status_pegawai'] ?? 'aktif');
        $tanggalPeriode = $this->tanggalPeriode($tanggalMulai, $tanggalSelesai);

        $daftarJenisPegawai = Pegawai::query()
            ->when(is_array($pegawaiIdsTerjangkau), fn ($query) => $query->whereIn('id', $pegawaiIdsTerjangkau))
            ->whereNotNull('jenis_pegawai')
            ->where('jenis_pegawai', '!=', '')
            ->select('jenis_pegawai')
            ->distinct()
            ->orderBy('jenis_pegawai')
            ->pluck('jenis_pegawai');

        $daftarPegawai = Pegawai::query()
            ->when(is_array($pegawaiIdsTerjangkau), fn ($query) => $query->whereIn('id', $pegawaiIdsTerjangkau))
            ->when($statusPegawai === 'aktif', fn ($query) => $query->where('aktif', true))
            ->when($statusPegawai === 'nonaktif', fn ($query) => $query->where('aktif', false))
            ->orderBy('nama_lengkap')
            ->get(['id', 'nama_lengkap', 'nip']);

        $pegawai = $pegawaiCetak
            ? collect([$pegawaiCetak])
            : $this->ambilPegawai(
                kataKunci: $kataKunci,
                jenisPegawai: $jenisPegawai,
                pegawaiId: $pegawaiId,
                statusPegawai: $statusPegawai,
                pegawaiIdsTerjangkau: $pegawaiIdsTerjangkau,
            );

        $jadwalAbsensiPegawai = PengaturanAbsensiPegawai::query()
            ->where('aktif', true)
            ->get();
        $absensiPerPegawai = $this->ambilAbsensiPerPegawai($tanggalMulai, $tanggalSelesai, $pegawai);
        $laporanAbsensiPegawai = $this->ambilLaporanAbsensiPegawai(
            pegawai: $pegawai,
            tanggalPeriode: $tanggalPeriode,
            jadwalAbsensiPegawai: $jadwalAbsensiPegawai,
            absensiPerPegawai: $absensiPerPegawai,
        );
        $ringkasan = $this->hitungRingkasan($laporanAbsensiPegawai);
        $labelPeriode = $bulanCarbon->copy()->locale('id')->translatedFormat('F Y');

        return compact(
            'bulan',
            'labelPeriode',
            'kataKunci',
            'jenisPegawai',
            'pegawaiId',
            'statusPegawai',
            'daftarJenisPegawai',
            'daftarPegawai',
            'laporanAbsensiPegawai',
            'ringkasan',
            'cakupanAbsensiPegawaiPribadi',
        ) + [
            'tanggalCetak' => now()->copy()->locale('id')->translatedFormat('d F Y'),
            'jumlahLembar' => $laporanAbsensiPegawai->count(),
        ];
    }

    private function aturanFilter(): array
    {
        return [
            'bulan' => ['nullable', 'date_format:Y-m'],
            'kata_kunci' => ['nullable', 'string', 'max:100'],
            'jenis_pegawai' => ['nullable', 'string', 'max:100'],
            'pegawai_id' => ['nullable', 'integer', 'exists:pegawai,id'],
            'status_pegawai' => ['nullable', Rule::in(['semua', 'aktif', 'nonaktif'])],
        ];
    }

    private function ambilPegawai(
        string $kataKunci,
        string $jenisPegawai,
        ?int $pegawaiId,
        string $statusPegawai,
        ?array $pegawaiIdsTerjangkau = null,
    ) {
        return Pegawai::query()
            ->select([
                'id',
                'nama_lengkap',
                'nip',
                'foto',
                'jenis_pegawai',
                'jabatan_utama',
                'status_kepegawaian',
                'aktif',
            ])
            ->when(is_array($pegawaiIdsTerjangkau), fn ($query) => $query->whereIn('id', $pegawaiIdsTerjangkau))
            ->when($statusPegawai === 'aktif', fn ($query) => $query->where('aktif', true))
            ->when($statusPegawai === 'nonaktif', fn ($query) => $query->where('aktif', false))
            ->when($jenisPegawai !== '', fn ($query) => $query->where('jenis_pegawai', $jenisPegawai))
            ->when($pegawaiId, fn ($query) => $query->whereKey($pegawaiId))
            ->when($kataKunci !== '', function ($query) use ($kataKunci) {
                $query->where(function ($query) use ($kataKunci) {
                    $query->where('nama_lengkap', 'ilike', '%' . $kataKunci . '%')
                        ->orWhere('nip', 'ilike', '%' . $kataKunci . '%')
                        ->orWhere('jabatan_utama', 'ilike', '%' . $kataKunci . '%')
                        ->orWhere('jenis_pegawai', 'ilike', '%' . $kataKunci . '%');
                });
            })
            ->orderBy('nama_lengkap')
            ->get();
    }

    private function pegawaiIdsPribadi(Request $request): array
    {
        abort_unless($request->user()?->pegawai_id, 403);

        return [(int) $request->user()->pegawai_id];
    }

    private function pastikanBolehAksesPegawai(Request $request, Pegawai $pegawai): void
    {
        abort_unless(
            $request->user()?->dapatMengaksesAbsensiPegawai($pegawai->id) ?? false,
            403,
        );
    }

    private function ambilAbsensiPerPegawai(Carbon $tanggalMulai, Carbon $tanggalSelesai, Collection $pegawai): Collection
    {
        if ($pegawai->isEmpty()) {
            return collect();
        }

        return AbsensiPegawai::query()
            ->whereBetween('tanggal', [$tanggalMulai->toDateString(), $tanggalSelesai->toDateString()])
            ->whereIn('pegawai_id', $pegawai->pluck('id'))
            ->get()
            ->groupBy('pegawai_id')
            ->map(fn (Collection $absensi) => $absensi->keyBy(fn (AbsensiPegawai $item) => $item->tanggal->toDateString()));
    }

    private function ambilLaporanAbsensiPegawai(
        Collection $pegawai,
        Collection $tanggalPeriode,
        Collection $jadwalAbsensiPegawai,
        Collection $absensiPerPegawai,
    ): Collection {
        return $pegawai->map(function (Pegawai $pegawai) use ($tanggalPeriode, $jadwalAbsensiPegawai, $absensiPerPegawai) {
            $absensi = $absensiPerPegawai->get($pegawai->id, collect());
            $tanggalEfektif = collect();

            foreach ($tanggalPeriode as $tanggal) {
                $hari = $this->hariDariTanggal(Carbon::parse($tanggal)->isoWeekday());
                $jadwal = $this->ambilJadwalPegawai($pegawai, $hari, $jadwalAbsensiPegawai);

                if ($jadwal) {
                    $tanggalEfektif->put($tanggal, $jadwal);
                }
            }

            foreach ($absensi->keys() as $tanggalAbsensi) {
                if (! $tanggalEfektif->has($tanggalAbsensi)) {
                    $hari = $this->hariDariTanggal(Carbon::parse($tanggalAbsensi)->isoWeekday());
                    $tanggalEfektif->put($tanggalAbsensi, $this->ambilJadwalPegawai($pegawai, $hari, $jadwalAbsensiPegawai));
                }
            }

            $tanggalEfektif = $tanggalEfektif->sortKeys();

            $hitung = [
                'hadir' => 0,
                'izin' => 0,
                'sakit' => 0,
                'dinas_luar' => 0,
                'cuti' => 0,
                'alfa' => 0,
            ];
            $terlambat = 0;
            $menitTerlambat = 0;
            $pulangCepat = 0;
            $menitPulangCepat = 0;
            $belumPulang = 0;
            $manual = 0;
            $rincian = collect();

            foreach ($tanggalEfektif as $tanggal => $jadwal) {
                $catatan = $absensi->get($tanggal);
                $status = $catatan?->status_kehadiran ?? 'alfa';

                if (array_key_exists($status, $hitung)) {
                    $hitung[$status]++;
                }

                if ((int) ($catatan?->menit_terlambat ?? 0) > 0) {
                    $terlambat++;
                    $menitTerlambat += (int) $catatan->menit_terlambat;
                }

                if ((int) ($catatan?->menit_pulang_cepat ?? 0) > 0) {
                    $pulangCepat++;
                    $menitPulangCepat += (int) $catatan->menit_pulang_cepat;
                }

                if ($status === 'hadir' && $catatan?->jam_masuk && ! $catatan?->jam_pulang) {
                    $belumPulang++;
                }

                if ($catatan?->sumber === 'manual') {
                    $manual++;
                }

                $rincian->push($this->barisRincian($tanggal, $jadwal, $catatan, $status));
            }

            $hariEfektif = $tanggalEfektif->count();

            return [
                'pegawai' => $pegawai,
                'rincian' => $rincian,
                'hari_efektif' => $hariEfektif,
                'hadir' => $hitung['hadir'],
                'izin' => $hitung['izin'],
                'sakit' => $hitung['sakit'],
                'dinas_luar' => $hitung['dinas_luar'],
                'cuti' => $hitung['cuti'],
                'alfa' => $hitung['alfa'],
                'terlambat' => $terlambat,
                'menit_terlambat' => $menitTerlambat,
                'pulang_cepat' => $pulangCepat,
                'menit_pulang_cepat' => $menitPulangCepat,
                'belum_pulang' => $belumPulang,
                'manual' => $manual,
                'persentase_hadir' => $hariEfektif > 0 ? round(($hitung['hadir'] / $hariEfektif) * 100, 1) : 0,
            ];
        });
    }

    private function barisRincian(
        string $tanggal,
        ?PengaturanAbsensiPegawai $jadwal,
        ?AbsensiPegawai $absensi,
        string $status,
    ): array {
        $tanggalCarbon = Carbon::parse($tanggal);

        return [
            'tanggal' => $tanggalCarbon,
            'hari' => PengaturanAbsensiPegawai::DAFTAR_HARI[$this->hariDariTanggal($tanggalCarbon->isoWeekday())]['label'] ?? '-',
            'jadwal' => $jadwal,
            'jam_jadwal' => $jadwal
                ? $jadwal->formatJam($jadwal->jam_masuk) . ' - ' . $jadwal->formatJam($jadwal->jam_pulang)
                : '-',
            'absensi' => $absensi,
            'status_kehadiran' => $status,
            'label_status' => AbsensiPegawai::DAFTAR_STATUS_KEHADIRAN[$status] ?? ucfirst($status),
            'jam_masuk' => $this->formatJam($absensi?->jam_masuk),
            'menit_terlambat' => (int) ($absensi?->menit_terlambat ?? 0),
            'jam_pulang' => $this->formatJam($absensi?->jam_pulang),
            'menit_pulang_cepat' => (int) ($absensi?->menit_pulang_cepat ?? 0),
            'keterangan' => $this->keteranganRincian($absensi, $status),
        ];
    }

    private function keteranganRincian(?AbsensiPegawai $absensi, string $status): string
    {
        if (filled($absensi?->catatan)) {
            return $absensi->catatan;
        }

        if (! $absensi) {
            return 'Belum ada scan atau koreksi.';
        }

        if ($status === 'hadir' && $absensi->jam_masuk && ! $absensi->jam_pulang) {
            return 'Belum scan pulang.';
        }

        if ($absensi->sumber === 'manual') {
            return 'Koreksi manual.';
        }

        return $status === 'hadir' ? 'Scan tercatat.' : '-';
    }

    private function ambilJadwalPegawai(Pegawai $pegawai, string $hari, Collection $jadwalAbsensiPegawai): ?PengaturanAbsensiPegawai
    {
        $jadwalPegawai = $jadwalAbsensiPegawai->first(fn (PengaturanAbsensiPegawai $jadwal) => $jadwal->hari === $hari
            && $jadwal->cakupan === 'pegawai'
            && (int) $jadwal->pegawai_id === (int) $pegawai->id);

        if ($jadwalPegawai) {
            return $jadwalPegawai;
        }

        if (filled($pegawai->jenis_pegawai)) {
            $jadwalJenisPegawai = $jadwalAbsensiPegawai->first(fn (PengaturanAbsensiPegawai $jadwal) => $jadwal->hari === $hari
                && $jadwal->cakupan === 'jenis_pegawai'
                && $jadwal->jenis_pegawai === $pegawai->jenis_pegawai);

            if ($jadwalJenisPegawai) {
                return $jadwalJenisPegawai;
            }
        }

        return $jadwalAbsensiPegawai->first(fn (PengaturanAbsensiPegawai $jadwal) => $jadwal->hari === $hari
            && $jadwal->cakupan === 'semua');
    }

    private function hitungRingkasan(Collection $laporanAbsensiPegawai): array
    {
        return [
            'pegawai' => $laporanAbsensiPegawai->count(),
            'hari_efektif' => $laporanAbsensiPegawai->sum('hari_efektif'),
            'hadir' => $laporanAbsensiPegawai->sum('hadir'),
            'izin' => $laporanAbsensiPegawai->sum('izin'),
            'sakit' => $laporanAbsensiPegawai->sum('sakit'),
            'dinas_luar' => $laporanAbsensiPegawai->sum('dinas_luar'),
            'cuti' => $laporanAbsensiPegawai->sum('cuti'),
            'alfa' => $laporanAbsensiPegawai->sum('alfa'),
            'terlambat' => $laporanAbsensiPegawai->sum('terlambat'),
            'menit_terlambat' => $laporanAbsensiPegawai->sum('menit_terlambat'),
            'pulang_cepat' => $laporanAbsensiPegawai->sum('pulang_cepat'),
            'menit_pulang_cepat' => $laporanAbsensiPegawai->sum('menit_pulang_cepat'),
            'belum_pulang' => $laporanAbsensiPegawai->sum('belum_pulang'),
            'manual' => $laporanAbsensiPegawai->sum('manual'),
            'rata_persentase_hadir' => $laporanAbsensiPegawai->count()
                ? round((float) $laporanAbsensiPegawai->avg('persentase_hadir'), 1)
                : 0,
        ];
    }

    private function tanggalPeriode(Carbon $tanggalMulai, Carbon $tanggalSelesai): Collection
    {
        return collect(CarbonPeriod::create($tanggalMulai->toDateString(), $tanggalSelesai->toDateString()))
            ->map(fn (Carbon $tanggal) => $tanggal->toDateString());
    }

    private function formatJam(?string $jam): string
    {
        return $jam ? substr($jam, 0, 5) : '-';
    }

    private function hariDariTanggal(int $isoWeekday): string
    {
        return [
            1 => 'senin',
            2 => 'selasa',
            3 => 'rabu',
            4 => 'kamis',
            5 => 'jumat',
            6 => 'sabtu',
            7 => 'minggu',
        ][$isoWeekday];
    }
}
