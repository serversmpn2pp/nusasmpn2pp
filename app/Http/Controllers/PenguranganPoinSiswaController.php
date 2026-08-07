<?php

namespace App\Http\Controllers;

use App\Models\Kelas;
use App\Models\PenguranganPoinSiswa;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use App\Models\TransaksiPoinSiswa;
use App\Services\Notifikasi\NotifikasiPenggunaService;
use App\Services\Pembinaan\ProsesPoinSiswaService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PenguranganPoinSiswaController extends Controller
{
    public function __construct(
        private ProsesPoinSiswaService $prosesPoinSiswaService,
        private NotifikasiPenggunaService $notifikasiPenggunaService,
    ) {}

    public function index(Request $request)
    {
        $status = (string) $request->input('status', 'semua');
        if ($status !== 'semua' && ! array_key_exists($status, PenguranganPoinSiswa::DAFTAR_STATUS)) {
            $status = 'semua';
        }

        $tahunPelajaranAktif = TahunPelajaran::query()
            ->where('aktif', true)
            ->latest('tanggal_mulai')
            ->first();
        $tahunPelajaranId = $tahunPelajaranAktif?->id;

        $daftarKelas = $tahunPelajaranId
            ? Kelas::query()
                ->where('tahun_pelajaran_id', $tahunPelajaranId)
                ->where('aktif', true)
                ->orderBy('tingkat')
                ->orderBy('nama')
                ->get(['id', 'nama', 'tingkat'])
            : collect();

        $kelasId = $request->integer('kelas_id') ?: null;
        if ($kelasId && ! $daftarKelas->contains('id', $kelasId)) {
            $kelasId = null;
        }

        $pengurangan = PenguranganPoinSiswa::query()
            ->with([
                'siswa' => fn ($query) => $query
                    ->select(['id', 'nama_lengkap', 'nisn'])
                    ->with(['anggotaKelas' => fn ($query) => $query
                        ->when($tahunPelajaranId, fn ($query) => $query->where('tahun_pelajaran_id', $tahunPelajaranId))
                        ->where('status_keanggotaan', 'aktif')
                        ->with('kelas:id,nama')]),
                'tahunPelajaran:id,nama',
                'diajukanOlehPengguna:id,nama',
                'disetujuiOlehPegawai:id,nama_lengkap',
            ])
            ->when($status !== 'semua', fn ($query) => $query->where('status', $status))
            ->when($tahunPelajaranId, fn ($query) => $query->where('tahun_pelajaran_id', $tahunPelajaranId))
            ->when(! $tahunPelajaranId, fn ($query) => $query->whereRaw('1 = 0'))
            ->when($kelasId, fn ($query) => $query->whereHas('siswa.anggotaKelas', fn ($query) => $query
                ->where('tahun_pelajaran_id', $tahunPelajaranId)
                ->where('kelas_id', $kelasId)
                ->where('status_keanggotaan', 'aktif')))
            ->latest('tanggal_kegiatan')
            ->paginate(15)
            ->withQueryString();

        $daftarSiswa = collect();
        if ($tahunPelajaranId) {
            $saldoPoin = TransaksiPoinSiswa::query()
                ->select('siswa_id')
                ->selectRaw('SUM(poin) AS saldo_poin')
                ->where('tahun_pelajaran_id', $tahunPelajaranId)
                ->groupBy('siswa_id')
                ->havingRaw('SUM(poin) > 0');

            $daftarSiswa = Siswa::query()
                ->joinSub($saldoPoin, 'saldo_poin_aktif', fn ($join) => $join
                    ->on('saldo_poin_aktif.siswa_id', '=', 'siswa.id'))
                ->select(['siswa.id', 'siswa.nama_lengkap', 'siswa.nisn', 'saldo_poin_aktif.saldo_poin'])
                ->where('siswa.aktif', true)
                ->when($kelasId, fn ($query) => $query->whereHas('anggotaKelas', fn ($query) => $query
                    ->where('tahun_pelajaran_id', $tahunPelajaranId)
                    ->where('kelas_id', $kelasId)
                    ->where('status_keanggotaan', 'aktif')))
                ->with(['anggotaKelas' => fn ($query) => $query
                    ->where('tahun_pelajaran_id', $tahunPelajaranId)
                    ->where('status_keanggotaan', 'aktif')
                    ->with('kelas:id,nama')])
                ->orderBy('siswa.nama_lengkap')
                ->get();
        }

        return view('pengurangan-poin-siswa.index', compact(
            'pengurangan',
            'daftarSiswa',
            'daftarKelas',
            'tahunPelajaranAktif',
            'status',
            'kelasId',
        ));
    }

    public function store(Request $request)
    {
        $tahunPelajaranAktif = TahunPelajaran::query()
            ->where('aktif', true)
            ->latest('tanggal_mulai')
            ->first();

        if (! $tahunPelajaranAktif) {
            throw ValidationException::withMessages([
                'siswa_id' => 'Pengajuan penghargaan belum dapat dibuat karena tidak ada tahun pelajaran aktif.',
            ]);
        }

        $data = $request->validate([
            'siswa_id' => ['required', 'integer', Rule::exists('siswa', 'id')->where('aktif', true)],
            'tanggal_kegiatan' => ['required', 'date'],
            'jenis_kegiatan' => ['required', 'string', 'max:160'],
            'deskripsi' => ['nullable', 'string'],
            'poin_pengurangan' => ['required', 'integer', Rule::in([10, 15, 20, 30])],
            'bukti' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:4096'],
        ]);

        $saldoPoin = $this->prosesPoinSiswaService->totalPoin(
            (int) $data['siswa_id'],
            (int) $tahunPelajaranAktif->id,
        );
        if ($saldoPoin <= 0) {
            throw ValidationException::withMessages([
                'siswa_id' => 'Penghargaan hanya dapat diajukan untuk siswa yang masih memiliki saldo poin.',
            ]);
        }

        if ($request->hasFile('bukti')) {
            $data['bukti'] = $request->file('bukti')->store('bukti-pengurangan-poin', 'public');
        }
        $data['tahun_pelajaran_id'] = $tahunPelajaranAktif->id;
        $data['deskripsi'] = filled($data['deskripsi'] ?? null) ? trim($data['deskripsi']) : null;
        $data['status'] = 'diajukan';
        $data['diajukan_oleh_pengguna_id'] = auth()->id();

        $pengurangan = PenguranganPoinSiswa::create($data);
        $pengurangan->loadMissing('siswa:id,nama_lengkap');

        $this->notifikasiPenggunaService->kirimKeBanyak(
            $this->notifikasiPenggunaService->penggunaDenganPeran(
                'wakil_pimpinan_kesiswaan',
                $request->user()?->id,
            ),
            'peringatan',
            'Pengajuan penghargaan menunggu persetujuan',
            sprintf(
                'Pengurangan %d poin untuk %s diajukan melalui kegiatan %s.',
                $pengurangan->poin_pengurangan,
                $pengurangan->siswa?->nama_lengkap ?? 'siswa',
                $pengurangan->jenis_kegiatan,
            ),
            route('pengurangan-poin-siswa.index', ['status' => 'diajukan'], false),
            "pengurangan-poin-diajukan:{$pengurangan->id}",
            [
                'pengurangan_poin_siswa_id' => $pengurangan->id,
                'siswa_id' => $pengurangan->siswa_id,
                'poin_pengurangan' => $pengurangan->poin_pengurangan,
            ],
        );

        return back()->with('berhasil', 'Pengurangan poin diajukan untuk persetujuan Wakil Kesiswaan.');
    }

    public function putuskan(Request $request, PenguranganPoinSiswa $penguranganPoinSiswa)
    {
        abort_unless($request->user()?->administrator() || $request->user()?->memilikiIzin('poin_siswa.putus_konflik'), 403);
        abort_if($penguranganPoinSiswa->status !== 'diajukan', 422, 'Pengajuan ini sudah diputuskan.');

        $data = $request->validate([
            'keputusan' => ['required', Rule::in(['disetujui', 'ditolak'])],
            'catatan_keputusan' => ['nullable', 'string'],
        ]);

        if ($data['keputusan'] === 'disetujui') {
            $diterapkan = $this->prosesPoinSiswaService->setujuiPengurangan(
                $penguranganPoinSiswa,
                $request->user()?->pegawai_id,
                $data['catatan_keputusan'] ?? null,
            );

            return back()->with('berhasil', "Pengurangan disetujui. {$diterapkan} poin diterapkan tanpa membuat saldo negatif.");
        }

        $penguranganPoinSiswa->update([
            'status' => 'ditolak',
            'disetujui_oleh_pegawai_id' => $request->user()?->pegawai_id,
            'diputuskan_pada' => now(),
            'catatan_keputusan' => filled($data['catatan_keputusan'] ?? null) ? trim($data['catatan_keputusan']) : null,
        ]);

        return back()->with('berhasil', 'Pengajuan pengurangan poin ditolak.');
    }
}
