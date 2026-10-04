<?php

namespace App\Http\Controllers\Api\V1\Humas;

use App\Models\DokumenHumas;
use App\Models\RiwayatDokumenHumas;
use App\Services\Humas\KelolaDokumenHumasService;
use App\Services\Mobile\HumasMobileService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DokumenController extends HumasController
{
    public function __construct(private readonly KelolaDokumenHumasService $kelola, private readonly HumasMobileService $mobile) {}

    public function index(Request $r)
    {
        $this->staff($r, ['dokumen_humas.lihat', 'dokumen_humas.kelola']);
        $filter = $r->validate(['kata_kunci' => ['nullable', 'string', 'max:120'], 'kategori' => ['nullable', Rule::in(array_keys(DokumenHumas::KATEGORI))],
            'status' => ['nullable', Rule::in(array_keys(DokumenHumas::STATUS))],
            'masa_berlaku' => ['nullable', Rule::in(['tanpa_batas', 'masih_berlaku', 'segera_berakhir', 'kedaluwarsa'])]]) + $this->halaman($r);
        $q = DokumenHumas::withMax('riwayat', 'versi')->when($filter['kata_kunci'] ?? null, fn ($q, $s) => $q->where(fn ($q) => $q
            ->whereLike('judul', '%'.$s.'%')->orWhereLike('nomor_dokumen', '%'.$s.'%')->orWhereLike('deskripsi', '%'.$s.'%')));
        foreach (['kategori', 'status'] as $key) {
            $q->when($filter[$key] ?? null, fn ($q, $v) => $q->where($key, $v));
        }
        switch ($filter['masa_berlaku'] ?? null) {
            case 'tanpa_batas': $q->whereNull('berlaku_sampai');
                break;
            case 'kedaluwarsa': $q->whereDate('berlaku_sampai', '<', today());
                break;
            case 'segera_berakhir': $q->whereBetween('berlaku_sampai', [today(), today()->addDays(30)]);
                break;
            case 'masih_berlaku': $q->whereDate('berlaku_sampai', '>', today()->addDays(30));
                break;
        }
        $aktif = DokumenHumas::where('status', 'aktif');

        return $this->json($this->mobile->paginasi($q->orderByRaw('berlaku_sampai IS NULL')->orderBy('berlaku_sampai')->orderByDesc('id')
            ->paginate($filter['per_halaman'] ?? 20, ['*'], 'halaman', $filter['halaman'] ?? 1), fn ($d) => $this->mobile->dokumen($d, $this->kelola->sidik($d))) + [
                'filter' => $filter, 'hak_akses' => ['dapat_kelola' => $r->user()->memilikiIzin('dokumen_humas.kelola')],
                'statistik' => ['aktif' => (clone $aktif)->count(), 'segera_berakhir' => (clone $aktif)->whereBetween('berlaku_sampai', [today(), today()->addDays(30)])->count(),
                    'kedaluwarsa' => (clone $aktif)->whereDate('berlaku_sampai', '<', today())->count()],
            ]);
    }

    public function referensi(Request $r)
    {
        $this->staff($r, ['dokumen_humas.lihat', 'dokumen_humas.kelola']);

        return $this->json(['kategori' => DokumenHumas::KATEGORI, 'status' => DokumenHumas::STATUS,
            'masa_berlaku' => ['tanpa_batas' => 'Tanpa batas berlaku', 'masih_berlaku' => 'Masih berlaku', 'segera_berakhir' => 'Segera berakhir', 'kedaluwarsa' => 'Kedaluwarsa'],
            'batas_berkas_mb' => KelolaDokumenHumasService::BATAS_BERKAS_KB / 1024, 'format_berkas' => KelolaDokumenHumasService::EKSTENSI_BERKAS]);
    }

    public function show(Request $r, DokumenHumas $dokumenHumas)
    {
        $this->staff($r, ['dokumen_humas.lihat', 'dokumen_humas.kelola']);

        return $this->detail($r, $dokumenHumas);
    }

    private function detail(Request $r, DokumenHumas $d, ?string $pesan = null, int $status = 200)
    {
        $d->loadMax('riwayat', 'versi');

        return $this->json($this->mobile->dokumen($d, $this->kelola->sidik($d), true)
            + ['hak_akses' => ['dapat_kelola' => $r->user()->memilikiIzin('dokumen_humas.kelola')]], $pesan, $status);
    }

    public function store(Request $r)
    {
        $this->staff($r, 'dokumen_humas.kelola');
        $d = $this->kelola->store($r);

        return $this->detail($r, $d, 'Dokumen berhasil diunggah.', $d->wasRecentlyCreated ? 201 : 200);
    }

    public function update(Request $r, DokumenHumas $dokumenHumas)
    {
        $this->staff($r, 'dokumen_humas.kelola');
        $r->validate(['berkas' => ['prohibited']]);
        $sidik = $this->sidikInput($r);

        return $this->detail($r, $this->kelola->update($r, $dokumenHumas, $sidik), 'Dokumen berhasil diperbarui.');
    }

    public function revisi(Request $r, DokumenHumas $dokumenHumas)
    {
        $this->staff($r, 'dokumen_humas.kelola');
        $r->validate(['berkas' => ['required', 'file']]);

        return $this->detail($r, $this->kelola->update($r, $dokumenHumas, $this->sidikInput($r)), 'Revisi dokumen berhasil disimpan.');
    }

    public function status(Request $r, DokumenHumas $dokumenHumas)
    {
        $this->staff($r, 'dokumen_humas.kelola');

        return $this->detail($r, $this->kelola->ubahStatus($r, $dokumenHumas, $this->sidikInput($r)), 'Status dokumen diperbarui.');
    }

    private function sidikInput(Request $r): string
    {
        return $r->validate(['sidik' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/D']])['sidik'];
    }

    public function riwayat(Request $r, DokumenHumas $dokumenHumas)
    {
        $this->staff($r, ['dokumen_humas.lihat', 'dokumen_humas.kelola']);
        $p = $this->halaman($r);

        return $this->json($this->mobile->paginasi($dokumenHumas->riwayat()->orderByDesc('id')->paginate($p['per_halaman'] ?? 20, ['*'], 'halaman', $p['halaman'] ?? 1), fn ($v) => [
            'id' => $v->id, 'versi' => $v->versi, 'catatan' => $v->catatan, 'diunggah_pada' => $v->diunggah_pada?->toIso8601String(),
            'nama' => $v->nama_file_asli, 'tipe' => $v->tipe_file, 'ukuran_byte' => $v->ukuran_file,
            'url' => route('api.v1.humas.dokumen.riwayat.unduh', [$dokumenHumas->id, $v->id]),
        ]));
    }

    public function unduh(Request $r, DokumenHumas $dokumenHumas)
    {
        $this->staff($r, ['dokumen_humas.lihat', 'dokumen_humas.kelola']);

        return $this->kelola->unduh($dokumenHumas);
    }

    public function unduhRiwayat(Request $r, DokumenHumas $dokumenHumas, RiwayatDokumenHumas $riwayatDokumenHumas)
    {
        $this->staff($r, ['dokumen_humas.lihat', 'dokumen_humas.kelola']);
        abort_unless($riwayatDokumenHumas->dokumen_humas_id === $dokumenHumas->id, 404);

        return $this->kelola->unduh($riwayatDokumenHumas);
    }
}
