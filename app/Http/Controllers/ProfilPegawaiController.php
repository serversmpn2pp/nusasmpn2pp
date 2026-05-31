<?php

namespace App\Http\Controllers;

use App\Models\Pegawai;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProfilPegawaiController extends Controller
{
    public function edit(Request $request)
    {
        $pegawai = $this->pegawaiLogin($request);

        return view('profil-pegawai.edit', compact('pegawai'));
    }

    public function update(Request $request)
    {
        $pegawai = $this->pegawaiLogin($request);
        $data = $request->validate([
            'nama_lengkap' => ['required', 'string', 'max:255'],
            'nuptk' => ['nullable', 'string', 'max:50', Rule::unique('pegawai', 'nuptk')->ignore($pegawai)],
            'nik' => ['nullable', 'string', 'max:50', Rule::unique('pegawai', 'nik')->ignore($pegawai)],
            'foto' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
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

        if ($request->hasFile('foto')) {
            if ($pegawai->foto) {
                Storage::disk('public')->delete($pegawai->foto);
            }

            $data['foto'] = $request->file('foto')->store('pegawai/foto', 'public');
        } else {
            unset($data['foto']);
        }

        $pegawai->update($data);
        $request->user()->update(['nama' => $pegawai->nama_lengkap]);

        return redirect()
            ->route('profil-pegawai.edit')
            ->with('berhasil', 'Profil Anda berhasil diperbarui.');
    }

    private function pegawaiLogin(Request $request): Pegawai
    {
        $pegawai = $request->user()?->pegawai;

        abort_unless($pegawai, 403);

        return $pegawai;
    }
}
