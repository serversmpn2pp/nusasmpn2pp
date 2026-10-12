<?php

namespace App\Services\Persuratan;

use App\Models\BerkasSuratTu;
use App\Models\BuktiTindakLanjutSuratTu;
use App\Models\Pengguna;
use App\Models\SuratTu;
use App\Models\TindakLanjutSuratTu;
use App\Services\Notifikasi\NotifikasiPenggunaService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class TindakLanjutSuratService
{
    public function __construct(private PersuratanService $suratService, private NotifikasiPenggunaService $notifikasi) {}

    public function daftar(Pengguna $akun, bool $monitoring = false): Builder
    {
        $this->suratService->pastikanPegawai($akun);
        $query = TindakLanjutSuratTu::query();
        if ($monitoring) {
            return $query->whereIn('surat_tu_id', $this->suratService->arsip($akun)->select('id'));
        }
        abort_unless($akun->akunPegawai(), 403);

        return $query->where('pegawai_id', $akun->pegawai_id);
    }

    public function pastikanAkses(Pengguna $akun, TindakLanjutSuratTu $tugas): bool
    {
        $this->suratService->pastikanPegawai($akun);
        $arsip = $akun->memilikiIzin(['surat.lihat', 'surat.kelola', 'surat.sahkan', 'surat.disposisi'])
            && $this->suratService->arsip($akun)->whereKey($tugas->surat_tu_id)->exists();
        abort_unless($arsip || ($akun->akunPegawai() && $tugas->pegawai_id === $akun->pegawai_id), 404);

        return $arsip;
    }

    public function dapatMemeriksa(Pengguna $akun, TindakLanjutSuratTu $tugas): bool
    {
        return $this->dapatMengatur($akun, $tugas) && $tugas->pegawai_id !== $akun->pegawai_id;
    }

    public function dapatMengatur(Pengguna $akun, TindakLanjutSuratTu $tugas): bool
    {
        return ($tugas->pemeriksa_pengguna_id === $akun->id || $akun->administrator())
            && $akun->memilikiIzin($tugas->jenis === 'disposisi' ? 'surat.disposisi' : 'surat.sahkan')
            && ($tugas->surat->sifat !== 'rahasia' || $akun->memilikiIzin('surat.rahasia'));
    }

    public function calonPemeriksa(TindakLanjutSuratTu $tugas)
    {
        $izin = $tugas->jenis === 'disposisi' ? 'surat.disposisi' : 'surat.sahkan';

        return Pengguna::with('daftarPeran.izin', 'pegawai')->where('aktif', true)->whereNull('siswa_id')->get()
            ->filter(fn ($akun) => ! $akun->akunOrangTua()
                && ($akun->administrator() || ($akun->akunPegawai() && $akun->pegawai?->aktif))
                && $akun->pegawai_id !== $tugas->pegawai_id && $akun->memilikiIzin($izin)
                && ($tugas->surat->sifat !== 'rahasia' || $akun->memilikiIzin('surat.rahasia')))
            ->sortBy('nama')->values();
    }

    public function disposisi(Request $request, SuratTu $surat): void
    {
        $akun = $request->user();
        $this->suratService->pastikanArsip($akun, $surat, 'surat.disposisi');
        $data = $request->validate(['versi' => ['required', 'integer', 'min:1'],
            'pegawai_ids' => ['required', 'array', 'min:1', 'max:100'], 'pegawai_ids.*' => ['required', 'integer', 'distinct'],
            'instruksi' => ['required', 'string', 'max:5000'], 'batas_tanggal' => ['required', 'date_format:Y-m-d', 'after_or_equal:today']]);
        DB::transaction(function () use ($akun, $surat, $data) {
            $surat = SuratTu::lockForUpdate()->findOrFail($surat->id);
            $this->suratService->pastikanArsip($akun, $surat, 'surat.disposisi');
            $this->syarat($surat->versi === (int) $data['versi'], 'Surat telah berubah. Muat ulang halaman.');
            $this->syarat($surat->jenis === 'masuk' && $surat->status === 'tercatat', 'Disposisi hanya untuk surat masuk aktif.');
            $utama = $surat->berkas()->where('aktif', true)->where('jenis', 'utama')->first();
            $this->syarat($utama && $this->path($utama, 'persuratan'), 'Berkas utama hilang atau berubah. Perbaiki arsip sebelum disposisi.');
            $ids = collect($data['pegawai_ids'])->map(fn ($id) => (int) $id);
            $pegawai = $this->suratService->pegawai()->whereIn('id', $ids);
            $this->syarat($pegawai->count() === $ids->count() && ! $ids->contains($akun->pegawai_id), 'Pilih penerima aktif yang memiliki akun, bukan pemberi disposisi sendiri.');
            $this->syarat(! $surat->tindakLanjut()->whereIn('pegawai_id', $ids)->exists(), 'Penerima sudah memiliki disposisi surat ini. Perbarui disposisi yang ada.');
            $this->syarat(filled(trim($data['instruksi'])), 'Instruksi tidak boleh kosong.');
            foreach ($pegawai as $p) {
                $tugas = $surat->tindakLanjut()->create(['pegawai_id' => $p->id, 'nama_pegawai' => $p->nama_lengkap,
                    'jenis' => 'disposisi', 'instruksi' => trim($data['instruksi']), 'batas_tanggal' => $data['batas_tanggal'],
                    'pemeriksa_pengguna_id' => $akun->id, 'dibuat_oleh_pengguna_id' => $akun->id]);
                $this->catat($tugas, $akun, 'ditugaskan');
                $this->beritahuPenerima($tugas, 'Disposisi surat baru', 'Anda menerima disposisi surat. Periksa instruksi dan tenggat di NUSA.', 'ditugaskan');
            }
            $surat->increment('versi');
            $surat->riwayat()->create(['pengguna_id' => $akun->id, 'aksi' => 'disposisi', 'versi' => $surat->versi,
                'catatan' => 'Disposisi kepada '.$ids->count().' pegawai.', 'data' => ['pegawai_ids' => $ids->all(), 'instruksi' => trim($data['instruksi']), 'batas_tanggal' => $data['batas_tanggal']]]);
        });
    }

    // Dipanggil di dalam transaksi pengesahan, tanpa notifikasi tambahan.
    public function buatDariSuratTugas(SuratTu $surat): void
    {
        if ($surat->jenis !== 'tugas') {
            return;
        }
        foreach ($surat->penugasan()->get() as $p) {
            $tugas = $surat->tindakLanjut()->firstOrCreate(['pegawai_id' => $p->pegawai_id], [
                'nama_pegawai' => $p->nama_pegawai, 'jenis' => 'tugas', 'instruksi' => $surat->uraian_tugas,
                'batas_tanggal' => $surat->selesai_tugas->toDateString(), 'pemeriksa_pengguna_id' => $surat->pengesah_pengguna_id,
                'dibuat_oleh_pengguna_id' => $surat->dibuat_oleh_pengguna_id]);
            if ($tugas->wasRecentlyCreated) {
                $this->catat($tugas, Pengguna::findOrFail($surat->disahkan_oleh_pengguna_id), 'ditugaskan');
            }
        }
    }

    public function batalkanDariSurat(SuratTu $surat, Pengguna $akun): void
    {
        foreach ($surat->tindakLanjut()->whereNotIn('status', ['selesai', 'dibatalkan'])->lockForUpdate()->get() as $tugas) {
            $tugas->forceFill(['status' => 'dibatalkan', 'versi' => $tugas->versi + 1])->save();
            $this->catat($tugas, $akun, 'dibatalkan', 'Surat dibatalkan atau digantikan.');
            if ($tugas->jenis === 'disposisi') {
                $this->beritahuPenerima($tugas, 'Disposisi dibatalkan', 'Surat yang mendasari disposisi dibatalkan. Periksa di NUSA.', 'dibatalkan');
            }
        }
    }

    public function laporan(Request $request, TindakLanjutSuratTu $tugas): void
    {
        $akun = $request->user();
        $this->pastikanAkses($akun, $tugas);
        abort_unless($akun->akunPegawai() && $tugas->pegawai_id === $akun->pegawai_id, 403);
        $data = $request->validate(['versi' => ['required', 'integer', 'min:1'], 'aksi' => ['required', Rule::in(['draf', 'ajukan'])],
            'hasil' => ['required', 'string', 'max:10000'], 'bukti_lama' => ['nullable', 'array', 'max:5'], 'bukti_lama.*' => ['integer', 'distinct'],
            'bukti' => ['nullable', 'array', 'max:5'], 'bukti.*' => ['file', 'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx,ppt,pptx', 'max:10240']]);
        $files = [];
        try {
            foreach ($request->file('bukti', []) as $file) {
                $lokasi = $file->storeAs('persuratan-tindak-lanjut', Str::uuid().'.'.$file->extension(), 'local');
                $this->syarat(is_string($lokasi), 'Bukti gagal diunggah.');
                $files[] = ['lokasi_file' => $lokasi, 'nama_file_asli' => mb_substr(basename($file->getClientOriginalName()), 0, 250),
                    'tipe_file' => $file->getMimeType(), 'ukuran_file' => $file->getSize(), 'sha256' => hash_file('sha256', $file->getRealPath())];
            }
            DB::transaction(function () use ($akun, $tugas, $data, $files) {
                $tugas = $this->kunci($akun, $tugas, $data['versi']);
                $this->syarat($tugas->dapatDilaporkan(), 'Tugas sudah dibatalkan, selesai, atau laporannya sedang diperiksa.');
                $this->syarat(filled(trim($data['hasil'])), 'Hasil tindak lanjut wajib diisi.');
                $terakhir = $tugas->laporan()->first();
                $ids = $data['bukti_lama'] ?? [];
                $lama = $terakhir ? $terakhir->bukti()->whereIn('id', $ids)->get() : collect();
                $this->syarat($lama->count() === count($ids), 'Bukti terdahulu bukan milik laporan terakhir tugas ini.');
                $this->syarat(count($files) + $lama->count() <= 5, 'Maksimal lima bukti per laporan.');
                $ajukan = $data['aksi'] === 'ajukan';
                if ($ajukan) {
                    $this->syarat($this->calonPemeriksa($tugas)->contains('id', $tugas->pemeriksa_pengguna_id), 'Pemeriksa tidak aktif, tidak memiliki izin, atau merupakan penerima tugas sendiri. Minta pejabat/admin mengganti pemeriksa.');
                    foreach ($lama as $bukti) {
                        $this->syarat($this->path($bukti, 'persuratan-tindak-lanjut') !== null, 'Bukti terdahulu hilang atau berubah. Lepas bukti tersebut dan unggah ulang.');
                    }
                }
                $laporan = $tugas->laporan()->create(['nomor' => ($terakhir?->nomor ?? 0) + 1, 'hasil' => trim($data['hasil']),
                    'diajukan_pada' => $ajukan ? now() : null, 'dibuat_oleh_pengguna_id' => $akun->id]);
                foreach ($lama as $bukti) {
                    $laporan->bukti()->create($bukti->only(['lokasi_file', 'nama_file_asli', 'tipe_file', 'ukuran_file', 'sha256']));
                }
                foreach ($files as $file) {
                    $laporan->bukti()->create($file);
                }
                $tugas->forceFill(['status' => $ajukan ? 'menunggu_pemeriksaan' : ($tugas->status === 'perlu_perbaikan' ? 'perlu_perbaikan' : 'diproses'),
                    'versi' => $tugas->versi + 1])->save();
                $this->catat($tugas, $akun, $ajukan ? 'laporan_diajukan' : 'progres_disimpan', null, ['laporan_id' => $laporan->id]);
                if ($ajukan) {
                    $this->notifikasi->kirim($tugas->pemeriksa_pengguna_id, 'penting', 'Laporan surat menunggu pemeriksaan',
                        'Ada laporan tindak lanjut surat yang perlu diperiksa.', route('tindak-lanjut-surat.show', $tugas), 'tindak-surat:'.$tugas->id.':'.$tugas->versi.':laporan');
                }
            });
        } catch (Throwable $e) {
            foreach ($files as $file) {
                Storage::disk('local')->delete($file['lokasi_file']);
            }
            throw $e;
        }
    }

    public function tindakan(Request $request, TindakLanjutSuratTu $tugas, string $aksi): void
    {
        $akun = $request->user();
        $this->pastikanAkses($akun, $tugas);
        $data = $request->validate(['versi' => ['required', 'integer', 'min:1'],
            'catatan' => [in_array($aksi, ['kembalikan', 'batal', 'ubah'], true) ? 'required' : 'nullable', 'string', 'max:2000'],
            'instruksi' => [$aksi === 'ubah' ? 'required' : 'nullable', 'string', 'max:5000'],
            'batas_tanggal' => [$aksi === 'ubah' ? 'required' : 'nullable', 'date_format:Y-m-d'],
            'pemeriksa_pengguna_id' => [$aksi === 'ubah' ? 'required' : 'nullable', 'integer']]);
        DB::transaction(function () use ($akun, $tugas, $data, $aksi) {
            $tugas = $this->kunci($akun, $tugas, $data['versi']);
            $this->syarat($tugas->surat->status !== 'dibatalkan', 'Surat telah dibatalkan.');
            $catatan = isset($data['catatan']) ? trim($data['catatan']) : null;
            if ($aksi === 'baca' || $aksi === 'mulai') {
                abort_unless($akun->akunPegawai() && $tugas->pegawai_id === $akun->pegawai_id, 403);
                $this->syarat(! in_array($tugas->status, ['selesai', 'dibatalkan'], true), 'Tugas tidak lagi aktif.');
                if ($aksi === 'baca' && $tugas->dibaca_pada) {
                    return;
                }
                if ($aksi === 'mulai') {
                    $this->syarat($tugas->status === 'belum_mulai', 'Tugas sudah dimulai.');
                    $tugas->status = 'diproses';
                }
                $tugas->dibaca_pada ??= now();
                if ($tugas->jenis === 'tugas') {
                    $tugas->surat->penugasan()->where('pegawai_id', $akun->pegawai_id)->whereNull('dibaca_pada')
                        ->update(['dibaca_pada' => $tugas->dibaca_pada, 'dibaca_oleh_pengguna_id' => $akun->id]);
                }
            } else {
                // Pemilik surat tugas sendiri tidak boleh menerima laporannya sendiri.
                abort_unless(in_array($aksi, ['ubah', 'batal'], true) ? $this->dapatMengatur($akun, $tugas) : $this->dapatMemeriksa($akun, $tugas), 403);
                if (in_array($aksi, ['kembalikan', 'batal', 'ubah'], true)) {
                    $this->syarat(filled($catatan), 'Alasan wajib diisi.');
                }
                if (in_array($aksi, ['terima', 'kembalikan'], true)) {
                    $this->syarat($tugas->status === 'menunggu_pemeriksaan', 'Tidak ada laporan yang menunggu pemeriksaan.');
                    $laporan = $tugas->laporan()->firstOrFail();
                    $this->syarat($laporan->diajukan_pada && ! $laporan->keputusan, 'Laporan tidak sedang diajukan.');
                    if ($aksi === 'terima') {
                        foreach ($laporan->bukti as $bukti) {
                            $this->syarat($this->path($bukti, 'persuratan-tindak-lanjut') !== null, 'Bukti hilang atau berubah. Kembalikan laporan agar diperbaiki.');
                        }
                    }
                    $laporan->update(['keputusan' => $aksi === 'terima' ? 'diterima' : 'dikembalikan', 'catatan_pemeriksa' => $catatan,
                        'diperiksa_oleh_pengguna_id' => $akun->id, 'diperiksa_pada' => now()]);
                    $tugas->status = $aksi === 'terima' ? 'selesai' : 'perlu_perbaikan';
                    $tugas->selesai_pada = $aksi === 'terima' ? now() : null;
                } elseif ($aksi === 'ubah') {
                    $this->syarat(in_array($tugas->status, ['belum_mulai', 'diproses', 'perlu_perbaikan'], true), 'Tugas selesai/dibatalkan atau laporan sedang diperiksa; instruksi tidak dapat diubah.');
                    $this->syarat(filled(trim($data['instruksi'])), 'Instruksi tidak boleh kosong.');
                    $this->syarat($data['batas_tanggal'] >= $tugas->batas_tanggal->toDateString() || $data['batas_tanggal'] >= today()->toDateString(), 'Tenggat baru tidak boleh dimundurkan ke masa lalu.');
                    $this->syarat($this->calonPemeriksa($tugas)->contains('id', (int) $data['pemeriksa_pengguna_id']), 'Pilih pemeriksa aktif yang berwenang, bukan penerima tugas sendiri.');
                    $tugas->fill(['instruksi' => trim($data['instruksi']), 'batas_tanggal' => $data['batas_tanggal'], 'pemeriksa_pengguna_id' => $data['pemeriksa_pengguna_id']]);
                } elseif ($aksi === 'batal') {
                    $this->syarat(! in_array($tugas->status, ['selesai', 'dibatalkan'], true), 'Tugas sudah selesai atau dibatalkan.');
                    $tugas->status = 'dibatalkan';
                } else {
                    abort(404);
                }
            }
            $tugas->versi++;
            $tugas->save();
            $this->catat($tugas, $akun, $aksi, $catatan);
            if (! in_array($aksi, ['baca', 'mulai'], true)) {
                $this->beritahuPenerima($tugas, 'Tindak lanjut surat diperbarui', 'Instruksi atau status tindak lanjut surat Anda diperbarui. Periksa di NUSA.', $aksi);
            }
        });
    }

    public function unduhBukti(Pengguna $akun, TindakLanjutSuratTu $tugas, BuktiTindakLanjutSuratTu $bukti)
    {
        $this->pastikanAkses($akun, $tugas);
        abort_unless($tugas->laporan()->whereKey($bukti->laporan_id)->exists(), 404);

        return $this->download($bukti, 'persuratan-tindak-lanjut');
    }

    public function unduhSurat(Pengguna $akun, TindakLanjutSuratTu $tugas, BerkasSuratTu $berkas)
    {
        $this->pastikanAkses($akun, $tugas);
        abort_unless($berkas->surat_tu_id === $tugas->surat_tu_id && $berkas->aktif, 404);

        return $this->download($berkas, 'persuratan');
    }

    private function download($berkas, string $folder)
    {
        abort_unless($this->path($berkas, $folder), 404);

        return Storage::disk('local')->download($berkas->lokasi_file, $berkas->nama_file_asli,
            ['Content-Type' => $berkas->tipe_file, 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store']);
    }

    private function path($berkas, string $folder): ?string
    {
        $root = realpath(Storage::disk('local')->path($folder));
        $path = realpath(Storage::disk('local')->path($berkas->lokasi_file));
        if (! $root || ! $path || ! is_file($path) || ! is_readable($path)) {
            return null;
        }
        $prefix = $root.DIRECTORY_SEPARATOR;
        $valid = PHP_OS_FAMILY === 'Windows' ? str_starts_with(strtolower($path), strtolower($prefix)) : str_starts_with($path, $prefix);

        return $valid && hash_equals($berkas->sha256, hash_file('sha256', $path)) ? $path : null;
    }

    private function kunci(Pengguna $akun, TindakLanjutSuratTu $tugas, int $versi): TindakLanjutSuratTu
    {
        // Semua jalur mengunci surat dahulu, termasuk pembatalan/revisi surat induk.
        SuratTu::lockForUpdate()->findOrFail($tugas->surat_tu_id);
        $tugas = TindakLanjutSuratTu::lockForUpdate()->findOrFail($tugas->id);
        $this->pastikanAkses($akun, $tugas);
        $this->syarat($tugas->versi === $versi, 'Tindak lanjut telah berubah. Muat ulang sebelum menyimpan.');

        return $tugas;
    }

    private function beritahuPenerima(TindakLanjutSuratTu $tugas, string $judul, string $pesan, string $aksi): void
    {
        $penerima = $this->notifikasi->penggunaUntukDaftarPegawai([$tugas->pegawai_id])
            ->filter(fn ($akun) => ! $akun->akunSiswa() && ! $akun->akunOrangTua() && $akun->akunPegawai() && $akun->pegawai?->aktif);
        $this->notifikasi->kirimKeBanyak($penerima, 'penting', $judul, $pesan,
            route('tindak-lanjut-surat.show', $tugas), 'tindak-surat:'.$tugas->id.':'.$tugas->versi.':'.$aksi);
    }

    private function catat(TindakLanjutSuratTu $tugas, Pengguna $akun, string $aksi, ?string $catatan = null, array $tambahan = []): void
    {
        $tugas->riwayat()->create(['pengguna_id' => $akun->id, 'aksi' => $aksi, 'versi' => $tugas->versi,
            'catatan' => $catatan, 'data' => $tugas->attributesToArray() + $tambahan]);
    }

    private function syarat(bool $benar, string $pesan): void
    {
        if (! $benar) {
            throw ValidationException::withMessages(['tindak_lanjut' => $pesan]);
        }
    }
}
