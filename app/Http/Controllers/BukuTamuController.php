<?php

namespace App\Http\Controllers;

use App\Models\KunjunganTamu;
use App\Models\LampiranKunjunganTamu;
use App\Models\Pegawai;
use App\Models\TahunPelajaran;
use App\Services\Humas\AksesBukuTamuService;
use App\Services\Humas\SimpanLampiranKunjunganService;
use App\Support\PenulisExcelBukuTamu;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class BukuTamuController extends Controller
{
    public function __construct(private AksesBukuTamuService $akses, private SimpanLampiranKunjunganService $berkas) {}

    public function index(Request $request)
    {
        $query = $this->akses->cakupan($request->user());
        $rekap = $request->query('tab') === 'rekap';
        abort_if($rekap && ! $this->akses->bolehRekap($request->user()), 403);
        $filter = $this->filter($request, $rekap);
        if (! $rekap) {
            $query->where(fn ($q) => $q->whereBetween('waktu_datang', [today(), today()->endOfDay()])
                ->orWhere(fn ($q) => $q->where('status', 'berkunjung')->where('waktu_datang', '<', today())));
        }
        $this->terapkanFilter($query, $filter, $rekap);

        return view('buku-tamu.index', $this->dataAkses($request) + [
            'rekap' => $rekap, 'filter' => $filter,
            'ringkasan' => $this->ringkasan(clone $query),
            'kunjungan' => $query->with('pencatat:id,nama')->withCount('lampiran')
                ->orderByRaw("CASE WHEN status = 'berkunjung' THEN 0 ELSE 1 END")
                ->orderByDesc('waktu_datang')->orderByDesc('id')->paginate(25)->withQueryString(),
            'tahunPelajaran' => $rekap ? TahunPelajaran::orderByDesc('tanggal_mulai')->get() : collect(),
        ]);
    }

    public function create(Request $request)
    {
        $this->pastikanCatat($request);

        return view('buku-tamu.form', $this->dataAkses($request) + [
            'tamu' => new KunjunganTamu(['waktu_datang' => now(), 'kategori' => 'dinas']),
            'pegawai' => $this->pegawai(), 'token' => (string) Str::uuid(),
        ]);
    }

    public function store(Request $request)
    {
        $this->pastikanCatat($request);
        $data = $request->validate($this->aturan($request) + $this->berkas->aturan() + ['token_pencatatan' => ['required', 'uuid']]);
        $this->berkas->pastikanUkuran($data);
        $lokasi = [];
        try {
            $tamu = DB::transaction(function () use ($request, $data, &$lokasi) {
                $lama = KunjunganTamu::where('token_pencatatan', $data['token_pencatatan'])->lockForUpdate()->first();
                if ($lama) {
                    abort_unless($lama->dicatat_oleh_pengguna_id === $request->user()->id, 403);

                    return $lama;
                }
                $tamu = new KunjunganTamu($this->dataKunjungan($data, $request));
                $tamu->forceFill(['token_pencatatan' => $data['token_pencatatan'],
                    'dicatat_oleh_pengguna_id' => $request->user()->id, 'diubah_oleh_pengguna_id' => $request->user()->id])->save();
                $this->berkas->simpan($tamu, $data, $request->user()->id, $lokasi);
                $this->catatRiwayat($tamu, 'datang', null, $tamu->dataAudit(), $request);

                return $tamu;
            });
        } catch (Throwable $e) {
            $this->berkas->bersihkan($lokasi);
            $tamu = $e instanceof QueryException ? KunjunganTamu::where('token_pencatatan', $data['token_pencatatan'])->first() : null;
            if (! $tamu || $tamu->dicatat_oleh_pengguna_id !== $request->user()->id) {
                throw $e;
            }
        }

        return $this->kembali($request, $tamu, 'Kedatangan tamu berhasil dicatat.');
    }

    public function show(Request $request, KunjunganTamu $tamu)
    {
        $this->akses->pastikanLihat($request->user(), $tamu);

        return view('buku-tamu.show', $this->dataAkses($request) + [
            'tamu' => $tamu->load('pencatat', 'lampiran'), 'tokenUnggahan' => (string) Str::uuid(),
            'riwayat' => $this->akses->bolehRekap($request->user()) ? $tamu->riwayat()->with('pengguna:id,nama')->paginate(15) : null,
        ]);
    }

    public function edit(Request $request, KunjunganTamu $tamu)
    {
        abort_unless($this->akses->bolehKelola($request->user()), 403);
        $this->pastikanTidakBatal($tamu);

        return view('buku-tamu.form', $this->dataAkses($request) + ['tamu' => $tamu, 'pegawai' => $this->pegawai($tamu), 'token' => null]);
    }

    public function update(Request $request, KunjunganTamu $tamu)
    {
        abort_unless($this->akses->bolehKelola($request->user()), 403);
        $data = $request->validate($this->aturan($request, $tamu) + ['versi' => ['required', 'integer', 'min:0']]);
        DB::transaction(function () use ($request, $data, $tamu) {
            $tamu = KunjunganTamu::whereKey($tamu->id)->lockForUpdate()->firstOrFail();
            $this->pastikanTidakBatal($tamu);
            if ((int) $data['versi'] !== $tamu->versi) {
                throw ValidationException::withMessages(['versi' => 'Data telah berubah. Muat ulang halaman sebelum melakukan koreksi.']);
            }
            $sebelum = $tamu->dataAudit();
            $tamu->fill($this->dataKunjungan($data, $request, $tamu));
            if ($tamu->isDirty()) {
                $tamu->versi++;
                $tamu->diubah_oleh_pengguna_id = $request->user()->id;
                $tamu->save();
                $this->catatRiwayat($tamu, 'koreksi', $sebelum, $tamu->dataAudit(), $request);
            }
        });

        return $this->kembali($request, $tamu, 'Koreksi kunjungan berhasil disimpan.');
    }

    public function pulang(Request $request, KunjunganTamu $tamu)
    {
        $this->pastikanCatat($request);
        DB::transaction(function () use ($request, $tamu) {
            $tamu = KunjunganTamu::whereKey($tamu->id)->lockForUpdate()->firstOrFail();
            $this->akses->pastikanLihat($request->user(), $tamu);
            $this->pastikanTidakBatal($tamu);
            if ($tamu->waktu_pulang) {
                return;
            }
            $sebelum = $tamu->dataAudit();
            $tamu->update(['waktu_pulang' => now(), 'status' => 'selesai', 'versi' => $tamu->versi + 1, 'diubah_oleh_pengguna_id' => $request->user()->id]);
            $this->catatRiwayat($tamu, 'pulang', $sebelum, $tamu->dataAudit(), $request);
        });

        return $this->kembali($request, $tamu, 'Kepulangan tamu berhasil dicatat.');
    }

    public function batalkan(Request $request, KunjunganTamu $tamu)
    {
        abort_unless($this->akses->bolehKelola($request->user()), 403);
        $data = $request->validate(['alasan_pembatalan' => ['required', 'string', 'min:5', 'max:1000']]);
        DB::transaction(function () use ($request, $data, $tamu) {
            $tamu = KunjunganTamu::whereKey($tamu->id)->lockForUpdate()->firstOrFail();
            if ($tamu->status === 'dibatalkan') {
                return;
            }
            $sebelum = $tamu->dataAudit();
            $tamu->update($data + ['status' => 'dibatalkan', 'versi' => $tamu->versi + 1, 'diubah_oleh_pengguna_id' => $request->user()->id]);
            $this->catatRiwayat($tamu, 'batal', $sebelum, $tamu->dataAudit(), $request);
        });

        return $this->kembali($request, $tamu, 'Kunjungan dibatalkan. Riwayatnya tetap tersimpan.');
    }

    public function tambahLampiran(Request $request, KunjunganTamu $tamu)
    {
        $this->pastikanCatat($request);
        $this->akses->pastikanLihat($request->user(), $tamu);
        $data = $request->validate($this->berkas->aturan() + ['token_unggahan' => ['required', 'uuid']]);
        $this->berkas->pastikanUkuran($data);
        if (count($data['surat_tugas'] ?? []) + count($data['dokumentasi'] ?? []) === 0) {
            throw ValidationException::withMessages(['lampiran' => 'Pilih sedikitnya satu berkas.']);
        }
        $lokasi = [];
        try {
            DB::transaction(function () use ($request, $data, $tamu, &$lokasi) {
                $tamu = KunjunganTamu::whereKey($tamu->id)->lockForUpdate()->firstOrFail();
                $this->akses->pastikanLihat($request->user(), $tamu);
                $this->pastikanTidakBatal($tamu);
                if ($tamu->riwayat()->where('token_operasi', $data['token_unggahan'])->exists()) {
                    return;
                }
                $jumlah = $this->berkas->simpan($tamu, $data, $request->user()->id, $lokasi);
                $this->catatRiwayat($tamu, 'lampiran', null, ['jumlah' => $jumlah,
                    'berkas' => collect($data['surat_tugas'] ?? [])->merge($data['dokumentasi'] ?? [])->map(fn ($f) => $f->getClientOriginalName())->all()], $request, $data['token_unggahan']);
            });
        } catch (Throwable $e) {
            $this->berkas->bersihkan($lokasi);
            throw $e;
        }

        return $this->kembali($request, $tamu, 'Lampiran berhasil disimpan.');
    }

    public function unduh(Request $request, KunjunganTamu $tamu, LampiranKunjunganTamu $lampiran)
    {
        $this->pastikanLampiran($request, $tamu, $lampiran);

        return Storage::disk('local')->download($lampiran->lokasi_file, $lampiran->nama_file_asli, ['X-Content-Type-Options' => 'nosniff']);
    }

    public function pratinjau(Request $request, KunjunganTamu $tamu, LampiranKunjunganTamu $lampiran)
    {
        $this->pastikanLampiran($request, $tamu, $lampiran);
        abort_unless(in_array($lampiran->tipe_file, ['image/jpeg', 'image/png', 'image/webp'], true), 404);

        return response()->file(Storage::disk('local')->path($lampiran->lokasi_file), [
            'Content-Type' => $lampiran->tipe_file, 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store',
        ]);
    }

    public function hapusLampiran(Request $request, KunjunganTamu $tamu, LampiranKunjunganTamu $lampiran)
    {
        abort_unless($this->akses->bolehKelola($request->user()), 403);
        abort_unless($lampiran->kunjungan_tamu_id === $tamu->id, 404);
        DB::transaction(function () use ($request, $tamu, $lampiran) {
            $tamu = KunjunganTamu::whereKey($tamu->id)->lockForUpdate()->firstOrFail();
            $this->pastikanTidakBatal($tamu);
            $lampiran = $tamu->lampiran()->whereKey($lampiran->id)->lockForUpdate()->firstOrFail();
            $this->catatRiwayat($tamu, 'hapus_lampiran', ['nama_file' => $lampiran->nama_file_asli, 'jenis' => $lampiran->jenis], null, $request);
            $lampiran->delete();
        });
        Storage::disk('local')->delete($lampiran->lokasi_file);

        return $this->kembali($request, $tamu, 'Lampiran dihapus dan dicatat dalam riwayat.');
    }

    public function export(Request $request, PenulisExcelBukuTamu $excel)
    {
        [$query, $filter] = $this->queryLaporan($request);
        $kunjungan = $query->with('pencatat:id,nama')->orderBy('waktu_datang')->orderBy('id')->limit(10001)->get();
        if ($kunjungan->count() > 10000) {
            throw ValidationException::withMessages(['periode' => 'Ekspor maksimal 10.000 kunjungan. Persempit periode laporan.']);
        }

        return response()->download($excel->buat($kunjungan, $filter['label_periode']), 'buku-tamu-'.$filter['dari'].'-'.$filter['sampai'].'.xlsx', ['Content-Type' => PenulisExcelBukuTamu::MIME])->deleteFileAfterSend(true);
    }

    public function cetak(Request $request)
    {
        [$query, $filter] = $this->queryLaporan($request);
        $ringkasan = $this->ringkasan(clone $query);
        $kunjungan = $query->orderBy('waktu_datang')->orderBy('id')->limit(1001)->get();
        if ($kunjungan->count() > 1000) {
            throw ValidationException::withMessages(['periode' => 'Cetak maksimal 1.000 kunjungan. Persempit periode laporan.']);
        }

        return view('buku-tamu.cetak', compact('kunjungan', 'filter', 'ringkasan'));
    }

    private function aturan(Request $request, ?KunjunganTamu $tamu = null): array
    {
        $kelola = $this->akses->bolehKelola($request->user());

        return [
            'nama_tamu' => ['required', 'string', 'max:180'], 'instansi' => ['nullable', 'string', 'max:180'],
            'alamat_instansi' => ['nullable', 'string', 'max:500'], 'jabatan' => ['nullable', 'string', 'max:120'],
            'nomor_wa' => ['nullable', 'string', 'max:32', 'regex:/^\+?[0-9][0-9\s().-]{5,30}$/'],
            'kategori' => ['required', Rule::in(array_keys(KunjunganTamu::KATEGORI))], 'keperluan' => ['required', 'string', 'max:3000'],
            'pegawai_tujuan_id' => ['nullable', 'integer', Rule::exists('pegawai', 'id')->where(fn ($q) => $q->where('aktif', true)->when($tamu?->pegawai_tujuan_id, fn ($q) => $q->orWhere('id', $tamu->pegawai_tujuan_id)))],
            'tujuan_lain' => ['required_without:pegawai_tujuan_id', 'nullable', 'string', 'max:180'], 'catatan' => ['nullable', 'string', 'max:1000'],
            'waktu_datang' => $kelola ? ['required', 'date_format:Y-m-d\TH:i', 'before_or_equal:now'] : ['exclude'],
            'waktu_pulang' => $tamu ? ['nullable', 'date_format:Y-m-d\TH:i', 'after_or_equal:waktu_datang', 'before_or_equal:now'] : ['exclude'],
        ];
    }

    private function dataKunjungan(array $data, Request $request, ?KunjunganTamu $tamu = null): array
    {
        $id = $data['pegawai_tujuan_id'] ?? null;
        $nama = $id ? Pegawai::findOrFail($id)->nama_lengkap : $data['tujuan_lain'];
        if ($tamu && $id && (int) $id === $tamu->pegawai_tujuan_id) {
            $nama = $tamu->nama_tujuan;
        }
        $datang = $this->akses->bolehKelola($request->user()) ? Carbon::parse($data['waktu_datang']) : now();
        $pulang = isset($data['waktu_pulang']) ? Carbon::parse($data['waktu_pulang']) : null;
        // Preserve seconds when the minute-level correction form has not changed the time.
        if ($tamu?->waktu_datang?->format('Y-m-d\TH:i') === $datang->format('Y-m-d\TH:i')) {
            $datang = $tamu->waktu_datang;
        }
        if ($pulang && $tamu?->waktu_pulang?->format('Y-m-d\TH:i') === $pulang->format('Y-m-d\TH:i')) {
            $pulang = $tamu->waktu_pulang;
        }
        if ($pulang && $pulang->lt($datang)) {
            throw ValidationException::withMessages(['waktu_pulang' => 'Waktu pulang tidak boleh mendahului kedatangan.']);
        }

        return collect($data)->only(['nama_tamu', 'instansi', 'alamat_instansi', 'jabatan', 'nomor_wa', 'kategori', 'keperluan', 'catatan'])->all()
            + ['pegawai_tujuan_id' => $id, 'nama_tujuan' => $nama, 'waktu_datang' => $datang, 'waktu_pulang' => $pulang, 'status' => $pulang ? 'selesai' : 'berkunjung'];
    }

    private function pegawai(?KunjunganTamu $tamu = null)
    {
        return Pegawai::where(fn ($q) => $q->where('aktif', true)->when($tamu?->pegawai_tujuan_id, fn ($q) => $q->orWhere('id', $tamu->pegawai_tujuan_id)))
            ->orderBy('nama_lengkap')->get(['id', 'nama_lengkap', 'jabatan_utama', 'aktif']);
    }

    private function filter(Request $request, bool $rekap): array
    {
        $data = $request->validate([
            'kata_kunci' => ['nullable', 'string', 'max:180'], 'kategori' => ['nullable', Rule::in(array_keys(KunjunganTamu::KATEGORI))],
            'status' => ['nullable', Rule::in(array_keys(KunjunganTamu::STATUS))],
            'periode' => ['nullable', Rule::in(['hari_ini', 'bulan_ini', 'semester', 'rentang'])],
            'tahun_pelajaran_id' => ['required_if:periode,semester', 'nullable', 'integer', Rule::exists('tahun_pelajaran', 'id')],
            'semester' => ['required_if:periode,semester', 'nullable', Rule::in(['ganjil', 'genap'])],
            'dari' => ['required_if:periode,rentang', 'nullable', 'date_format:Y-m-d'],
            'sampai' => ['required_if:periode,rentang', 'nullable', 'date_format:Y-m-d', 'after_or_equal:dari'],
        ]);
        $periode = $rekap ? ($data['periode'] ?? 'bulan_ini') : 'hari_ini';
        $dari = today();
        $sampai = today();
        $label = 'Hari ini';
        if ($periode === 'bulan_ini') {
            $dari = today()->startOfMonth();
            $sampai = today()->endOfMonth();
            $label = today()->locale('id')->translatedFormat('F Y');
        } elseif ($periode === 'rentang') {
            $dari = Carbon::parse($data['dari']);
            $sampai = Carbon::parse($data['sampai']);
            $label = $dari->format('d-m-Y').' s.d. '.$sampai->format('d-m-Y');
        } elseif ($periode === 'semester') {
            $tahun = TahunPelajaran::findOrFail($data['tahun_pelajaran_id']);
            $batas = $tahun->tanggal_mulai->copy()->startOfMonth()->addMonths(6);
            $dari = $data['semester'] === 'ganjil' ? $tahun->tanggal_mulai->copy() : $batas;
            $sampai = $data['semester'] === 'ganjil' ? $batas->copy()->subDay()->min($tahun->tanggal_selesai) : $tahun->tanggal_selesai->copy();
            if ($dari->gt($sampai)) {
                throw ValidationException::withMessages(['semester' => 'Rentang semester tidak sesuai tanggal tahun pelajaran.']);
            }
            $label = $tahun->nama.' - Semester '.ucfirst($data['semester']);
        }

        return array_replace($data, ['periode' => $periode, 'dari' => $dari->toDateString(), 'sampai' => $sampai->toDateString(), 'label_periode' => $label]);
    }

    private function terapkanFilter(Builder $query, array $filter, bool $periode): void
    {
        if ($periode) {
            $query->where('waktu_datang', '>=', Carbon::parse($filter['dari'])->startOfDay())->where('waktu_datang', '<', Carbon::parse($filter['sampai'])->addDay()->startOfDay());
        }
        foreach (['kategori', 'status'] as $field) {
            if (! empty($filter[$field])) {
                $query->where($field, $filter[$field]);
            }
        }
        if (! empty($filter['kata_kunci'])) {
            $query->where(fn ($q) => $q->whereLike('nama_tamu', '%'.$filter['kata_kunci'].'%')->orWhereLike('instansi', '%'.$filter['kata_kunci'].'%')->orWhereLike('nama_tujuan', '%'.$filter['kata_kunci'].'%'));
        }
    }

    private function ringkasan(Builder $query): array
    {
        $status = (clone $query)->selectRaw('status, COUNT(*) AS jumlah')->groupBy('status')->pluck('jumlah', 'status');

        return ['total' => (int) $status->sum(), 'berkunjung' => (int) ($status['berkunjung'] ?? 0),
            'selesai' => (int) ($status['selesai'] ?? 0), 'dibatalkan' => (int) ($status['dibatalkan'] ?? 0),
            'kategori' => (clone $query)->where('status', '!=', 'dibatalkan')->selectRaw('kategori, COUNT(*) AS jumlah')->groupBy('kategori')->orderByDesc('jumlah')->pluck('jumlah', 'kategori')];
    }

    private function queryLaporan(Request $request): array
    {
        abort_unless($this->akses->bolehRekap($request->user()), 403);
        $query = $this->akses->cakupan($request->user());
        $filter = $this->filter($request, true);
        $this->terapkanFilter($query, $filter, true);

        return [$query, $filter];
    }

    private function dataAkses(Request $request): array
    {
        return ['bolehCatat' => $this->akses->bolehMencatat($request->user()), 'bolehKelola' => $this->akses->bolehKelola($request->user()), 'bolehRekap' => $this->akses->bolehRekap($request->user())];
    }

    private function pastikanCatat(Request $request): void
    {
        abort_unless($this->akses->bolehMencatat($request->user()), 403);
    }

    private function pastikanTidakBatal(KunjunganTamu $tamu): void
    {
        if ($tamu->status === 'dibatalkan') {
            throw ValidationException::withMessages(['kunjungan' => 'Kunjungan telah dibatalkan dan tidak dapat diubah.']);
        }
    }

    private function pastikanLampiran(Request $request, KunjunganTamu $tamu, LampiranKunjunganTamu $lampiran): void
    {
        $this->akses->pastikanLihat($request->user(), $tamu);
        abort_unless($lampiran->kunjungan_tamu_id === $tamu->id && Storage::disk('local')->exists($lampiran->lokasi_file), 404);
    }

    private function catatRiwayat(KunjunganTamu $tamu, string $aksi, ?array $sebelum, ?array $sesudah, Request $request, ?string $token = null): void
    {
        $tamu->riwayat()->create(['aksi' => $aksi, 'data_sebelum' => $sebelum, 'data_sesudah' => $sesudah,
            'pengguna_id' => $request->user()->id, 'created_at' => now(), 'token_operasi' => $token]);
    }

    private function kembali(Request $request, KunjunganTamu $tamu, string $pesan)
    {
        $url = $this->akses->cakupan($request->user())->whereKey($tamu->id)->exists()
            ? route('buku-tamu.show', $tamu) : route('buku-tamu.index');
        $request->session()->flash('sukses', $pesan);

        return $request->expectsJson() ? response()->json(['redirect' => $url, 'pesan' => $pesan]) : redirect($url)->with('sukses', $pesan);
    }
}
