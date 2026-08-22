<?php

namespace App\Http\Controllers;

use App\Models\KegiatanUjianCbt;
use App\Models\PenempatanPesertaUjianCbt;
use App\Models\RuangKegiatanUjianCbt;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class RuangKegiatanUjianCbtController extends Controller
{
    public function store(Request $request, KegiatanUjianCbt $kegiatanUjianCbt)
    {
        $this->pastikanAkses($request, $kegiatanUjianCbt);
        $data = $this->rapikan($request, $request->validate($this->aturanValidasi()));
        $urutan = (int) $kegiatanUjianCbt->ruangKegiatanUjianCbt()->max('urutan') + 1;

        $kegiatanUjianCbt->ruangKegiatanUjianCbt()->create([
            ...$data,
            'kode' => 'R'.str_pad((string) $urutan, 2, '0', STR_PAD_LEFT),
            'urutan' => $urutan,
        ]);

        return back()->with('berhasil', 'Ruang ujian berhasil ditambahkan.');
    }

    public function update(Request $request, KegiatanUjianCbt $kegiatanUjianCbt, RuangKegiatanUjianCbt $ruangKegiatanUjianCbt)
    {
        $this->pastikanAkses($request, $kegiatanUjianCbt);
        $this->pastikanMilikKegiatan($kegiatanUjianCbt, $ruangKegiatanUjianCbt);
        $data = $this->rapikan($request, $request->validate($this->aturanValidasi()));
        $maksimalTerisi = (int) PenempatanPesertaUjianCbt::query()
            ->where('ruang_kegiatan_ujian_cbt_id', $ruangKegiatanUjianCbt->id)
            ->selectRaw('COUNT(*) as jumlah')
            ->groupBy('kelompok_peserta_kegiatan_ujian_cbt_id')
            ->get()
            ->max('jumlah');

        if ($data['kapasitas'] < $maksimalTerisi) {
            throw ValidationException::withMessages([
                'kapasitas' => "Kapasitas tidak boleh kurang dari {$maksimalTerisi} karena ruang sudah terisi dalam pembagian peserta.",
            ]);
        }

        $ruangKegiatanUjianCbt->update($data);
        $ruangKegiatanUjianCbt->kelompokPesertaKegiatanUjianCbt()
            ->with('ruangKegiatanUjianCbt')
            ->get()
            ->each(fn ($kelompok) => $kelompok->update([
                'total_kapasitas' => $kelompok->ruangKegiatanUjianCbt->sum('kapasitas'),
            ]));

        return back()->with('berhasil', 'Ruang ujian berhasil diperbarui.');
    }

    public function destroy(Request $request, KegiatanUjianCbt $kegiatanUjianCbt, RuangKegiatanUjianCbt $ruangKegiatanUjianCbt)
    {
        $this->pastikanAkses($request, $kegiatanUjianCbt);
        $this->pastikanMilikKegiatan($kegiatanUjianCbt, $ruangKegiatanUjianCbt);

        if ($ruangKegiatanUjianCbt->kelompokPesertaKegiatanUjianCbt()->exists() || $ruangKegiatanUjianCbt->penempatanPesertaUjianCbt()->exists()) {
            throw ValidationException::withMessages(['ruang' => 'Ruang sudah digunakan dalam pembagian peserta sehingga tidak dapat dihapus.']);
        }

        $ruangKegiatanUjianCbt->delete();

        return back()->with('berhasil', 'Ruang ujian berhasil dihapus.');
    }

    private function aturanValidasi(): array
    {
        return [
            'nama' => ['required', 'string', 'max:100'],
            'lokasi' => ['nullable', 'string', 'max:180'],
            'kapasitas' => ['required', 'integer', 'min:1', 'max:100'],
            'aktif' => ['nullable', 'boolean'],
            'keterangan' => ['nullable', 'string', 'max:500'],
        ];
    }

    private function rapikan(Request $request, array $data): array
    {
        return [
            'nama' => trim($data['nama']),
            'lokasi' => filled($data['lokasi'] ?? null) ? trim($data['lokasi']) : null,
            'kapasitas' => (int) $data['kapasitas'],
            'aktif' => $request->boolean('aktif'),
            'keterangan' => filled($data['keterangan'] ?? null) ? trim($data['keterangan']) : null,
        ];
    }

    private function pastikanAkses(Request $request, KegiatanUjianCbt $kegiatan): void
    {
        abort_unless($kegiatan->dapatDiaksesOleh($request->user()), 403);
    }

    private function pastikanMilikKegiatan(KegiatanUjianCbt $kegiatan, RuangKegiatanUjianCbt $ruang): void
    {
        abort_unless((int) $ruang->kegiatan_ujian_cbt_id === (int) $kegiatan->id, 404);
    }
}
