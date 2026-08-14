<?php

namespace App\Http\Controllers;

use App\Models\AnggotaKelas;
use App\Models\Kelas;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use App\Support\QrCodeNisn;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class KartuPelajarController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'tahun_pelajaran_id' => ['nullable', 'integer', 'exists:tahun_pelajaran,id'],
            'kelas_id' => ['nullable', 'integer', 'exists:kelas,id'],
            'siswa_id' => ['nullable', 'integer', 'exists:siswa,id'],
        ]);

        $daftarTahunPelajaran = TahunPelajaran::query()
            ->orderByDesc('aktif')
            ->orderByDesc('tanggal_mulai')
            ->orderByDesc('id')
            ->get();
        $tahunPelajaranId = $this->ambilTahunPelajaranId($data['tahun_pelajaran_id'] ?? null, $daftarTahunPelajaran);
        $daftarKelas = $tahunPelajaranId
            ? Kelas::query()
                ->where('tahun_pelajaran_id', $tahunPelajaranId)
                ->where('aktif', true)
                ->orderBy('tingkat')
                ->orderBy('nama')
                ->get()
            : collect();
        $kelasId = $this->ambilKelasId(
            kelasId: $data['kelas_id'] ?? null,
            daftarKelas: $daftarKelas,
            gunakanDefault: ! $request->has('kelas_id'),
        );
        $daftarSiswa = $this->ambilDaftarSiswa($tahunPelajaranId, $kelasId);
        $siswaId = $this->ambilSiswaId($data['siswa_id'] ?? null, $daftarSiswa);
        $anggotaKelas = $this->ambilAnggotaKelas($tahunPelajaranId, $kelasId, $siswaId);
        $kartuPelajar = $anggotaKelas->map(fn (AnggotaKelas $anggota) => $this->buatDataKartu($anggota));

        return view('kartu-pelajar.index', compact(
            'daftarTahunPelajaran',
            'tahunPelajaranId',
            'daftarKelas',
            'kelasId',
            'daftarSiswa',
            'siswaId',
            'kartuPelajar',
        ));
    }

    private function ambilTahunPelajaranId(?int $tahunPelajaranId, $daftarTahunPelajaran): ?int
    {
        if ($tahunPelajaranId && $daftarTahunPelajaran->contains('id', $tahunPelajaranId)) {
            return $tahunPelajaranId;
        }

        $tahunAktif = $daftarTahunPelajaran->firstWhere('aktif', true);

        return $tahunAktif?->id ?? $daftarTahunPelajaran->first()?->id;
    }

    private function ambilKelasId(?int $kelasId, $daftarKelas, bool $gunakanDefault): ?int
    {
        if ($kelasId && $daftarKelas->contains('id', $kelasId)) {
            return $kelasId;
        }

        return $gunakanDefault ? $daftarKelas->first()?->id : null;
    }

    private function ambilSiswaId(?int $siswaId, $daftarSiswa): ?int
    {
        if ($siswaId && $daftarSiswa->contains('id', $siswaId)) {
            return $siswaId;
        }

        return null;
    }

    private function ambilDaftarSiswa(?int $tahunPelajaranId, ?int $kelasId)
    {
        if (! $tahunPelajaranId) {
            return collect();
        }

        return Siswa::query()
            ->where('aktif', true)
            ->whereHas('anggotaKelas', function ($query) use ($tahunPelajaranId, $kelasId) {
                $query->where('tahun_pelajaran_id', $tahunPelajaranId)
                    ->where('status_keanggotaan', 'aktif')
                    ->when($kelasId, function ($query) use ($kelasId) {
                        $query->where('kelas_id', $kelasId);
                    });
            })
            ->orderBy('nama_lengkap')
            ->get(['id', 'nama_lengkap', 'nisn']);
    }

    private function ambilAnggotaKelas(?int $tahunPelajaranId, ?int $kelasId, ?int $siswaId)
    {
        if (! $tahunPelajaranId) {
            return collect();
        }

        return AnggotaKelas::query()
            ->with(['tahunPelajaran', 'kelas', 'siswa'])
            ->where('tahun_pelajaran_id', $tahunPelajaranId)
            ->where('status_keanggotaan', 'aktif')
            ->whereHas('siswa', function ($query) {
                $query->where('aktif', true);
            })
            ->when($kelasId, function ($query) use ($kelasId) {
                $query->where('kelas_id', $kelasId);
            })
            ->when($siswaId, function ($query) use ($siswaId) {
                $query->where('siswa_id', $siswaId);
            })
            ->orderBy('kelas_id')
            ->orderByRaw('nomor_absen IS NULL')
            ->orderBy('nomor_absen')
            ->orderBy('id')
            ->get();
    }

    private function buatDataKartu(AnggotaKelas $anggota): array
    {
        $siswa = $anggota->siswa;
        $nisn = (string) ($siswa?->nisn ?? '');

        return [
            'anggota_kelas' => $anggota,
            'siswa' => $siswa,
            'foto_url' => $this->fotoUrl($siswa),
            'tempat_tanggal_lahir' => $this->tempatTanggalLahir($siswa),
            'ukuran_font_nama' => $this->ukuranFontNama($siswa?->nama_lengkap),
            'ukuran_font_nama_belakang' => $this->ukuranFontNamaBelakang($siswa?->nama_lengkap),
            'qr_svg' => preg_match('/^[0-9]{1,41}$/', $nisn) ? QrCodeNisn::svg($nisn) : null,
        ];
    }

    private function fotoUrl(?Siswa $siswa): string
    {
        if ($siswa?->foto && Storage::disk('public')->exists($siswa->foto)) {
            return asset('storage/' . $siswa->foto);
        }

        return asset('images/kartu-pelajar/default-user.png');
    }

    private function tempatTanggalLahir(?Siswa $siswa): string
    {
        $tanggalLahir = $siswa?->tanggal_lahir
            ? $siswa->tanggal_lahir->locale('id')->translatedFormat('d F Y')
            : null;

        return collect([$siswa?->tempat_lahir, $tanggalLahir])
            ->filter()
            ->join(', ') ?: '-';
    }

    private function ukuranFontNama(?string $nama): float
    {
        $panjang = mb_strlen(trim((string) $nama));

        return match (true) {
            $panjang <= 16 => 11.0,
            $panjang <= 20 => 9.0,
            $panjang <= 24 => 7.5,
            $panjang <= 28 => 7.0,
            $panjang <= 34 => 6.1,
            $panjang <= 42 => 5.3,
            default => 4.7,
        };
    }

    private function ukuranFontNamaBelakang(?string $nama): float
    {
        return round(min($this->ukuranFontNama($nama) * 0.72, 6.2), 1);
    }
}
