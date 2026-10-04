<?php

namespace App\Http\Controllers\Api\V1\Humas;

use App\Models\LampiranPengaduanHumas;
use App\Models\PengaduanHumas;
use App\Services\Humas\KelolaPengaduanHumasService;
use App\Services\Humas\KelolaTiketPengaduanHumasService;
use App\Services\Humas\LampiranPengaduanHumasService;
use App\Services\Mobile\HumasMobileService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PengaduanController extends HumasController
{
    public function __construct(private readonly KelolaTiketPengaduanHumasService $proses, private readonly KelolaPengaduanHumasService $kelola, private readonly HumasMobileService $mobile) {}

    public function index(Request $r)
    {
        $this->staff($r, PengaduanHumas::IZIN);
        $filter = $r->validate(['status' => ['nullable', Rule::in([...array_keys(PengaduanHumas::STATUS), 'aktif', 'semua'])],
            'jenis' => ['nullable', Rule::in(array_keys(PengaduanHumas::JENIS))], 'kategori' => ['nullable', Rule::in(array_keys(PengaduanHumas::KATEGORI))],
            'kata_kunci' => ['nullable', 'string', 'max:120'], 'tugas' => ['nullable', Rule::in(['semua', 'saya'])]]) + $this->halaman($r);
        $q = PengaduanHumas::untukPengguna($r->user());
        $status = $filter['status'] ?? 'aktif';
        if ($status === 'aktif') {
            $q->whereIn('status', PengaduanHumas::AKTIF);
        } elseif ($status !== 'semua') {
            $q->where('status', $status);
        }
        foreach (['jenis', 'kategori'] as $key) {
            $q->when($filter[$key] ?? null, fn ($q, $value) => $q->where($key, $value));
        }
        $q->when($filter['kata_kunci'] ?? null, fn ($q, $s) => $q->whereLike('judul', '%'.$s.'%'))
            ->when(($filter['tugas'] ?? null) === 'saya', fn ($q) => $q->where('petugas_pengguna_id', $r->user()->id));

        return $this->json($this->mobile->paginasi($q->latest('updated_at')->orderByDesc('id')->paginate($filter['per_halaman'] ?? 20, ['*'], 'halaman', $filter['halaman'] ?? 1), fn ($t) => $this->mobile->pengaduan($t)));
    }

    public function referensi(Request $r)
    {
        $this->staff($r, 'pengaduan_humas.kelola');

        return $this->json(['jenis' => PengaduanHumas::JENIS, 'kategori' => PengaduanHumas::KATEGORI, 'kanal' => array_diff_key(PengaduanHumas::KANAL, ['akun_orang_tua' => true]),
            'prioritas' => PengaduanHumas::PRIORITAS, 'status' => PengaduanHumas::STATUS, 'token_pembuatan' => (string) Str::uuid(),
            'petugas' => $this->kelola->kandidat()->map(fn ($u) => $u->only(['id', 'nama']))->values()]);
    }

    public function show(Request $r, PengaduanHumas $tiket)
    {
        $this->staff($r, PengaduanHumas::IZIN);
        $this->kelola->akses($tiket, $r->user());
        $manager = $r->user()->memilikiIzin('pengaduan_humas.kelola');
        $data = $this->mobile->pengaduan($tiket, false, $manager, true);
        if ($manager) {
            $data['lampiran'] = $tiket->lampiran()->get()->map(fn ($f) => ['id' => $f->id, 'nama' => $f->nama_file_asli, 'tipe' => $f->tipe_file,
                'ukuran_byte' => $f->ukuran_file, 'url' => route('api.v1.humas.pengaduan.lampiran', [$tiket, $f])]);
        }

        return $this->json($data);
    }

    public function riwayat(Request $r, PengaduanHumas $tiket)
    {
        $this->staff($r, PengaduanHumas::IZIN);
        $this->kelola->akses($tiket, $r->user());
        $p = $this->halaman($r);

        return $this->json($this->mobile->paginasi($tiket->riwayat()->paginate($p['per_halaman'] ?? 15, ['versi', 'aksi', 'status', 'catatan', 'created_at'], 'halaman', $p['halaman'] ?? 1), fn ($h) => $h->only(['versi', 'aksi', 'status', 'catatan', 'created_at'])));
    }

    public function pesan(Request $r, PengaduanHumas $tiket)
    {
        $this->staff($r, PengaduanHumas::IZIN);
        $this->kelola->akses($tiket, $r->user());
        $p = $this->halaman($r);

        return $this->json($this->mobile->paginasi($tiket->pesan()->orderByDesc('id')->paginate($p['per_halaman'] ?? 15, ['id', 'asal', 'isi', 'created_at'], 'halaman', $p['halaman'] ?? 1), fn ($m) => $m->only(['id', 'asal', 'isi', 'created_at'])));
    }

    public function store(Request $r)
    {
        $this->staff($r, 'pengaduan_humas.kelola');
        $tiket = $this->proses->store($r);

        return $this->json($this->mobile->pengaduan($tiket, false, true, true), 'Tiket tersimpan.', $tiket->wasRecentlyCreated ? 201 : 200);
    }

    public function update(Request $r, PengaduanHumas $tiket)
    {
        $this->staff($r, 'pengaduan_humas.kelola');

        return $this->json($this->mobile->pengaduan($this->proses->update($r, $tiket), false, true, true), 'Tiket diperbarui.');
    }

    public function tindakan(Request $r, PengaduanHumas $tiket, string $aksi)
    {
        $this->staff($r, ['pengaduan_humas.kelola', 'pengaduan_humas.tangani']);

        return $this->json($this->mobile->pengaduan($this->proses->tindakan($r, $tiket, $aksi)), 'Penanganan diperbarui.');
    }

    public function balasan(Request $r, PengaduanHumas $tiket)
    {
        $this->staff($r, 'pengaduan_humas.kelola');

        return $this->json($this->mobile->pengaduan($this->proses->balasan($r, $tiket)), 'Balasan resmi terkirim.');
    }

    public function tambahLampiran(Request $r, PengaduanHumas $tiket)
    {
        $this->staff($r, 'pengaduan_humas.kelola');

        return $this->json($this->mobile->pengaduan($this->proses->tambahLampiran($r, $tiket)), 'Lampiran tersimpan.');
    }

    public function lampiran(Request $r, PengaduanHumas $tiket, LampiranPengaduanHumas $lampiran)
    {
        $this->staff($r, 'pengaduan_humas.kelola');
        $this->kelola->akses($tiket, $r->user());
        abort_unless($lampiran->pengaduan_humas_id === $tiket->id, 404);

        return app(LampiranPengaduanHumasService::class)->unduh($lampiran);
    }
}
