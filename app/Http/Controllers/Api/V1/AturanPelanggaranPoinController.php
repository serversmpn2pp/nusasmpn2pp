<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\JenisPelanggaranSiswa;
use App\Models\KategoriPembinaanSiswa;
use Illuminate\Http\Request;

class AturanPelanggaranPoinController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->akunSiswa(), 403);
        $data = $request->validate([
            'cari' => ['nullable', 'string', 'max:100'], 'kategori_id' => ['nullable', 'integer'],
            'halaman' => ['sometimes', 'integer', 'min:1'],
        ]);
        $query = JenisPelanggaranSiswa::where('aktif', true)
            ->where(fn ($q) => $q->whereNull('kategori_pembinaan_siswa_id')->orWhereHas('kategoriPembinaanSiswa', fn ($q) => $q->where('aktif', true)))
            ->with('kategoriPembinaanSiswa:id,nama')
            ->when($data['kategori_id'] ?? null, fn ($q, $id) => $q->where('kategori_pembinaan_siswa_id', $id))
            ->when(filled($data['cari'] ?? null), fn ($q) => $q->where(function ($q) use ($data) {
                $q->where('nama', 'like', '%'.trim($data['cari']).'%')->orWhere('kode', 'like', '%'.trim($data['cari']).'%');
            }));
        $items = $query->orderBy('urutan')->orderBy('nama')->paginate(30, ['*'], 'halaman', $data['halaman'] ?? 1);

        return response()->json(['data' => [
            'items' => $items->getCollection()->map(fn ($item) => [
                'id' => $item->id, 'kode' => $item->kode, 'nama' => $item->nama,
                'poin' => $item->poin, 'tingkat' => $item->tingkat,
                'kategori' => $item->kategoriPembinaanSiswa?->nama ?? 'Tanpa kategori',
            ]),
            'kategori' => KategoriPembinaanSiswa::where('aktif', true)->orderBy('nama')->get(['id', 'nama']),
            'halaman' => $items->currentPage(), 'halaman_terakhir' => $items->lastPage(), 'total' => $items->total(),
        ]])->header('Cache-Control', 'private, no-store');
    }
}
