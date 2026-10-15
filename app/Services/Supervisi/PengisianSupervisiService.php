<?php

namespace App\Services\Supervisi;

use App\Models\LampiranSupervisi;
use App\Models\Pengguna;
use App\Models\PenilaianSupervisi;
use App\Models\PraobservasiSupervisi;
use App\Models\SesiSupervisi;
use App\Services\Notifikasi\NotifikasiPenggunaService;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class PengisianSupervisiService
{
    public const ISIAN = [
        'topik' => 'Materi / topik',
        'tujuan_pembelajaran' => 'Tujuan pembelajaran',
        'jumlah_peserta_didik' => 'Jumlah peserta didik',
        'karakteristik_peserta_didik' => 'Karakteristik dan kebutuhan peserta didik',
        'kegiatan_pembelajaran' => 'Rencana kegiatan pembelajaran',
        'media_sumber_belajar' => 'Media dan sumber belajar',
        'rencana_asesmen' => 'Rencana asesmen',
        'fokus_supervisi' => 'Fokus pendampingan / kebutuhan guru',
    ];

    public function __construct(private AksesSupervisi $akses, private NotifikasiPenggunaService $notifikasi) {}

    public function lihat(Pengguna $u, SesiSupervisi $s): void
    {
        $this->akses->sesi(SesiSupervisi::query(), $u)->findOrFail($s->id);
    }

    public function hak(Pengguna $u, SesiSupervisi $s): array
    {
        $terbuka = $s->status === 'terjadwal' && $s->program->status !== 'diarsipkan';
        $final = $s->penilaian?->status === 'final';
        $guru = $this->milik($u, $s->guru_pegawai_id);
        $supervisor = $this->milik($u, $s->supervisor_pegawai_id);

        return [
            'isi_praobservasi' => $terbuka && $guru && ! $final && $s->praobservasi?->status !== 'terkirim',
            'kembalikan_praobservasi' => $terbuka && $supervisor && ! $final && $s->praobservasi?->status === 'terkirim',
            'isi_penilaian' => $terbuka && $supervisor && ! $final && $s->praobservasi?->status === 'terkirim',
            'buka_ulang_penilaian' => $terbuka && $final && $u->memilikiIzin('supervisi.kelola'),
        ];
    }

    private function milik(Pengguna $u, int $pegawai): bool
    {
        return $u->akunPegawai() && $u->pegawai?->aktif && (int) $u->pegawai_id === $pegawai;
    }

    public function dataPra(Pengguna $u, SesiSupervisi $s): array
    {
        $p = $s->praobservasi;
        $terlihat = $this->milik($u, $s->guru_pegawai_id) || $p?->status === 'terkirim';

        return [
            'status' => $p?->status ?? 'belum_diisi', 'terlihat' => $terlihat,
            'versi' => $terlihat ? ($p?->versi ?? 0) : null,
            'isian' => $terlihat ? ($p?->isian ?? []) : null,
            'catatan_supervisor' => $terlihat ? $p?->catatan_supervisor : null,
            'dikirim_pada' => $p?->dikirim_pada?->toIso8601String(),
            'lampiran' => $terlihat && $p ? $p->lampiran->map(fn ($f) => [
                ...$f->only(['id', 'nama_file_asli', 'ukuran_file', 'tipe_file']),
                'unduh' => '/api/v1/supervisi/sesi/'.$s->id.'/lampiran/'.$f->id,
            ])->all() : [],
        ];
    }

    public function dataPenilaian(Pengguna $u, SesiSupervisi $s): array
    {
        $p = $s->penilaian;
        $terlihat = $this->milik($u, $s->supervisor_pegawai_id) || $p?->status === 'final';
        $data = ['status' => $p?->status ?? 'belum_diisi', 'terlihat' => $terlihat];
        if (! $terlihat) {
            return $data;
        }

        return [...$data, 'versi' => $p?->versi ?? 0, 'supervisor_pegawai_id' => $s->supervisor_pegawai_id,
            'praobservasi_versi' => $p?->praobservasi_versi,
            'tanggal_observasi' => $p?->tanggal_observasi?->toDateString(), 'nilai' => $p?->nilai ?? [],
            'kekuatan' => $p?->kekuatan, 'perbaikan' => $p?->perbaikan, 'catatan' => $p?->catatan,
            'difinalisasi_pada' => $p?->difinalisasi_pada?->toIso8601String(),
            'ringkasan' => $this->hitung($s, $p?->nilai ?? [])];
    }

    private function kontrol(array $input): array
    {
        return Validator::make($input, ['versi_sesi' => ['required', 'integer', 'min:1'], 'versi' => ['required', 'integer', 'min:0']])->validate();
    }

    private function kunci(Pengguna $u, SesiSupervisi $sesi, array $kontrol): SesiSupervisi
    {
        $this->akses->kunci();
        $s = SesiSupervisi::lockForUpdate()->findOrFail($sesi->id);
        $this->lihat($u, $s);
        $this->akses->versi($s, (int) $kontrol['versi_sesi']);
        $this->syarat($s->status === 'terjadwal' && $s->program->status !== 'diarsipkan', 'Jadwal harus sudah terbit, tidak dibatalkan, dan program tidak diarsipkan.');

        return $s;
    }

    private function versi(?object $record, int $versi): void
    {
        $this->syarat(($record?->versi ?? 0) === $versi, 'Data telah berubah. Muat ulang sebelum melanjutkan.', 'versi');
    }

    private function syarat(bool $benar, string $pesan, string $field = 'supervisi'): void
    {
        if (! $benar) {
            throw ValidationException::withMessages([$field => $pesan]);
        }
    }

    public function simpanPra(Pengguna $u, SesiSupervisi $sesi, array $input): PraobservasiSupervisi
    {
        $this->lihat($u, $sesi);
        abort_unless($this->milik($u, $sesi->guru_pegawai_id), 403);
        $kontrol = $this->kontrol($input);
        $rules = ['isian' => ['present', 'array:'.implode(',', array_keys(self::ISIAN))]];
        foreach (self::ISIAN as $key => $label) {
            $rules['isian.'.$key] = $key === 'jumlah_peserta_didik' ? ['nullable', 'integer', 'between:1,1000'] : ['nullable', 'string', 'max:'.($key === 'topik' ? 500 : 5000)];
        }
        $d = Validator::make($input, $rules)->validate();
        $isian = array_fill_keys(array_keys(self::ISIAN), null);
        foreach ($d['isian'] as $k => $v) {
            $isian[$k] = $k === 'jumlah_peserta_didik' ? ($v !== null ? (int) $v : null) : (trim($v ?? '') ?: null);
        }

        return DB::transaction(function () use ($u, $sesi, $kontrol, $isian) {
            $s = $this->kunci($u, $sesi, $kontrol);
            $this->syarat($this->hak($u, $s)['isi_praobservasi'], 'Praobservasi terkirim atau hasil final tidak dapat diubah.');
            $p = $s->praobservasi;
            $this->versi($p, (int) $kontrol['versi']);
            $p ??= new PraobservasiSupervisi(['sesi_supervisi_id' => $s->id, 'status' => 'draf', 'versi' => 0]);
            $p->fill(['isian' => $isian, 'versi' => $p->versi + 1, 'pengubah_id' => $u->id])->save();
            $this->akses->catat($u, $p, 'Draf praobservasi disimpan');

            return $p;
        });
    }

    public function tindakanPra(Pengguna $u, SesiSupervisi $sesi, string $aksi, array $input): void
    {
        $this->lihat($u, $sesi);
        abort_unless($this->milik($u, $aksi === 'kirim' ? $sesi->guru_pegawai_id : $sesi->supervisor_pegawai_id), 403);
        $kontrol = $this->kontrol($input);
        $d = Validator::make($input, ['konfirmasi' => ['required', 'accepted'], 'catatan' => [$aksi === 'kembalikan' ? 'required' : 'nullable', 'string', 'min:10', 'max:3000']])->validate();

        DB::transaction(function () use ($u, $sesi, $aksi, $kontrol, $d) {
            $s = $this->kunci($u, $sesi, $kontrol);
            $hak = $this->hak($u, $s);
            $this->syarat($hak[$aksi === 'kirim' ? 'isi_praobservasi' : 'kembalikan_praobservasi'], 'Praobservasi tidak dapat diproses pada status ini.');
            $p = $s->praobservasi;
            $this->syarat($p !== null, 'Simpan draf praobservasi terlebih dahulu.');
            $this->versi($p, (int) $kontrol['versi']);
            if ($aksi === 'kirim') {
                foreach (['topik', 'tujuan_pembelajaran', 'jumlah_peserta_didik', 'kegiatan_pembelajaran', 'rencana_asesmen'] as $field) {
                    $this->syarat(filled($p->isian[$field] ?? null), 'Lengkapi '.self::ISIAN[$field].' sebelum mengirim.', 'isian.'.$field);
                }
                $p->fill(['status' => 'terkirim', 'dikirim_pada' => now()]);
            } else {
                $p->fill(['status' => 'dikembalikan', 'catatan_supervisor' => trim($d['catatan'])]);
            }
            $p->fill(['versi' => $p->versi + 1, 'pengubah_id' => $u->id])->save();
            $this->akses->catat($u, $p, $aksi === 'kirim' ? 'Praobservasi dikirim' : 'Praobservasi dikembalikan', $d['catatan'] ?? null);
            $this->beritahu($s, [$aksi === 'kirim' ? $s->supervisor_pegawai_id : $s->guru_pegawai_id], $aksi === 'kirim' ? 'Praobservasi siap ditinjau' : 'Praobservasi perlu perbaikan', 'praobservasi:'.$p->versi);
        });
    }

    public function simpanNilai(Pengguna $u, SesiSupervisi $sesi, array $input): PenilaianSupervisi
    {
        $this->lihat($u, $sesi);
        abort_unless($this->milik($u, $sesi->supervisor_pegawai_id), 403);
        $kontrol = $this->kontrol($input);
        $d = Validator::make($input, [
            'tanggal_observasi' => ['nullable', 'date_format:Y-m-d'], 'nilai' => ['present', 'array', 'list', 'max:500'],
            'nilai.*' => ['required', 'array:kelompok,indikator,skor,catatan'],
            'nilai.*.kelompok' => ['required', 'integer', 'min:0'], 'nilai.*.indikator' => ['required', 'integer', 'min:0'],
            'nilai.*.skor' => ['present', 'nullable', 'integer', Rule::in(array_column($sesi->instrumen->skala, 'skor'))],
            'nilai.*.catatan' => ['nullable', 'string', 'max:2000'],
            'kekuatan' => ['nullable', 'string', 'max:5000'], 'perbaikan' => ['nullable', 'string', 'max:5000'], 'catatan' => ['nullable', 'string', 'max:5000'],
        ])->validate();
        $nilai = [];
        foreach ($d['nilai'] as $n) {
            $g = (int) $n['kelompok'];
            $i = (int) $n['indikator'];
            $this->syarat(isset($sesi->instrumen->kelompok[$g]['indikator'][$i]) && ! isset($nilai[$g.':'.$i]), 'Indikator tidak sesuai versi instrumen atau dikirim dua kali.', 'nilai');
            $nilai[$g.':'.$i] = ['kelompok' => $g, 'indikator' => $i, 'skor' => $n['skor'] !== null ? (int) $n['skor'] : null, 'catatan' => trim($n['catatan'] ?? '') ?: null];
        }

        return DB::transaction(function () use ($u, $sesi, $kontrol, $d, $nilai) {
            $s = $this->kunci($u, $sesi, $kontrol);
            $this->syarat($this->hak($u, $s)['isi_penilaian'], 'Kirim praobservasi terlebih dahulu. Hasil final tidak dapat diubah langsung.');
            $p = $s->penilaian;
            $this->versi($p, (int) $kontrol['versi']);
            $p ??= new PenilaianSupervisi(['sesi_supervisi_id' => $s->id, 'supervisor_pegawai_id' => $s->supervisor_pegawai_id, 'status' => 'draf', 'versi' => 0]);
            $p->fill(['nilai' => array_values($nilai), 'praobservasi_versi' => $s->praobservasi->versi, 'tanggal_observasi' => $d['tanggal_observasi'] ?? null,
                'kekuatan' => trim($d['kekuatan'] ?? '') ?: null, 'perbaikan' => trim($d['perbaikan'] ?? '') ?: null,
                'catatan' => trim($d['catatan'] ?? '') ?: null, 'pengubah_id' => $u->id, 'versi' => $p->versi + 1])->save();
            $this->akses->catat($u, $p, 'Draf penilaian disimpan');

            return $p;
        });
    }

    public function tindakanNilai(Pengguna $u, SesiSupervisi $sesi, string $aksi, array $input): void
    {
        $this->lihat($u, $sesi);
        if ($aksi === 'buka-ulang') {
            $this->akses->kelola($u);
        } else {
            abort_unless($this->milik($u, $sesi->supervisor_pegawai_id), 403);
        }
        $kontrol = $this->kontrol($input);
        $d = Validator::make($input, ['konfirmasi' => ['required', 'accepted'], 'alasan' => [$aksi === 'buka-ulang' ? 'required' : 'nullable', 'string', 'min:10', 'max:3000']])->validate();

        DB::transaction(function () use ($u, $sesi, $aksi, $kontrol, $d) {
            $s = $this->kunci($u, $sesi, $kontrol);
            $hak = $this->hak($u, $s);
            $this->syarat($hak[$aksi === 'buka-ulang' ? 'buka_ulang_penilaian' : 'isi_penilaian'], 'Penilaian tidak dapat diproses pada status ini.');
            $p = $s->penilaian;
            $this->syarat($p !== null, 'Simpan draf penilaian terlebih dahulu.');
            $this->versi($p, (int) $kontrol['versi']);
            if ($aksi === 'finalisasi') {
                $this->syarat($p->praobservasi_versi === $s->praobservasi->versi, 'Praobservasi telah diperbaiki. Tinjau kembali dan simpan ulang penilaian sebelum finalisasi.');
                $r = $this->hitung($s, $p->nilai);
                $this->syarat($r['lengkap'], 'Seluruh indikator perencanaan dan pelaksanaan wajib dinilai sebelum finalisasi.', 'nilai');
                $this->syarat($p->tanggal_observasi !== null, 'Isi tanggal observasi sebenarnya.', 'tanggal_observasi');
                $tanggal = $p->tanggal_observasi->toDateString();
                $this->syarat($tanggal >= $s->waktu_mulai->toDateString() && $tanggal <= Carbon::today()->toDateString()
                    && $tanggal <= $s->program->tanggal_selesai->toDateString(), 'Tanggal observasi harus sejak tanggal jadwal, tidak melebihi hari ini atau akhir program.', 'tanggal_observasi');
                $this->syarat(filled($p->kekuatan) && filled($p->perbaikan), 'Lengkapi kekuatan dan saran perbaikan sebelum finalisasi.');
                $p->fill(['status' => 'final', 'difinalisasi_pada' => now()]);
            } else {
                // Preserve the finalized snapshot before withdrawing it for correction.
                $this->akses->catat($u, $p, 'Hasil final sebelum koreksi', trim($d['alasan']));
                $p->fill(['status' => 'draf', 'difinalisasi_pada' => null]);
            }
            $p->fill(['versi' => $p->versi + 1, 'pengubah_id' => $u->id])->save();
            $this->akses->catat($u, $p, $aksi === 'finalisasi' ? 'Penilaian difinalisasi' : 'Penilaian dibuka untuk koreksi', $d['alasan'] ?? null);
            $this->beritahu($s, $aksi === 'finalisasi' ? [$s->guru_pegawai_id] : [$s->guru_pegawai_id, $s->supervisor_pegawai_id],
                $aksi === 'finalisasi' ? 'Hasil supervisi tersedia' : 'Hasil supervisi ditarik untuk koreksi', 'penilaian:'.$p->versi);
        });
    }

    public function hitung(SesiSupervisi $s, array $nilai): array
    {
        $baris = collect($nilai)->keyBy(fn ($n) => $n['kelompok'].':'.$n['indikator']);
        $max = max(array_column($s->instrumen->skala, 'skor'));
        $hasil = [];
        $rasio = [];
        foreach (['perencanaan', 'pelaksanaan'] as $tahap) {
            $jumlah = $terisi = $total = 0;
            foreach ($s->instrumen->kelompok as $g => $group) {
                if ($group['tahap'] !== $tahap) {
                    continue;
                }
                foreach ($group['indikator'] as $i => $label) {
                    $jumlah++;
                    $skor = $baris->get($g.':'.$i)['skor'] ?? null;
                    if ($skor !== null) {
                        $terisi++;
                        $total += $skor;
                    }
                }
            }
            $lengkap = $jumlah > 0 && $terisi === $jumlah;
            $rasio[$tahap] = $lengkap ? $total / ($jumlah * $max) * 100 : null;
            $angka = $lengkap ? round($rasio[$tahap], 2) : null;
            $hasil[$tahap] = ['jumlah_indikator' => $jumlah, 'terisi' => $terisi, 'skor_perolehan' => $total, 'skor_maksimal' => $jumlah * $max,
                'lengkap' => $lengkap, 'nilai' => $angka, 'kategori' => $angka !== null ? $this->kategori($angka) : null, 'bobot' => 50];
        }
        $lengkap = $hasil['perencanaan']['lengkap'] && $hasil['pelaksanaan']['lengkap'];
        $akhir = $lengkap ? round(($rasio['perencanaan'] + $rasio['pelaksanaan']) / 2, 2) : null;

        return ['tahap' => $hasil, 'lengkap' => $lengkap, 'nilai_akhir' => $akhir, 'kategori' => $akhir !== null ? $this->kategori($akhir) : null];
    }

    private function kategori(float $nilai): string
    {
        return match (true) {
            $nilai >= 91 => 'Sangat Baik', $nilai >= 81 => 'Baik', $nilai >= 71 => 'Cukup', default => 'Perlu Pembinaan',
        };
    }

    public function unggah(Pengguna $u, SesiSupervisi $sesi, array $input, UploadedFile $file): void
    {
        $this->lihat($u, $sesi);
        abort_unless($this->milik($u, $sesi->guru_pegawai_id), 403);
        $kontrol = $this->kontrol($input);
        Validator::make(['berkas' => $file], ['berkas' => ['required', 'file', 'mimes:pdf,doc,docx,jpg,jpeg,png,webp', 'extensions:pdf,doc,docx,jpg,jpeg,png,webp', 'max:10240']])->validate();
        $path = null;
        try {
            DB::transaction(function () use ($u, $sesi, $kontrol, $file, &$path) {
                $s = $this->kunci($u, $sesi, $kontrol);
                $this->syarat($this->hak($u, $s)['isi_praobservasi'], 'Lampiran hanya dapat diubah saat praobservasi masih dapat diedit.');
                $p = $s->praobservasi;
                $this->versi($p, (int) $kontrol['versi']);
                $this->syarat(($p?->lampiran()->count() ?? 0) < 5, 'Maksimal lima lampiran perangkat ajar per sesi.', 'berkas');
                $p ??= new PraobservasiSupervisi(['sesi_supervisi_id' => $s->id, 'status' => 'draf', 'versi' => 0, 'isian' => []]);
                $p->fill(['versi' => $p->versi + 1, 'pengubah_id' => $u->id])->save();
                $path = $file->storeAs('supervisi/'.$s->id, Str::uuid().'.'.$file->extension(), 'local');
                $this->syarat((bool) $path, 'Berkas belum dapat disimpan.', 'berkas');
                $nama = Str::limit(preg_replace('/[\x00-\x1F\x7F\\\\\/]/u', '_', $file->getClientOriginalName()), 190, '');
                $f = $p->lampiran()->create(['lokasi_file' => $path, 'nama_file_asli' => $nama, 'tipe_file' => $file->getMimeType(), 'ukuran_file' => $file->getSize(), 'pengunggah_id' => $u->id]);
                $this->akses->catat($u, $p, 'Perangkat ajar diunggah', $f->nama_file_asli);
            });
        } catch (Throwable $e) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }
            throw $e;
        }
    }

    public function hapusLampiran(Pengguna $u, SesiSupervisi $sesi, LampiranSupervisi $file, array $input): void
    {
        $this->lihat($u, $sesi);
        abort_unless($this->milik($u, $sesi->guru_pegawai_id), 403);
        $kontrol = $this->kontrol($input);
        DB::transaction(function () use ($u, $sesi, $file, $kontrol) {
            $s = $this->kunci($u, $sesi, $kontrol);
            $this->syarat($this->hak($u, $s)['isi_praobservasi'], 'Lampiran terkirim atau hasil final tidak dapat dihapus.');
            $p = $s->praobservasi;
            abort_unless($p && $p->lampiran()->whereKey($file->id)->exists(), 404);
            $this->versi($p, (int) $kontrol['versi']);
            $path = $file->lokasi_file;
            $file->delete();
            $p->update(['versi' => $p->versi + 1, 'pengubah_id' => $u->id]);
            $this->akses->catat($u, $p, 'Lampiran dihapus', $file->nama_file_asli);
            DB::afterCommit(fn () => Storage::disk('local')->delete($path));
        });
    }

    public function unduh(Pengguna $u, SesiSupervisi $s, LampiranSupervisi $f)
    {
        $this->lihat($u, $s);
        abort_unless($s->praobservasi && $s->praobservasi->lampiran()->whereKey($f->id)->exists(), 404);
        abort_unless($this->dataPra($u, $s)['terlihat'], 404);
        $root = realpath(Storage::disk('local')->path('supervisi/'.$s->id));
        $path = realpath(Storage::disk('local')->path($f->lokasi_file));
        abort_unless($root && $path && str_starts_with(strtolower($path), strtolower($root.DIRECTORY_SEPARATOR)) && is_file($path), 404);

        return Storage::disk('local')->download($f->lokasi_file, $f->nama_file_asli, ['Content-Type' => $f->tipe_file, 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store']);
    }

    private function beritahu(SesiSupervisi $s, array $pegawai, string $judul, string $kunci): void
    {
        $penerima = $this->notifikasi->penggunaUntukDaftarPegawai($pegawai)->filter(fn ($u) => $u->akunPegawai() && ! $u->akunSiswa() && ! $u->akunOrangTua());
        $this->notifikasi->kirimKeBanyak($penerima, 'informasi', $judul, $s->konteks['guru'].' - '.$s->konteks['mapel'].' - '.$s->konteks['kelas'], '/supervisi/sesi/'.$s->id, 'supervisi:'.$s->id.':'.$kunci);
    }
}
