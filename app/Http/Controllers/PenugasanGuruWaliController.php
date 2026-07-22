<?php

namespace App\Http\Controllers;

use App\Models\Pegawai;
use App\Models\PenugasanGuruWaliSiswa;
use App\Models\Peran;
use App\Models\Pengguna;
use App\Models\Siswa;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PenugasanGuruWaliController extends Controller
{
    public function index(Request $request)
    {
        $kataKunci = trim((string) $request->input('kata_kunci', ''));
        $guruWaliDipilih = $this->inputId($request, 'guru_wali_pegawai_id');

        $penugasan = PenugasanGuruWaliSiswa::query()
            ->with([
                'siswa.anggotaKelas' => fn ($query) => $query->where('status_keanggotaan', 'aktif')->with('kelas:id,nama'),
                'guruWali:id,nama_lengkap,nip',
            ])
            ->where('aktif', true)
            ->when($guruWaliDipilih, fn ($query) => $query->where('guru_wali_pegawai_id', $guruWaliDipilih))
            ->when($kataKunci !== '', function ($query) use ($kataKunci) {
                $query->where(function ($query) use ($kataKunci) {
                    $query->whereHas('siswa', fn ($query) => $query
                        ->where('nama_lengkap', 'ilike', '%' . $kataKunci . '%')
                        ->orWhere('nisn', 'ilike', '%' . $kataKunci . '%'))
                        ->orWhereHas('guruWali', fn ($query) => $query
                            ->where('nama_lengkap', 'ilike', '%' . $kataKunci . '%')
                            ->orWhere('nip', 'ilike', '%' . $kataKunci . '%'));
                });
            })
            ->latest('tanggal_mulai')
            ->paginate(15)
            ->withQueryString();

        $daftarPegawai = Pegawai::where('aktif', true)
            ->orderBy('nama_lengkap')
            ->get(['id', 'nama_lengkap', 'nip', 'jabatan_utama']);

        $daftarSiswa = Siswa::query()
            ->with([
                'anggotaKelas' => fn ($query) => $query
                    ->where('status_keanggotaan', 'aktif')
                    ->with('kelas:id,nama')
                    ->latest('tanggal_masuk'),
                'penugasanGuruWaliSiswa' => fn ($query) => $query
                    ->where('aktif', true)
                    ->with('guruWali:id,nama_lengkap'),
            ])
            ->where('aktif', true)
            ->orderBy('nama_lengkap')
            ->get(['id', 'nama_lengkap', 'nisn', 'nis']);

        return view('penugasan-guru-wali.index', compact(
            'penugasan',
            'daftarPegawai',
            'daftarSiswa',
            'kataKunci',
            'guruWaliDipilih',
        ));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'guru_wali_pegawai_id' => ['required', 'integer', Rule::exists('pegawai', 'id')->where('aktif', true)],
            'siswa_ids' => ['required', 'array', 'min:1', 'max:200'],
            'siswa_ids.*' => ['required', 'integer', 'distinct', Rule::exists('siswa', 'id')->where('aktif', true)],
            'tanggal_mulai' => ['required', 'date'],
            'nomor_sk' => ['nullable', 'string', 'max:100'],
            'catatan' => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($data) {
            foreach ($data['siswa_ids'] as $siswaId) {
                PenugasanGuruWaliSiswa::query()
                    ->where('siswa_id', $siswaId)
                    ->where('aktif', true)
                    ->update([
                        'aktif' => false,
                        'tanggal_selesai' => $data['tanggal_mulai'],
                    ]);

                PenugasanGuruWaliSiswa::create([
                    'siswa_id' => $siswaId,
                    'guru_wali_pegawai_id' => $data['guru_wali_pegawai_id'],
                    'tanggal_mulai' => $data['tanggal_mulai'],
                    'nomor_sk' => filled($data['nomor_sk'] ?? null) ? trim($data['nomor_sk']) : null,
                    'catatan' => filled($data['catatan'] ?? null) ? trim($data['catatan']) : null,
                    'aktif' => true,
                    'dibuat_oleh_pengguna_id' => auth()->id(),
                ]);
            }

            $this->pasangPeranGuruWali((int) $data['guru_wali_pegawai_id']);
        });

        return redirect()
            ->route('penugasan-guru-wali.index')
            ->with('berhasil', count($data['siswa_ids']) . ' siswa berhasil ditugaskan kepada guru wali.');
    }

    public function destroy(PenugasanGuruWaliSiswa $penugasanGuruWali)
    {
        $penugasanGuruWali->update([
            'aktif' => false,
            'tanggal_selesai' => now()->toDateString(),
        ]);

        return redirect()
            ->route('penugasan-guru-wali.index')
            ->with('berhasil', 'Penugasan guru wali berhasil diakhiri.');
    }

    private function pasangPeranGuruWali(int $pegawaiId): void
    {
        $pengguna = Pengguna::where('pegawai_id', $pegawaiId)->first();
        $peran = Peran::where('kode', 'guru_wali')->first();

        if ($pengguna && $peran) {
            $pengguna->daftarPeran()->syncWithoutDetaching([$peran->id]);
        }
    }

    private function inputId(Request $request, string $field): ?int
    {
        $value = $request->input($field);

        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }
}
