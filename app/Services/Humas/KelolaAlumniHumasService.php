<?php

namespace App\Services\Humas;

use App\Models\AlumniHumas;
use App\Models\AnggotaKelas;
use App\Models\Siswa;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class KelolaAlumniHumasService
{
    public function normalisasi(Request $request): void
    {
        $data = $request->only([...AlumniHumas::PUBLIK, ...AlumniHumas::PRIVAT]);
        $data['anggota_kelas_id'] = $request->input('anggota_kelas_id');
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $data[$key] = in_array($key, ['nama_lengkap', 'nama_sekolah', 'kota_sekolah', 'kelas_terakhir', 'nis', 'nisn', 'jurusan'], true) ? Str::squish($value) : trim($value);
                if ($data[$key] === '') {
                    $data[$key] = null;
                }
            }
        }
        if (is_string($data['nomor_wa'] ?? null)) {
            $data['nomor_wa'] = preg_replace('/[\s()\-]/', '', $data['nomor_wa']);
        }
        if (($data['status_penelusuran'] ?? null) !== 'melanjutkan') {
            foreach (['jenis_sekolah', 'nama_sekolah', 'kota_sekolah', 'jurusan'] as $key) {
                $data[$key] = null;
            }
        }
        if (($data['status_penelusuran'] ?? null) === 'belum_terdata') {
            $data['tanggal_penelusuran'] = null;
        }
        $request->merge($data);
    }

    public function sumber(Request $request): void
    {
        if ($request->filled('siswa_id')) {
            $siswa = Siswa::findOrFail($request->integer('siswa_id'));
            $request->merge($siswa->only(['nama_lengkap', 'nis', 'nisn', 'jenis_kelamin']));
        }
    }

    public function validasi(AlumniHumas $alumni): void
    {
        if ($alumni->tanggal_lulus && $alumni->tanggal_lulus->year !== $alumni->tahun_lulus) {
            throw ValidationException::withMessages(['tanggal_lulus' => 'Tanggal kelulusan harus sesuai tahun lulus.']);
        }
        if ($alumni->tahun_masuk && $alumni->tahun_masuk > $alumni->tahun_lulus) {
            throw ValidationException::withMessages(['tahun_masuk' => 'Tahun masuk tidak boleh setelah tahun lulus.']);
        }
        if ($alumni->tanggal_penelusuran && ($alumni->tanggal_penelusuran->year < $alumni->tahun_lulus || ($alumni->tanggal_lulus && $alumni->tanggal_penelusuran->lt($alumni->tanggal_lulus)))) {
            throw ValidationException::withMessages(['tanggal_penelusuran' => 'Tanggal penelusuran tidak boleh sebelum kelulusan.']);
        }
        if ($alumni->anggota_kelas_id) {
            $anggota = AnggotaKelas::with(['kelas:id,nama,tingkat', 'tahunPelajaran:id,tanggal_selesai'])->findOrFail($alumni->anggota_kelas_id);
            if (! $alumni->siswa_id || $anggota->siswa_id !== $alumni->siswa_id || $anggota->kelas?->tingkat !== 9) {
                throw ValidationException::withMessages(['anggota_kelas_id' => 'Pilih riwayat kelas IX milik siswa yang dipilih.']);
            }
            if ($anggota->tahunPelajaran?->tanggal_selesai && $anggota->tahunPelajaran->tanggal_selesai->year !== $alumni->tahun_lulus) {
                throw ValidationException::withMessages(['tahun_lulus' => 'Tahun lulus harus sesuai akhir tahun pelajaran pada kelas terakhir.']);
            }
            $alumni->kelas_terakhir = $anggota->kelas->nama;
        }
        if (! $alumni->siswa_id && $alumni->nisn && Siswa::where('nisn', $alumni->nisn)->exists()) {
            throw ValidationException::withMessages(['nisn' => 'NISN sudah ada pada data siswa NUSA. Pilih siswa tersebut sebagai sumber data.']);
        }
    }

    public function catat(AlumniHumas $alumni, Request $request, string $aksi, ?string $catatan = null): void
    {
        $alumni->riwayat()->create(['aksi' => $aksi, 'versi' => $alumni->versi, 'snapshot' => $alumni->snapshot(), 'snapshot_privat' => $alumni->only(AlumniHumas::PRIVAT),
            'catatan_perubahan' => $catatan, 'pengguna_id' => $request->user()->id, 'created_at' => now()]);
    }
}
