<?php

namespace App\Services\Supervisi;

use App\Models\LampiranTindakLanjutSupervisi;
use App\Models\PascaobservasiSupervisi;
use App\Models\Pengguna;
use App\Models\SesiSupervisi;
use App\Models\TindakLanjutSupervisi;
use App\Services\Notifikasi\NotifikasiPenggunaService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class PascaobservasiSupervisiService
{
    public const RENCANA = ['temuan' => 'Temuan / hasil supervisi', 'rencana_perbaikan' => 'Rencana perbaikan', 'bentuk_pendampingan' => 'Bentuk pendampingan', 'indikator_keberhasilan' => 'Indikator keberhasilan'];

    public function __construct(private AksesSupervisi $akses, private NotifikasiPenggunaService $notifikasi) {}

    public function lihat(Pengguna $u, SesiSupervisi $s): void
    {
        $this->akses->sesi(SesiSupervisi::query(), $u)->findOrFail($s->id);
    }

    private function milik(Pengguna $u, int $id): bool
    {
        return $u->akunPegawai() && $u->pegawai?->aktif && (int) $u->pegawai_id === $id;
    }

    public function sejalan(SesiSupervisi $s): bool
    {
        return $s->penilaian?->status === 'final' && (! $s->pascaobservasi || $s->pascaobservasi->penilaian_versi === $s->penilaian->versi);
    }

    public function hak(Pengguna $u, SesiSupervisi $s): array
    {
        $terbuka = $s->status === 'terjadwal' && $s->program->status !== 'diarsipkan' && $s->penilaian?->status === 'final';
        $sejalan = $this->sejalan($s);
        $status = $s->pascaobservasi?->status ?? 'draf';
        $guru = $this->milik($u, $s->guru_pegawai_id);
        $supervisor = $this->milik($u, $s->supervisor_pegawai_id);

        return [
            'isi_refleksi' => $terbuka && $sejalan && $guru && in_array($status, ['draf', 'dikembalikan'], true),
            'tinjau_refleksi' => $terbuka && $sejalan && $supervisor && $status === 'terkirim',
            'sesuaikan_hasil' => $terbuka && ! $sejalan && $guru,
            'kelola_rencana' => $terbuka && $sejalan && $supervisor && $status === 'diterima',
            'tutup_pascaobservasi' => $terbuka && $sejalan && $supervisor && $status === 'diterima',
            'buka_pascaobservasi' => $terbuka && $sejalan && $status === 'selesai' && ($supervisor || $u->memilikiIzin('supervisi.kelola')),
        ];
    }

    public function data(Pengguna $u, SesiSupervisi $s): array
    {
        $this->lihat($u, $s);
        $p = $s->pascaobservasi;
        $terlihat = $this->milik($u, $s->guru_pegawai_id) || in_array($p?->status, ['terkirim', 'diterima', 'selesai'], true);
        $tasks = $s->tindakLanjut()->when(! $this->milik($u, $s->supervisor_pegawai_id), fn ($q) => $q->whereNotNull('diterbitkan_pada'))->orderBy('id')->get();
        $daftar = $tasks->map(fn ($t) => $this->dataTugas($u, $s, $t))->all();
        $berlaku = collect($daftar)->where('hasil_terbaru', true)->where('status', '!=', 'dibatalkan');

        return [
            'status' => $p?->status ?? 'belum_diisi', 'versi' => $terlihat ? ($p?->versi ?? 0) : null,
            'penilaian_versi' => $s->penilaian?->versi, 'acuan_penilaian_versi' => $p?->penilaian_versi,
            'hasil_final' => $s->penilaian?->status === 'final', 'hasil_terbaru' => $this->sejalan($s),
            'terlihat' => $terlihat, 'pertanyaan' => $p?->pertanyaan ?? $s->instrumen->pertanyaan_refleksi ?? InstrumenSupervisiAwal::pertanyaanRefleksi(),
            'jawaban' => $terlihat ? ($p?->jawaban ?? []) : null, 'catatan_supervisor' => $terlihat ? $p?->catatan_supervisor : null,
            'catatan_penutup' => $terlihat ? $p?->catatan_penutup : null,
            'dikirim_pada' => $p?->dikirim_pada?->toIso8601String(), 'diterima_pada' => $p?->diterima_pada?->toIso8601String(), 'selesai_pada' => $p?->selesai_pada?->toIso8601String(),
            'rencana' => $daftar, 'statistik' => ['rencana' => $berlaku->count(), 'selesai' => $berlaku->where('status', 'selesai')->count(), 'melewati_tenggat' => $berlaku->where('melewati_tenggat', true)->count()],
            'hak' => $this->hak($u, $s),
        ];
    }

    public function dataTugas(Pengguna $u, SesiSupervisi $s, TindakLanjutSupervisi $t): array
    {
        $this->lihat($u, $s);
        abort_unless((int) $t->sesi_supervisi_id === $s->id && ($t->diterbitkan_pada || $this->milik($u, $s->supervisor_pegawai_id)), 404);
        $terlihat = $this->milik($u, $s->guru_pegawai_id) || in_array($t->status, ['diajukan', 'selesai'], true);
        $terbaru = $s->penilaian?->status === 'final' && $t->penilaian_versi === $s->penilaian->versi;
        $kelola = $terbaru && $this->hak($u, $s)['kelola_rencana'];
        $isi = $terbaru && $this->sejalan($s) && $s->pascaobservasi?->status === 'diterima'
            && $this->milik($u, $s->guru_pegawai_id) && $s->status === 'terjadwal' && $s->program->status !== 'diarsipkan' && in_array($t->status, ['ditugaskan', 'dikembalikan'], true);

        return [...$t->only(['id', 'sesi_supervisi_id', 'versi', 'penilaian_versi', 'refleksi_versi', 'status', ...array_keys(self::RENCANA), 'catatan_supervisor']),
            'tenggat' => $t->tenggat?->toDateString(), 'hasil_terbaru' => $terbaru,
            'melewati_tenggat' => $terbaru && $t->tenggat?->lt(today()) && in_array($t->status, ['ditugaskan', 'dikembalikan'], true),
            'laporan_terlihat' => $terlihat, 'tanggal_pelaksanaan' => $terlihat ? $t->tanggal_pelaksanaan?->toDateString() : null,
            'laporan_pelaksanaan' => $terlihat ? $t->laporan_pelaksanaan : null, 'hasil_pelaksanaan' => $terlihat ? $t->hasil_pelaksanaan : null,
            'diterbitkan_pada' => $t->diterbitkan_pada?->toIso8601String(), 'diajukan_pada' => $t->diajukan_pada?->toIso8601String(), 'selesai_pada' => $t->selesai_pada?->toIso8601String(),
            'lampiran' => $terlihat ? $t->lampiran->map(fn ($f) => [...$f->only(['id', 'nama_file_asli', 'tipe_file', 'ukuran_file']), 'unduh' => '/api/v1/supervisi/sesi/'.$s->id.'/tindak-lanjut/'.$t->id.'/lampiran/'.$f->id])->all() : [],
            'hak' => ['ubah_rencana' => $kelola && $t->status === 'draf', 'isi_pelaksanaan' => $isi, 'verifikasi' => $kelola && $t->status === 'diajukan',
                'batalkan' => $kelola && ! in_array($t->status, ['selesai', 'dibatalkan'], true), 'buka_ulang' => $kelola && $t->status === 'selesai']];
    }

    private function kontrol(array $d): array
    {
        return Validator::make($d, ['versi_sesi' => ['required', 'integer', 'min:1'], 'penilaian_versi' => ['required', 'integer', 'min:1'], 'versi' => ['required', 'integer', 'min:0']])->validate();
    }

    private function kunci(Pengguna $u, SesiSupervisi $sesi, array $d): SesiSupervisi
    {
        $this->akses->kunci();
        $s = SesiSupervisi::lockForUpdate()->findOrFail($sesi->id);
        $this->lihat($u, $s);
        $this->akses->versi($s, (int) $d['versi_sesi']);
        $this->syarat($s->status === 'terjadwal' && $s->program->status !== 'diarsipkan' && $s->penilaian?->status === 'final', 'Pascaobservasi memerlukan hasil observasi final, jadwal aktif dan program tidak diarsipkan.');
        $this->syarat($s->penilaian->versi === (int) $d['penilaian_versi'], 'Hasil observasi telah berubah. Muat ulang dan tinjau kembali.', 'penilaian_versi');

        return $s;
    }

    private function syarat(bool $ok, string $pesan, string $key = 'pascaobservasi'): void
    {
        if (! $ok) {
            throw ValidationException::withMessages([$key => $pesan]);
        }
    }

    private function versi(?object $row, int $versi): void
    {
        $this->syarat(($row?->versi ?? 0) === $versi, 'Data telah berubah. Muat ulang sebelum melanjutkan.', 'versi');
    }

    public function simpanRefleksi(Pengguna $u, SesiSupervisi $sesi, array $input): void
    {
        $this->lihat($u, $sesi);
        abort_unless($this->milik($u, $sesi->guru_pegawai_id), 403);
        $c = $this->kontrol($input);
        $d = Validator::make($input, ['jawaban' => ['present', 'array', 'list', 'max:30'], 'jawaban.*' => ['nullable', 'string', 'max:5000']])->validate();
        DB::transaction(function () use ($u, $sesi, $c, $d) {
            $s = $this->kunci($u, $sesi, $c);
            $this->syarat($this->hak($u, $s)['isi_refleksi'], 'Refleksi terkunci atau mengacu pada hasil lama.');
            $p = $s->pascaobservasi;
            $this->versi($p, (int) $c['versi']);
            $pertanyaan = $p?->pertanyaan ?? $s->instrumen->pertanyaan_refleksi ?? InstrumenSupervisiAwal::pertanyaanRefleksi();
            $this->syarat(count($d['jawaban']) <= count($pertanyaan), 'Jawaban tidak sesuai instrumen refleksi.', 'jawaban');
            $p ??= new PascaobservasiSupervisi(['sesi_supervisi_id' => $s->id, 'penilaian_versi' => $s->penilaian->versi, 'status' => 'draf', 'versi' => 0, 'pertanyaan' => $pertanyaan]);
            $jawaban = array_map(fn ($v) => trim($v ?? '') === '' ? null : trim($v), $d['jawaban']);
            $p->fill(['jawaban' => array_pad($jawaban, count($pertanyaan), null), 'versi' => $p->versi + 1, 'pengubah_id' => $u->id])->save();
            $this->akses->catat($u, $p, 'Draf refleksi disimpan');
        });
    }

    public function tindakanRefleksi(Pengguna $u, SesiSupervisi $sesi, string $aksi, array $input): void
    {
        $this->lihat($u, $sesi);
        abort_unless($this->milik($u, in_array($aksi, ['kirim', 'sesuaikan-hasil'], true) ? $sesi->guru_pegawai_id : $sesi->supervisor_pegawai_id), 403);
        $c = $this->kontrol($input);
        $d = Validator::make($input, ['konfirmasi' => ['required', 'accepted'], 'catatan' => [in_array($aksi, ['kembalikan', 'sesuaikan-hasil'], true) ? 'required' : 'nullable', 'string', 'min:10', 'max:3000']])->validate();
        DB::transaction(function () use ($u, $sesi, $c, $d, $aksi) {
            $s = $this->kunci($u, $sesi, $c);
            $p = $s->pascaobservasi;
            $this->syarat($p !== null, 'Simpan refleksi terlebih dahulu.');
            $this->versi($p, (int) $c['versi']);
            $hak = $this->hak($u, $s);
            if ($aksi === 'sesuaikan-hasil') {
                $this->syarat($hak['sesuaikan_hasil'], 'Refleksi sudah mengacu pada hasil terbaru.');
                $this->akses->catat($u, $p, 'Refleksi sebelum penyesuaian hasil', $d['catatan']);
                $p->fill(['penilaian_versi' => $s->penilaian->versi, 'status' => 'draf', 'dikirim_pada' => null, 'diterima_pada' => null, 'selesai_pada' => null, 'catatan_penutup' => null]);
            } elseif ($aksi === 'kirim') {
                $this->syarat($hak['isi_refleksi'], 'Refleksi tidak dapat dikirim pada status ini.');
                $this->syarat(count($p->jawaban) === count($p->pertanyaan) && collect($p->jawaban)->every(fn ($v) => filled($v)), 'Jawab seluruh pertanyaan refleksi sebelum mengirim.', 'jawaban');
                $p->fill(['status' => 'terkirim', 'dikirim_pada' => now()]);
            } else {
                $this->syarat($hak['tinjau_refleksi'], 'Refleksi tidak dapat ditinjau pada status ini.');
                $p->fill(['status' => $aksi === 'terima' ? 'diterima' : 'dikembalikan', 'catatan_supervisor' => $d['catatan'] ?? null, 'diterima_pada' => $aksi === 'terima' ? now() : null]);
            }
            $p->fill(['versi' => $p->versi + 1, 'pengubah_id' => $u->id])->save();
            $this->akses->catat($u, $p, 'Refleksi: '.$aksi, $d['catatan'] ?? null);
            $judul = match ($aksi) {
                'kirim' => 'Refleksi guru siap ditinjau', 'terima' => 'Refleksi guru diterima',
                'kembalikan' => 'Refleksi perlu diperbaiki', default => 'Refleksi disesuaikan dengan hasil terbaru',
            };
            $this->beritahu($s, $aksi === 'kirim' ? [$s->supervisor_pegawai_id] : ($aksi === 'sesuaikan-hasil' ? [] : [$s->guru_pegawai_id]), $judul, 'refleksi:'.$p->versi);
        });
    }

    public function tutup(Pengguna $u, SesiSupervisi $sesi, string $aksi, array $input): void
    {
        $this->lihat($u, $sesi);
        abort_unless($this->milik($u, $sesi->supervisor_pegawai_id) || ($aksi === 'buka-ulang' && $u->memilikiIzin('supervisi.kelola')), 403);
        $c = $this->kontrol($input);
        $d = Validator::make($input, ['konfirmasi' => ['required', 'accepted'], 'catatan' => ['required', 'string', 'min:20', 'max:3000'], 'tanpa_tindak_lanjut' => ['nullable', 'boolean']])->validate();
        DB::transaction(function () use ($u, $sesi, $c, $d, $aksi) {
            $s = $this->kunci($u, $sesi, $c);
            $this->syarat($this->hak($u, $s)[$aksi === 'selesaikan' ? 'tutup_pascaobservasi' : 'buka_pascaobservasi'], 'Pascaobservasi tidak dapat diproses pada status ini.');
            $p = $s->pascaobservasi;
            $this->versi($p, (int) $c['versi']);
            if ($aksi === 'selesaikan') {
                $tugas = $s->tindakLanjut()->where('penilaian_versi', $s->penilaian->versi)->where('status', '!=', 'dibatalkan')->get();
                $this->syarat($tugas->every(fn ($t) => $t->status === 'selesai'), 'Masih ada tindak lanjut yang belum diverifikasi selesai.');
                $this->syarat($tugas->isNotEmpty() || ($d['tanpa_tindak_lanjut'] ?? false), 'Konfirmasikan jika tidak memerlukan tindak lanjut tambahan.');
                $p->fill(['status' => 'selesai', 'selesai_pada' => now(), 'catatan_penutup' => trim($d['catatan'])]);
            } else {
                $this->akses->catat($u, $p, 'Pascaobservasi selesai sebelum dibuka ulang', $d['catatan']);
                $p->fill(['status' => 'diterima', 'selesai_pada' => null]);
            }
            $p->fill(['versi' => $p->versi + 1, 'pengubah_id' => $u->id])->save();
            $this->akses->catat($u, $p, 'Pascaobservasi: '.$aksi, $d['catatan']);
            $this->beritahu($s, [$s->guru_pegawai_id, $s->supervisor_pegawai_id], $aksi === 'selesaikan' ? 'Pascaobservasi selesai' : 'Pascaobservasi dibuka ulang', 'pasca:'.$p->versi);
        });
    }

    private function rencanaAktif(SesiSupervisi $s): void
    {
        $this->syarat($this->sejalan($s) && $s->pascaobservasi?->status === 'diterima', 'Refleksi terbaru harus diterima dan pascaobservasi belum ditutup.');
    }

    public function simpanRencana(Pengguna $u, SesiSupervisi $sesi, array $input, ?TindakLanjutSupervisi $tugas = null): TindakLanjutSupervisi
    {
        $this->lihat($u, $sesi);
        abort_unless($this->milik($u, $sesi->supervisor_pegawai_id), 403);
        $c = $this->kontrol($input);
        $rules = ['token_pembuatan' => [$tugas ? 'nullable' : 'required', 'uuid'], 'tenggat' => ['nullable', 'date_format:Y-m-d'], 'refleksi_versi' => ['required', 'integer', 'min:1']];
        foreach (self::RENCANA as $k => $label) {
            $rules[$k] = [$k === 'temuan' ? 'required' : 'nullable', 'string', 'max:3000'];
        }
        $d = Validator::make($input, $rules)->validate();
        $data = ['tenggat' => $d['tenggat'] ?? null];
        foreach (self::RENCANA as $k => $label) {
            $data[$k] = trim($d[$k] ?? '') === '' ? null : trim($d[$k]);
        }

        return DB::transaction(function () use ($u, $sesi, $c, $d, $data, $tugas) {
            $s = $this->kunci($u, $sesi, $c);
            $this->rencanaAktif($s);
            $this->akses->versi($s->pascaobservasi, (int) $d['refleksi_versi']);
            if ($tugas) {
                $t = $this->tugasKunci($s, $tugas, (int) $c['versi']);
                $this->syarat($t->status === 'draf', 'Rencana terbit tidak dapat diubah. Batalkan dengan alasan dan buat rencana pengganti.');
                $t->fill([...$data, 'versi' => $t->versi + 1]);
            } else {
                $sidik = $this->akses->sidik([...$data, 'sesi_id' => $s->id, 'penilaian_versi' => $s->penilaian->versi, 'refleksi_versi' => (int) $d['refleksi_versi']]);
                if ($t = TindakLanjutSupervisi::where('token_pembuatan', $d['token_pembuatan'])->first()) {
                    abort_unless((int) $t->sesi_supervisi_id === $s->id, 404);
                    $this->akses->pengulangan($t, $u, $sidik);

                    return $t;
                }
                $this->syarat($s->tindakLanjut()->where('penilaian_versi', $s->penilaian->versi)->where('status', '!=', 'dibatalkan')->count() < 50, 'Maksimal 50 rencana aktif per versi hasil observasi.');
                $this->syarat((int) $c['versi'] === 0, 'Versi pembuatan rencana harus 0.', 'versi');
                $t = new TindakLanjutSupervisi([...$data, 'sesi_supervisi_id' => $s->id, 'penilaian_versi' => $s->penilaian->versi, 'refleksi_versi' => $s->pascaobservasi->versi,
                    'token_pembuatan' => $d['token_pembuatan'], 'sidik_pembuatan' => $sidik, 'pembuat_id' => $u->id, 'versi' => 1, 'status' => 'draf']);
            }
            $t->pengubah_id = $u->id;
            $t->save();
            $this->akses->catat($u, $t, 'Draf tindak lanjut disimpan');

            return $t;
        });
    }

    private function tugasKunci(SesiSupervisi $s, TindakLanjutSupervisi $tugas, int $versi): TindakLanjutSupervisi
    {
        $t = $s->tindakLanjut()->lockForUpdate()->findOrFail($tugas->id);
        $this->akses->versi($t, $versi);
        $this->syarat($t->penilaian_versi === $s->penilaian->versi, 'Rencana ini mengacu pada hasil lama. Buat rencana baru untuk hasil terbaru.');

        return $t;
    }

    public function terbitkan(Pengguna $u, SesiSupervisi $sesi, array $input): void
    {
        $this->lihat($u, $sesi);
        abort_unless($this->milik($u, $sesi->supervisor_pegawai_id), 403);
        $c = $this->kontrol($input);
        $d = Validator::make($input, ['konfirmasi' => ['required', 'accepted'], 'rencana' => ['required', 'array', 'list', 'min:1', 'max:50'], 'rencana.*.id' => ['required', 'integer', 'distinct'], 'rencana.*.versi' => ['required', 'integer', 'min:1']])->validate();
        DB::transaction(function () use ($u, $sesi, $c, $d) {
            $s = $this->kunci($u, $sesi, $c);
            $this->rencanaAktif($s);
            $this->akses->versi($s->pascaobservasi, (int) $c['versi']);
            foreach ($d['rencana'] as $row) {
                $t = $this->tugasKunci($s, TindakLanjutSupervisi::findOrFail($row['id']), (int) $row['versi']);
                $this->syarat($t->status === 'draf', 'Penerbitan hanya untuk draf rencana.');
                foreach (array_keys(self::RENCANA) as $k) {
                    $this->syarat(filled($t->$k), 'Lengkapi '.self::RENCANA[$k].' sebelum penerbitan.');
                }
                $this->syarat($t->tenggat && $t->tenggat->gte(today()), 'Tenggat penerbitan harus hari ini atau setelahnya.');
                $t->fill(['status' => 'ditugaskan', 'diterbitkan_pada' => now(), 'versi' => $t->versi + 1, 'pengubah_id' => $u->id])->save();
                $this->akses->catat($u, $t, 'Tindak lanjut diterbitkan');
                $this->beritahu($s, [$s->guru_pegawai_id], 'Tindak lanjut supervisi ditugaskan', 'tugas:'.$t->id.':'.$t->versi, $t);
            }
        });
    }

    public function simpanPelaksanaan(Pengguna $u, SesiSupervisi $sesi, TindakLanjutSupervisi $tugas, array $input): void
    {
        $this->dataTugas($u, $sesi, $tugas);
        abort_unless($this->milik($u, $sesi->guru_pegawai_id), 403);
        $c = $this->kontrol($input);
        $d = Validator::make($input, ['tanggal_pelaksanaan' => ['nullable', 'date_format:Y-m-d'], 'laporan_pelaksanaan' => ['nullable', 'string', 'max:5000'], 'hasil_pelaksanaan' => ['nullable', 'string', 'max:5000']])->validate();
        DB::transaction(function () use ($u, $sesi, $tugas, $c, $d) {
            $s = $this->kunci($u, $sesi, $c);
            $this->rencanaAktif($s);
            $t = $this->tugasKunci($s, $tugas, (int) $c['versi']);
            $this->syarat($this->dataTugas($u, $s, $t)['hak']['isi_pelaksanaan'], 'Laporan tidak dapat diubah pada status ini.');
            $t->fill(['tanggal_pelaksanaan' => $d['tanggal_pelaksanaan'] ?? null, 'laporan_pelaksanaan' => trim($d['laporan_pelaksanaan'] ?? '') ?: null,
                'hasil_pelaksanaan' => trim($d['hasil_pelaksanaan'] ?? '') ?: null, 'versi' => $t->versi + 1, 'pengubah_id' => $u->id])->save();
            $this->akses->catat($u, $t, 'Draf pelaksanaan tindak lanjut disimpan');
        });
    }

    public function tindakanTugas(Pengguna $u, SesiSupervisi $sesi, TindakLanjutSupervisi $tugas, string $aksi, array $input): void
    {
        $this->dataTugas($u, $sesi, $tugas);
        abort_unless($this->milik($u, $aksi === 'ajukan' ? $sesi->guru_pegawai_id : $sesi->supervisor_pegawai_id), 403);
        $c = $this->kontrol($input);
        $d = Validator::make($input, ['konfirmasi' => ['required', 'accepted'], 'catatan' => [$aksi === 'ajukan' ? 'nullable' : 'required', 'string', 'min:10', 'max:3000']])->validate();
        DB::transaction(function () use ($u, $sesi, $tugas, $c, $d, $aksi) {
            $s = $this->kunci($u, $sesi, $c);
            $this->rencanaAktif($s);
            $t = $this->tugasKunci($s, $tugas, (int) $c['versi']);
            $hak = $this->dataTugas($u, $s, $t)['hak'];
            if ($aksi === 'ajukan') {
                $this->syarat($hak['isi_pelaksanaan'], 'Laporan tidak dapat diajukan pada status ini.');
                $this->syarat($t->tanggal_pelaksanaan && $t->tanggal_pelaksanaan->gte($s->penilaian->tanggal_observasi) && $t->tanggal_pelaksanaan->lte(today()), 'Tanggal pelaksanaan harus sejak observasi hingga hari ini.');
                $this->syarat(filled($t->laporan_pelaksanaan) && filled($t->hasil_pelaksanaan), 'Lengkapi laporan dan hasil pelaksanaan sebelum mengajukan.');
                $t->fill(['status' => 'diajukan', 'diajukan_pada' => now()]);
            } elseif (in_array($aksi, ['verifikasi', 'kembalikan'], true)) {
                $this->syarat($hak['verifikasi'], 'Hanya laporan yang diajukan dapat diverifikasi.');
                $t->fill(['status' => $aksi === 'verifikasi' ? 'selesai' : 'dikembalikan', 'selesai_pada' => $aksi === 'verifikasi' ? now() : null]);
            } elseif ($aksi === 'buka-ulang') {
                $this->syarat($hak['buka_ulang'], 'Hanya tindak lanjut selesai dapat dibuka ulang.');
                $this->akses->catat($u, $t, 'Tindak lanjut sebelum dibuka ulang', $d['catatan']);
                $t->fill(['status' => 'dikembalikan', 'selesai_pada' => null]);
            } else {
                $this->syarat($hak['batalkan'], 'Tindak lanjut tidak dapat dibatalkan pada status ini.');
                $t->status = 'dibatalkan';
            }
            if ($aksi !== 'ajukan') {
                $t->catatan_supervisor = trim($d['catatan']);
            }
            $t->fill(['versi' => $t->versi + 1, 'pengubah_id' => $u->id])->save();
            $this->akses->catat($u, $t, 'Tindak lanjut: '.$aksi, $d['catatan'] ?? null);
            if ($t->diterbitkan_pada) {
                $judul = match ($aksi) {
                    'ajukan' => 'Laporan tindak lanjut siap diverifikasi', 'verifikasi' => 'Tindak lanjut diverifikasi selesai',
                    'kembalikan' => 'Laporan tindak lanjut perlu perbaikan', 'batalkan' => 'Rencana tindak lanjut dibatalkan',
                    default => 'Tindak lanjut dibuka untuk perbaikan',
                };
                $this->beritahu($s, [$aksi === 'ajukan' ? $s->supervisor_pegawai_id : $s->guru_pegawai_id], $judul, 'tugas:'.$t->id.':'.$t->versi, $t);
            }
        });
    }

    public function unggah(Pengguna $u, SesiSupervisi $sesi, TindakLanjutSupervisi $tugas, array $input, UploadedFile $file): void
    {
        $this->dataTugas($u, $sesi, $tugas);
        abort_unless($this->milik($u, $sesi->guru_pegawai_id), 403);
        $c = $this->kontrol($input);
        Validator::make(['berkas' => $file], ['berkas' => ['required', 'file', 'mimes:pdf,doc,docx,jpg,jpeg,png,webp', 'extensions:pdf,doc,docx,jpg,jpeg,png,webp', 'max:10240']])->validate();
        $path = null;
        try {
            DB::transaction(function () use ($u, $sesi, $tugas, $c, $file, &$path) {
                $s = $this->kunci($u, $sesi, $c);
                $this->rencanaAktif($s);
                $t = $this->tugasKunci($s, $tugas, (int) $c['versi']);
                $this->syarat($this->dataTugas($u, $s, $t)['hak']['isi_pelaksanaan'], 'Lampiran hanya dapat diubah saat laporan dapat diedit.');
                $this->syarat($t->lampiran()->count() < 5, 'Maksimal lima bukti per tindak lanjut.');
                $path = $file->storeAs('supervisi-tindak-lanjut/'.$s->id.'/'.$t->id, Str::uuid().'.'.$file->extension(), 'local');
                $this->syarat((bool) $path, 'Bukti belum dapat disimpan.');
                $nama = Str::limit(preg_replace('/[\x00-\x1F\x7F\\\\\/]/u', '_', $file->getClientOriginalName()), 190, '');
                $t->lampiran()->create(['lokasi_file' => $path, 'nama_file_asli' => $nama, 'tipe_file' => $file->getMimeType(), 'ukuran_file' => $file->getSize(), 'pengunggah_id' => $u->id]);
                $t->update(['versi' => $t->versi + 1, 'pengubah_id' => $u->id]);
                $this->akses->catat($u, $t, 'Bukti tindak lanjut diunggah', $nama);
            });
        } catch (Throwable $e) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }
            throw $e;
        }
    }

    private function lampiranMilik(SesiSupervisi $s, TindakLanjutSupervisi $t, LampiranTindakLanjutSupervisi $f): void
    {
        abort_unless((int) $t->sesi_supervisi_id === $s->id && (int) $f->tindak_lanjut_supervisi_id === $t->id, 404);
    }

    public function unduh(Pengguna $u, SesiSupervisi $s, TindakLanjutSupervisi $t, LampiranTindakLanjutSupervisi $f)
    {
        $d = $this->dataTugas($u, $s, $t);
        $this->lampiranMilik($s, $t, $f);
        abort_unless($d['laporan_terlihat'], 404);
        $root = realpath(Storage::disk('local')->path('supervisi-tindak-lanjut/'.$s->id.'/'.$t->id));
        $path = realpath(Storage::disk('local')->path($f->lokasi_file));
        abort_unless($root && $path && str_starts_with(strtolower($path), strtolower($root.DIRECTORY_SEPARATOR)) && is_file($path), 404);

        return Storage::disk('local')->download($f->lokasi_file, $f->nama_file_asli, ['Content-Type' => $f->tipe_file, 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store']);
    }

    public function hapus(Pengguna $u, SesiSupervisi $sesi, TindakLanjutSupervisi $tugas, LampiranTindakLanjutSupervisi $file, array $input): void
    {
        $this->dataTugas($u, $sesi, $tugas);
        abort_unless($this->milik($u, $sesi->guru_pegawai_id), 403);
        $c = $this->kontrol($input);
        DB::transaction(function () use ($u, $sesi, $tugas, $file, $c) {
            $s = $this->kunci($u, $sesi, $c);
            $this->rencanaAktif($s);
            $t = $this->tugasKunci($s, $tugas, (int) $c['versi']);
            $this->lampiranMilik($s, $t, $file);
            $this->syarat($this->dataTugas($u, $s, $t)['hak']['isi_pelaksanaan'], 'Bukti laporan terkirim tidak dapat dihapus.');
            $path = $file->lokasi_file;
            $file->delete();
            $t->update(['versi' => $t->versi + 1, 'pengubah_id' => $u->id]);
            $this->akses->catat($u, $t, 'Bukti tindak lanjut dihapus', $file->nama_file_asli);
            DB::afterCommit(fn () => Storage::disk('local')->delete($path));
        });
    }

    private function beritahu(SesiSupervisi $s, array $pegawai, string $judul, string $kunci, ?TindakLanjutSupervisi $t = null): void
    {
        $penerima = $this->notifikasi->penggunaUntukDaftarPegawai($pegawai)->filter(fn ($u) => $u->akunPegawai() && ! $u->akunSiswa() && ! $u->akunOrangTua());
        $tautan = '/supervisi/sesi/'.$s->id.($t ? '/tindak-lanjut/'.$t->id : '/pascaobservasi');
        $this->notifikasi->kirimKeBanyak($penerima, 'informasi', $judul, $s->konteks['guru'].' - '.$s->konteks['mapel'].' - '.$s->konteks['kelas'], $tautan, 'supervisi:'.$s->id.':'.$kunci);
    }
}
