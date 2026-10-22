<?php

namespace App\Services\Humas;

use App\Models\AgendaHumas;
use App\Models\DokumenHumas;
use App\Models\Pengguna;
use App\Models\ProgramKerjaHumas;
use App\Models\ProgramKomiteHumas;
use App\Models\SuratTu;
use App\Services\Persuratan\PersuratanService;
use App\Services\Persuratan\TindakLanjutSuratService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class KaitanDokumenHumasService
{
    private const TARGET = [
        'agenda' => ['label' => 'Rapat / agenda', 'model' => AgendaHumas::class, 'relasi' => 'agenda', 'balik' => 'dokumen', 'izin' => 'agenda_humas', 'judul' => 'judul', 'route' => 'agenda-humas.show'],
        'program' => ['label' => 'Program kerja Humas', 'model' => ProgramKerjaHumas::class, 'relasi' => 'program', 'balik' => 'dokumenHumas', 'izin' => 'program_kerja_humas', 'judul' => 'nama', 'route' => 'program-kerja-humas.show'],
        'program_komite' => ['label' => 'Program kerja komite', 'model' => ProgramKomiteHumas::class, 'relasi' => 'programKomite', 'balik' => 'dokumenHumas', 'izin' => 'komite_humas', 'judul' => 'nama', 'route' => 'komite-humas.program.show'],
        'surat' => ['label' => 'Surat TU', 'model' => SuratTu::class, 'relasi' => 'surat', 'balik' => 'dokumenHumas', 'judul' => 'perihal'],
    ];

    public function jenis(Pengguna $akun, bool $kelola = false): array
    {
        if (! $akun->aktif || $akun->akunSiswa() || $akun->akunOrangTua()) {
            return [];
        }

        return collect(self::TARGET)->filter(function ($t, $kode) use ($akun, $kelola) {
            return $kode === 'surat' ? ($akun->administrator() || ($akun->akunPegawai() && $akun->pegawai?->aktif))
                : $akun->memilikiIzin($kelola ? $t['izin'].'.kelola' : [$t['izin'].'.lihat', $t['izin'].'.kelola']);
        })->map(fn ($t) => $t['label'])->all();
    }

    public function query(Pengguna $akun, string $jenis, bool $kelola = false): Builder
    {
        abort_unless(isset($this->jenis($akun, $kelola)[$jenis]), 403);
        if ($jenis !== 'surat') {
            return self::TARGET[$jenis]['model']::query();
        }
        $surat = app(PersuratanService::class);
        $surat->pastikanPegawai($akun);

        return SuratTu::where(function ($q) use ($akun, $surat) {
            $q->whereRaw('1 = 0');
            if ($akun->memilikiIzin(['surat.lihat', 'surat.kelola', 'surat.sahkan', 'surat.disposisi'])) {
                $q->orWhereIn('id', $surat->arsip($akun)->select('id'));
            }
            if ($akun->akunPegawai()) {
                $q->orWhereIn('id', $surat->penugasanSaya($akun)->select('id'))
                    ->orWhereIn('id', app(TindakLanjutSuratService::class)->daftar($akun)->select('surat_tu_id'));
            }
        });
    }

    private function item(Pengguna $akun, string $jenis, $target): array
    {
        $t = self::TARGET[$jenis];
        $url = null;
        if ($jenis === 'surat') {
            $arsip = $akun->memilikiIzin(['surat.lihat', 'surat.kelola', 'surat.sahkan', 'surat.disposisi'])
                && app(PersuratanService::class)->arsip($akun)->whereKey($target->id)->exists();
            $tugas = ! $arsip && $akun->akunPegawai() ? $target->tindakLanjut()->where('pegawai_id', $akun->pegawai_id)->first() : null;
            $url = $arsip ? route('persuratan.show', $target) : ($tugas ? route('tindak-lanjut-surat.show', $tugas) : route('surat-saya.show', $target));
        } else {
            $url = route($t['route'], $jenis === 'program_komite' ? [$target->periode_komite_humas_id, $target] : [$target]);
        }

        return ['jenis' => $jenis, 'id' => $target->id, 'kelompok' => $t['label'],
            'label' => ($jenis === 'surat' ? $target->nomor_surat.' - ' : '').$target->{$t['judul']}, 'url' => $url];
    }

    public function pilihan(Pengguna $akun, string $jenis, ?string $cari = null): array
    {
        $kolom = self::TARGET[$jenis]['judul'] ?? 'id';
        $q = $this->query($akun, $jenis, true)->where('status', '<>', 'dibatalkan');
        $q->when($cari, fn ($q, $kata) => $q->where(function ($q) use ($jenis, $kolom, $kata) {
            $q->whereLike($kolom, '%'.$kata.'%');
            if ($jenis === 'surat') {
                $q->orWhereLike('nomor_surat', '%'.$kata.'%');
            }
        }));

        return $q->latest('id')->limit(30)->get()->map(fn ($t) => $this->item($akun, $jenis, $t))->all();
    }

    public function hubungan(Pengguna $akun, DokumenHumas $d): array
    {
        $items = [];
        foreach ($this->jenis($akun) as $kode => $label) {
            $t = self::TARGET[$kode];
            $targets = $this->query($akun, $kode)->whereHas($t['balik'], fn ($q) => $q->where('dokumen_humas.id', $d->id))->latest('id')->limit(50)->get();
            foreach ($targets as $target) {
                $items[] = $this->item($akun, $kode, $target) + ['dapat_lepas' => isset($this->jenis($akun, true)[$kode]) && $akun->memilikiIzin('dokumen_humas.kelola')];
            }
        }

        return $items;
    }

    public function ubah(Request $request, DokumenHumas $d): void
    {
        $akun = $request->user();
        abort_unless($akun->aktif && ! $akun->akunSiswa() && ! $akun->akunOrangTua() && $akun->memilikiIzin('dokumen_humas.kelola'), 403);
        $data = $request->validate(['jenis_kaitan' => ['required', Rule::in(array_keys(self::TARGET))],
            'target_id' => ['required', 'integer'], 'aksi' => ['required', Rule::in(['tambah', 'lepas'])],
            'sidik' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/D'], 'alasan' => ['required', 'string', 'min:5', 'max:1000']]);
        DB::transaction(function () use ($akun, $data, $d) {
            $jenis = $data['jenis_kaitan'];
            $target = $this->query($akun, $jenis, true)->whereKey($data['target_id'])->lockForUpdate()->firstOrFail();
            $dokumen = DokumenHumas::lockForUpdate()->findOrFail($d->id);
            app(KelolaDokumenHumasService::class)->periksaSidik($dokumen, $data['sidik']);
            if ($data['aksi'] === 'tambah' && ($dokumen->status !== 'aktif' || $target->status === 'dibatalkan')) {
                throw ValidationException::withMessages(['target_id' => 'Pilih dokumen aktif dan kegiatan/surat yang tidak dibatalkan.']);
            }
            $relasi = $dokumen->{self::TARGET[$jenis]['relasi']}();
            $ada = $relasi->whereKey($target->id)->exists();
            if ($ada === ($data['aksi'] === 'tambah')) {
                return;
            }
            if ($data['aksi'] === 'tambah') {
                $relasi->syncWithoutDetaching([$target->id]);
            } else {
                $relasi->detach($target->id);
            }
            $dokumen->forceFill(['versi_metadata' => $dokumen->versi_metadata + 1, 'diubah_oleh_pengguna_id' => $akun->id])->save();
            app(KlasifikasiDokumenHumasService::class)->catat($dokumen, 'kaitan_'.$data['aksi'], null,
                ['jenis_kaitan' => $jenis, 'target_id' => $target->id], $data['alasan'], $akun);
        });
    }
}
