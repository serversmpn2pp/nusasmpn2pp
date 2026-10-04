<?php

namespace App\Services\Humas;

use App\Models\LampiranPengaduanHumas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class LampiranPengaduanHumasService
{
    public function unduh(LampiranPengaduanHumas $lampiran)
    {
        $root = realpath(Storage::disk('local')->path('pengaduan-humas'));
        $path = realpath(Storage::disk('local')->path($lampiran->lokasi_file));
        abort_unless($root && $path && str_starts_with(strtolower($path), strtolower($root.DIRECTORY_SEPARATOR)) && is_file($path), 404);

        return Storage::disk('local')->download($lampiran->lokasi_file, $lampiran->nama_file_asli, [
            'Content-Type' => $lampiran->tipe_file, 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store',
        ]);
    }

    public function aturan(bool $required = false): array
    {
        return ['lampiran' => [$required ? 'required' : 'nullable', 'array', 'min:1', 'max:3'],
            'lampiran.*' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'extensions:pdf,jpg,jpeg,png,webp', 'max:10240']];
    }

    public function simpan(Request $request, string $asal = 'internal'): array
    {
        $files = [];
        try {
            foreach ($request->file('lampiran', []) as $file) {
                $path = $file->storeAs('pengaduan-humas', Str::uuid().'.'.$file->extension(), 'local');
                if (! $path) {
                    throw ValidationException::withMessages(['lampiran' => 'Lampiran belum dapat disimpan. Silakan coba kembali.']);
                }
                $files[] = ['lokasi_file' => $path, 'nama_file_asli' => $file->getClientOriginalName(), 'tipe_file' => $file->getMimeType(),
                    'ukuran_file' => $file->getSize(), 'diunggah_oleh_pengguna_id' => $request->user()->id, 'asal' => $asal];
            }
        } catch (Throwable $e) {
            $this->hapus($files);
            throw $e;
        }

        return $files;
    }

    public function hapus(array $files): void
    {
        foreach ($files as $file) {
            Storage::disk('local')->delete($file['lokasi_file']);
        }
    }
}
