<?php

namespace App\Http\Controllers;

use App\Models\AturanSanksiPoin;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AturanSanksiPoinController extends Controller
{
    public function index()
    {
        $aturanSanksi = AturanSanksiPoin::orderBy('batas_poin')->get();

        return view('aturan-sanksi-poin.index', compact('aturanSanksi'));
    }

    public function create()
    {
        return view('aturan-sanksi-poin.form', [
            'aturanSanksiPoin' => new AturanSanksiPoin(['aktif' => true]),
            'judul' => 'Tambah Aturan Sanksi',
            'aksi' => route('aturan-sanksi-poin.store'),
            'metode' => 'POST',
        ]);
    }

    public function store(Request $request)
    {
        AturanSanksiPoin::create($this->validasi($request));

        return redirect()->route('aturan-sanksi-poin.index')->with('berhasil', 'Aturan sanksi berhasil ditambahkan.');
    }

    public function edit(AturanSanksiPoin $aturanSanksiPoin)
    {
        return view('aturan-sanksi-poin.form', [
            'aturanSanksiPoin' => $aturanSanksiPoin,
            'judul' => 'Edit Aturan Sanksi',
            'aksi' => route('aturan-sanksi-poin.update', $aturanSanksiPoin),
            'metode' => 'PUT',
        ]);
    }

    public function update(Request $request, AturanSanksiPoin $aturanSanksiPoin)
    {
        $aturanSanksiPoin->update($this->validasi($request, $aturanSanksiPoin));

        return redirect()->route('aturan-sanksi-poin.index')->with('berhasil', 'Aturan sanksi berhasil diperbarui.');
    }

    public function destroy(AturanSanksiPoin $aturanSanksiPoin)
    {
        $aturanSanksiPoin->update(['aktif' => false]);

        return redirect()->route('aturan-sanksi-poin.index')->with('berhasil', 'Aturan sanksi dinonaktifkan.');
    }

    private function validasi(Request $request, ?AturanSanksiPoin $aturan = null): array
    {
        return $request->validate([
            'batas_poin' => ['required', 'integer', 'min:1', 'max:10000', Rule::unique('aturan_sanksi_poin', 'batas_poin')->ignore($aturan)],
            'nama' => ['required', 'string', 'max:120'],
            'deskripsi' => ['required', 'string'],
            'urutan' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'aktif' => ['required', 'boolean'],
        ]);
    }
}
