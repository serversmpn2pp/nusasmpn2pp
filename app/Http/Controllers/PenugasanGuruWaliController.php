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
                'siswa.anggotaKelas' => fn ($query) => $query->where('status_keanggotaan', 'aktif')->with('kelas:id,nama,tingkat'),
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

        $daftarPegawai = Pegawai::query()
            ->withCount([
                'penugasanGuruWaliSiswa as jumlah_siswa_wali_aktif' => fn ($query) => $query->where('aktif', true),
            ])
            ->where('aktif', true)
            ->orderBy('nama_lengkap')
            ->get(['id', 'nama_lengkap', 'nip', 'jabatan_utama']);

        $daftarSiswa = Siswa::query()
            ->with([
                'anggotaKelas' => fn ($query) => $query
                    ->where('status_keanggotaan', 'aktif')
                    ->with('kelas:id,nama,tingkat')
                    ->latest('tanggal_masuk'),
                'penugasanGuruWaliSiswa' => fn ($query) => $query
                    ->where('aktif', true)
                    ->with('guruWali:id,nama_lengkap,nip'),
            ])
            ->where('aktif', true)
            ->orderBy('nama_lengkap')
            ->get(['id', 'nama_lengkap', 'nisn', 'nis'])
            ->sortBy(function (Siswa $siswa) {
                $kelas = $siswa->anggotaKelas->first()?->kelas;

                return sprintf(
                    '%02d|%s|%s',
                    $kelas?->tingkat ?? 99,
                    mb_strtolower($kelas?->nama ?? 'zzzz'),
                    mb_strtolower($siswa->nama_lengkap),
                );
            })
            ->values();

        $daftarKelas = $daftarSiswa
            ->map(fn (Siswa $siswa) => $siswa->anggotaKelas->first()?->kelas)
            ->filter()
            ->unique('id')
            ->sortBy(fn ($kelas) => sprintf('%02d|%s', $kelas->tingkat, mb_strtolower($kelas->nama)))
            ->values();

        $jumlahPenugasanAktif = PenugasanGuruWaliSiswa::query()
            ->where('aktif', true)
            ->whereHas('siswa', fn ($query) => $query->where('aktif', true))
            ->count();

        $ringkasan = [
            'jumlah_siswa_aktif' => $daftarSiswa->count(),
            'jumlah_ditugaskan' => $jumlahPenugasanAktif,
            'jumlah_belum_ditugaskan' => max(0, $daftarSiswa->count() - $jumlahPenugasanAktif),
            'jumlah_guru_wali' => PenugasanGuruWaliSiswa::query()
                ->where('aktif', true)
                ->distinct('guru_wali_pegawai_id')
                ->count('guru_wali_pegawai_id'),
        ];

        return view('penugasan-guru-wali.index', compact(
            'penugasan',
            'daftarPegawai',
            'daftarSiswa',
            'daftarKelas',
            'ringkasan',
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

        $hasil = DB::transaction(function () use ($data) {
            $hasil = [
                'baru' => 0,
                'dipindahkan' => 0,
                'tetap' => 0,
            ];

            foreach ($data['siswa_ids'] as $siswaId) {
                $penugasanAktif = PenugasanGuruWaliSiswa::query()
                    ->where('siswa_id', $siswaId)
                    ->where('aktif', true)
                    ->lockForUpdate()
                    ->first();

                if ((int) $penugasanAktif?->guru_wali_pegawai_id === (int) $data['guru_wali_pegawai_id']) {
                    $hasil['tetap']++;

                    continue;
                }

                if ($penugasanAktif) {
                    $penugasanAktif->update([
                        'aktif' => false,
                        'tanggal_selesai' => $data['tanggal_mulai'],
                    ]);
                    $hasil['dipindahkan']++;
                } else {
                    $hasil['baru']++;
                }

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

            return $hasil;
        });

        $jumlahBerubah = $hasil['baru'] + $hasil['dipindahkan'];
        $bagianPesan = [];

        if ($jumlahBerubah > 0) {
            $bagianPesan[] = $jumlahBerubah . ' siswa berhasil ditugaskan';
        }

        if ($hasil['dipindahkan'] > 0) {
            $bagianPesan[] = $hasil['dipindahkan'] . ' di antaranya dipindahkan dari Guru Wali sebelumnya';
        }

        if ($hasil['tetap'] > 0) {
            $bagianPesan[] = $hasil['tetap'] . ' siswa sudah berada pada Guru Wali yang dipilih sehingga tidak diubah';
        }

        return redirect()
            ->route('penugasan-guru-wali.index')
            ->with('berhasil', implode('. ', $bagianPesan) . '.');
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
