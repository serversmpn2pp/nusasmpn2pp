<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\PenggunaResource;
use App\Models\Pengguna;
use App\Services\FotoProfilService;
use App\Services\Mobile\ProfilSayaMobileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Throwable;

class ProfilSayaController extends Controller
{
    public function __construct(private readonly ProfilSayaMobileService $profil) {}

    public function show(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->profil->siapkan($request->user()),
        ])->header('Cache-Control', 'no-store, private');
    }

    public function update(Request $request): JsonResponse
    {
        $pengguna = $request->user();
        $pesan = match (true) {
            $pengguna->akunPegawai() => $this->perbaruiPegawai($request, $pengguna),
            $pengguna->akunSiswa() => $this->perbaruiSiswa($request, $pengguna),
            $pengguna->akunOrangTua() => $this->perbaruiOrangTua($request, $pengguna),
            default => $this->perbaruiAkun($request, $pengguna),
        };

        return response()->json([
            'message' => $pesan,
            'data' => $this->profil->siapkan($pengguna->fresh()),
            'pengguna' => new PenggunaResource($this->muatPengguna($pengguna->fresh())),
        ]);
    }

    public function updateFoto(
        Request $request,
        FotoProfilService $fotoProfilService,
    ): JsonResponse {
        $pengguna = $request->user();
        $pegawai = $pengguna->pegawai;
        abort_unless($pengguna->akunPegawai() && $pegawai, 403);

        $data = $request->validate([
            'foto' => $fotoProfilService->aturan(wajib: true),
        ], $fotoProfilService->pesanValidasi());
        $fotoLama = $pegawai->foto;
        $fotoBaru = $fotoProfilService->simpan($data['foto'], 'pegawai/foto');

        try {
            $pegawai->update(['foto' => $fotoBaru]);
        } catch (Throwable $exception) {
            $fotoProfilService->hapus($fotoBaru);

            throw $exception;
        }

        $fotoProfilService->hapus($fotoLama);

        return response()->json([
            'message' => 'Foto profil berhasil diperbarui.',
            'data' => $this->profil->siapkan($pengguna->fresh()),
        ]);
    }

    private function perbaruiPegawai(Request $request, Pengguna $pengguna): string
    {
        $pegawai = $pengguna->pegawai;
        abort_unless($pegawai, 403);
        $data = $request->validate([
            'nama_lengkap' => ['required', 'string', 'max:255'],
            'nuptk' => ['nullable', 'string', 'max:50', Rule::unique('pegawai', 'nuptk')->ignore($pegawai)],
            'nik' => ['nullable', 'string', 'max:50', Rule::unique('pegawai', 'nik')->ignore($pegawai)],
            'jenis_kelamin' => ['nullable', Rule::in(['L', 'P'])],
            'tempat_lahir' => ['nullable', 'string', 'max:100'],
            'tanggal_lahir' => ['nullable', 'date'],
            'alamat' => ['nullable', 'string'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('pegawai', 'email')->ignore($pegawai)],
            'no_hp' => ['nullable', 'string', 'max:30'],
            'pendidikan_terakhir' => ['nullable', 'string', 'max:100'],
            'jurusan_pendidikan' => ['nullable', 'string', 'max:150'],
            'tahun_lulus' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'keterangan' => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($pegawai, $pengguna, $data) {
            $pegawai->update($data);
            $pengguna->forceFill(['nama' => trim($data['nama_lengkap'])])->save();
        });

        return 'Profil Anda berhasil diperbarui.';
    }

    private function perbaruiSiswa(Request $request, Pengguna $pengguna): string
    {
        $siswa = $pengguna->siswa;
        abort_unless($siswa, 403);
        $data = $request->validate([
            'alamat' => ['nullable', 'string', 'max:2000'],
        ]);

        $siswa->update([
            'alamat' => filled($data['alamat'] ?? null) ? trim($data['alamat']) : null,
        ]);

        return 'Alamat profil berhasil diperbarui.';
    }

    private function perbaruiOrangTua(Request $request, Pengguna $pengguna): string
    {
        $orangTua = $pengguna->orangTuaWali;
        abort_unless($orangTua, 403);
        $data = $request->validate([
            'nama_lengkap' => ['required', 'string', 'max:255'],
            'nomor_wa' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+() .-]+$/'],
        ], [
            'nama_lengkap.required' => 'Nama orang tua atau wali wajib diisi.',
            'nomor_wa.regex' => 'Nomor WhatsApp hanya boleh berisi angka dan tanda telepon yang umum.',
        ]);
        $nama = trim($data['nama_lengkap']);
        $nomorWa = filled($data['nomor_wa'] ?? null) ? trim($data['nomor_wa']) : null;

        DB::transaction(function () use ($orangTua, $pengguna, $nama, $nomorWa) {
            $orangTua->update(['nama_lengkap' => $nama, 'nomor_wa' => $nomorWa]);
            $pengguna->forceFill(['nama' => $nama])->save();
        });

        return 'Profil orang tua berhasil diperbarui.';
    }

    private function perbaruiAkun(Request $request, Pengguna $pengguna): string
    {
        $data = $request->validate([
            'nama_lengkap' => ['required', 'string', 'max:255'],
        ]);
        $pengguna->forceFill(['nama' => trim($data['nama_lengkap'])])->save();

        return 'Profil berhasil diperbarui.';
    }

    private function muatPengguna(Pengguna $pengguna): Pengguna
    {
        return $pengguna->load([
            'orangTuaWali:id,pengguna_id',
            'daftarPeran' => fn ($query) => $query->where('peran.aktif', true),
            'daftarPeran.izin' => fn ($query) => $query->where('izin.aktif', true),
        ]);
    }
}
