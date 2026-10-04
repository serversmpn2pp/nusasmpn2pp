<?php

namespace App\Services\Humas;

use App\Models\Izin;
use App\Models\OrangTuaWali;
use App\Models\PertanyaanUmpanBalikHumas;
use App\Models\TindakLanjutUmpanBalikHumas;
use App\Models\UmpanBalikHumas;
use App\Services\Notifikasi\NotifikasiPenggunaService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class KelolaInstrumenUmpanBalikHumasService
{
    public function __construct(private readonly UmpanBalikHumasService $kelola) {}

    public function store(Request $request)
    {
        $this->akses($request);
        $data = $this->validasi($request);
        $f = DB::transaction(function () use ($request, $data) {
            Izin::where('kode', 'umpan_balik_humas.kelola')->lockForUpdate()->firstOrFail();
            $f = UmpanBalikHumas::where('token_pembuatan', $data['token_pembuatan'])->first();
            if ($f) {
                abort_unless($f->dibuat_oleh_pengguna_id === $request->user()->id, 403);

                return $f;
            }
            $f = new UmpanBalikHumas(Arr::only($data, ['token_pembuatan', ...UmpanBalikHumas::KOLOM]));
            $f->forceFill(['status' => 'draf', 'versi' => 0, 'dibuat_oleh_pengguna_id' => $request->user()->id])->save();
            $this->simpanPertanyaan($f, $data['pertanyaan']);
            $this->kelola->catat($f, $request->user(), 'Formulir dibuat');

            return $f;
        });

        return $f->refresh();
    }

    public function update(Request $request, UmpanBalikHumas $formulir)
    {
        $this->akses($request, $formulir);
        $data = $this->validasi($request, true);
        DB::transaction(function () use ($request, $formulir, $data) {
            $f = $this->kelola->kunci($formulir, $data['versi']);
            if ($f->status !== 'draf' || $f->dibuka_pada) {
                $this->kelola->gagal('Pertanyaan dan sasaran dikunci setelah formulir dibuka. Buat formulir baru untuk instrumen berbeda.');
            }
            $f->fill(Arr::only($data, UmpanBalikHumas::KOLOM))->save();
            $f->pertanyaan()->delete();
            $this->simpanPertanyaan($f, $data['pertanyaan']);
            $this->kelola->catat($f, $request->user(), 'Draf diperbarui', $data['alasan']);
        });

        return $formulir->fresh();
    }

    public function status(Request $request, UmpanBalikHumas $formulir)
    {
        $this->akses($request, $formulir);
        $data = $request->validate(['versi' => ['required', 'integer', 'min:0'], 'status' => ['required', Rule::in(array_keys(UmpanBalikHumas::STATUS))], 'alasan' => ['required', 'string', 'min:5', 'max:2000'],
            'selesai_pada' => ['nullable', 'date_format:Y-m-d\TH:i']]);
        DB::transaction(function () use ($request, $formulir, $data) {
            $f = $this->kelola->kunci($formulir, $data['versi']);
            $tujuan = $data['status'];
            $sah = ['draf' => ['aktif', 'arsip'], 'aktif' => ['aktif', 'ditutup', 'arsip'], 'ditutup' => ['aktif', 'arsip'], 'arsip' => [$f->dibuka_pada ? 'ditutup' : 'draf']];
            if (! in_array($tujuan, $sah[$f->status], true)) {
                $this->kelola->gagal('Perubahan status tidak sesuai.');
            }
            if ($tujuan === 'aktif') {
                if ($f->dibuka_pada) {
                    if (empty($data['selesai_pada'])) {
                        $this->kelola->gagal('Isi batas waktu untuk membuka kembali atau memperpanjang formulir.');
                    }
                    $akhir = Carbon::parse($data['selesai_pada']);
                    if ($akhir->lte(now()) || $akhir->lte($f->mulai_pada) || ($f->status === 'aktif' && $akhir->lte($f->selesai_pada))) {
                        $this->kelola->gagal('Batas waktu baru harus sesudah sekarang dan sesudah batas lama untuk perpanjangan.');
                    }
                    $f->selesai_pada = $akhir;
                } else {
                    if ($f->selesai_pada->lte(now()) || ! $f->pertanyaan()->exists()) {
                        $this->kelola->gagal('Periksa periode pengisian dan pertanyaan sebelum membuka formulir.');
                    }
                    $this->kelola->bekukanSasaran($f);
                    $f->dibuka_pada = now();
                    $ids = OrangTuaWali::whereIn('id', $f->sasaran()->pluck('orang_tua_wali_id'))->pluck('pengguna_id');
                    app(NotifikasiPenggunaService::class)->kirimKeBanyak($ids->all(), 'informasi', 'Form evaluasi orang tua', $f->judul, '/umpan-balik-saya/'.$f->id, 'umpan-balik-'.$f->id.'-dibuka');
                }
            }
            $f->status = $tujuan;
            $f->save();
            $this->kelola->catat($f, $request->user(), 'Status: '.UmpanBalikHumas::STATUS[$tujuan], $data['alasan']);
        });

        return $formulir->fresh();
    }

    public function tindak(Request $request, UmpanBalikHumas $formulir, ?TindakLanjutUmpanBalikHumas $tindak = null)
    {
        $this->akses($request, $formulir);
        $edit = $tindak?->exists ?? false;
        $data = $request->validate(['versi' => ['required', 'integer', 'min:0'], 'token_pembuatan' => [$edit ? 'nullable' : 'required', 'uuid'],
            'pertanyaan_umpan_balik_humas_id' => ['nullable', 'integer', Rule::exists('pertanyaan_umpan_balik_humas', 'id')->where('umpan_balik_humas_id', $formulir->id)],
            'uraian' => ['required', 'string', 'min:10', 'max:5000'], 'penanggung_jawab' => ['required', 'string', 'max:180'], 'batas_tanggal' => ['required', 'date_format:Y-m-d'],
            'status' => ['required', Rule::in(array_keys(TindakLanjutUmpanBalikHumas::STATUS))], 'hasil' => ['nullable', 'required_if:status,selesai', 'string', 'min:5', 'max:5000'],
            'bagikan_ringkasan' => ['required', 'boolean'], 'ringkasan_publik' => ['nullable', 'required_if:bagikan_ringkasan,1', 'string', 'min:5', 'max:2000'],
            'alasan' => [$edit ? 'required' : 'nullable', 'string', 'min:5', 'max:2000']]);
        DB::transaction(function () use ($request, $formulir, $tindak, $edit, $data) {
            $f = UmpanBalikHumas::lockForUpdate()->findOrFail($formulir->id);
            if (! $edit && ($ada = TindakLanjutUmpanBalikHumas::where('token_pembuatan', $data['token_pembuatan'])->first())) {
                abort_unless($ada->umpan_balik_humas_id === $f->id && $ada->dibuat_oleh_pengguna_id === $request->user()->id, 403);

                return;
            }
            $f = $this->kelola->kunci($f, $data['versi']);
            if (! $f->dibuka_pada || $f->status === 'arsip') {
                $this->kelola->gagal('Tindak lanjut dikelola setelah formulir dibuka, sebelum diarsipkan.');
            }
            if ($data['bagikan_ringkasan'] && $data['status'] !== 'selesai') {
                $this->kelola->gagal('Ringkasan untuk orang tua hanya dibagikan setelah tindak lanjut selesai.');
            }
            $t = $edit ? TindakLanjutUmpanBalikHumas::lockForUpdate()->findOrFail($tindak->id) : new TindakLanjutUmpanBalikHumas;
            if ($edit) {
                abort_unless($t->umpan_balik_humas_id === $f->id, 404);
            }
            $t->fill(Arr::except($data, ['versi', 'alasan', ...($edit ? ['token_pembuatan'] : [])]));
            if (! $data['bagikan_ringkasan']) {
                $t->ringkasan_publik = null;
            }
            $t->forceFill(['umpan_balik_humas_id' => $f->id, 'selesai_pada' => $data['status'] === 'selesai' ? ($t->selesai_pada ?? now()) : null]);
            if (! $edit) {
                $t->dibuat_oleh_pengguna_id = $request->user()->id;
            }
            $t->save();
            $this->kelola->catat($f, $request->user(), $edit ? 'Tindak lanjut diperbarui' : 'Tindak lanjut ditambahkan', $data['alasan'] ?? null, ['tindak_lanjut' => $t->only(['id', 'uraian', 'penanggung_jawab', 'batas_tanggal', 'status', 'hasil', 'bagikan_ringkasan', 'ringkasan_publik'])]);
        });

        return $formulir->fresh();
    }

    private function validasi(Request $request, bool $edit = false): array
    {
        $tahunId = is_scalar($request->input('tahun_pelajaran_id')) ? (int) $request->input('tahun_pelajaran_id') : 0;
        $data = $request->validate(['token_pembuatan' => [$edit ? 'nullable' : 'required', 'uuid'], 'versi' => [$edit ? 'required' : 'nullable', 'integer', 'min:0'],
            'tahun_pelajaran_id' => ['required', 'integer', 'exists:tahun_pelajaran,id'], 'judul' => ['required', 'string', 'max:180'], 'pengantar' => ['required', 'string', 'max:5000'], 'penanggung_jawab' => ['required', 'string', 'max:180'],
            'cakupan' => ['required', Rule::in(array_keys(UmpanBalikHumas::CAKUPAN))], 'tingkat' => ['exclude_unless:cakupan,tingkat', 'required', 'integer', Rule::exists('kelas', 'tingkat')->where('tahun_pelajaran_id', $tahunId)->where('aktif', true)],
            'kelas_ids' => ['exclude_unless:cakupan,kelas', 'required', 'array', 'min:1', 'max:100'], 'kelas_ids.*' => ['integer', 'distinct', Rule::exists('kelas', 'id')->where('tahun_pelajaran_id', $tahunId)->where('aktif', true)],
            'agenda_humas_id' => ['exclude_unless:cakupan,agenda', 'required', 'integer', 'exists:agenda_humas,id'],
            'mulai_pada' => ['required', 'date_format:Y-m-d\TH:i'], 'selesai_pada' => ['required', 'date_format:Y-m-d\TH:i', 'after:mulai_pada'],
            'pertanyaan' => ['required', 'array', 'min:1', 'max:30'], 'pertanyaan.*' => ['required', 'array:jenis,teks,wajib'], 'pertanyaan.*.jenis' => ['required', Rule::in(array_keys(PertanyaanUmpanBalikHumas::JENIS))],
            'pertanyaan.*.teks' => ['required', 'string', 'min:5', 'max:1000'], 'pertanyaan.*.wajib' => ['required', 'boolean'], 'alasan' => [$edit ? 'required' : 'nullable', 'string', 'min:5', 'max:2000']]);
        if ($data['cakupan'] === 'agenda') {
            $this->aksesAgenda($request);
        }

        return $data + ['tingkat' => null, 'kelas_ids' => null, 'agenda_humas_id' => null];
    }

    private function simpanPertanyaan(UmpanBalikHumas $f, array $daftar): void
    {
        foreach (array_values($daftar) as $i => $p) {
            $f->pertanyaan()->create($p + ['urutan' => $i + 1]);
        }
    }

    public function akses(Request $r, ?UmpanBalikHumas $formulir = null): void
    {
        abort_unless($r->user()->aktif && ! $r->user()->akunOrangTua() && ! $r->user()->akunSiswa() && $r->user()->memilikiIzin(['umpan_balik_humas.lihat', 'umpan_balik_humas.kelola']), 403);
        if ($formulir?->cakupan === 'agenda') {
            $this->aksesAgenda($r);
        }
    }

    private function aksesAgenda(Request $r): void
    {
        abort_unless($r->user()->memilikiIzin(['agenda_humas.lihat', 'agenda_humas.kelola']), 403);
    }
}
