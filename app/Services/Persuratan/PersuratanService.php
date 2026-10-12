<?php

namespace App\Services\Persuratan;

use App\Models\BerkasSuratTu;
use App\Models\Pegawai;
use App\Models\Pengguna;
use App\Models\SuratTu;
use App\Services\Notifikasi\NotifikasiPenggunaService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class PersuratanService
{
    public function __construct(private NotifikasiPenggunaService $notifikasi) {}

    public function pastikanPegawai(Pengguna $akun): void
    {
        abort_unless($akun->aktif && ! $akun->akunSiswa() && ! $akun->akunOrangTua()
            && ($akun->administrator() || ($akun->akunPegawai() && $akun->pegawai?->aktif)), 403);
    }

    public function arsip(Pengguna $akun): Builder
    {
        $this->pastikanPegawai($akun);
        abort_unless($akun->memilikiIzin(['surat.lihat', 'surat.kelola', 'surat.sahkan', 'surat.disposisi']), 403);

        return SuratTu::query()->when(! $akun->memilikiIzin('surat.rahasia'), fn ($q) => $q->where('sifat', '<>', 'rahasia'));
    }

    public function pastikanArsip(Pengguna $akun, SuratTu $surat, ?string $izin = null): void
    {
        $this->arsip($akun)->findOrFail($surat->id);
        if ($izin) {
            abort_unless($akun->memilikiIzin($izin), 403);
        }
    }

    public function penugasanSaya(Pengguna $akun): Builder
    {
        $this->pastikanPegawai($akun);
        abort_unless($akun->akunPegawai(), 403);

        return SuratTu::where('jenis', 'tugas')->whereNotNull('disahkan_pada')
            ->whereHas('penugasan', fn ($q) => $q->where('pegawai_id', $akun->pegawai_id));
    }

    public function pengesah(): Collection
    {
        return Pengguna::with('daftarPeran.izin', 'pegawai')->where('aktif', true)->whereNull('siswa_id')->get()
            ->filter(fn ($akun) => ! $akun->akunOrangTua()
                && ($akun->administrator() || ($akun->akunPegawai() && $akun->pegawai?->aktif))
                && $akun->memilikiIzin('surat.sahkan'))->sortBy('nama')->values();
    }

    public function pegawai(): Collection
    {
        return Pegawai::where('aktif', true)->whereHas('pengguna', fn ($q) => $q
            ->where('aktif', true)->where('akun_sistem', false)->whereNull('siswa_id'))
            ->orderBy('nama_lengkap')->get(['id', 'nama_lengkap', 'jabatan_utama']);
    }

    public function simpan(Request $request, ?SuratTu $surat = null): SuratTu
    {
        $akun = $request->user();
        $this->arsip($akun);
        abort_unless($akun->memilikiIzin('surat.kelola'), 403);
        if ($surat) {
            $this->pastikanArsip($akun, $surat);
        }
        $data = $request->validate([
            'versi' => [$surat ? 'required' : 'prohibited', 'integer', 'min:1'],
            'jenis' => ['required', Rule::in(array_keys(SuratTu::JENIS))],
            'nomor_surat' => ['required', 'string', 'max:120'],
            'nomor_agenda' => [Rule::requiredIf($request->jenis === 'masuk'), 'nullable', 'string', 'max:80'],
            'tanggal_surat' => ['required', 'date_format:Y-m-d'],
            'tanggal_diterima' => [Rule::requiredIf($request->jenis === 'masuk'), 'nullable', 'date_format:Y-m-d', 'after_or_equal:tanggal_surat'],
            'perihal' => ['required', 'string', 'max:200'],
            'pihak' => ['required', 'string', 'max:200'],
            'sifat' => ['required', Rule::in(array_keys(SuratTu::SIFAT))],
            'keterangan' => ['nullable', 'string', 'max:5000'],
            'pengesah_pengguna_id' => [Rule::requiredIf($request->jenis !== 'masuk'), 'nullable', 'integer'],
            'mulai_tugas' => [Rule::requiredIf($request->jenis === 'tugas'), 'nullable', 'date_format:Y-m-d'],
            'selesai_tugas' => [Rule::requiredIf($request->jenis === 'tugas'), 'nullable', 'date_format:Y-m-d', 'after_or_equal:mulai_tugas'],
            'tempat_tugas' => [Rule::requiredIf($request->jenis === 'tugas'), 'nullable', 'string', 'max:200'],
            'uraian_tugas' => [Rule::requiredIf($request->jenis === 'tugas'), 'nullable', 'string', 'max:5000'],
            'pegawai_ids' => [Rule::requiredIf($request->jenis === 'tugas'), 'nullable', 'array', 'max:100'],
            'pegawai_ids.*' => ['required', 'integer', 'distinct'],
            'berkas_utama' => [$surat ? 'nullable' : 'required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'lampiran' => ['nullable', 'array', 'max:5'],
            'lampiran.*' => ['file', 'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx,ppt,pptx', 'max:10240'],
            'hapus_lampiran' => ['nullable', 'array', 'max:5'],
            'hapus_lampiran.*' => ['integer', 'distinct'],
            'catatan_revisi' => [$surat ? 'required' : 'nullable', 'string', 'max:2000'],
            'revisi_dari_id' => [$surat ? 'prohibited' : 'nullable', 'integer'],
            'versi_asal' => [Rule::requiredIf(filled($request->revisi_dari_id)), 'nullable', 'integer', 'min:1'],
        ]);
        $this->validasiIsi($data, $akun);
        $tersimpan = [];
        try {
            if ($request->hasFile('berkas_utama')) {
                $tersimpan[] = $this->unggah($request->file('berkas_utama'), 'utama');
            }
            foreach ($request->file('lampiran', []) as $file) {
                $tersimpan[] = $this->unggah($file, 'lampiran');
            }

            return DB::transaction(function () use ($data, $akun, $surat, $tersimpan) {
                $ada = $surat !== null;
                if ($ada) {
                    $surat = SuratTu::lockForUpdate()->findOrFail($surat->id);
                    $this->pastikanArsip($akun, $surat);
                    $this->versi($surat, $data['versi']);
                    $this->syarat($surat->dapatDiedit(), 'Surat telah diajukan atau disahkan. Gunakan revisi untuk surat yang sudah disahkan.');
                    $this->syarat($surat->jenis === $data['jenis'], 'Jenis surat tidak boleh diubah.');
                } else {
                    $surat = new SuratTu(['dibuat_oleh_pengguna_id' => $akun->id,
                        'status' => $data['jenis'] === 'masuk' ? 'tercatat' : 'draf', 'versi' => 1]);
                    if (! empty($data['revisi_dari_id'])) {
                        $asal = SuratTu::lockForUpdate()->findOrFail($data['revisi_dari_id']);
                        $this->pastikanArsip($akun, $asal);
                        $this->versi($asal, $data['versi_asal']);
                        $this->syarat($asal->disahkan_pada && $asal->jenis === $data['jenis'], 'Revisi hanya untuk surat keluar/tugas yang pernah disahkan, dengan jenis yang sama.');
                        $surat->revisi_dari_id = $asal->id;
                    }
                }
                $isi = collect($data)->only(['jenis', 'nomor_surat', 'nomor_agenda', 'tanggal_surat', 'tanggal_diterima',
                    'perihal', 'pihak', 'sifat', 'keterangan', 'pengesah_pengguna_id', 'mulai_tugas', 'selesai_tugas',
                    'tempat_tugas', 'uraian_tugas'])->all();
                foreach (['nomor_surat', 'nomor_agenda', 'perihal', 'pihak', 'tempat_tugas', 'uraian_tugas'] as $kolom) {
                    $isi[$kolom] = isset($isi[$kolom]) ? trim($isi[$kolom]) : null;
                }
                $this->syarat(filled($isi['nomor_surat']) && filled($isi['perihal']) && filled($isi['pihak']), 'Nomor, perihal, dan pengirim/penerima tidak boleh kosong.');
                if ($data['jenis'] !== 'masuk') {
                    $isi['nomor_agenda'] = $isi['tanggal_diterima'] = null;
                } else {
                    $isi['pengesah_pengguna_id'] = null;
                }
                if ($data['jenis'] !== 'tugas') {
                    foreach (['mulai_tugas', 'selesai_tugas', 'tempat_tugas', 'uraian_tugas'] as $kolom) {
                        $isi[$kolom] = null;
                    }
                }
                $tahun = substr($data['tanggal_surat'], 0, 4);
                $normal = fn ($nilai) => Str::upper(Str::squish((string) $nilai));
                $isi['nomor_unik'] = hash('sha256', json_encode([$tahun, $data['jenis'] === 'masuk' ? 'masuk' : 'keluar',
                    $data['jenis'] === 'masuk' ? $normal($isi['pihak']) : null, $normal($isi['nomor_surat'])], JSON_THROW_ON_ERROR));
                $isi['agenda_unik'] = $data['jenis'] === 'masuk'
                    ? hash('sha256', substr($data['tanggal_diterima'], 0, 4).'|'.$normal($isi['nomor_agenda'])) : null;
                $this->syarat(! SuratTu::where('nomor_unik', $isi['nomor_unik'])->when($ada, fn ($q) => $q->where('id', '<>', $surat->id))->exists(), 'Nomor surat sudah terdaftar pada tahun surat tersebut.');
                $this->syarat(! $isi['agenda_unik'] || ! SuratTu::where('agenda_unik', $isi['agenda_unik'])->when($ada, fn ($q) => $q->where('id', '<>', $surat->id))->exists(), 'Nomor agenda sudah digunakan pada tahun surat tersebut.');
                $surat->fill($isi + ['diubah_oleh_pengguna_id' => $akun->id]);
                if ($ada) {
                    $surat->versi++;
                }
                $surat->save();

                $hapus = $data['hapus_lampiran'] ?? [];
                $this->syarat($surat->berkas()->where('aktif', true)->where('jenis', 'lampiran')->whereIn('id', $hapus)->count() === count($hapus), 'Lampiran yang akan dilepas bukan milik surat ini atau sudah tidak aktif.');
                $surat->berkas()->whereIn('id', $hapus)->update(['aktif' => false]);
                foreach ($tersimpan as $berkas) {
                    if ($berkas['jenis'] === 'utama') {
                        $surat->berkas()->where('jenis', 'utama')->where('aktif', true)->update(['aktif' => false]);
                    }
                    $surat->berkas()->create($berkas + ['diunggah_oleh_pengguna_id' => $akun->id]);
                }
                $this->syarat($surat->berkas()->where('aktif', true)->where('jenis', 'lampiran')->count() <= 5, 'Maksimal lima lampiran aktif per surat.');
                $ids = $data['jenis'] === 'tugas' ? ($data['pegawai_ids'] ?? []) : [];
                $surat->penugasan()->whereNotIn('pegawai_id', $ids)->delete();
                foreach (Pegawai::whereIn('id', $ids)->get() as $pegawai) {
                    $surat->penugasan()->updateOrCreate(['pegawai_id' => $pegawai->id], ['nama_pegawai' => $pegawai->nama_lengkap]);
                }
                $this->catat($surat, $akun, $ada ? 'diperbarui' : 'dicatat', $data['catatan_revisi'] ?? null);

                return $surat;
            });
        } catch (Throwable $e) {
            foreach ($tersimpan as $file) {
                Storage::disk('local')->delete($file['lokasi_file']);
            }
            if ($e instanceof QueryException && (str_contains($e->getMessage(), 'nomor_unik') || str_contains($e->getMessage(), 'agenda_unik'))
                && in_array($e->getCode(), ['23505', '23000'], true)) {
                throw ValidationException::withMessages(['nomor_surat' => 'Nomor surat/agenda baru saja digunakan oleh petugas lain. Muat ulang dan periksa nomor.']);
            }
            throw $e;
        }
    }

    public function tindakan(Request $request, SuratTu $surat, string $aksi): SuratTu
    {
        $akun = $request->user();
        $this->pastikanArsip($akun, $surat);
        $data = $request->validate(['versi' => ['required', 'integer', 'min:1'],
            'catatan' => [in_array($aksi, ['kembalikan', 'batal', 'tarik', 'kirim'], true) ? 'required' : 'nullable', 'string', 'max:2000'],
            'konfirmasi_berkas' => [$aksi === 'sahkan' ? 'accepted' : 'nullable']]);

        return DB::transaction(function () use ($akun, $surat, $aksi, $data) {
            $surat = SuratTu::lockForUpdate()->findOrFail($surat->id);
            $this->pastikanArsip($akun, $surat);
            $this->versi($surat, $data['versi']);
            $periksa = in_array($aksi, ['sahkan', 'kembalikan'], true)
                || ($aksi === 'batal' && $surat->disahkan_pada);
            abort_unless($akun->memilikiIzin($periksa ? 'surat.sahkan' : 'surat.kelola'), 403);
            if ($periksa) {
                abort_unless($surat->pengesah_pengguna_id === $akun->id || ($aksi === 'batal' && $akun->administrator()), 403);
            }
            $status = $surat->status;
            $catatan = isset($data['catatan']) ? trim($data['catatan']) : null;
            if (in_array($aksi, ['kembalikan', 'batal', 'tarik', 'kirim'], true)) {
                $this->syarat(filled($catatan), 'Alasan atau catatan wajib diisi.');
            }
            switch ($aksi) {
                case 'ajukan':
                    $this->syarat($surat->jenis !== 'masuk' && in_array($status, ['draf', 'dikembalikan'], true), 'Hanya draf atau surat yang dikembalikan yang dapat diajukan.');
                    $this->validasiKesiapan($surat);
                    $this->syarat($surat->pengesah_pengguna_id !== $akun->id, 'Pengaju tidak boleh mengesahkan suratnya sendiri.');
                    $surat->status = 'diajukan';
                    $surat->diajukan_pada = now();
                    break;
                case 'tarik':
                    $this->syarat($status === 'diajukan', 'Hanya pengajuan yang belum disahkan dapat ditarik.');
                    $surat->status = 'draf';
                    break;
                case 'kembalikan':
                    $this->syarat($status === 'diajukan', 'Surat tidak sedang menunggu pengesahan.');
                    $surat->status = 'dikembalikan';
                    break;
                case 'sahkan':
                    $this->syarat($status === 'diajukan', 'Surat tidak sedang menunggu pengesahan atau sudah disahkan.');
                    $this->syarat($surat->dibuat_oleh_pengguna_id !== $akun->id, 'Pembuat surat tidak boleh mengesahkan suratnya sendiri.');
                    $this->validasiKesiapan($surat);
                    if ($surat->revisi_dari_id) {
                        $asal = SuratTu::lockForUpdate()->findOrFail($surat->revisi_dari_id);
                        $this->pastikanArsip($akun, $asal);
                        $this->syarat(! $asal->revisi()->whereNotNull('disahkan_pada')->exists(), 'Surat asal sudah digantikan oleh revisi lain.');
                        if ($asal->status !== 'dibatalkan') {
                            $asal->forceFill(['status' => 'dibatalkan', 'dibatalkan_pada' => now(), 'versi' => $asal->versi + 1,
                                'diubah_oleh_pengguna_id' => $akun->id, 'catatan_status' => 'Digantikan oleh surat '.$surat->nomor_surat])->save();
                            $this->catat($asal, $akun, 'digantikan', $asal->catatan_status);
                            app(TindakLanjutSuratService::class)->batalkanDariSurat($asal, $akun);
                            $this->beritahuPenugasan($asal, 'batal');
                        }
                    }
                    $surat->status = 'disahkan';
                    $surat->disahkan_pada = now();
                    $surat->disahkan_oleh_pengguna_id = $akun->id;
                    break;
                case 'kirim':
                    $this->syarat($status === 'disahkan', 'Pengiriman hanya dapat dicatat setelah pengesahan.');
                    $surat->status = 'dikirim';
                    $surat->dikirim_pada = now();
                    break;
                case 'batal':
                    $this->syarat($status !== 'dibatalkan', 'Surat sudah dibatalkan.');
                    $surat->status = 'dibatalkan';
                    $surat->dibatalkan_pada = now();
                    break;
                default:
                    abort(404);
            }
            $surat->versi++;
            $surat->catatan_status = $catatan;
            $surat->diubah_oleh_pengguna_id = $akun->id;
            $surat->save();
            $this->catat($surat, $akun, $aksi, $catatan);
            if ($aksi === 'sahkan') {
                app(TindakLanjutSuratService::class)->buatDariSuratTugas($surat);
            } elseif ($aksi === 'batal') {
                app(TindakLanjutSuratService::class)->batalkanDariSurat($surat, $akun);
            }
            if ($aksi === 'ajukan') {
                $this->notifikasi->kirim($surat->pengesah_pengguna_id, 'penting', 'Surat menunggu pengesahan',
                    'Ada surat yang perlu diperiksa dan disahkan.', route('persuratan.show', $surat),
                    'surat-tu:'.$surat->id.':'.$surat->versi.':ajukan');
            } elseif (in_array($aksi, ['kembalikan', 'sahkan', 'batal', 'tarik'], true)) {
                $this->notifikasi->kirim($surat->dibuat_oleh_pengguna_id, 'informasi', 'Status surat diperbarui',
                    'Status surat menjadi '.SuratTu::STATUS[$surat->status].'.', route('persuratan.show', $surat),
                    'surat-tu:'.$surat->id.':'.$surat->versi.':status');
            }
            if ($aksi === 'sahkan' || ($aksi === 'batal' && $surat->disahkan_pada)) {
                $this->beritahuPenugasan($surat, $aksi);
            }

            return $surat;
        });
    }

    public function baca(Pengguna $akun, SuratTu $surat): void
    {
        DB::transaction(function () use ($akun, $surat) {
            $surat = SuratTu::lockForUpdate()->findOrFail($surat->id);
            $this->penugasanSaya($akun)->findOrFail($surat->id);
            $this->syarat(in_array($surat->status, ['disahkan', 'dikirim'], true), 'Surat sudah dibatalkan dan tidak dapat dikonfirmasi.');
            $tugas = $surat->penugasan()->where('pegawai_id', $akun->pegawai_id)->lockForUpdate()->firstOrFail();
            if (! $tugas->dibaca_pada) {
                $tugas->update(['dibaca_pada' => now(), 'dibaca_oleh_pengguna_id' => $akun->id]);
                $surat->tindakLanjut()->where('pegawai_id', $akun->pegawai_id)->whereNull('dibaca_pada')->update(['dibaca_pada' => now()]);
                $this->catat($surat, $akun, 'dibaca', null, ['pegawai_id' => $akun->pegawai_id]);
            }
        });
    }

    public function unduh(Pengguna $akun, SuratTu $surat, BerkasSuratTu $berkas, bool $pribadi = false)
    {
        if ($pribadi) {
            $this->penugasanSaya($akun)->findOrFail($surat->id);
            abort_unless($berkas->aktif, 404);
        } else {
            $this->pastikanArsip($akun, $surat);
        }
        abort_unless($berkas->surat_tu_id === $surat->id, 404);
        $path = $this->path($berkas);
        abort_unless($path && hash_equals($berkas->sha256, hash_file('sha256', $path)), 404);

        return Storage::disk('local')->download($berkas->lokasi_file, $berkas->nama_file_asli,
            ['Content-Type' => $berkas->tipe_file, 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store']);
    }

    private function validasiIsi(array $data, Pengguna $akun): void
    {
        if ($data['sifat'] === 'rahasia') {
            abort_unless($akun->memilikiIzin('surat.rahasia'), 403);
        }
        if ($data['jenis'] !== 'masuk') {
            $pengesah = $this->pengesah()->firstWhere('id', (int) $data['pengesah_pengguna_id']);
            $this->syarat($pengesah !== null && ($data['sifat'] !== 'rahasia' || $pengesah->memilikiIzin('surat.rahasia')), 'Pengesah harus akun aktif dengan izin pengesahan dan akses sifat surat tersebut.');
            $this->syarat((int) $data['pengesah_pengguna_id'] !== $akun->id, 'Pilih pengesah yang berbeda dari pembuat surat.');
        }
        if ($data['jenis'] === 'tugas') {
            $ids = collect($data['pegawai_ids'] ?? [])->map(fn ($id) => (int) $id);
            $this->syarat($ids->isNotEmpty() && $ids->diff($this->pegawai()->pluck('id'))->isEmpty(), 'Pilih guru/pegawai aktif yang memiliki akun NUSA aktif.');
            $this->syarat(filled(trim($data['tempat_tugas'])) && filled(trim($data['uraian_tugas'])), 'Tempat dan uraian tugas wajib diisi.');
        }
    }

    private function validasiKesiapan(SuratTu $surat): void
    {
        $pengesah = $this->pengesah()->firstWhere('id', $surat->pengesah_pengguna_id);
        $this->syarat($pengesah !== null && ($surat->sifat !== 'rahasia' || $pengesah->memilikiIzin('surat.rahasia')), 'Akun pengesah sudah tidak aktif atau tidak memiliki akses surat tersebut.');
        $berkas = $surat->berkas()->where('aktif', true)->get();
        $this->syarat($berkas->where('jenis', 'utama')->count() === 1, 'Berkas utama surat wajib tersedia.');
        foreach ($berkas as $item) {
            $path = $this->path($item);
            $this->syarat($path && hash_equals($item->sha256, hash_file('sha256', $path)), 'Berkas surat hilang atau berubah. Unggah berkas yang benar sebelum pengesahan.');
        }
        if ($surat->jenis === 'tugas') {
            $ids = $surat->penugasan()->pluck('pegawai_id');
            $this->syarat($ids->isNotEmpty() && $ids->diff($this->pegawai()->pluck('id'))->isEmpty(), 'Ada penerima tugas yang tidak aktif atau tidak memiliki akun aktif. Perbaiki daftar penerima.');
        }
    }

    private function beritahuPenugasan(SuratTu $surat, string $aksi): void
    {
        if ($surat->jenis !== 'tugas') {
            return;
        }
        $penerima = $this->notifikasi->penggunaUntukDaftarPegawai($surat->penugasan()->pluck('pegawai_id'))
            ->filter(fn ($akun) => ! $akun->akunSiswa() && ! $akun->akunOrangTua() && $akun->akunPegawai());
        $batal = $aksi === 'batal';
        $this->notifikasi->kirimKeBanyak($penerima, $batal ? 'peringatan' : 'penting',
            $batal ? 'Surat tugas dibatalkan' : 'Surat tugas baru disahkan',
            $batal ? 'Penugasan Anda dibatalkan atau digantikan. Periksa surat di NUSA.' : 'Anda menerima penugasan resmi. Buka surat untuk melihat rincian tugas.',
            route('surat-saya.show', $surat), 'surat-tu:'.$surat->id.':'.$surat->versi.':'.$aksi,
            ['surat_id' => $surat->id]);
    }

    private function catat(SuratTu $surat, Pengguna $akun, string $aksi, ?string $catatan, ?array $data = null): void
    {
        $surat->riwayat()->create(['pengguna_id' => $akun->id, 'aksi' => $aksi, 'versi' => $surat->versi,
            'catatan' => $catatan, 'data' => $data ?? ['surat' => $surat->attributesToArray(),
                'pegawai' => $surat->penugasan()->get(['pegawai_id', 'nama_pegawai'])->toArray(),
                'berkas' => $surat->berkas()->where('aktif', true)->get(['id', 'jenis', 'nama_file_asli', 'sha256'])->toArray()]]);
    }

    private function unggah(UploadedFile $file, string $jenis): array
    {
        $lokasi = $file->storeAs('persuratan', Str::uuid().'.'.$file->extension(), 'local');
        $this->syarat(is_string($lokasi), 'Berkas gagal disimpan. Coba kembali.');

        return ['jenis' => $jenis, 'lokasi_file' => $lokasi, 'nama_file_asli' => mb_substr(basename($file->getClientOriginalName()), 0, 250),
            'tipe_file' => $file->getMimeType(), 'ukuran_file' => $file->getSize(), 'sha256' => hash_file('sha256', $file->getRealPath())];
    }

    private function path(BerkasSuratTu $berkas): ?string
    {
        $root = realpath(Storage::disk('local')->path('persuratan'));
        $path = realpath(Storage::disk('local')->path($berkas->lokasi_file));
        if (! $root || ! $path || ! is_file($path) || ! is_readable($path)) {
            return null;
        }
        $prefix = $root.DIRECTORY_SEPARATOR;
        $valid = PHP_OS_FAMILY === 'Windows' ? str_starts_with(strtolower($path), strtolower($prefix)) : str_starts_with($path, $prefix);

        return $valid ? $path : null;
    }

    private function versi(SuratTu $surat, int $versi): void
    {
        $this->syarat($surat->versi === $versi, 'Surat sudah berubah. Muat ulang sebelum menyimpan atau mengambil keputusan.');
    }

    private function syarat(bool $benar, string $pesan): void
    {
        if (! $benar) {
            throw ValidationException::withMessages(['surat' => $pesan]);
        }
    }
}
