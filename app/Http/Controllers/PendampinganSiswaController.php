<?php

namespace App\Http\Controllers;

use App\Models\Kelas;
use App\Models\Pegawai;
use App\Models\PendampinganSiswa;
use App\Models\PeringatanDiniSiswa;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use App\Services\Pembinaan\AksesRekapPoinSiswaService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PendampinganSiswaController extends Controller
{
    public function __construct(private AksesRekapPoinSiswaService $akses) {}

    public function index(Request $request)
    {
        $konteksGuruWali = $request->routeIs('pendampingan-siswa-wali.*');
        $tahunPelajaranId = $this->inputId($request, 'tahun_pelajaran_id')
            ?? TahunPelajaran::where('aktif', true)->latest('tanggal_mulai')->value('id');
        $kelasId = $this->inputId($request, 'kelas_id');
        $status = $this->nilaiPilihan($request, 'status', array_keys(PendampinganSiswa::DAFTAR_STATUS), 'dalam_proses');
        $kataKunci = trim((string) $request->input('kata_kunci', ''));

        $cakupanSiswa = Siswa::query()
            ->when($kelasId, fn (Builder $query) => $query->whereHas('anggotaKelas', fn (Builder $query) => $query
                ->where('kelas_id', $kelasId)
                ->where('status_keanggotaan', 'aktif')
                ->when($tahunPelajaranId, fn (Builder $query) => $query->where('tahun_pelajaran_id', $tahunPelajaranId))))
            ->when($kataKunci !== '', fn (Builder $query) => $query->where(function (Builder $query) use ($kataKunci) {
                $query->where('nama_lengkap', 'ilike', '%'.$kataKunci.'%')
                    ->orWhere('nisn', 'ilike', '%'.$kataKunci.'%')
                    ->orWhere('nis', 'ilike', '%'.$kataKunci.'%');
            }));
        $this->akses->terapkanCakupan(
            $cakupanSiswa,
            $request->user(),
            $tahunPelajaranId,
            $konteksGuruWali ? 'guru_wali' : null,
        );
        $siswaIds = $cakupanSiswa->pluck('siswa.id');

        $cakupanPendampingan = PendampinganSiswa::query()
            ->whereIn('siswa_id', $siswaIds)
            ->when($tahunPelajaranId, fn (Builder $query) => $query->where('tahun_pelajaran_id', $tahunPelajaranId));

        $ringkasan = [
            'dalam_proses' => (clone $cakupanPendampingan)->where('status', 'dalam_proses')->count(),
            'selesai' => (clone $cakupanPendampingan)->where('status', 'selesai')->count(),
        ];

        $daftarPendampingan = (clone $cakupanPendampingan)
            ->with([
                'siswa' => fn ($query) => $query->with([
                    'anggotaKelas' => fn ($query) => $query
                        ->when($tahunPelajaranId, fn ($query) => $query->where('tahun_pelajaran_id', $tahunPelajaranId))
                        ->where('status_keanggotaan', 'aktif')
                        ->with('kelas:id,nama'),
                ]),
                'petugasPegawai:id,nama_lengkap,nip',
                'peringatanDiniSiswa:id,jenis,judul',
            ])
            ->when($status, fn (Builder $query) => $query->where('status', $status))
            ->orderByRaw("CASE WHEN status = 'dalam_proses' THEN 0 ELSE 1 END")
            ->latest('tanggal_tindak_lanjut')
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        $daftarTahunPelajaran = TahunPelajaran::orderByDesc('aktif')->orderByDesc('tanggal_mulai')->get();
        $daftarKelas = $this->daftarKelas($request, $tahunPelajaranId, $konteksGuruWali);

        return view('pendampingan-siswa.index', compact(
            'daftarPendampingan',
            'daftarTahunPelajaran',
            'daftarKelas',
            'ringkasan',
            'tahunPelajaranId',
            'kelasId',
            'status',
            'kataKunci',
            'konteksGuruWali',
        ));
    }

    public function create(Request $request)
    {
        $peringatan = $this->inputId($request, 'peringatan_id')
            ? PeringatanDiniSiswa::findOrFail($this->inputId($request, 'peringatan_id'))
            : null;
        $siswa = $peringatan?->siswa
            ?? Siswa::findOrFail($this->inputId($request, 'siswa_id'));
        $tahunPelajaran = $peringatan?->tahunPelajaran
            ?? TahunPelajaran::findOrFail($this->inputId($request, 'tahun_pelajaran_id'));

        abort_unless($this->akses->bolehLihat($request->user(), $siswa, $tahunPelajaran->id), 403);

        if ($aktif = $this->pendampinganAktif($siswa->id, $tahunPelajaran->id)) {
            return redirect()->route('pendampingan-siswa.edit', $aktif)
                ->with('berhasil', 'Siswa ini sudah memiliki tindak lanjut aktif. Silakan lanjutkan catatan yang sama.');
        }

        $pendampinganSiswa = new PendampinganSiswa([
            'siswa_id' => $siswa->id,
            'tahun_pelajaran_id' => $tahunPelajaran->id,
            'peringatan_dini_siswa_id' => $peringatan?->id,
            'petugas_pegawai_id' => $request->user()?->pegawai_id,
            'jenis_tindakan' => $peringatan?->jenis === 'sering_terlambat' ? 'pembinaan_wali' : 'konseling',
            'tanggal_tindak_lanjut' => today(),
            'status' => 'dalam_proses',
        ]);

        return view('pendampingan-siswa.create', array_merge(
            compact('pendampinganSiswa', 'siswa', 'tahunPelajaran', 'peringatan'),
            $this->pilihanForm($siswa, $tahunPelajaran),
        ));
    }

    public function store(Request $request)
    {
        $data = $request->validate(array_merge($this->aturanValidasi(), [
            'siswa_id' => ['required', 'integer', Rule::exists('siswa', 'id')],
            'tahun_pelajaran_id' => ['required', 'integer', Rule::exists('tahun_pelajaran', 'id')],
            'peringatan_dini_siswa_id' => ['nullable', 'integer', Rule::exists('peringatan_dini_siswa', 'id')],
        ]));
        $siswa = Siswa::findOrFail($data['siswa_id']);
        abort_unless($this->akses->bolehLihat($request->user(), $siswa, (int) $data['tahun_pelajaran_id']), 403);
        $this->pastikanPeringatanSesuai($data);

        $pendampinganSiswa = DB::transaction(function () use ($data, $request) {
            if ($this->pendampinganAktif((int) $data['siswa_id'], (int) $data['tahun_pelajaran_id'], true)) {
                throw ValidationException::withMessages([
                    'siswa_id' => 'Siswa ini sudah memiliki tindak lanjut yang masih dalam proses.',
                ]);
            }

            return PendampinganSiswa::create(array_merge($this->rapikanData($data), [
                'status' => 'dalam_proses',
                'kunci_aktif' => PendampinganSiswa::kunciAktif((int) $data['siswa_id'], (int) $data['tahun_pelajaran_id']),
                'dibuat_oleh_pengguna_id' => $request->user()?->id,
                'diperbarui_oleh_pengguna_id' => $request->user()?->id,
            ]));
        });

        return redirect()->route('pendampingan-siswa.edit', $pendampinganSiswa)
            ->with('berhasil', 'Tindak lanjut siswa berhasil dibuat.');
    }

    public function edit(Request $request, PendampinganSiswa $pendampinganSiswa)
    {
        $konteksGuruWali = $request->routeIs('pendampingan-siswa-wali.*');
        $pendampinganSiswa->load('siswa', 'tahunPelajaran', 'peringatanDiniSiswa');
        abort_unless($this->akses->bolehLihat(
            $request->user(),
            $pendampinganSiswa->siswa,
            $pendampinganSiswa->tahun_pelajaran_id,
            $konteksGuruWali ? 'guru_wali' : null,
        ), 403);

        $siswa = $pendampinganSiswa->siswa;
        $tahunPelajaran = $pendampinganSiswa->tahunPelajaran;
        $peringatan = $pendampinganSiswa->peringatanDiniSiswa;

        return view('pendampingan-siswa.edit', array_merge(
            compact('pendampinganSiswa', 'siswa', 'tahunPelajaran', 'peringatan', 'konteksGuruWali'),
            $this->pilihanForm($siswa, $tahunPelajaran),
        ));
    }

    public function update(Request $request, PendampinganSiswa $pendampinganSiswa)
    {
        $konteksGuruWali = $request->routeIs('pendampingan-siswa-wali.*');
        $pendampinganSiswa->loadMissing('siswa');
        abort_unless($this->akses->bolehLihat(
            $request->user(),
            $pendampinganSiswa->siswa,
            $pendampinganSiswa->tahun_pelajaran_id,
            $konteksGuruWali ? 'guru_wali' : null,
        ), 403);

        $data = $this->rapikanData($request->validate($this->aturanValidasi(true)));
        $menjadiAktif = $data['status'] === 'dalam_proses';

        DB::transaction(function () use ($pendampinganSiswa, $data, $menjadiAktif, $request) {
            if ($menjadiAktif) {
                $aktifLain = PendampinganSiswa::query()
                    ->where('siswa_id', $pendampinganSiswa->siswa_id)
                    ->where('tahun_pelajaran_id', $pendampinganSiswa->tahun_pelajaran_id)
                    ->where('status', 'dalam_proses')
                    ->whereKeyNot($pendampinganSiswa->id)
                    ->lockForUpdate()
                    ->exists();

                if ($aktifLain) {
                    throw ValidationException::withMessages([
                        'status' => 'Masih ada tindak lanjut lain yang sedang diproses untuk siswa ini.',
                    ]);
                }
            }

            $pendampinganSiswa->update(array_merge($data, [
                'kunci_aktif' => $menjadiAktif
                    ? PendampinganSiswa::kunciAktif($pendampinganSiswa->siswa_id, $pendampinganSiswa->tahun_pelajaran_id)
                    : null,
                'selesai_pada' => $menjadiAktif ? null : ($pendampinganSiswa->selesai_pada ?? now()),
                'diperbarui_oleh_pengguna_id' => $request->user()?->id,
            ]));
        });

        return redirect()->route(
            $konteksGuruWali ? 'pendampingan-siswa-wali.edit' : 'pendampingan-siswa.edit',
            $pendampinganSiswa,
        )
            ->with('berhasil', $menjadiAktif
                ? 'Tindak lanjut siswa berhasil diperbarui.'
                : 'Tindak lanjut siswa telah ditandai selesai.');
    }

    private function aturanValidasi(bool $sertakanStatus = false): array
    {
        $aturan = [
            'jenis_tindakan' => ['required', Rule::in(array_keys(PendampinganSiswa::DAFTAR_JENIS))],
            'petugas_pegawai_id' => ['required', 'integer', Rule::exists('pegawai', 'id')->where('aktif', true)],
            'tanggal_tindak_lanjut' => ['required', 'date'],
            'catatan' => ['required', 'string', 'max:3000'],
            'hasil' => [$sertakanStatus ? 'required_if:status,selesai' : 'nullable', 'nullable', 'string', 'max:3000'],
        ];

        if ($sertakanStatus) {
            $aturan['status'] = ['required', Rule::in(array_keys(PendampinganSiswa::DAFTAR_STATUS))];
        }

        return $aturan;
    }

    private function rapikanData(array $data): array
    {
        foreach (['catatan', 'hasil'] as $field) {
            $data[$field] = filled($data[$field] ?? null) ? trim((string) $data[$field]) : null;
        }

        return $data;
    }

    private function pastikanPeringatanSesuai(array $data): void
    {
        if (empty($data['peringatan_dini_siswa_id'])) {
            return;
        }

        $sesuai = PeringatanDiniSiswa::query()
            ->whereKey($data['peringatan_dini_siswa_id'])
            ->where('siswa_id', $data['siswa_id'])
            ->where('tahun_pelajaran_id', $data['tahun_pelajaran_id'])
            ->exists();

        if (! $sesuai) {
            throw ValidationException::withMessages([
                'peringatan_dini_siswa_id' => 'Peringatan tidak sesuai dengan siswa dan tahun pelajaran.',
            ]);
        }
    }

    private function pendampinganAktif(int $siswaId, int $tahunPelajaranId, bool $kunci = false): ?PendampinganSiswa
    {
        return PendampinganSiswa::query()
            ->where('siswa_id', $siswaId)
            ->where('tahun_pelajaran_id', $tahunPelajaranId)
            ->where('status', 'dalam_proses')
            ->when($kunci, fn (Builder $query) => $query->lockForUpdate())
            ->first();
    }

    private function pilihanForm(Siswa $siswa, TahunPelajaran $tahunPelajaran): array
    {
        $anggotaKelas = $siswa->anggotaKelas()
            ->with('kelas:id,nama')
            ->where('tahun_pelajaran_id', $tahunPelajaran->id)
            ->where('status_keanggotaan', 'aktif')
            ->first();

        return [
            'anggotaKelas' => $anggotaKelas,
            'daftarJenisTindakan' => PendampinganSiswa::DAFTAR_JENIS,
            'daftarStatus' => PendampinganSiswa::DAFTAR_STATUS,
            'daftarPegawai' => Pegawai::where('aktif', true)
                ->orderBy('nama_lengkap')
                ->get(['id', 'nama_lengkap', 'nip', 'jabatan_utama']),
        ];
    }

    private function daftarKelas(Request $request, ?int $tahunPelajaranId, bool $konteksGuruWali = false)
    {
        $query = Kelas::query()
            ->when($tahunPelajaranId, fn (Builder $query) => $query->where('tahun_pelajaran_id', $tahunPelajaranId));

        if ($konteksGuruWali) {
            $siswaWaliIds = $request->user()->siswaWaliIds();

            return $query
                ->when(
                    $siswaWaliIds === [],
                    fn (Builder $query) => $query->whereRaw('1 = 0'),
                    fn (Builder $query) => $query->whereHas('anggotaKelas', fn (Builder $query) => $query
                        ->whereIn('siswa_id', $siswaWaliIds)
                        ->where('status_keanggotaan', 'aktif')),
                )
                ->orderBy('tingkat')
                ->orderBy('nama')
                ->get();
        }

        if (! $this->akses->aksesLuas($request->user())) {
            $kelasWaliIds = $request->user()->kelasWaliIds();
            $siswaWaliIds = $request->user()->siswaWaliIds();

            $query->where(function (Builder $query) use ($kelasWaliIds, $siswaWaliIds) {
                if ($kelasWaliIds !== []) {
                    $query->whereIn('id', $kelasWaliIds);
                }

                if ($siswaWaliIds !== []) {
                    $metode = $kelasWaliIds !== [] ? 'orWhereHas' : 'whereHas';
                    $query->{$metode}('anggotaKelas', fn (Builder $query) => $query
                        ->whereIn('siswa_id', $siswaWaliIds)
                        ->where('status_keanggotaan', 'aktif'));
                }

                if ($kelasWaliIds === [] && $siswaWaliIds === []) {
                    $query->whereRaw('1 = 0');
                }
            });
        }

        return $query->orderBy('tingkat')->orderBy('nama')->get();
    }

    private function inputId(Request $request, string $field): ?int
    {
        $value = $request->input($field);

        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }

    private function nilaiPilihan(Request $request, string $field, array $pilihan, string $bawaan = ''): string
    {
        $nilai = (string) $request->input($field, $bawaan);

        return in_array($nilai, $pilihan, true) ? $nilai : $bawaan;
    }
}
