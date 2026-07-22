<?php

namespace App\Http\Controllers;

use App\Models\Kelas;
use App\Models\Pegawai;
use App\Models\Pengguna;
use App\Models\SanksiPoinSiswa;
use App\Models\TahunPelajaran;
use App\Services\Notifikasi\NotifikasiPenggunaService;
use App\Services\Pembinaan\AksesSanksiPoinService;
use App\Services\Pembinaan\CatatRiwayatSanksiPoinService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SanksiPoinSiswaController extends Controller
{
    public function __construct(
        private AksesSanksiPoinService $akses,
        private CatatRiwayatSanksiPoinService $riwayat,
        private NotifikasiPenggunaService $notifikasi,
    ) {}

    public function index(Request $request)
    {
        $tahunPelajaranId = $this->inputId($request, 'tahun_pelajaran_id')
            ?? TahunPelajaran::where('aktif', true)->latest('tanggal_mulai')->value('id');
        $kelasId = $this->inputId($request, 'kelas_id');
        $status = (string) $request->input('status', 'aktif');
        $kataKunci = trim((string) $request->input('kata_kunci', ''));

        if (! array_key_exists($status, ['semua' => true, 'aktif' => true, ...array_fill_keys(array_keys(SanksiPoinSiswa::DAFTAR_STATUS), true)])) {
            $status = 'aktif';
        }

        $dasar = $this->akses->terapkanCakupan(SanksiPoinSiswa::query(), $request->user())
            ->when($tahunPelajaranId, fn ($query) => $query->where('tahun_pelajaran_id', $tahunPelajaranId));

        $ringkasan = [
            'aktif' => (clone $dasar)->whereIn('status', ['menunggu', 'diproses'])->count(),
            'menunggu' => (clone $dasar)->where('status', 'menunggu')->count(),
            'diproses' => (clone $dasar)->where('status', 'diproses')->count(),
            'terlambat' => (clone $dasar)->whereIn('status', ['menunggu', 'diproses'])
                ->whereNotNull('batas_pelaksanaan')->whereDate('batas_pelaksanaan', '<', today())->count(),
            'selesai' => (clone $dasar)->where('status', 'selesai')->count(),
        ];

        $daftarSanksi = (clone $dasar)
            ->with([
                'siswa:id,nama_lengkap,nisn,nis',
                'siswa.anggotaKelas.kelas:id,nama',
                'aturanSanksiPoin:id,batas_poin,nama,deskripsi',
                'tahunPelajaran:id,nama',
                'petugasPegawai:id,nama_lengkap,nip',
            ])
            ->withCount('buktiPelaksanaanSanksi')
            ->when($status === 'aktif', fn ($query) => $query->whereIn('status', ['menunggu', 'diproses']))
            ->when(array_key_exists($status, SanksiPoinSiswa::DAFTAR_STATUS), fn ($query) => $query->where('status', $status))
            ->when($kelasId, fn ($query) => $query->whereHas('siswa.anggotaKelas', fn ($query) => $query
                ->where('kelas_id', $kelasId)
                ->when($tahunPelajaranId, fn ($query) => $query->where('tahun_pelajaran_id', $tahunPelajaranId))))
            ->when($kataKunci !== '', fn ($query) => $query->where(function ($query) use ($kataKunci) {
                $query->whereHas('siswa', fn ($query) => $query
                    ->where('nama_lengkap', 'ilike', '%'.$kataKunci.'%')
                    ->orWhere('nisn', 'ilike', '%'.$kataKunci.'%')
                    ->orWhere('nis', 'ilike', '%'.$kataKunci.'%'))
                    ->orWhereHas('aturanSanksiPoin', fn ($query) => $query->where('nama', 'ilike', '%'.$kataKunci.'%'));
            }))
            ->orderByRaw("CASE status WHEN 'menunggu' THEN 1 WHEN 'diproses' THEN 2 WHEN 'selesai' THEN 3 ELSE 4 END")
            ->orderByRaw('batas_pelaksanaan ASC NULLS LAST')
            ->latest('terpicu_pada')
            ->paginate(12)
            ->withQueryString();

        $daftarTahunPelajaran = TahunPelajaran::orderByDesc('aktif')->orderByDesc('tanggal_mulai')->get();
        $daftarKelas = Kelas::when($tahunPelajaranId, fn ($query) => $query->where('tahun_pelajaran_id', $tahunPelajaranId))
            ->orderBy('tingkat')->orderBy('nama')->get();

        return view('sanksi-poin-siswa.index', compact(
            'daftarSanksi', 'daftarTahunPelajaran', 'daftarKelas', 'ringkasan',
            'tahunPelajaranId', 'kelasId', 'status', 'kataKunci',
        ));
    }

    public function show(Request $request, SanksiPoinSiswa $sanksiPoinSiswa)
    {
        abort_unless($this->akses->bolehLihat($request->user(), $sanksiPoinSiswa), 403);
        $tahunPelajaranId = $sanksiPoinSiswa->tahun_pelajaran_id;
        $sanksiPoinSiswa->load([
            'siswa.anggotaKelas' => fn ($query) => $query->where('tahun_pelajaran_id', $tahunPelajaranId)->with('kelas.waliKelas'),
            'siswa.penugasanGuruWaliSiswa' => fn ($query) => $query->where('aktif', true)->with('guruWali'),
            'aturanSanksiPoin', 'tahunPelajaran', 'petugasPegawai', 'diperbaruiOlehPengguna',
            'buktiPelaksanaanSanksi' => fn ($query) => $query->with('diunggahOlehPengguna')->latest('diunggah_pada'),
            'riwayatSanksiPoinSiswa' => fn ($query) => $query->with('dibuatOlehPengguna')->latest('terjadi_pada')->latest('id'),
        ]);

        $bolehKelola = $this->akses->bolehKelola($request->user(), $sanksiPoinSiswa);
        $daftarPetugas = $bolehKelola ? $this->daftarPetugas() : collect();

        return view('sanksi-poin-siswa.show', compact('sanksiPoinSiswa', 'bolehKelola', 'daftarPetugas'));
    }

    public function update(Request $request, SanksiPoinSiswa $sanksiPoinSiswa)
    {
        abort_unless($this->akses->bolehKelola($request->user(), $sanksiPoinSiswa), 403);
        abort_if($sanksiPoinSiswa->sudahFinal(), 422, 'Sanksi yang sudah selesai atau dibatalkan tidak dapat diubah.');

        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(SanksiPoinSiswa::DAFTAR_STATUS))],
            'petugas_pegawai_id' => ['nullable', 'integer', Rule::exists('pegawai', 'id')->where('aktif', true)],
            'batas_pelaksanaan' => ['nullable', 'date'],
            'catatan' => ['nullable', 'string', 'max:5000'],
            'hasil_pelaksanaan' => ['nullable', 'string', 'max:10000'],
        ]);

        $statusBaru = $data['status'];
        $statusDiizinkan = match ($sanksiPoinSiswa->status) {
            'menunggu' => ['menunggu', 'diproses', 'dibatalkan'],
            'diproses' => ['diproses', 'selesai', 'dibatalkan'],
            default => [],
        };
        if (! in_array($statusBaru, $statusDiizinkan, true)) {
            throw ValidationException::withMessages(['status' => 'Perubahan status tersebut tidak diizinkan.']);
        }

        if (in_array($statusBaru, ['diproses', 'selesai'], true) && ! $data['petugas_pegawai_id']) {
            throw ValidationException::withMessages(['petugas_pegawai_id' => 'Petugas penanggung jawab wajib dipilih.']);
        }
        if ($statusBaru === 'diproses' && ! $data['batas_pelaksanaan']) {
            throw ValidationException::withMessages(['batas_pelaksanaan' => 'Batas pelaksanaan wajib diisi saat sanksi mulai diproses.']);
        }
        if ($statusBaru === 'selesai' && blank($data['hasil_pelaksanaan'] ?? null)) {
            throw ValidationException::withMessages(['hasil_pelaksanaan' => 'Hasil pelaksanaan wajib diisi sebelum sanksi diselesaikan.']);
        }
        if ($statusBaru === 'dibatalkan' && blank($data['catatan'] ?? null)) {
            throw ValidationException::withMessages(['catatan' => 'Alasan pembatalan wajib diisi.']);
        }
        if ($data['batas_pelaksanaan'] && $sanksiPoinSiswa->terpicu_pada
            && $data['batas_pelaksanaan'] < $sanksiPoinSiswa->terpicu_pada->toDateString()) {
            throw ValidationException::withMessages(['batas_pelaksanaan' => 'Batas pelaksanaan tidak boleh sebelum sanksi terpicu.']);
        }

        if (! $request->user()->memilikiIzin('poin_siswa.sanksi_kelola')) {
            $data['petugas_pegawai_id'] = $request->user()->pegawai_id;
        }

        $statusSebelum = $sanksiPoinSiswa->status;
        DB::transaction(function () use ($request, $data, $statusBaru, $statusSebelum, $sanksiPoinSiswa) {
            $sanksiPoinSiswa->update([
                'status' => $statusBaru,
                'petugas_pegawai_id' => $data['petugas_pegawai_id'] ?: null,
                'mulai_diproses_pada' => $statusBaru === 'diproses' && ! $sanksiPoinSiswa->mulai_diproses_pada
                    ? now() : $sanksiPoinSiswa->mulai_diproses_pada,
                'batas_pelaksanaan' => $data['batas_pelaksanaan'] ?: $sanksiPoinSiswa->batas_pelaksanaan,
                'dilaksanakan_pada' => $statusBaru === 'selesai' ? now() : $sanksiPoinSiswa->dilaksanakan_pada,
                'catatan' => filled($data['catatan'] ?? null) ? trim($data['catatan']) : $sanksiPoinSiswa->catatan,
                'hasil_pelaksanaan' => filled($data['hasil_pelaksanaan'] ?? null) ? trim($data['hasil_pelaksanaan']) : $sanksiPoinSiswa->hasil_pelaksanaan,
                'diperbarui_oleh_pengguna_id' => $request->user()?->id,
            ]);

            $this->riwayat->catat(
                $sanksiPoinSiswa,
                $statusSebelum === $statusBaru ? 'pelaksanaan_diperbarui' : 'status_diubah',
                $this->judulRiwayat($statusBaru, $statusSebelum === $statusBaru),
                $statusSebelum,
                $statusBaru,
                $data['catatan'] ?? $data['hasil_pelaksanaan'] ?? null,
                $request->user()?->id,
                [
                    'petugas_pegawai_id' => $sanksiPoinSiswa->petugas_pegawai_id,
                    'batas_pelaksanaan' => $sanksiPoinSiswa->batas_pelaksanaan?->toDateString(),
                ],
            );
        });

        $this->kirimNotifikasiPerubahan($request, $sanksiPoinSiswa->fresh(), $statusSebelum);

        return redirect()->route('sanksi-poin-siswa.show', $sanksiPoinSiswa)
            ->with('berhasil', 'Pelaksanaan sanksi berhasil diperbarui.');
    }

    private function daftarPetugas()
    {
        return Pegawai::query()
            ->where('aktif', true)
            ->whereHas('pengguna', fn ($query) => $query
                ->where('aktif', true)
                ->where(function ($query) {
                    $query->where('akun_sistem', true)
                        ->orWhereHas('daftarPeran', fn ($query) => $query
                            ->where('peran.aktif', true)
                            ->whereHas('izin', fn ($query) => $query
                                ->where('izin.kode', 'poin_siswa.sanksi_kelola')
                                ->where('izin.aktif', true)));
                }))
            ->orderBy('nama_lengkap')
            ->get(['id', 'nama_lengkap', 'nip']);
    }

    private function kirimNotifikasiPerubahan(Request $request, SanksiPoinSiswa $sanksi, string $statusSebelum): void
    {
        $sanksi->loadMissing(['siswa', 'aturanSanksiPoin', 'petugasPegawai']);
        $penerima = $this->notifikasi->penggunaDenganIzin('poin_siswa.sanksi_kelola', $request->user()?->id);
        if ($sanksi->petugas_pegawai_id) {
            $penerima = $penerima->merge($this->notifikasi->penggunaUntukPegawai($sanksi->petugas_pegawai_id));
        }

        $anggota = $sanksi->siswa?->anggotaKelas()->where('tahun_pelajaran_id', $sanksi->tahun_pelajaran_id)
            ->where('status_keanggotaan', 'aktif')->with('kelas')->first();
        if ($anggota?->kelas?->wali_kelas_id) {
            $penerima = $penerima->merge($this->notifikasi->penggunaUntukPegawai($anggota->kelas->wali_kelas_id));
        }
        $guruWaliId = $sanksi->siswa?->penugasanGuruWaliSiswa()->where('aktif', true)->value('guru_wali_pegawai_id');
        if ($guruWaliId) {
            $penerima = $penerima->merge($this->notifikasi->penggunaUntukPegawai((int) $guruWaliId));
        }

        $judul = match ($sanksi->status) {
            'diproses' => 'Pelaksanaan sanksi dimulai',
            'selesai' => 'Pelaksanaan sanksi selesai',
            'dibatalkan' => 'Sanksi dibatalkan',
            default => 'Pelaksanaan sanksi diperbarui',
        };
        $this->notifikasi->kirimKeBanyak(
            $penerima->filter(fn (Pengguna $pengguna) => (int) $pengguna->id !== (int) $request->user()?->id)->unique('id')->values(),
            in_array($sanksi->status, ['dibatalkan'], true) ? 'penting' : 'informasi',
            $judul,
            sprintf('%s untuk %s: %s.', $sanksi->aturanSanksiPoin?->nama ?? 'Sanksi', $sanksi->siswa?->nama_lengkap ?? 'siswa', $sanksi->labelStatus()),
            route('sanksi-poin-siswa.show', $sanksi, false),
            "sanksi-status:{$sanksi->id}:{$statusSebelum}:{$sanksi->status}",
        );
    }

    private function judulRiwayat(string $status, bool $hanyaDiperbarui): string
    {
        if ($hanyaDiperbarui) {
            return 'Data pelaksanaan diperbarui';
        }

        return match ($status) {
            'diproses' => 'Sanksi mulai diproses',
            'selesai' => 'Sanksi dinyatakan selesai',
            'dibatalkan' => 'Sanksi dibatalkan',
            default => 'Status sanksi diperbarui',
        };
    }

    private function inputId(Request $request, string $field): ?int
    {
        $value = $request->input($field);

        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }
}
