<?php

namespace App\Http\Controllers;

use App\Models\Pegawai;
use App\Models\PenugasanGuruBkTingkat;
use App\Models\TahunPelajaran;
use App\Services\Pembinaan\PenugasanGuruBkTingkatService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PenugasanGuruBkTingkatController extends Controller
{
    public function index(Request $request)
    {
        $daftarTahunPelajaran = TahunPelajaran::query()
            ->orderByDesc('aktif')
            ->orderByDesc('tanggal_mulai')
            ->get(['id', 'nama', 'aktif']);
        $tahunPelajaranId = (int) ($request->input('tahun_pelajaran_id')
            ?: $daftarTahunPelajaran->firstWhere('aktif', true)?->id
            ?: $daftarTahunPelajaran->first()?->id);
        $tahunPelajaran = $daftarTahunPelajaran->firstWhere('id', $tahunPelajaranId);

        abort_unless($tahunPelajaran, 404);

        $penugasan = PenugasanGuruBkTingkat::query()
            ->with('pegawai:id,nama_lengkap,nip,jabatan_utama')
            ->where('tahun_pelajaran_id', $tahunPelajaranId)
            ->where('aktif', true)
            ->orderBy('tingkat')
            ->orderBy('pegawai_id')
            ->get()
            ->groupBy('tingkat');

        $daftarGuruBk = Pegawai::query()
            ->where('aktif', true)
            ->whereHas('pengguna', fn ($query) => $query
                ->where('aktif', true)
                ->where(function ($query) {
                    $query->where('peran', 'bk')
                        ->orWhereHas('daftarPeran', fn ($query) => $query
                            ->where('kode', 'bk')
                            ->where('aktif', true));
                }))
            ->orderBy('nama_lengkap')
            ->get(['id', 'nama_lengkap', 'nip', 'jabatan_utama']);

        return view('penugasan-guru-bk-tingkat.index', [
            'daftarTahunPelajaran' => $daftarTahunPelajaran,
            'tahunPelajaran' => $tahunPelajaran,
            'penugasan' => $penugasan,
            'daftarGuruBk' => $daftarGuruBk,
            'daftarTingkat' => PenugasanGuruBkTingkatService::DAFTAR_TINGKAT,
            'pembagianAktif' => $penugasan->flatten(1)->isNotEmpty(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'tahun_pelajaran_id' => ['required', 'integer', Rule::exists('tahun_pelajaran', 'id')],
            'pegawai_id' => ['required', 'integer', Rule::exists('pegawai', 'id')->where('aktif', true)],
            'tingkat' => ['required', 'array', 'min:1', 'max:3'],
            'tingkat.*' => ['required', 'integer', 'distinct', Rule::in(array_keys(PenugasanGuruBkTingkatService::DAFTAR_TINGKAT))],
        ]);

        $guruBkValid = Pegawai::query()
            ->whereKey($data['pegawai_id'])
            ->whereHas('pengguna', fn ($query) => $query
                ->where('aktif', true)
                ->where(function ($query) {
                    $query->where('peran', 'bk')
                        ->orWhereHas('daftarPeran', fn ($query) => $query
                            ->where('kode', 'bk')
                            ->where('aktif', true));
                }))
            ->exists();
        abort_unless($guruBkValid, 422, 'Pegawai yang dipilih harus memiliki akun aktif dengan role Guru BK.');

        DB::transaction(function () use ($data) {
            foreach ($data['tingkat'] as $tingkat) {
                PenugasanGuruBkTingkat::query()->updateOrCreate(
                    [
                        'tahun_pelajaran_id' => $data['tahun_pelajaran_id'],
                        'pegawai_id' => $data['pegawai_id'],
                        'tingkat' => $tingkat,
                    ],
                    [
                        'tanggal_mulai' => now()->toDateString(),
                        'tanggal_selesai' => null,
                        'aktif' => true,
                        'dibuat_oleh_pengguna_id' => auth()->id(),
                    ],
                );
            }
        });

        return redirect()
            ->route('penugasan-guru-bk-tingkat.index', ['tahun_pelajaran_id' => $data['tahun_pelajaran_id']])
            ->with('berhasil', 'Penugasan tingkat Guru BK berhasil disimpan.');
    }

    public function destroy(PenugasanGuruBkTingkat $penugasanGuruBkTingkat)
    {
        $penugasanGuruBkTingkat->update([
            'aktif' => false,
            'tanggal_selesai' => now()->toDateString(),
        ]);

        return redirect()
            ->route('penugasan-guru-bk-tingkat.index', [
                'tahun_pelajaran_id' => $penugasanGuruBkTingkat->tahun_pelajaran_id,
            ])
            ->with('berhasil', 'Penugasan Guru BK berhasil diakhiri.');
    }
}
