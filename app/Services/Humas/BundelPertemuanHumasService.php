<?php

namespace App\Services\Humas;

use App\Models\AgendaHumas;
use App\Models\Pengguna;
use App\Models\RiwayatBundelPertemuanHumas;
use App\Models\UmpanBalikHumas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use ZipArchive;

class BundelPertemuanHumasService
{
    public function pilihan(Request $r, array $k): array
    {
        if ($r->filled('dokumen_ids')) {
            abort_unless($k['bolehDokumen'], 403);
        }
        if ($r->filled('formulir_ids')) {
            abort_unless($k['bolehUmpan'], 403);
        }
        $data = $r->validate(['sidik' => ['required', 'string', 'size:64', 'regex:/^[a-f0-9]{64}$/'],
            'dokumen_ids' => ['nullable', 'array', 'max:'.BundelPertemuanHumasService::MAKS_BERKAS],
            'dokumen_ids.*' => ['required', 'integer', 'distinct', Rule::in($k['dokumen']->where('status', 'aktif')->map(fn ($d) => $d->riwayat->first()?->id)->filter()->all())],
            'formulir_ids' => ['nullable', 'array', 'max:20'], 'formulir_ids.*' => ['required', 'integer', 'distinct', Rule::in($k['formulir']->pluck('id')->all())],
        ]);
        if (! hash_equals($k['sidik'], $data['sidik'])) {
            $this->gagal('Data pertemuan atau lampiran telah berubah. Muat ulang dan periksa pilihan sebelum membuat bundel.');
        }

        return ['dokumen_ids' => array_map('intval', $data['dokumen_ids'] ?? []), 'formulir_ids' => array_map('intval', $data['formulir_ids'] ?? [])];
    }

    public function export(Request $request, AgendaHumas $agendaHumas)
    {
        abort_unless($request->user()->memilikiIzin('agenda_humas.bundel'), 403);
        $path = null;
        try {
            [$path, $nama] = DB::transaction(function () use ($request, $agendaHumas, &$path) {
                $a = AgendaHumas::lockForUpdate()->findOrFail($agendaHumas->id);
                $konteks = $this->konteks($a, $request->user());
                $pilihan = $this->pilihan($request, $konteks);
                [$path, $nama] = $this->bundel($konteks, $pilihan, $request->user());

                return [$path, $nama];
            });

            return [$path, $nama];
        } catch (\Throwable $e) {
            if ($path && is_file($path)) {
                unlink($path);
            }
            throw $e;
        }
    }

    public const MAKS_BERKAS = 200;

    public const MAKS_BYTE = 209715200;

    public const MAKS_PESERTA = 5000;

    public function konteks(AgendaHumas $agenda, Pengguna $u): array
    {
        if ($agenda->peserta()->count() > self::MAKS_PESERTA) {
            $this->gagal('Maksimal 5.000 peserta per bundel pertemuan.');
        }
        $agenda->load('peserta', 'tindakLanjut');
        $bolehDokumen = $u->memilikiIzin(['dokumen_humas.lihat', 'dokumen_humas.kelola']);
        $bolehUmpan = $u->memilikiIzin(['umpan_balik_humas.lihat', 'umpan_balik_humas.kelola']);
        $dokumen = $bolehDokumen ? $agenda->dokumen()->with(['riwayat' => fn ($q) => $q->limit(1)])->orderBy('judul')->get() : collect();
        $formulir = $bolehUmpan ? UmpanBalikHumas::where('agenda_humas_id', $agenda->id)->where('cakupan', 'agenda')->whereNotNull('dibuka_pada')
            ->where('status', '!=', 'draf')->withCount(['sasaran', 'sasaran as respons_count' => fn ($q) => $q->whereNotNull('dikirim_pada')])->orderBy('id')->get() : collect();
        $hambatan = [];
        if ($agenda->status !== 'selesai') {
            $hambatan[] = 'Agenda belum berstatus Selesai.';
        }
        if (blank($agenda->pembahasan) || blank($agenda->keputusan)) {
            $hambatan[] = 'Pembahasan dan keputusan notulen belum lengkap.';
        }
        if ($agenda->peserta->isEmpty()) {
            $hambatan[] = 'Daftar peserta belum tersedia.';
        }
        if ($agenda->peserta->contains('status_kehadiran', 'belum_dicatat')) {
            $hambatan[] = 'Masih ada peserta yang kehadirannya belum dicatat.';
        }
        $sidik = hash('sha256', json_encode([
            $agenda->getAttributes(), $agenda->peserta->map->getAttributes()->all(), $agenda->tindakLanjut->map->getAttributes()->all(),
            $dokumen->map(fn ($d) => [$d->id, $d->judul, $d->status, $d->riwayat->first()?->getAttributes()])->all(),
            $formulir->map(fn ($f) => [$f->id, $f->judul, $f->versi, $f->status, $f->selesai_pada->toDateTimeString()])->all(),
        ], JSON_THROW_ON_ERROR));

        return ['agendaHumas' => $agenda, 'dokumen' => $dokumen, 'formulir' => $formulir, 'bolehDokumen' => $bolehDokumen, 'bolehUmpan' => $bolehUmpan,
            'hambatan' => $hambatan, 'sidik' => $sidik, 'rekapPresensi' => $agenda->rekapPresensi(), 'tab' => 'bundel'];
    }

    public function isi(array $konteks, array $pilihan, bool $offline = false): array
    {
        $daftar = [];
        $byte = 0;
        $berkasService = app(PortofolioAkreditasiHumasService::class);
        foreach ($konteks['dokumen']->filter(fn ($d) => in_array($d->riwayat->first()?->id, $pilihan['dokumen_ids'], true)) as $d) {
            $v = $d->riwayat->first();
            if ($d->status !== 'aktif' || ! $v) {
                $this->gagal('Dokumen yang dipilih tidak aktif atau belum memiliki versi berkas.');
            }
            $path = $berkasService->berkas($v);
            $ukuran = filesize($path);
            $byte += $ukuran;
            if ($byte > self::MAKS_BYTE) {
                $this->gagal('Total lampiran maksimal 200 MB. Kurangi berkas yang dipilih.');
            }
            $nama = 'lampiran/'.$v->id.'-'.$berkasService->namaUnduh($d->judul, $v);
            $daftar[] = ['judul' => $d->judul, 'versi' => $v->versi, 'id_versi' => $v->id, 'id_dokumen' => $d->id, 'path' => $path, 'nama' => $nama, 'ukuran' => $ukuran,
                'foto' => str_starts_with($v->tipe_file, 'image/'), 'url' => $offline ? $nama : route('dokumen-humas.riwayat.unduh', [$d, $v])];
        }
        if (count($daftar) !== count($pilihan['dokumen_ids'])) {
            $this->gagal('Pilihan lampiran telah berubah. Muat ulang halaman bundel.');
        }
        $rekap = [];
        foreach ($konteks['formulir']->whereIn('id', $pilihan['formulir_ids']) as $f) {
            $f->load('pertanyaan');
            $rekap[] = ['formulir' => $f, 'rekap' => app(UmpanBalikHumasService::class)->rekap($f)];
        }

        return $konteks + ['daftar' => $daftar, 'rekapUmpan' => $rekap, 'foto' => collect($daftar)->where('foto', true)->values(),
            'offline' => $offline, 'dibuatPada' => now(), 'logoKota' => $offline ? 'assets/logo-kota.png' : asset('images/logo-padang-panjang.png'),
            'logoSekolah' => $offline ? 'assets/logo-sekolah.png' : asset('images/kartu-pelajar/logo-smpn2pp.png')];
    }

    public function bundel(array $konteks, array $pilihan, Pengguna $u): array
    {
        if ($konteks['hambatan']) {
            $this->gagal('Lengkapi kesiapan bundel: '.implode(' ', $konteks['hambatan']));
        }
        if (! class_exists(ZipArchive::class)) {
            $this->gagal('Ekstensi ZIP PHP belum aktif. Hubungi administrator.');
        }
        $data = $this->isi($konteks, $pilihan, true);
        $path = Storage::disk('local')->path('ekspor-pertemuan/'.Str::uuid().'.zip');
        $zip = new ZipArchive;
        $terbuka = false;
        try {
            if (! Storage::disk('local')->makeDirectory('ekspor-pertemuan') || $zip->open($path, ZipArchive::CREATE | ZipArchive::EXCL) !== true) {
                $this->gagal('Bundel tidak dapat dibuat. Periksa izin folder dan ruang penyimpanan server.');
            }
            $terbuka = true;
            foreach (['index.html' => 'bundel-pertemuan-humas.indeks', '01-bundel-pertemuan.html' => 'bundel-pertemuan-humas.cetak'] as $nama => $view) {
                if (! $zip->addFromString($nama, view($view, $data)->render())) {
                    $this->gagal('Halaman bundel tidak dapat disiapkan.');
                }
            }
            foreach (['assets/logo-kota.png' => 'images/logo-padang-panjang.png', 'assets/logo-sekolah.png' => 'images/kartu-pelajar/logo-smpn2pp.png'] as $nama => $logo) {
                if (! is_file(public_path($logo)) || ! $zip->addFile(public_path($logo), $nama)) {
                    $this->gagal('Logo sekolah pada bundel tidak tersedia.');
                }
            }
            foreach ($data['daftar'] as $item) {
                if (! $zip->addFile($item['path'], $item['nama'])) {
                    $this->gagal('Lampiran tidak dapat ditambahkan ke bundel.');
                }
            }
            if (! $zip->close()) {
                $this->gagal('Bundel gagal disimpan. Periksa ruang penyimpanan server.');
            }
            $terbuka = false;
            $a = $konteks['agendaHumas'];
            RiwayatBundelPertemuanHumas::create(['agenda_humas_id' => $a->id, 'pengguna_id' => $u->id, 'created_at' => now(), 'sha256' => hash_file('sha256', $path), 'ukuran_byte' => filesize($path),
                'ringkasan' => ['peserta' => $a->peserta->count(), 'hadir' => $a->peserta->where('status_kehadiran', 'hadir')->count(),
                    'lampiran' => array_column($data['daftar'], 'id_versi'), 'formulir' => array_map(fn ($r) => ['id' => $r['formulir']->id, 'versi' => $r['formulir']->versi, 'respons' => $r['rekap']['respons']], $data['rekapUmpan']), 'sidik' => $konteks['sidik']]]);

            return [$path, 'bundel-pertemuan-'.$a->id.'-'.Str::limit(Str::slug($a->judul) ?: 'agenda', 70, '').'.zip'];
        } catch (\Throwable $e) {
            if ($terbuka) {
                try {
                    $zip->close();
                } catch (\Throwable) {
                }
            }
            if (is_file($path)) {
                unlink($path);
            }
            if (! $e instanceof ValidationException) {
                report($e);
                $this->gagal('Bundel gagal diproses. Periksa berkas dan ruang penyimpanan server.');
            }
            throw $e;
        }
    }

    public function gagal(string $pesan): never
    {
        throw ValidationException::withMessages(['bundel' => $pesan]);
    }
}
