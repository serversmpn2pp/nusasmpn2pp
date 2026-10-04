<?php

namespace App\Services\Humas;

use App\Models\ButirAkreditasiHumas;
use App\Models\PortofolioAkreditasiHumas;
use App\Models\RiwayatDokumenHumas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use ZipArchive;

class PortofolioAkreditasiHumasService
{
    public const MAKS_BERKAS = 200;

    public const MAKS_BYTE = 209715200;

    private const EKSTENSI = [
        'application/pdf' => 'pdf', 'application/msword' => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/vnd.ms-excel' => 'xls', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
        'application/vnd.ms-powerpoint' => 'ppt', 'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
        'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp',
    ];

    public function kunci(PortofolioAkreditasiHumas $p, int $versi, bool $draf = true): PortofolioAkreditasiHumas
    {
        $p = PortofolioAkreditasiHumas::lockForUpdate()->findOrFail($p->id);
        if ($p->versi !== $versi) {
            $this->gagal('Portofolio telah berubah. Muat ulang sebelum menyimpan atau mengunduh.');
        }
        if ($draf && $p->status !== 'draf') {
            $this->gagal('Portofolio dikunci. Buka revisi dengan alasan sebelum mengubahnya.');
        }

        return $p;
    }

    public function butir(PortofolioAkreditasiHumas $p, ButirAkreditasiHumas $b): ButirAkreditasiHumas
    {
        $b = ButirAkreditasiHumas::lockForUpdate()->findOrFail($b->id);
        abort_unless($b->portofolio_akreditasi_humas_id === $p->id && ! $b->dihapus_pada, 404);

        return $b;
    }

    public function resetPemeriksaan(ButirAkreditasiHumas $b): void
    {
        $b->forceFill(['status' => 'belum_diperiksa', 'catatan_pemeriksaan' => null, 'diperiksa_oleh_pengguna_id' => null, 'diperiksa_pada' => null])->save();
    }

    public function berkas(RiwayatDokumenHumas $berkas): string
    {
        $root = realpath(Storage::disk('local')->path('dokumen-humas'));
        $path = realpath(Storage::disk('local')->path($berkas->lokasi_file));
        if (! $root || ! $path || ! str_starts_with(strtolower($path), strtolower($root.DIRECTORY_SEPARATOR)) || ! is_file($path) || ! is_readable($path)) {
            $this->gagal('Berkas bukti tidak tersedia. Periksa kembali dokumen yang ditautkan.');
        }
        if (! isset(self::EKSTENSI[$berkas->tipe_file])) {
            $this->gagal('Format bukti tidak didukung. Gunakan PDF, dokumen Office, atau gambar dari Pusat Dokumen Humas.');
        }

        return $path;
    }

    public function periksaBukti(ButirAkreditasiHumas $b): void
    {
        $b->load('bukti.berkas');
        if ($b->bukti->count() < $b->target_bukti) {
            $this->gagal('Butir '.$b->kode.' belum memenuhi target '.$b->target_bukti.' bukti.');
        }
        foreach ($b->bukti as $bukti) {
            if (! $bukti->berkas) {
                $this->gagal('Referensi bukti tidak tersedia pada butir '.$b->kode.'.');
            }
            $this->berkas($bukti->berkas);
        }
    }

    public function namaUnduh(string $judul, RiwayatDokumenHumas $berkas): string
    {
        return $this->namaAman($judul).'-v'.$berkas->versi.'.'.self::EKSTENSI[$berkas->tipe_file];
    }

    public function pastikanSiap(PortofolioAkreditasiHumas $p): void
    {
        $p->load('butir.bukti.berkas');
        $berlaku = $p->butir->where('status', '!=', 'tidak_berlaku');
        if ($berlaku->isEmpty()) {
            $this->gagal('Minimal satu butir yang berlaku harus diperiksa dan terpenuhi.');
        }
        foreach ($p->butir as $b) {
            if ($b->status === 'tidak_berlaku') {
                if (blank($b->catatan_pemeriksaan)) {
                    $this->gagal('Alasan tidak berlaku harus dicatat untuk butir '.$b->kode.'.');
                }

                continue;
            }
            if ($b->status !== 'terpenuhi') {
                $this->gagal('Butir '.$b->kode.' masih '.strtolower(ButirAkreditasiHumas::STATUS[$b->status]).'.');
            }
            $this->periksaBukti($b);
        }
    }

    public function catat(PortofolioAkreditasiHumas $p, Request $request, string $aksi, ?string $catatan = null, bool $naikVersi = true): void
    {
        if ($naikVersi) {
            $p->forceFill(['versi' => $p->versi + 1])->save();
        }
        $p->load('butir.bukti');
        $p->riwayat()->create(['aksi' => $aksi, 'versi' => $p->versi, 'catatan' => $catatan,
            'snapshot' => ['nama' => $p->nama, 'instrumen' => $p->instrumen, 'tahun_pelajaran_id' => $p->tahun_pelajaran_id, 'penanggung_jawab' => $p->penanggung_jawab, 'catatan' => $p->catatan, 'status' => $p->status,
                'butir' => $p->butir->map(fn ($b) => ['id' => $b->id, 'kode' => $b->kode, 'judul' => $b->judul, 'deskripsi' => $b->deskripsi, 'urutan' => $b->urutan,
                    'status' => $b->status, 'target_bukti' => $b->target_bukti, 'catatan_pemeriksaan' => $b->catatan_pemeriksaan,
                    'bukti' => $b->bukti->map(fn ($e) => ['id' => $e->id, 'riwayat_dokumen_humas_id' => $e->riwayat_dokumen_humas_id])->all()])->all()],
            'pengguna_id' => $request->user()->id, 'created_at' => now()]);
    }

    public function ringkasan(PortofolioAkreditasiHumas $p): array
    {
        $p->loadMissing('butir.bukti.berkas');
        $berlaku = $p->butir->where('status', '!=', 'tidak_berlaku');
        $terpenuhi = $berlaku->where('status', 'terpenuhi')->count();
        $hilang = $p->butir->flatMap(fn ($b) => $b->bukti)->filter(function ($bukti) {
            try {
                return ! $bukti->berkas || ! $this->berkas($bukti->berkas);
            } catch (ValidationException) {
                return true;
            }
        })->count();

        return ['berlaku' => $berlaku->count(), 'terpenuhi' => $terpenuhi, 'tidak_berlaku' => $p->butir->where('status', 'tidak_berlaku')->count(),
            'bukti' => $p->butir->sum(fn ($b) => $b->bukti->count()), 'hilang' => $hilang,
            'persen' => $berlaku->isEmpty() ? 0 : (int) round($terpenuhi / $berlaku->count() * 100)];
    }

    public function bundel(PortofolioAkreditasiHumas $p): array
    {
        if (! class_exists(ZipArchive::class)) {
            $this->gagal('Ekstensi ZIP PHP belum aktif pada server. Hubungi administrator.');
        }
        $this->pastikanSiap($p);
        $daftar = [];
        $byte = 0;
        foreach ($p->butir->where('status', 'terpenuhi') as $b) {
            $folder = sprintf('%03d', $b->urutan).'-'.$b->id.'-'.Str::limit($this->namaAman($b->kode.' '.$b->judul), 40, '');
            foreach ($b->bukti as $bukti) {
                $path = $this->berkas($bukti->berkas);
                $byte += filesize($path);
                if (count($daftar) >= self::MAKS_BERKAS || $byte > self::MAKS_BYTE) {
                    $this->gagal('Bundel dibatasi 200 berkas dan 200 MB. Pisahkan portofolio menjadi beberapa paket.');
                }
                $daftar[] = ['butir' => $b, 'bukti' => $bukti, 'path' => $path,
                    'nama' => $folder.'/'.$bukti->id.'-'.$this->namaUnduh($bukti->judul, $bukti->berkas)];
            }
        }
        $path = Storage::disk('local')->path('ekspor-akreditasi/'.Str::uuid().'.zip');
        $zip = new ZipArchive;
        try {
            if (! Storage::disk('local')->makeDirectory('ekspor-akreditasi')) {
                $this->gagal('Folder bundel tidak dapat dibuat. Periksa izin penyimpanan server.');
            }
            if ($zip->open($path, ZipArchive::CREATE | ZipArchive::EXCL) !== true) {
                $this->gagal('Bundel tidak dapat dibuat. Periksa ruang penyimpanan dan izin folder server.');
            }
            $p->loadMissing('tahunPelajaran');
            if (! $zip->addFromString('index.html', view('akreditasi-humas.manifest', ['portofolio' => $p, 'daftar' => $daftar])->render())) {
                $this->gagal('Indeks bundel tidak dapat dibuat.');
            }
            foreach ($daftar as $item) {
                if (! $zip->addFile($item['path'], $item['nama'])) {
                    $this->gagal('Berkas bukti tidak dapat dimasukkan ke bundel.');
                }
            }
            if (! $zip->close()) {
                $this->gagal('Pembuatan bundel gagal. Periksa ruang penyimpanan server.');
            }

            return [$path, 'akreditasi-'.$p->id.'-'.$this->namaAman($p->nama).'-v'.$p->versi.'.zip'];
        } catch (\Throwable $e) {
            try {
                $zip->close();
            } catch (\Throwable) { /* ZIP may already be closed. */
            }
            if (is_file($path)) {
                unlink($path);
            }
            if (! $e instanceof ValidationException) {
                report($e);
                $this->gagal('Pembuatan bundel gagal. Periksa berkas, ruang penyimpanan, dan izin folder server.');
            }
            throw $e;
        }
    }

    private function namaAman(string $nama): string
    {
        return Str::limit(Str::slug($nama) ?: 'bukti', 70, '');
    }

    private function gagal(string $pesan): never
    {
        throw ValidationException::withMessages(['portofolio' => $pesan]);
    }
}
