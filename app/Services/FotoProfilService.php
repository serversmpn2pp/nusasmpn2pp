<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class FotoProfilService
{
    public const BATAS_UKURAN_KB = 1536;

    public function aturan(bool $wajib = false): array
    {
        return [
            $wajib ? 'required' : 'nullable',
            'file',
            'image',
            'mimes:jpg,jpeg,png,webp',
            'max:'.self::BATAS_UKURAN_KB,
        ];
    }

    public function pesanValidasi(): array
    {
        return [
            'foto.required' => 'Pilih foto yang akan diunggah.',
            'foto.uploaded' => 'Foto gagal diunggah. Ukurannya mungkin melebihi batas server. Pilih ulang foto agar diperkecil otomatis.',
            'foto.file' => 'Berkas foto tidak dapat dibaca.',
            'foto.image' => 'Berkas yang dipilih bukan gambar yang valid.',
            'foto.mimes' => 'Format foto harus JPG, JPEG, PNG, atau WebP.',
            'foto.max' => 'Foto setelah diproses masih terlalu besar. Ukuran maksimal yang dapat disimpan adalah 1,5 MB.',
        ];
    }

    public function simpan(UploadedFile $foto, string $direktori): string
    {
        return $foto->store($direktori, 'public');
    }

    public function hapus(?string $lokasi): void
    {
        if ($lokasi) {
            Storage::disk('public')->delete($lokasi);
        }
    }
}
