<?php

namespace App\Services\Humas;

use App\Models\KunjunganTamu;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SimpanLampiranKunjunganService
{
    public function aturan(): array
    {
        return [
            'surat_tugas' => ['nullable', 'array', 'max:3'],
            'surat_tugas.*' => ['file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:5120'],
            'dokumentasi' => ['nullable', 'array', 'max:5'],
            'dokumentasi.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }

    public function pastikanUkuran(array $data): void
    {
        $ukuran = collect($data['surat_tugas'] ?? [])->merge($data['dokumentasi'] ?? [])->sum(fn ($file) => $file->getSize());
        if ($ukuran > 20 * 1024 * 1024) {
            throw ValidationException::withMessages(['lampiran' => 'Total unggahan maksimal 20 MB sekali kirim.']);
        }
    }

    public function simpan(KunjunganTamu $tamu, array $data, int $penggunaId, array &$lokasi): int
    {
        $jumlah = count($data['surat_tugas'] ?? []) + count($data['dokumentasi'] ?? []);
        if ($tamu->lampiran()->count() + $jumlah > 20) {
            throw ValidationException::withMessages(['lampiran' => 'Maksimal 20 lampiran per kunjungan.']);
        }
        foreach (['surat_tugas', 'dokumentasi'] as $jenis) {
            foreach ($data[$jenis] ?? [] as $file) {
                $path = $file->storeAs('buku-tamu/'.$tamu->id, Str::uuid().'.'.$file->extension(), 'local');
                if (! $path) {
                    throw ValidationException::withMessages(['lampiran' => 'Berkas belum tersimpan. Silakan coba kembali.']);
                }
                $lokasi[] = $path;
                $tamu->lampiran()->create([
                    'jenis' => $jenis, 'lokasi_file' => $path,
                    'nama_file_asli' => Str::limit(basename(str_replace('\\', '/', $file->getClientOriginalName())), 240, ''),
                    'tipe_file' => $file->getMimeType(), 'ukuran_file' => $file->getSize(),
                    'diunggah_oleh_pengguna_id' => $penggunaId,
                ]);
            }
        }

        return $jumlah;
    }

    public function bersihkan(array $lokasi): void
    {
        foreach ($lokasi as $path) {
            Storage::disk('local')->delete($path);
        }
    }
}
