<?php

namespace App\Http\Controllers;

use App\Models\Kelas;
use App\Models\Pegawai;
use App\Models\PengaturanBerhalanganIbadah;
use App\Models\PenugasanPendampingIbadahSiswi;
use App\Models\TahunPelajaran;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PengaturanBerhalanganIbadahController extends Controller
{
    public function index(Request $request)
    {
        $tahunPelajaran = TahunPelajaran::query()
            ->where('aktif', true)
            ->orderByDesc('tanggal_mulai')
            ->first();

        if (! $tahunPelajaran) {
            return view('pengaturan-berhalangan-ibadah.index', [
                'tahunPelajaran' => null,
                'pengaturan' => null,
                'daftarPegawaiPerempuan' => collect(),
                'daftarKelas' => collect(),
                'penugasanPendamping' => collect(),
                'penugasanDiedit' => null,
                'jumlahKelasTercakup' => 0,
            ]);
        }

        $pengaturan = PengaturanBerhalanganIbadah::query()
            ->whereBelongsTo($tahunPelajaran)
            ->first() ?? new PengaturanBerhalanganIbadah([
                'tahun_pelajaran_id' => $tahunPelajaran->id,
                'batas_hari_konfirmasi' => 7,
                'aktif' => true,
            ]);

        $daftarPegawaiPerempuan = Pegawai::query()
            ->where('aktif', true)
            ->where('jenis_kelamin', 'P')
            ->with(['pengguna:id,pegawai_id,aktif', 'pengguna.daftarPeran:id,kode,aktif'])
            ->orderBy('nama_lengkap')
            ->get(['id', 'nama_lengkap', 'nip', 'jenis_kelamin', 'jenis_pegawai', 'jabatan_utama', 'aktif'])
            ->filter(fn (Pegawai $pegawai) => $this->dapatMenjadiPendamping($pegawai))
            ->values();

        $daftarKelas = Kelas::query()
            ->whereBelongsTo($tahunPelajaran)
            ->where('aktif', true)
            ->orderBy('tingkat')
            ->orderBy('nama')
            ->get(['id', 'nama', 'tingkat']);

        $penugasanPendamping = PenugasanPendampingIbadahSiswi::query()
            ->whereBelongsTo($tahunPelajaran)
            ->where('aktif', true)
            ->with([
                'pegawai:id,nama_lengkap,nip,jabatan_utama,aktif',
                'kelas:id,nama,tingkat',
                'ditugaskanOlehPengguna:id,nama',
            ])
            ->get()
            ->sortBy(fn (PenugasanPendampingIbadahSiswi $penugasan) => $penugasan->pegawai?->nama_lengkap)
            ->values();

        $penugasanDiedit = null;
        if ($request->filled('ubah')) {
            $penugasanDiedit = $penugasanPendamping->firstWhere('id', $request->integer('ubah'));
        }

        $jumlahKelasTercakup = $penugasanPendamping->contains('semua_kelas', true)
            ? $daftarKelas->count()
            : $penugasanPendamping->flatMap->kelas->pluck('id')->unique()->count();

        return view('pengaturan-berhalangan-ibadah.index', compact(
            'tahunPelajaran',
            'pengaturan',
            'daftarPegawaiPerempuan',
            'daftarKelas',
            'penugasanPendamping',
            'penugasanDiedit',
            'jumlahKelasTercakup',
        ));
    }

    public function update(Request $request)
    {
        $tahunPelajaran = $this->tahunPelajaranAktif();
        $data = $request->validate([
            'batas_hari_konfirmasi' => ['required', 'integer', 'min:1', 'max:30'],
            'aktif' => ['nullable', 'boolean'],
        ]);

        PengaturanBerhalanganIbadah::query()->updateOrCreate(
            ['tahun_pelajaran_id' => $tahunPelajaran->id],
            [
                'batas_hari_konfirmasi' => $data['batas_hari_konfirmasi'],
                'aktif' => $request->boolean('aktif'),
                'diperbarui_oleh_pengguna_id' => $request->user()->id,
            ],
        );

        return back()->with('berhasil', 'Pengaturan berhalangan berhasil disimpan.');
    }

    public function storePendamping(Request $request)
    {
        $tahunPelajaran = $this->tahunPelajaranAktif();
        $data = $request->validate([
            'pegawai_id' => [
                'required',
                'integer',
                Rule::exists('pegawai', 'id')->where(function ($query) {
                    $query->where('aktif', true)
                        ->where('jenis_kelamin', 'P');
                }),
            ],
            'semua_kelas' => ['required', 'boolean'],
            'kelas_ids' => ['nullable', 'array'],
            'kelas_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('kelas', 'id')->where(function ($query) use ($tahunPelajaran) {
                    $query->where('tahun_pelajaran_id', $tahunPelajaran->id)
                        ->where('aktif', true);
                }),
            ],
        ], [
            'pegawai_id.exists' => 'Pendamping harus merupakan guru perempuan atau Guru PL perempuan yang masih aktif.',
        ]);

        $pegawai = Pegawai::query()
            ->with(['pengguna.daftarPeran:id,kode,aktif'])
            ->findOrFail($data['pegawai_id']);
        if (! $this->dapatMenjadiPendamping($pegawai)) {
            throw ValidationException::withMessages([
                'pegawai_id' => 'Pendamping harus merupakan guru perempuan atau Guru PL perempuan yang masih aktif.',
            ]);
        }

        $semuaKelas = (bool) $data['semua_kelas'];
        $kelasIds = collect($data['kelas_ids'] ?? [])->map(fn ($id) => (int) $id)->unique()->values();

        if (! $semuaKelas && $kelasIds->isEmpty()) {
            throw ValidationException::withMessages([
                'kelas_ids' => 'Pilih sedikitnya satu kelas atau gunakan cakupan seluruh kelas.',
            ]);
        }

        DB::transaction(function () use ($request, $tahunPelajaran, $data, $semuaKelas, $kelasIds) {
            $penugasan = PenugasanPendampingIbadahSiswi::query()->firstOrNew([
                'tahun_pelajaran_id' => $tahunPelajaran->id,
                'pegawai_id' => $data['pegawai_id'],
            ]);

            $penugasan->fill([
                'semua_kelas' => $semuaKelas,
                'aktif' => true,
                'ditugaskan_oleh_pengguna_id' => $request->user()->id,
                'dinonaktifkan_pada' => null,
            ])->save();

            $penugasan->kelas()->sync($semuaKelas ? [] : $kelasIds->all());
        });

        return redirect()
            ->route('pengaturan-berhalangan-ibadah.index')
            ->with('berhasil', 'Pendamping ibadah siswi berhasil disimpan.');
    }

    public function destroyPendamping(
        Request $request,
        PenugasanPendampingIbadahSiswi $penugasanPendampingIbadahSiswi,
    ) {
        $penugasanPendampingIbadahSiswi->update([
            'aktif' => false,
            'ditugaskan_oleh_pengguna_id' => $request->user()->id,
            'dinonaktifkan_pada' => now(),
        ]);

        return redirect()
            ->route('pengaturan-berhalangan-ibadah.index')
            ->with('berhasil', 'Pendamping telah dinonaktifkan. Riwayat penugasannya tetap tersimpan.');
    }

    private function tahunPelajaranAktif(): TahunPelajaran
    {
        $tahunPelajaran = TahunPelajaran::query()
            ->where('aktif', true)
            ->orderByDesc('tanggal_mulai')
            ->first();

        if (! $tahunPelajaran) {
            throw ValidationException::withMessages([
                'tahun_pelajaran' => 'Aktifkan tahun pelajaran terlebih dahulu.',
            ]);
        }

        return $tahunPelajaran;
    }

    private function dapatMenjadiPendamping(Pegawai $pegawai): bool
    {
        return $pegawai->aktif
            && $pegawai->jenis_kelamin === 'P'
            && (
                mb_strtolower(trim((string) $pegawai->jenis_pegawai)) === 'guru'
                || $pegawai->pengguna?->memilikiPeran('guru_pl')
            );
    }
}
