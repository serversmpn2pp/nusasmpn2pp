<?php

namespace App\Http\Controllers;

use App\Models\MediaResmiHumas;
use App\Support\TautanPublikHumas;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MediaResmiHumasController extends Controller
{
    public function index(Request $request)
    {
        $filter = $request->validate(['kata_kunci' => ['nullable', 'string', 'max:120'], 'jenis' => ['nullable', Rule::in(array_keys(MediaResmiHumas::JENIS))],
            'status' => ['nullable', Rule::in([...array_keys(MediaResmiHumas::STATUS), 'semua'])], 'penanggung_jawab' => ['nullable', 'string', 'max:180']]);
        $status = $filter['status'] ?? 'aktif';
        $query = MediaResmiHumas::when($status !== 'semua', fn ($q) => $q->where('status', $status))
            ->when($filter['jenis'] ?? null, fn ($q, $jenis) => $q->where('jenis', $jenis))
            ->when($filter['penanggung_jawab'] ?? null, fn ($q, $nama) => $q->where('penanggung_jawab', $nama))
            ->when($filter['kata_kunci'] ?? null, fn ($q, $kata) => $q->where(fn ($q) => $q->whereLike('nama', '%'.$kata.'%')->orWhereLike('tautan', '%'.$kata.'%')
                ->orWhereLike('identitas_akun', '%'.$kata.'%')->orWhereLike('penanggung_jawab', '%'.$kata.'%')));
        $statistik = MediaResmiHumas::selectRaw('status, COUNT(*) AS jumlah')->groupBy('status')->pluck('jumlah', 'status');
        $statistik['penanggung_jawab'] = MediaResmiHumas::where('status', 'aktif')->distinct()->count('penanggung_jawab');

        return view('media-resmi-humas.index', ['daftar' => $query->orderBy('jenis')->orderBy('nama')->orderBy('id')->paginate(20)->withQueryString(),
            'filter' => $filter, 'statistik' => $statistik,
            'penanggungJawab' => MediaResmiHumas::distinct()->orderBy('penanggung_jawab')->pluck('penanggung_jawab')]);
    }

    public function create()
    {
        return $this->form(new MediaResmiHumas(['jenis' => 'website', 'status' => 'aktif']));
    }

    public function store(Request $request)
    {
        $data = $this->validasi($request, false);
        try {
            $media = DB::transaction(function () use ($request, $data) {
                $media = MediaResmiHumas::firstOrCreate(['token_pembuatan' => $data['token_pembuatan']], collect($data)->only(MediaResmiHumas::KOLOM)->all() + ['tautan_hash' => TautanPublikHumas::hash($data['tautan'])]);
                if ($media->wasRecentlyCreated) {
                    $media->forceFill(['tautan_hash' => TautanPublikHumas::hash($media->tautan), 'dibuat_oleh_pengguna_id' => $request->user()->id, 'diubah_oleh_pengguna_id' => $request->user()->id])->save();
                    $media->refresh();
                    $this->catat($media, $request, 'Media resmi ditambahkan');
                } else {
                    abort_unless($media->dibuat_oleh_pengguna_id === $request->user()->id, 403);
                }

                return $media;
            });
        } catch (UniqueConstraintViolationException $e) {
            throw ValidationException::withMessages(['tautan' => 'Alamat media ini sudah terdaftar. Gunakan atau perbarui data yang sudah ada.']);
        }

        return $this->selesai($request, $media, 'Media resmi berhasil ditambahkan.');
    }

    public function show(MediaResmiHumas $media)
    {
        return view('media-resmi-humas.show', ['media' => $media, 'riwayat' => $media->riwayat()->with('pengguna:id,nama')->paginate(15)]);
    }

    public function edit(MediaResmiHumas $media)
    {
        return $this->form($media);
    }

    public function update(Request $request, MediaResmiHumas $media)
    {
        $data = $this->validasi($request, true);
        try {
            DB::transaction(function () use ($request, $data, $media) {
                $media = MediaResmiHumas::lockForUpdate()->findOrFail($media->id);
                if ($media->versi !== (int) $data['versi']) {
                    throw ValidationException::withMessages(['versi' => 'Data media telah berubah. Muat ulang dan periksa data terbaru sebelum menyimpan.']);
                }
                $media->fill(collect($data)->only(MediaResmiHumas::KOLOM)->all());
                $media->forceFill(['tautan_hash' => TautanPublikHumas::hash($media->tautan)]);
                if ($media->isDirty()) {
                    $media->forceFill(['versi' => $media->versi + 1, 'diubah_oleh_pengguna_id' => $request->user()->id])->save();
                    $this->catat($media, $request, 'Media resmi diperbarui', $data['catatan_perubahan']);
                }
            });
        } catch (UniqueConstraintViolationException $e) {
            throw ValidationException::withMessages(['tautan' => 'Alamat media ini sudah terdaftar pada media lain.']);
        }

        return $this->selesai($request, $media, 'Perubahan media resmi berhasil disimpan.');
    }

    private function validasi(Request $request, bool $edit): array
    {
        // Only public registry fields may be flashed back or persisted.
        $rahasia = array_intersect(array_map('strtolower', array_keys($request->all())), TautanPublikHumas::KREDENSIAL);
        $publik = $request->only([...MediaResmiHumas::KOLOM, 'token_pembuatan', 'versi', 'catatan_perubahan', '_token', '_method']);
        $request->query->replace([]);
        $request->replace($publik);
        $tautanRahasia = is_string($publik['tautan'] ?? null) && TautanPublikHumas::berkredensial($publik['tautan']);
        if ($tautanRahasia) {
            $request->merge(['tautan' => null]);
        }
        if ($rahasia) {
            throw ValidationException::withMessages(['kredensial' => 'Kata sandi dan token akses tidak boleh dikirim ke daftar media resmi.']);
        }
        if ($tautanRahasia) {
            throw ValidationException::withMessages(['tautan' => 'Gunakan alamat publik media, tanpa kata sandi atau token dalam tautan.']);
        }

        return $request->validate(['nama' => ['required', 'string', 'max:180'], 'jenis' => ['required', Rule::in(array_keys(MediaResmiHumas::JENIS))],
            'tautan' => ['bail', 'required', 'string', 'url:http,https', 'max:2000'],
            'identitas_akun' => ['nullable', 'string', 'max:180'], 'penanggung_jawab' => ['required', 'string', 'max:180'], 'jabatan_penanggung_jawab' => ['nullable', 'string', 'max:100'],
            'status' => ['required', Rule::in(array_keys(MediaResmiHumas::STATUS))], 'tanggal_diperiksa' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'], 'catatan' => ['nullable', 'string', 'max:2000'],
            'token_pembuatan' => [$edit ? 'nullable' : 'required', 'uuid'], 'versi' => [$edit ? 'required' : 'nullable', 'integer', 'min:0'],
            'catatan_perubahan' => [$edit ? 'required' : 'nullable', 'string', 'min:5', 'max:2000']]);
    }

    private function catat(MediaResmiHumas $media, Request $request, string $aksi, ?string $catatan = null): void
    {
        $media->riwayat()->create(['versi' => $media->versi, 'aksi' => $aksi, 'snapshot' => $media->snapshot(), 'catatan_perubahan' => $catatan,
            'pengguna_id' => $request->user()->id, 'created_at' => now()]);
    }

    private function form(MediaResmiHumas $media)
    {
        return view('media-resmi-humas.form', ['media' => $media, 'tokenPembuatan' => (string) Str::uuid()]);
    }

    private function selesai(Request $request, MediaResmiHumas $media, string $pesan)
    {
        $request->session()->flash('berhasil', $pesan);
        $url = route('media-resmi-humas.show', $media);

        return $request->expectsJson() ? response()->json(['redirect' => $url, 'pesan' => $pesan]) : redirect($url);
    }
}
