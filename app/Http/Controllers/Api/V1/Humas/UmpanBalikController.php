<?php

namespace App\Http\Controllers\Api\V1\Humas;

use App\Models\JawabanUmpanBalikHumas;
use App\Models\Kelas;
use App\Models\TahunPelajaran;
use App\Models\TindakLanjutUmpanBalikHumas;
use App\Models\UmpanBalikHumas;
use App\Services\Humas\KelolaInstrumenUmpanBalikHumasService;
use App\Services\Humas\UmpanBalikHumasService;
use App\Services\Mobile\HumasMobileService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UmpanBalikController extends HumasController
{
    public function __construct(private readonly KelolaInstrumenUmpanBalikHumasService $kelola, private readonly UmpanBalikHumasService $rekap, private readonly HumasMobileService $mobile) {}

    public function index(Request $r)
    {
        $this->staff($r, ['umpan_balik_humas.lihat', 'umpan_balik_humas.kelola']);
        $filter = $r->validate(['kata_kunci' => ['nullable', 'string', 'max:120'], 'status' => ['nullable', Rule::in(array_keys(UmpanBalikHumas::STATUS))],
            'tahun_pelajaran_id' => ['nullable', 'integer', 'exists:tahun_pelajaran,id']]) + $this->halaman($r);
        $q = UmpanBalikHumas::withCount(['sasaran', 'sasaran as respons_count' => fn ($q) => $q->whereNotNull('dikirim_pada')]);
        if (! $r->user()->memilikiIzin(['agenda_humas.lihat', 'agenda_humas.kelola'])) {
            $q->where('cakupan', '!=', 'agenda');
        }
        $q->when($filter['kata_kunci'] ?? null, fn ($q, $s) => $q->whereLike('judul', '%'.$s.'%'))
            ->when($filter['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($filter['tahun_pelajaran_id'] ?? null, fn ($q, $id) => $q->where('tahun_pelajaran_id', $id));

        return $this->json($this->mobile->paginasi($q->latest('updated_at')->orderByDesc('id')->paginate($filter['per_halaman'] ?? 20, ['*'], 'halaman', $filter['halaman'] ?? 1), fn ($f) => $this->mobile->formulir($f) + ['sasaran' => (int) $f->sasaran_count, 'respons' => (int) $f->respons_count])
            + ['hak_akses' => ['dapat_kelola' => $r->user()->memilikiIzin('umpan_balik_humas.kelola')]]);
    }

    public function referensi(Request $r)
    {
        $this->staff($r, 'umpan_balik_humas.kelola');

        return $this->json(['token_pembuatan' => (string) Str::uuid(), 'status' => UmpanBalikHumas::STATUS, 'cakupan' => $r->user()->memilikiIzin(['agenda_humas.lihat', 'agenda_humas.kelola']) ? UmpanBalikHumas::CAKUPAN : array_diff_key(UmpanBalikHumas::CAKUPAN, ['agenda' => true]),
            'tahun' => TahunPelajaran::orderByDesc('nama')->limit(100)->get(['id', 'nama', 'aktif']),
            'kelas' => Kelas::where('aktif', true)->orderBy('nama')->limit(500)->get(['id', 'nama', 'tingkat', 'tahun_pelajaran_id'])]);
    }

    public function show(Request $r, UmpanBalikHumas $formulir)
    {
        $this->staff($r, ['umpan_balik_humas.lihat', 'umpan_balik_humas.kelola']);
        $this->kelola->akses($r, $formulir);
        $data = $this->mobile->formulir($formulir) + $formulir->only(['tahun_pelajaran_id', 'cakupan', 'tingkat', 'kelas_ids', 'agenda_humas_id', 'penanggung_jawab'])
            + ['pertanyaan' => $formulir->pertanyaan()->get()->map(fn ($p) => $p->only(['id', 'urutan', 'jenis', 'teks', 'wajib'])), 'rekap' => $this->rekap->rekap($formulir)];

        return $this->json($data);
    }

    public function jawabanTeks(Request $r, UmpanBalikHumas $formulir, int $pertanyaan)
    {
        $this->staff($r, ['umpan_balik_humas.lihat', 'umpan_balik_humas.kelola']);
        $this->kelola->akses($r, $formulir);
        $p = $formulir->pertanyaan()->where('jenis', 'teks')->findOrFail($pertanyaan);
        $filter = $this->halaman($r);

        return $this->json($this->mobile->paginasi(JawabanUmpanBalikHumas::where('pertanyaan_umpan_balik_humas_id', $p->id)->whereNotNull('teks')->orderBy('id')->paginate($filter['per_halaman'] ?? 15, ['teks'], 'halaman', $filter['halaman'] ?? 1), fn ($j) => ['teks' => $j->teks]));
    }

    public function tindakLanjut(Request $r, UmpanBalikHumas $formulir)
    {
        $this->staff($r, ['umpan_balik_humas.lihat', 'umpan_balik_humas.kelola']);
        $this->kelola->akses($r, $formulir);
        $p = $this->halaman($r);

        return $this->json($this->mobile->paginasi($formulir->tindakLanjut()->paginate($p['per_halaman'] ?? 50, ['*'], 'halaman', $p['halaman'] ?? 1), fn ($t) => $t->only(['id', 'pertanyaan_umpan_balik_humas_id', 'uraian', 'penanggung_jawab', 'batas_tanggal', 'status', 'hasil', 'bagikan_ringkasan', 'ringkasan_publik'])));
    }

    public function store(Request $r)
    {
        $this->staff($r, 'umpan_balik_humas.kelola');
        $formulir = $this->kelola->store($r);

        return $this->json($this->mobile->formulir($formulir), 'Draf tersimpan.', $formulir->wasRecentlyCreated ? 201 : 200);
    }

    public function update(Request $r, UmpanBalikHumas $formulir)
    {
        $this->staff($r, 'umpan_balik_humas.kelola');

        return $this->json($this->mobile->formulir($this->kelola->update($r, $formulir)), 'Draf diperbarui.');
    }

    public function status(Request $r, UmpanBalikHumas $formulir)
    {
        $this->staff($r, 'umpan_balik_humas.kelola');

        return $this->json($this->mobile->formulir($this->kelola->status($r, $formulir)), 'Status diperbarui.');
    }

    public function tambahTindak(Request $r, UmpanBalikHumas $formulir)
    {
        $this->staff($r, 'umpan_balik_humas.kelola');

        return $this->json($this->mobile->formulir($this->kelola->tindak($r, $formulir)), 'Tindak lanjut tersimpan.', 201);
    }

    public function ubahTindak(Request $r, UmpanBalikHumas $formulir, TindakLanjutUmpanBalikHumas $tindak)
    {
        $this->staff($r, 'umpan_balik_humas.kelola');

        return $this->json($this->mobile->formulir($this->kelola->tindak($r, $formulir, $tindak)), 'Tindak lanjut diperbarui.');
    }
}
