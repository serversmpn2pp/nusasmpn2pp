<?php

namespace App\Http\Controllers;

use App\Models\KegiatanUjianCbt;
use App\Models\SesiKegiatanUjianCbt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SesiKegiatanUjianCbtController extends Controller
{
    public function store(Request $request, KegiatanUjianCbt $kegiatanUjianCbt)
    {
        $this->pastikanAkses($request, $kegiatanUjianCbt);
        $data = $this->validasi($request);
        $urutan = (int) $kegiatanUjianCbt->sesiKegiatanUjianCbt()->max('urutan') + 1;

        $kegiatanUjianCbt->sesiKegiatanUjianCbt()->create([
            ...$this->rapikan($data),
            'kode' => 'S'.str_pad((string) $urutan, 2, '0', STR_PAD_LEFT),
            'urutan' => $urutan,
        ]);

        return back()->with('berhasil', 'Sesi ujian berhasil ditambahkan.');
    }

    public function update(Request $request, KegiatanUjianCbt $kegiatanUjianCbt, SesiKegiatanUjianCbt $sesiKegiatanUjianCbt)
    {
        $this->pastikanAkses($request, $kegiatanUjianCbt);
        $this->pastikanMilikKegiatan($kegiatanUjianCbt, $sesiKegiatanUjianCbt);
        $data = $this->rapikan($this->validasi($request));
        DB::transaction(function () use ($sesiKegiatanUjianCbt, $data) {
            $sesiKegiatanUjianCbt->update($data);
            $sesiKegiatanUjianCbt->jadwalUjianCbt()->update([
                'waktu_mulai' => $data['waktu_mulai'],
                'waktu_selesai' => $data['waktu_selesai'],
                'label_sesi' => $data['nama'],
            ]);
        });

        return back()->with('berhasil', 'Sesi ujian berhasil diperbarui.');
    }

    public function destroy(Request $request, KegiatanUjianCbt $kegiatanUjianCbt, SesiKegiatanUjianCbt $sesiKegiatanUjianCbt)
    {
        $this->pastikanAkses($request, $kegiatanUjianCbt);
        $this->pastikanMilikKegiatan($kegiatanUjianCbt, $sesiKegiatanUjianCbt);

        if ($sesiKegiatanUjianCbt->kelompokPesertaKegiatanUjianCbt()->exists() || $sesiKegiatanUjianCbt->jadwalUjianCbt()->exists()) {
            throw ValidationException::withMessages(['sesi' => 'Sesi sudah digunakan dalam pembagian peserta atau jadwal sehingga tidak dapat dihapus.']);
        }

        $sesiKegiatanUjianCbt->delete();

        return back()->with('berhasil', 'Sesi ujian berhasil dihapus.');
    }

    private function validasi(Request $request): array
    {
        $data = $request->validate([
            'nama' => ['required', 'string', 'max:100'],
            'waktu_mulai' => ['required', 'date_format:H:i'],
            'waktu_selesai' => ['required', 'date_format:H:i'],
            'aktif' => ['nullable', 'boolean'],
            'keterangan' => ['nullable', 'string', 'max:500'],
        ]);

        if ($data['waktu_selesai'] <= $data['waktu_mulai']) {
            throw ValidationException::withMessages(['waktu_selesai' => 'Waktu selesai harus setelah waktu mulai.']);
        }

        return $data;
    }

    private function rapikan(array $data): array
    {
        return [
            'nama' => trim($data['nama']),
            'waktu_mulai' => $data['waktu_mulai'],
            'waktu_selesai' => $data['waktu_selesai'],
            'aktif' => filter_var($data['aktif'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'keterangan' => filled($data['keterangan'] ?? null) ? trim($data['keterangan']) : null,
        ];
    }

    private function pastikanAkses(Request $request, KegiatanUjianCbt $kegiatan): void
    {
        abort_unless($kegiatan->dapatDiaksesOleh($request->user()), 403);
    }

    private function pastikanMilikKegiatan(KegiatanUjianCbt $kegiatan, SesiKegiatanUjianCbt $sesi): void
    {
        abort_unless((int) $sesi->kegiatan_ujian_cbt_id === (int) $kegiatan->id, 404);
    }
}
