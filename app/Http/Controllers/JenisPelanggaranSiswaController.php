<?php

namespace App\Http\Controllers;

use App\Models\JenisPelanggaranSiswa;
use App\Models\KategoriPembinaanSiswa;
use App\Models\LaporanPembinaanSiswa;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class JenisPelanggaranSiswaController extends Controller
{
    public function index(Request $request)
    {
        $kataKunci = trim((string) $request->input('kata_kunci', ''));
        $tingkat = (string) $request->input('tingkat', 'semua');

        $jenisPelanggaran = JenisPelanggaranSiswa::query()
            ->with('kategoriPembinaanSiswa:id,nama')
            ->when($tingkat !== 'semua', fn ($query) => $query->where('tingkat', $tingkat))
            ->when($kataKunci !== '', fn ($query) => $query->where(function ($query) use ($kataKunci) {
                $query->where('kode', 'ilike', '%' . $kataKunci . '%')
                    ->orWhere('nama', 'ilike', '%' . $kataKunci . '%');
            }))
            ->orderBy('urutan')
            ->paginate(20)
            ->withQueryString();

        return view('jenis-pelanggaran-siswa.index', compact('jenisPelanggaran', 'kataKunci', 'tingkat'));
    }

    public function create()
    {
        return view('jenis-pelanggaran-siswa.form', [
            'jenisPelanggaranSiswa' => new JenisPelanggaranSiswa(['aktif' => true]),
            'daftarKategori' => KategoriPembinaanSiswa::where('aktif', true)->orderBy('nama')->get(),
            'daftarTingkat' => LaporanPembinaanSiswa::DAFTAR_TINGKAT,
            'judul' => 'Tambah Jenis Pelanggaran',
            'aksi' => route('jenis-pelanggaran-siswa.store'),
            'metode' => 'POST',
        ]);
    }

    public function store(Request $request)
    {
        JenisPelanggaranSiswa::create($this->validasi($request));

        return redirect()->route('jenis-pelanggaran-siswa.index')->with('berhasil', 'Jenis pelanggaran berhasil ditambahkan.');
    }

    public function edit(JenisPelanggaranSiswa $jenisPelanggaranSiswa)
    {
        return view('jenis-pelanggaran-siswa.form', [
            'jenisPelanggaranSiswa' => $jenisPelanggaranSiswa,
            'daftarKategori' => KategoriPembinaanSiswa::where('aktif', true)->orderBy('nama')->get(),
            'daftarTingkat' => LaporanPembinaanSiswa::DAFTAR_TINGKAT,
            'judul' => 'Edit Jenis Pelanggaran',
            'aksi' => route('jenis-pelanggaran-siswa.update', $jenisPelanggaranSiswa),
            'metode' => 'PUT',
        ]);
    }

    public function update(Request $request, JenisPelanggaranSiswa $jenisPelanggaranSiswa)
    {
        $jenisPelanggaranSiswa->update($this->validasi($request, $jenisPelanggaranSiswa));

        return redirect()->route('jenis-pelanggaran-siswa.index')->with('berhasil', 'Jenis pelanggaran berhasil diperbarui.');
    }

    public function destroy(JenisPelanggaranSiswa $jenisPelanggaranSiswa)
    {
        $jenisPelanggaranSiswa->update(['aktif' => false]);

        return redirect()->route('jenis-pelanggaran-siswa.index')->with('berhasil', 'Jenis pelanggaran dinonaktifkan tanpa mengubah laporan lama.');
    }

    private function validasi(Request $request, ?JenisPelanggaranSiswa $jenis = null): array
    {
        return $request->validate([
            'kategori_pembinaan_siswa_id' => ['nullable', 'integer', Rule::exists('kategori_pembinaan_siswa', 'id')],
            'kode' => ['required', 'string', 'max:20', Rule::unique('jenis_pelanggaran_siswa', 'kode')->ignore($jenis)],
            'nama' => ['required', 'string'],
            'tingkat' => ['required', Rule::in(array_keys(LaporanPembinaanSiswa::DAFTAR_TINGKAT))],
            'poin' => ['required', 'integer', 'min:1', 'max:1000'],
            'urutan' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'aktif' => ['required', 'boolean'],
        ]);
    }
}
