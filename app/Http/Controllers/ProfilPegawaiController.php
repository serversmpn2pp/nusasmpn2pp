<?php

namespace App\Http\Controllers;

use App\Models\Pegawai;
use App\Services\FotoProfilService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Throwable;

class ProfilPegawaiController extends Controller
{
    public function edit(Request $request)
    {
        $pegawai = $this->pegawaiLogin($request);

        return view('profil-pegawai.edit', compact('pegawai'));
    }

    public function update(Request $request, FotoProfilService $fotoProfilService)
    {
        $pegawai = $this->pegawaiLogin($request);
        $data = $request->validate([
            'nama_lengkap' => ['required', 'string', 'max:255'],
            'nuptk' => ['nullable', 'string', 'max:50', Rule::unique('pegawai', 'nuptk')->ignore($pegawai)],
            'nik' => ['nullable', 'string', 'max:50', Rule::unique('pegawai', 'nik')->ignore($pegawai)],
            'foto' => $fotoProfilService->aturan(),
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
        ], $fotoProfilService->pesanValidasi());
        $fotoLama = $pegawai->foto;
        $fotoBaru = null;

        if ($request->hasFile('foto')) {
            $fotoBaru = $fotoProfilService->simpan($request->file('foto'), 'pegawai/foto');
            $data['foto'] = $fotoBaru;
        } else {
            unset($data['foto']);
        }

        try {
            $pegawai->update($data);
        } catch (Throwable $exception) {
            $fotoProfilService->hapus($fotoBaru);

            throw $exception;
        }

        if ($fotoBaru) {
            $fotoProfilService->hapus($fotoLama);
        }

        $request->user()->update(['nama' => $pegawai->nama_lengkap]);

        return redirect()
            ->route('profil-pegawai.edit')
            ->with('berhasil', 'Profil Anda berhasil diperbarui.');
    }

    public function updateFoto(Request $request, FotoProfilService $fotoProfilService)
    {
        $pegawai = $this->pegawaiLogin($request);
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
            'pesan' => 'Foto profil berhasil diperbarui.',
            'url' => asset('storage/'.$fotoBaru),
        ]);
    }

    private function pegawaiLogin(Request $request): Pegawai
    {
        $pegawai = $request->user()?->pegawai;

        abort_unless($pegawai, 403);

        return $pegawai;
    }
}
