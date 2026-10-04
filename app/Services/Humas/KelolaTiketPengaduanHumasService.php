<?php

namespace App\Services\Humas;

use App\Models\PengaduanHumas;
use App\Services\Notifikasi\NotifikasiPenggunaService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class KelolaTiketPengaduanHumasService
{
    public function __construct(private readonly KelolaPengaduanHumasService $kelola, private readonly NotifikasiPenggunaService $notifikasi, private readonly LampiranPengaduanHumasService $berkas) {}

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

        return $tiket->refresh();
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

        return $tiket->fresh();
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

        return $tiket->fresh();
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

        return $tiket->fresh();
    }

    public function balasan(Request $request, PengaduanHumas $tiket)
    {
        $this->kelola->akses($tiket, $request->user());
        abort_unless($tiket->dariOrangTua() && $tiket->pelapor_pengguna_id, 404);
        $data = $request->validate(['token_pengiriman' => ['required', 'uuid'], 'versi' => ['required', 'integer', 'min:0'], 'isi_pesan' => ['required', 'string', 'min:5', 'max:3000']]);
        DB::transaction(function () use ($request, $tiket, $data) {
            $this->kelola->kirimPesan(PengaduanHumas::lockForUpdate()->findOrFail($tiket->id), $request->user(), $data, 'humas');
        });

        return $tiket->fresh();
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
}
