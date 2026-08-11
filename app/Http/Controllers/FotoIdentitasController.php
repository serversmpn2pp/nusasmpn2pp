<?php

namespace App\Http\Controllers;

use App\Models\AnggotaKelas;
use App\Models\Kelas;
use App\Models\Pegawai;
use App\Models\TahunPelajaran;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class FotoIdentitasController extends Controller
{
    public function index(Request $request)
    {
        $pengguna = $request->user();
        $bolehKelolaSiswa = $pengguna?->memilikiIzin('siswa.kelola') ?? false;
        $bolehKelolaPegawai = $pengguna?->memilikiIzin('pegawai.kelola') ?? false;
        abort_unless($bolehKelolaSiswa || $bolehKelolaPegawai, 403);

        $tab = $request->input('tab');
        if (! in_array($tab, ['siswa', 'pegawai'], true)) {
            $tab = $bolehKelolaSiswa ? 'siswa' : 'pegawai';
        }
        if (($tab === 'siswa' && ! $bolehKelolaSiswa) || ($tab === 'pegawai' && ! $bolehKelolaPegawai)) {
            $tab = $bolehKelolaSiswa ? 'siswa' : 'pegawai';
        }

        $data = $tab === 'siswa'
            ? $this->dataFotoSiswa($request)
            : $this->dataFotoPegawai($request);

        return view('foto-identitas.index', $data + compact(
            'tab',
            'bolehKelolaSiswa',
            'bolehKelolaPegawai',
        ));
    }

    private function dataFotoSiswa(Request $request): array
    {
        $daftarTahunPelajaran = TahunPelajaran::query()
            ->whereHas('kelas')
            ->orderByDesc('aktif')
            ->orderByDesc('tanggal_mulai')
            ->get(['id', 'nama', 'aktif']);
        $tahunPelajaranDipilih = $daftarTahunPelajaran
            ->firstWhere('id', (int) $request->input('tahun_pelajaran_id'))
            ?: $daftarTahunPelajaran->firstWhere('aktif', true)
            ?: $daftarTahunPelajaran->first();
        $daftarKelas = Kelas::query()
            ->when(
                $tahunPelajaranDipilih,
                fn ($query) => $query->where('tahun_pelajaran_id', $tahunPelajaranDipilih->id),
                fn ($query) => $query->whereRaw('1 = 0'),
            )
            ->where('aktif', true)
            ->orderBy('tingkat')
            ->orderBy('nama')
            ->get(['id', 'tahun_pelajaran_id', 'nama', 'tingkat']);
        $kelasDipilih = $daftarKelas
            ->firstWhere('id', (int) $request->input('kelas_id'))
            ?: $daftarKelas->first();
        $statusFoto = in_array($request->input('status_foto'), ['semua', 'belum', 'sudah'], true)
            ? $request->input('status_foto')
            : 'semua';
        $kataKunci = trim((string) $request->input('kata_kunci'));
        $semuaAnggota = collect();

        if ($tahunPelajaranDipilih && $kelasDipilih) {
            $semuaAnggota = AnggotaKelas::query()
                ->with('siswa:id,nama_lengkap,nis,nisn,foto,jenis_kelamin,aktif')
                ->where('tahun_pelajaran_id', $tahunPelajaranDipilih->id)
                ->where('kelas_id', $kelasDipilih->id)
                ->where('status_keanggotaan', 'aktif')
                ->whereHas('siswa', fn ($query) => $query->where('aktif', true))
                ->get(['id', 'siswa_id', 'tahun_pelajaran_id', 'kelas_id', 'nomor_absen'])
                ->filter(fn (AnggotaKelas $anggota) => $anggota->siswa)
                ->unique('siswa_id')
                ->sortBy(fn (AnggotaKelas $anggota) => sprintf(
                    '%05d|%s',
                    $anggota->nomor_absen ?? 99999,
                    $anggota->siswa?->nama_lengkap ?? '',
                ))
                ->values();
        }

        $ringkasanFoto = $this->ringkasanFoto($semuaAnggota->map->siswa);
        $daftarAnggota = $semuaAnggota
            ->filter(function (AnggotaKelas $anggota) use ($statusFoto, $kataKunci) {
                $siswa = $anggota->siswa;
                $punyaFoto = filled($siswa?->foto);
                $sesuaiStatus = match ($statusFoto) {
                    'belum' => ! $punyaFoto,
                    'sudah' => $punyaFoto,
                    default => true,
                };
                $sesuaiKataKunci = blank($kataKunci) || Str::contains(
                    Str::lower(implode(' ', [
                        $siswa?->nama_lengkap,
                        $siswa?->nis,
                        $siswa?->nisn,
                    ])),
                    Str::lower($kataKunci),
                );

                return $sesuaiStatus && $sesuaiKataKunci;
            })
            ->values();

        return compact(
            'daftarTahunPelajaran',
            'tahunPelajaranDipilih',
            'daftarKelas',
            'kelasDipilih',
            'statusFoto',
            'kataKunci',
            'ringkasanFoto',
            'daftarAnggota',
        ) + [
            'daftarPegawai' => collect(),
            'daftarJenisPegawai' => collect(),
            'jenisPegawai' => '',
            'statusPegawai' => 'aktif',
        ];
    }

    private function dataFotoPegawai(Request $request): array
    {
        $daftarJenisPegawai = Pegawai::query()
            ->whereNotNull('jenis_pegawai')
            ->where('jenis_pegawai', '<>', '')
            ->distinct()
            ->orderBy('jenis_pegawai')
            ->pluck('jenis_pegawai');
        $jenisPegawai = trim((string) $request->input('jenis_pegawai'));
        if ($jenisPegawai !== '' && ! $daftarJenisPegawai->contains($jenisPegawai)) {
            $jenisPegawai = '';
        }
        $statusPegawai = in_array($request->input('status_pegawai'), ['aktif', 'nonaktif', 'semua'], true)
            ? $request->input('status_pegawai')
            : 'aktif';
        $statusFoto = in_array($request->input('status_foto'), ['semua', 'belum', 'sudah'], true)
            ? $request->input('status_foto')
            : 'semua';
        $kataKunci = trim((string) $request->input('kata_kunci'));
        $semuaPegawai = Pegawai::query()
            ->when($statusPegawai === 'aktif', fn ($query) => $query->where('aktif', true))
            ->when($statusPegawai === 'nonaktif', fn ($query) => $query->where('aktif', false))
            ->when($jenisPegawai !== '', fn ($query) => $query->where('jenis_pegawai', $jenisPegawai))
            ->orderBy('nama_lengkap')
            ->get([
                'id',
                'nama_lengkap',
                'nip',
                'nuptk',
                'foto',
                'jenis_kelamin',
                'jenis_pegawai',
                'jabatan_utama',
                'aktif',
            ]);
        $ringkasanFoto = $this->ringkasanFoto($semuaPegawai);
        $daftarPegawai = $semuaPegawai
            ->filter(function (Pegawai $pegawai) use ($statusFoto, $kataKunci) {
                $punyaFoto = filled($pegawai->foto);
                $sesuaiStatus = match ($statusFoto) {
                    'belum' => ! $punyaFoto,
                    'sudah' => $punyaFoto,
                    default => true,
                };
                $sesuaiKataKunci = blank($kataKunci) || Str::contains(
                    Str::lower(implode(' ', [
                        $pegawai->nama_lengkap,
                        $pegawai->nip,
                        $pegawai->nuptk,
                    ])),
                    Str::lower($kataKunci),
                );

                return $sesuaiStatus && $sesuaiKataKunci;
            })
            ->values();

        return compact(
            'daftarJenisPegawai',
            'jenisPegawai',
            'statusPegawai',
            'statusFoto',
            'kataKunci',
            'ringkasanFoto',
            'daftarPegawai',
        ) + [
            'daftarTahunPelajaran' => collect(),
            'tahunPelajaranDipilih' => null,
            'daftarKelas' => collect(),
            'kelasDipilih' => null,
            'daftarAnggota' => collect(),
        ];
    }

    private function ringkasanFoto(Collection $daftar): array
    {
        $total = $daftar->count();
        $sudah = $daftar->filter(fn ($item) => filled($item?->foto))->count();

        return [
            'total' => $total,
            'sudah' => $sudah,
            'belum' => $total - $sudah,
        ];
    }
}
