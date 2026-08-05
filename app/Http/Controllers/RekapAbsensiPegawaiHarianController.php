<?php

namespace App\Http\Controllers;

use App\Models\AbsensiPegawai;
use App\Models\Pegawai;
use App\Models\PengaturanAbsensiPegawai;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RekapAbsensiPegawaiHarianController extends Controller
{
    public function index(Request $request)
    {
        return $this->tampilkanRekap($request);
    }

    public function pribadi(Request $request)
    {
        return $this->tampilkanRekap($request, true);
    }

    private function tampilkanRekap(Request $request, bool $paksaPribadi = false)
    {
        $pengguna = $request->user();
        $cakupanAbsensiPegawaiPribadi = $paksaPribadi || ($pengguna?->membatasiCakupanAbsensiPegawai() ?? false);
        $pegawaiIdsTerjangkau = $cakupanAbsensiPegawaiPribadi ? $this->pegawaiIdsPribadi($request) : null;

        $aturan = [
            'tanggal' => ['nullable', 'date'],
        ];

        if (! $cakupanAbsensiPegawaiPribadi) {
            $aturan += [
                'kata_kunci' => ['nullable', 'string', 'max:100'],
                'jenis_pegawai' => ['nullable', 'string', 'max:100'],
                'pegawai_id' => ['nullable', 'integer', 'exists:pegawai,id'],
                'status_pegawai' => ['nullable', Rule::in(['semua', 'aktif', 'nonaktif'])],
                'status_kehadiran' => ['nullable', Rule::in(['semua', ...array_keys(AbsensiPegawai::DAFTAR_STATUS_KEHADIRAN)])],
            ];
        }

        $data = $request->validate($aturan);

        $tanggal = Carbon::parse($data['tanggal'] ?? now())->toDateString();
        $kataKunci = $cakupanAbsensiPegawaiPribadi ? '' : trim((string) ($data['kata_kunci'] ?? ''));
        $jenisPegawai = $cakupanAbsensiPegawaiPribadi ? '' : ($data['jenis_pegawai'] ?? '');
        $pegawaiId = $cakupanAbsensiPegawaiPribadi ? $request->user()?->pegawai_id : ($data['pegawai_id'] ?? null);
        $statusPegawai = $cakupanAbsensiPegawaiPribadi ? 'semua' : ($data['status_pegawai'] ?? 'aktif');
        $statusKehadiran = $cakupanAbsensiPegawaiPribadi ? 'semua' : ($data['status_kehadiran'] ?? 'semua');
        $daftarJenisPegawai = $cakupanAbsensiPegawaiPribadi
            ? collect()
            : Pegawai::query()
                ->whereNotNull('jenis_pegawai')
                ->where('jenis_pegawai', '!=', '')
                ->select('jenis_pegawai')
                ->distinct()
                ->orderBy('jenis_pegawai')
                ->pluck('jenis_pegawai');
        $daftarPegawai = $cakupanAbsensiPegawaiPribadi
            ? collect()
            : Pegawai::query()
                ->when($statusPegawai === 'aktif', fn ($query) => $query->where('aktif', true))
                ->when($statusPegawai === 'nonaktif', fn ($query) => $query->where('aktif', false))
                ->orderBy('nama_lengkap')
                ->get(['id', 'nama_lengkap', 'nip']);

        $pegawai = $this->ambilPegawai(
            kataKunci: $kataKunci,
            jenisPegawai: $jenisPegawai,
            pegawaiId: $pegawaiId,
            statusPegawai: $statusPegawai,
            pegawaiIdsTerjangkau: $pegawaiIdsTerjangkau,
        );

        $rekapSemua = $this->ambilRekapAbsensi($tanggal, $pegawai);
        $ringkasan = $this->hitungRingkasan($rekapSemua);
        $rekapAbsensi = $statusKehadiran === 'semua'
            ? $rekapSemua
            : $rekapSemua->where('status_kehadiran', $statusKehadiran)->values();

        return view('rekap-absensi-pegawai-harian.index', compact(
            'tanggal',
            'kataKunci',
            'jenisPegawai',
            'pegawaiId',
            'statusPegawai',
            'statusKehadiran',
            'daftarJenisPegawai',
            'daftarPegawai',
            'rekapAbsensi',
            'ringkasan',
            'cakupanAbsensiPegawaiPribadi',
        ))->with('halamanPribadi', $cakupanAbsensiPegawaiPribadi);
    }

    public function editKoreksi(Request $request, Pegawai $pegawai)
    {
        $data = $request->validate([
            'tanggal' => ['nullable', 'date'],
        ]);

        $tanggal = Carbon::parse($data['tanggal'] ?? now())->toDateString();
        $this->pastikanBolehAksesPegawai($request, $pegawai);
        $absensi = $this->ambilAbsensi($tanggal, $pegawai);
        $pengaturanAbsensiPegawai = $this->ambilPengaturanAbsensiPegawai($pegawai, $tanggal);

        return view('rekap-absensi-pegawai-harian.koreksi', compact(
            'tanggal',
            'pegawai',
            'absensi',
            'pengaturanAbsensiPegawai',
        ));
    }

    public function updateKoreksi(Request $request, Pegawai $pegawai)
    {
        $data = $request->validate([
            'tanggal' => ['required', 'date'],
            'status_kehadiran' => ['required', Rule::in(array_keys(AbsensiPegawai::DAFTAR_STATUS_KEHADIRAN))],
            'jam_masuk' => ['nullable', 'date_format:H:i'],
            'jam_pulang' => ['nullable', 'date_format:H:i'],
            'catatan' => ['nullable', 'string'],
        ]);

        $tanggal = Carbon::parse($data['tanggal'])->toDateString();
        $this->pastikanBolehAksesPegawai($request, $pegawai);
        $this->pastikanDataKoreksiValid($data);

        DB::transaction(function () use ($data, $tanggal, $pegawai) {
            $pengaturanAbsensiPegawai = $this->ambilPengaturanAbsensiPegawai($pegawai, $tanggal);
            $statusKehadiran = $data['status_kehadiran'];
            $jamMasuk = $statusKehadiran === 'hadir' ? ($data['jam_masuk'] ?? null) : null;
            $jamPulang = $statusKehadiran === 'hadir' ? ($data['jam_pulang'] ?? null) : null;
            $statusMasuk = null;
            $statusPulang = null;
            $menitTerlambat = 0;
            $menitPulangCepat = 0;

            if ($statusKehadiran === 'hadir') {
                [$statusMasuk, $menitTerlambat] = $this->hitungStatusMasuk($jamMasuk, $pengaturanAbsensiPegawai);
                [$statusPulang, $menitPulangCepat] = $this->hitungStatusPulang($jamPulang, $pengaturanAbsensiPegawai);
            }

            AbsensiPegawai::updateOrCreate(
                [
                    'tanggal' => $tanggal,
                    'pegawai_id' => $pegawai->id,
                ],
                [
                    'pengaturan_absensi_pegawai_id' => $pengaturanAbsensiPegawai?->id,
                    'jam_masuk' => $jamMasuk,
                    'status_masuk' => $statusMasuk,
                    'menit_terlambat' => $menitTerlambat,
                    'jam_pulang' => $jamPulang,
                    'status_pulang' => $statusPulang,
                    'menit_pulang_cepat' => $menitPulangCepat,
                    'status_kehadiran' => $statusKehadiran,
                    'sumber' => 'manual',
                    'catatan' => $data['catatan'] ?? null,
                ],
            );
        });

        return redirect()
            ->route('rekap-absensi-pegawai-harian.index', [
                'tanggal' => $tanggal,
                'pegawai_id' => $pegawai->id,
                'status_pegawai' => $pegawai->aktif ? 'aktif' : 'semua',
            ])
            ->with('berhasil', 'Koreksi absensi pegawai berhasil disimpan.');
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
                'jenis_kelamin',
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
                    $query->where('nama_lengkap', 'ilike', '%'.$kataKunci.'%')
                        ->orWhere('nip', 'ilike', '%'.$kataKunci.'%')
                        ->orWhere('jabatan_utama', 'ilike', '%'.$kataKunci.'%')
                        ->orWhere('jenis_pegawai', 'ilike', '%'.$kataKunci.'%');
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

    private function ambilRekapAbsensi(string $tanggal, $pegawai)
    {
        $absensi = AbsensiPegawai::query()
            ->with('pengaturanAbsensiPegawai:id,nama_jadwal,cakupan,jenis_pegawai,pegawai_id')
            ->whereDate('tanggal', $tanggal)
            ->whereIn('pegawai_id', $pegawai->pluck('id'))
            ->get()
            ->keyBy('pegawai_id');

        return $pegawai->map(function (Pegawai $pegawai) use ($absensi) {
            $absen = $absensi->get($pegawai->id);
            $statusKehadiran = $absen?->status_kehadiran ?? 'alfa';

            return [
                'pegawai' => $pegawai,
                'absensi' => $absen,
                'status_kehadiran' => $statusKehadiran,
                'status_sumber' => $absen ? 'catatan' : 'inferensi',
                'terlambat' => (int) ($absen?->menit_terlambat ?? 0),
                'pulang_cepat' => (int) ($absen?->menit_pulang_cepat ?? 0),
                'belum_pulang' => $statusKehadiran === 'hadir' && $absen?->jam_masuk && ! $absen?->jam_pulang,
            ];
        });
    }

    private function ambilAbsensi(string $tanggal, Pegawai $pegawai): ?AbsensiPegawai
    {
        return AbsensiPegawai::query()
            ->whereDate('tanggal', $tanggal)
            ->where('pegawai_id', $pegawai->id)
            ->first();
    }

    private function ambilPengaturanAbsensiPegawai(
        Pegawai $pegawai,
        string $tanggal,
    ): ?PengaturanAbsensiPegawai {
        $hari = $this->hariDariTanggal(Carbon::parse($tanggal)->isoWeekday());

        $jadwalPegawai = PengaturanAbsensiPegawai::query()
            ->where('hari', $hari)
            ->where('aktif', true)
            ->where('cakupan', 'pegawai')
            ->where('pegawai_id', $pegawai->id)
            ->first();

        if ($jadwalPegawai) {
            return $jadwalPegawai;
        }

        if (filled($pegawai->jenis_pegawai)) {
            $jadwalJenisPegawai = PengaturanAbsensiPegawai::query()
                ->where('hari', $hari)
                ->where('aktif', true)
                ->where('cakupan', 'jenis_pegawai')
                ->where('jenis_pegawai', $pegawai->jenis_pegawai)
                ->first();

            if ($jadwalJenisPegawai) {
                return $jadwalJenisPegawai;
            }
        }

        return PengaturanAbsensiPegawai::query()
            ->where('hari', $hari)
            ->where('aktif', true)
            ->where('cakupan', 'semua')
            ->first();
    }

    private function pastikanDataKoreksiValid(array $data): void
    {
        if ($data['status_kehadiran'] === 'hadir' && blank($data['jam_masuk'] ?? null)) {
            throw ValidationException::withMessages([
                'jam_masuk' => 'Jam masuk wajib diisi jika status kehadiran adalah hadir.',
            ]);
        }

        if (filled($data['jam_masuk'] ?? null) && filled($data['jam_pulang'] ?? null)) {
            if ($this->menitDariJam($data['jam_pulang']) < $this->menitDariJam($data['jam_masuk'])) {
                throw ValidationException::withMessages([
                    'jam_pulang' => 'Jam pulang tidak boleh lebih awal dari jam masuk.',
                ]);
            }
        }
    }

    private function hitungStatusMasuk(?string $jamMasuk, ?PengaturanAbsensiPegawai $pengaturanAbsensiPegawai): array
    {
        if (! $jamMasuk) {
            return [null, 0];
        }

        if (! $pengaturanAbsensiPegawai) {
            return ['manual', 0];
        }

        $menitTerlambat = max(0, $this->menitDariJam($jamMasuk) - $this->menitDariJam($pengaturanAbsensiPegawai->formatJam($pengaturanAbsensiPegawai->jam_masuk)));

        return [$menitTerlambat > 0 ? 'terlambat' : 'tepat_waktu', $menitTerlambat];
    }

    private function hitungStatusPulang(?string $jamPulang, ?PengaturanAbsensiPegawai $pengaturanAbsensiPegawai): array
    {
        if (! $jamPulang) {
            return [null, 0];
        }

        if (! $pengaturanAbsensiPegawai) {
            return ['manual', 0];
        }

        $menitPulangCepat = max(0, $this->menitDariJam($pengaturanAbsensiPegawai->formatJam($pengaturanAbsensiPegawai->jam_pulang)) - $this->menitDariJam($jamPulang));

        return [$menitPulangCepat > 0 ? 'pulang_cepat' : 'normal', $menitPulangCepat];
    }

    private function menitDariJam(string $jam): int
    {
        [$hour, $minute] = array_map('intval', explode(':', substr($jam, 0, 5)));

        return ($hour * 60) + $minute;
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

    private function hitungRingkasan($rekapAbsensi): array
    {
        return [
            'total' => $rekapAbsensi->count(),
            'hadir' => $rekapAbsensi->where('status_kehadiran', 'hadir')->count(),
            'izin' => $rekapAbsensi->where('status_kehadiran', 'izin')->count(),
            'sakit' => $rekapAbsensi->where('status_kehadiran', 'sakit')->count(),
            'dinas_luar' => $rekapAbsensi->where('status_kehadiran', 'dinas_luar')->count(),
            'cuti' => $rekapAbsensi->where('status_kehadiran', 'cuti')->count(),
            'alfa' => $rekapAbsensi->where('status_kehadiran', 'alfa')->count(),
            'terlambat' => $rekapAbsensi->where('terlambat', '>', 0)->count(),
            'pulang_cepat' => $rekapAbsensi->where('pulang_cepat', '>', 0)->count(),
            'belum_pulang' => $rekapAbsensi->where('belum_pulang', true)->count(),
        ];
    }
}
