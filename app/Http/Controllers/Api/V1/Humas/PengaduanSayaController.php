<?php

namespace App\Http\Controllers\Api\V1\Humas;

use App\Models\LampiranPengaduanHumas;
use App\Models\PengaduanHumas;
use App\Services\Humas\LampiranPengaduanHumasService;
use App\Services\Humas\PengaduanOrangTuaHumasService;
use App\Services\Mobile\HumasMobileService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PengaduanSayaController extends HumasController
{
    public function __construct(private readonly PengaduanOrangTuaHumasService $kelola, private readonly HumasMobileService $mobile) {}

    public function index(Request $r)
    {
        $this->kelola->wali($r);
        $filter = $r->validate(['status' => ['nullable', Rule::in(['aktif', 'selesai', 'semua'])], 'kata_kunci' => ['nullable', 'string', 'max:120']]) + $this->halaman($r);
        $q = $this->kelola->milik($r);
        if (($filter['status'] ?? 'semua') === 'aktif') {
            $q->whereIn('status', PengaduanHumas::AKTIF);
        } elseif (($filter['status'] ?? null) === 'selesai') {
            $q->whereIn('status', ['selesai', 'ditutup']);
        }
        $q->when($filter['kata_kunci'] ?? null, fn ($q, $s) => $q->whereLike('judul', '%'.$s.'%'));

        return $this->json($this->mobile->paginasi($q->latest('updated_at')->orderByDesc('id')->paginate($filter['per_halaman'] ?? 15, ['*'], 'halaman', $filter['halaman'] ?? 1), fn ($t) => $this->mobile->pengaduan($t, true)));
    }

    public function referensi(Request $r)
    {
        $this->kelola->wali($r);

        return $this->json(['jenis' => PengaduanHumas::JENIS, 'kategori' => PengaduanHumas::KATEGORI,
            'token_pembuatan' => (string) Str::uuid(), 'batas_lampiran' => ['jumlah' => 3, 'mb_per_berkas' => 10, 'format' => ['pdf', 'jpg', 'jpeg', 'png', 'webp']]]);
    }

    public function show(Request $r, int $tiket)
    {
        $this->kelola->wali($r);
        $t = $this->kelola->milik($r)->findOrFail($tiket);

        return $this->json($this->mobile->pengaduan($t, true, false, true) + ['lampiran' => $t->lampiran()->where('asal', 'orang_tua')->get()->map(fn ($f) => [
            'id' => $f->id, 'nama' => $f->nama_file_asli, 'tipe' => $f->tipe_file, 'ukuran_byte' => $f->ukuran_file,
            'url' => route('api.v1.humas.pengaduan-saya.lampiran', [$t->id, $f->id]),
        ])]);
    }

    public function pesan(Request $r, int $tiket)
    {
        $this->kelola->wali($r);
        $t = $this->kelola->milik($r)->findOrFail($tiket);
        $p = $this->halaman($r);

        return $this->json($this->mobile->paginasi($t->pesan()->orderByDesc('id')->paginate($p['per_halaman'] ?? 15, ['id', 'asal', 'isi', 'created_at'], 'halaman', $p['halaman'] ?? 1), fn ($m) => $m->only(['id', 'asal', 'isi', 'created_at'])));
    }

    public function store(Request $r)
    {
        $t = $this->kelola->store($r);

        return $this->json($this->mobile->pengaduan($t, true, false, true), 'Laporan berhasil dikirim.', $t->wasRecentlyCreated ? 201 : 200);
    }

    public function informasi(Request $r, int $tiket)
    {
        $t = $this->kelola->informasi($r, $tiket);

        return $this->json($this->mobile->pengaduan($t, true, false, true), 'Informasi tambahan terkirim.');
    }

    public function lampiran(Request $r, int $tiket, int $lampiran)
    {
        $this->kelola->wali($r);
        $t = $this->kelola->milik($r)->findOrFail($tiket);
        $f = LampiranPengaduanHumas::where('pengaduan_humas_id', $t->id)->where('asal', 'orang_tua')->findOrFail($lampiran);

        return app(LampiranPengaduanHumasService::class)->unduh($f);
    }
}
