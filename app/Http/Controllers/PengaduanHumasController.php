<?php

namespace App\Http\Controllers;

use App\Models\LampiranPengaduanHumas;
use App\Models\PengaduanHumas;
use App\Services\Humas\KelolaPengaduanHumasService;
use App\Services\Humas\LampiranPengaduanHumasService;
use App\Services\Notifikasi\NotifikasiPenggunaService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class PengaduanHumasController extends Controller
{
    public function __construct(private readonly KelolaPengaduanHumasService $kelola, private readonly NotifikasiPenggunaService $notifikasi, private readonly LampiranPengaduanHumasService $berkas) {}

    public function index(Request $request)
    {
        $filter = $request->validate(['kata_kunci' => ['nullable', 'string', 'max:120'], 'status' => ['nullable', Rule::in([...array_keys(PengaduanHumas::STATUS), 'aktif', 'semua'])],
            'jenis' => ['nullable', Rule::in(array_keys(PengaduanHumas::JENIS))], 'kategori' => ['nullable', Rule::in(array_keys(PengaduanHumas::KATEGORI))],
            'prioritas' => ['nullable', Rule::in(array_keys(PengaduanHumas::PRIORITAS))], 'tugas' => ['nullable', Rule::in(['semua', 'saya'])],
            'batas' => ['nullable', Rule::in(['lewat'])], 'mulai' => ['nullable', 'date_format:Y-m-d'], 'sampai' => ['nullable', 'date_format:Y-m-d', Rule::when($request->filled('mulai'), 'after_or_equal:mulai')]]);
        $scope = PengaduanHumas::untukPengguna($request->user());
        $query = (clone $scope)->select(['id', 'judul', 'jenis', 'kategori', 'prioritas', 'status', 'tanggal_diterima', 'batas_tanggal', 'petugas_pengguna_id', 'created_at', 'updated_at'])
            ->with('petugas:id,nama');
        $status = $filter['status'] ?? 'aktif';
        if ($status === 'aktif') {
            $query->whereIn('status', PengaduanHumas::AKTIF);
        } elseif ($status !== 'semua') {
            $query->where('status', $status);
        }
        foreach (['jenis', 'kategori', 'prioritas'] as $kolom) {
            if (! empty($filter[$kolom])) {
                $query->where($kolom, $filter[$kolom]);
            }
        }
        if ($kata = $filter['kata_kunci'] ?? null) {
            $id = preg_match('/^(?:HM-\d{4}-)?(\d+)$/i', $kata, $match) ? (int) $match[1] : null;
            $query->where(fn ($q) => $q->whereLike('judul', '%'.$kata.'%')->when($id, fn ($q) => $q->orWhere('id', $id)));
        }
        if (($filter['tugas'] ?? null) === 'saya') {
            $query->where('petugas_pengguna_id', $request->user()->id);
        }
        if (($filter['batas'] ?? null) === 'lewat') {
            $query->whereIn('status', PengaduanHumas::AKTIF)->whereDate('batas_tanggal', '<', today());
        }
        $query->when($filter['mulai'] ?? null, fn ($q, $date) => $q->whereDate('tanggal_diterima', '>=', $date))->when($filter['sampai'] ?? null, fn ($q, $date) => $q->whereDate('tanggal_diterima', '<=', $date));

        return $this->halaman('index', ['daftar' => $query->latest('updated_at')->orderByDesc('id')->paginate(20)->withQueryString(), 'filter' => $filter,
            'statistik' => ['aktif' => (clone $scope)->whereIn('status', PengaduanHumas::AKTIF)->count(), 'verifikasi' => (clone $scope)->where('status', 'verifikasi')->count(),
                'terlambat' => (clone $scope)->whereIn('status', PengaduanHumas::AKTIF)->whereDate('batas_tanggal', '<', today())->count(), 'selesai' => (clone $scope)->where('status', 'selesai')->count()]]);
    }

    public function create(Request $request)
    {
        abort_unless($request->user()->aktif && ! $request->user()->akunOrangTua(), 403);

        return $this->form(new PengaduanHumas(['jenis' => 'pengaduan', 'kategori' => 'layanan', 'kanal' => 'tatap_muka', 'tanggal_diterima' => today(), 'prioritas' => 'normal', 'anonim' => false]));
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->aktif && ! $request->user()->akunOrangTua(), 403);
        $data = $this->validasi($request, false);
        $files = $this->berkas->simpan($request);
        try {
            $tiket = DB::transaction(function () use ($request, $data, $files) {
                $tiket = PengaduanHumas::firstOrCreate(['token_pembuatan' => $data['token_pembuatan']], Arr::only($data, PengaduanHumas::KOLOM));
                if ($tiket->wasRecentlyCreated) {
                    $tiket->forceFill(['dibuat_oleh_pengguna_id' => $request->user()->id, 'status' => 'baru', 'versi' => 0])->save();
                    $tiket->lampiran()->createMany($files);
                    $this->kelola->catat($tiket, $request->user(), 'Tiket dicatat', 'Laporan diterima dan dicatat oleh pengelola Humas.');
                    $this->notifikasi->kirimKeBanyak($this->notifikasi->penggunaDenganIzin('pengaduan_humas.kelola', $request->user()->id), 'informasi', 'Tiket Humas baru',
                        $tiket->nomor.' telah dicatat.', '/pengaduan-humas/'.$tiket->id, 'pengaduan-'.$tiket->id.'-baru');
                } else {
                    abort_unless($tiket->dibuat_oleh_pengguna_id === $request->user()->id, 403);
                }

                return $tiket;
            });
        } catch (Throwable $e) {
            $this->berkas->hapus($files);
            throw $e;
        }
        $tersimpan = $tiket->lampiran()->pluck('lokasi_file')->all();
        $this->berkas->hapus(array_filter($files, fn ($f) => ! in_array($f['lokasi_file'], $tersimpan, true)));

        return $this->selesai($request, $tiket, 'Tiket berhasil dicatat.');
    }

    public function show(Request $request, PengaduanHumas $tiket)
    {
        $this->kelola->akses($tiket, $request->user());
        $manager = $request->user()->memilikiIzin('pengaduan_humas.kelola');
        $tiket->load('petugas:id,nama');
        if ($manager) {
            $tiket->load('lampiran');
        }

        return $this->halaman('show', ['tiket' => $tiket, 'manager' => $manager, 'kandidat' => $manager ? $this->kelola->kandidat() : collect(),
            'bolehMenangani' => $manager || ($request->user()->akunPegawai() && $request->user()->memilikiIzin('pengaduan_humas.tangani') && $tiket->petugas_pengguna_id === $request->user()->id),
            'riwayat' => $tiket->riwayat()->with(['pengguna' => fn ($q) => $q->select('id', 'nama')->when(! $manager && $tiket->pelapor_pengguna_id, fn ($q) => $q->where('id', '<>', $tiket->pelapor_pengguna_id))])->paginate(15),
            'pesan' => $tiket->dariOrangTua() ? $tiket->pesan()->latest('id')->paginate(15, ['id', 'asal', 'isi', 'created_at'], 'halaman_pesan') : null,
            'tokenPengiriman' => (string) Str::uuid()]);
    }

    public function edit(Request $request, PengaduanHumas $tiket)
    {
        $this->kelola->akses($tiket, $request->user());
        $this->pastikanEdit($tiket);
        $this->pastikanKoreksi($tiket);

        return $this->form($tiket);
    }

    public function update(Request $request, PengaduanHumas $tiket)
    {
        $this->kelola->akses($tiket, $request->user());
        $data = $this->validasi($request, true);
        DB::transaction(function () use ($request, $tiket, $data) {
            $tiket = PengaduanHumas::lockForUpdate()->findOrFail($tiket->id);
            $this->kelola->versi($tiket, (int) $data['versi']);
            $this->pastikanEdit($tiket);
            $this->pastikanKoreksi($tiket);
            $tiket->fill(Arr::only($data, PengaduanHumas::KOLOM));
            if ($tiket->isDirty()) {
                $tiket->forceFill(['versi' => $tiket->versi + 1])->save();
                $this->kelola->catat($tiket, $request->user(), 'Data tiket dikoreksi', $data['catatan_perubahan']);
            }
        });

        return $this->selesai($request, $tiket, 'Koreksi tiket berhasil disimpan.');
    }

    public function tindakan(Request $request, PengaduanHumas $tiket, string $aksi)
    {
        $this->kelola->akses($tiket, $request->user());
        $data = $request->validate(['versi' => ['required', 'integer', 'min:0'], 'catatan' => ['required', 'string', 'min:5', 'max:3000'],
            'petugas_pengguna_id' => [$aksi === 'disposisi' ? 'required' : 'prohibited', 'integer'],
            'batas_tanggal' => [$aksi === 'disposisi' ? 'required' : 'prohibited', 'date_format:Y-m-d', 'after_or_equal:today']]);
        DB::transaction(function () use ($request, $tiket, $aksi, $data) {
            $this->kelola->tindakan(PengaduanHumas::lockForUpdate()->findOrFail($tiket->id), $request->user(), $aksi, $data);
        });

        return $this->selesai($request, $tiket, 'Penanganan tiket berhasil diperbarui.');
    }

    public function tambahLampiran(Request $request, PengaduanHumas $tiket)
    {
        $this->kelola->akses($tiket, $request->user());
        $data = $request->validate(['versi' => ['required', 'integer', 'min:0'], 'catatan_perubahan' => ['required', 'string', 'min:5', 'max:2000']]
            + $this->berkas->aturan(true));
        $files = $this->berkas->simpan($request);
        try {
            DB::transaction(function () use ($request, $tiket, $data, $files) {
                $tiket = PengaduanHumas::lockForUpdate()->findOrFail($tiket->id);
                $this->kelola->versi($tiket, (int) $data['versi']);
                $this->pastikanEdit($tiket);
                if ($tiket->lampiran()->count() + count($files) > 10) {
                    throw ValidationException::withMessages(['lampiran' => 'Maksimal 10 lampiran per tiket.']);
                }
                $tiket->lampiran()->createMany($files);
                $tiket->forceFill(['versi' => $tiket->versi + 1])->save();
                $this->kelola->catat($tiket, $request->user(), 'Lampiran privat ditambahkan', $data['catatan_perubahan']);
            });
        } catch (Throwable $e) {
            $this->berkas->hapus($files);
            throw $e;
        }

        return $this->selesai($request, $tiket, 'Lampiran privat berhasil disimpan.');
    }

    public function berkas(Request $request, PengaduanHumas $tiket, LampiranPengaduanHumas $lampiran)
    {
        $this->kelola->akses($tiket, $request->user());
        abort_unless($lampiran->pengaduan_humas_id === $tiket->id && Storage::disk('local')->exists($lampiran->lokasi_file), 404);
        $headers = ['Content-Type' => $lampiran->tipe_file, 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store, max-age=0'];

        return $request->boolean('unduh') ? Storage::disk('local')->download($lampiran->lokasi_file, $lampiran->nama_file_asli, $headers)
            : Storage::disk('local')->response($lampiran->lokasi_file, $lampiran->nama_file_asli, $headers);
    }

    public function balasan(Request $request, PengaduanHumas $tiket)
    {
        $this->kelola->akses($tiket, $request->user());
        abort_unless($tiket->dariOrangTua() && $tiket->pelapor_pengguna_id, 404);
        $data = $request->validate(['token_pengiriman' => ['required', 'uuid'], 'versi' => ['required', 'integer', 'min:0'], 'isi_pesan' => ['required', 'string', 'min:5', 'max:3000']]);
        DB::transaction(function () use ($request, $tiket, $data) {
            $this->kelola->kirimPesan(PengaduanHumas::lockForUpdate()->findOrFail($tiket->id), $request->user(), $data, 'humas');
        });

        return $this->selesai($request, $tiket, 'Balasan resmi berhasil dikirim kepada orang tua.');
    }

    private function validasi(Request $request, bool $edit): array
    {
        $data = Arr::only($request->input(), [...PengaduanHumas::KOLOM, 'token_pembuatan', 'versi', 'catatan_perubahan', '_token', '_method']);
        $request->query->replace([]);
        $request->replace($data);
        $data = $request->validate(['judul' => ['required', 'string', 'max:180'], 'jenis' => ['required', Rule::in(array_keys(PengaduanHumas::JENIS))],
            'kategori' => ['required', Rule::in(array_keys(PengaduanHumas::KATEGORI))], 'kanal' => ['required', Rule::in(array_keys(Arr::except(PengaduanHumas::KANAL, 'akun_orang_tua')))],
            'tanggal_diterima' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'], 'isi' => ['required', 'string', 'min:10', 'max:5000'],
            'anonim' => ['required', 'boolean'], 'nama_pelapor' => [Rule::requiredIf(! $request->boolean('anonim')), 'nullable', 'string', 'max:180'], 'kontak_pelapor' => ['nullable', 'string', 'max:250'],
            'prioritas' => ['required', Rule::in(array_keys(PengaduanHumas::PRIORITAS))], 'token_pembuatan' => [$edit ? 'nullable' : 'required', 'uuid'],
            'versi' => [$edit ? 'required' : 'nullable', 'integer', 'min:0'], 'catatan_perubahan' => [$edit ? 'required' : 'nullable', 'string', 'min:5', 'max:2000']]
            + ($edit ? ['lampiran' => ['prohibited']] : $this->berkas->aturan()));
        if ($data['anonim']) {
            $data['nama_pelapor'] = $data['kontak_pelapor'] = null;
        }

        return $data;
    }

    private function form(PengaduanHumas $tiket)
    {
        return $this->halaman('form', ['tiket' => $tiket, 'tokenPembuatan' => (string) Str::uuid()]);
    }

    private function halaman(string $view, array $data)
    {
        return response()->view('pengaduan-humas.'.$view, $data)->header('Cache-Control', 'private, no-store, max-age=0');
    }

    private function pastikanEdit(PengaduanHumas $tiket): void
    {
        if (! $tiket->aktif()) {
            throw ValidationException::withMessages(['status' => 'Tiket sudah selesai atau ditutup. Buka kembali dengan alasan sebelum mengoreksi data.']);
        }
    }

    private function pastikanKoreksi(PengaduanHumas $tiket): void
    {
        if ($tiket->dariOrangTua()) {
            throw ValidationException::withMessages(['tiket' => 'Laporan asli orang tua tidak dapat diubah. Gunakan balasan resmi untuk meminta klarifikasi.']);
        }
    }

    private function selesai(Request $request, PengaduanHumas $tiket, string $pesan)
    {
        $url = route('pengaduan-humas.show', $tiket);
        $request->session()->flash('berhasil', $pesan);

        return $request->expectsJson() ? response()->json(['redirect' => $url, 'pesan' => $pesan]) : redirect($url);
    }
}
