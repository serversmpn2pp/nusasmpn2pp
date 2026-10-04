<?php

namespace App\Services\Humas;

use App\Models\OrangTuaWali;
use App\Models\PengaduanHumas;
use App\Services\Notifikasi\NotifikasiPenggunaService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Throwable;

class PengaduanOrangTuaHumasService
{
    public function __construct(private readonly KelolaPengaduanHumasService $kelola, private readonly LampiranPengaduanHumasService $berkas, private readonly NotifikasiPenggunaService $notifikasi) {}

    public function store(Request $request)
    {
        $wali = $this->wali($request);
        $request->query->replace([]);
        $request->replace(Arr::only($request->input(), ['token_pembuatan', 'judul', 'jenis', 'kategori', 'isi', 'rahasiakan_identitas', '_token']));
        $data = $request->validate(['token_pembuatan' => ['required', 'uuid'], 'judul' => ['required', 'string', 'max:180'],
            'jenis' => ['required', Rule::in(array_keys(PengaduanHumas::JENIS))], 'kategori' => ['required', Rule::in(array_keys(PengaduanHumas::KATEGORI))],
            'isi' => ['required', 'string', 'min:10', 'max:5000'], 'rahasiakan_identitas' => ['required', 'boolean']] + $this->berkas->aturan());
        $files = $this->berkas->simpan($request, 'orang_tua');
        try {
            $tiket = DB::transaction(function () use ($request, $wali, $data, $files) {
                $tiket = PengaduanHumas::firstOrCreate(['token_pembuatan' => $data['token_pembuatan']], Arr::only($data, ['judul', 'jenis', 'kategori', 'isi']) + [
                    'kanal' => 'akun_orang_tua', 'tanggal_diterima' => today(), 'prioritas' => 'normal', 'anonim' => false,
                    'nama_pelapor' => $wali->nama_lengkap ?: $request->user()->nama, 'kontak_pelapor' => $wali->nomor_wa]);
                if ($tiket->wasRecentlyCreated) {
                    $tiket->forceFill(['pelapor_pengguna_id' => $request->user()->id, 'dibuat_oleh_pengguna_id' => $request->user()->id,
                        'rahasiakan_identitas' => $data['rahasiakan_identitas'], 'status' => 'baru', 'versi' => 0])->save();
                    $tiket->lampiran()->createMany($files);
                    $this->kelola->catat($tiket, $request->user(), 'Laporan orang tua diterima', 'Laporan masuk melalui akun orang tua NUSA.');
                    $this->notifikasi->kirimKeBanyak($this->notifikasi->penggunaDenganIzin('pengaduan_humas.kelola'), 'informasi', 'Laporan Humas baru',
                        $tiket->nomor.' telah diterima.', '/pengaduan-humas/'.$tiket->id, 'pengaduan-'.$tiket->id.'-baru');
                } else {
                    abort_unless($tiket->dariOrangTua() && $tiket->pelapor_pengguna_id === $request->user()->id, 403);
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

    public function informasi(Request $request, int $tiket)
    {
        $this->wali($request);
        $laporan = $this->milik($request)->findOrFail($tiket);
        $data = $request->validate(['token_pengiriman' => ['required', 'uuid'], 'versi' => ['required', 'integer', 'min:0'], 'isi_pesan' => ['required', 'string', 'min:5', 'max:3000'], 'lampiran' => ['prohibited']]);
        DB::transaction(function () use ($request, $laporan, $data) {
            $laporan = $this->milik($request)->lockForUpdate()->findOrFail($laporan->id);
            $this->kelola->kirimPesan($laporan, $request->user(), $data, 'orang_tua');
        });

        return $laporan->fresh();
    }

    public function wali(Request $request): OrangTuaWali
    {
        abort_unless($request->user()->aktif && $request->user()->akunOrangTua(), 403, 'Gunakan akun orang tua/wali yang aktif.');
        $wali = $request->user()->orangTuaWali;
        abort_unless($wali->siswa()->exists(), 403, 'Akun orang tua belum terhubung dengan siswa. Hubungi sekolah.');

        return $wali;
    }

    public function milik(Request $request)
    {
        return PengaduanHumas::where('pelapor_pengguna_id', $request->user()->id)->where('kanal', 'akun_orang_tua');
    }
}
