<?php

namespace App\Services\Persuratan;

use App\Models\Pengguna;
use App\Models\TindakLanjutSuratTu;
use App\Services\Notifikasi\NotifikasiPenggunaService;

class IngatkanTindakLanjutSuratService
{
    public function __construct(private NotifikasiPenggunaService $notifikasi, private TindakLanjutSuratService $service) {}

    public function kirimPengingat(): int
    {
        $jumlah = 0;
        TindakLanjutSuratTu::with('surat')->whereNotIn('status', ['selesai', 'dibatalkan'])
            ->whereDate('batas_tanggal', '<=', today()->addDays(2))
            ->whereHas('surat', fn ($q) => $q->where('status', '<>', 'dibatalkan'))
            ->chunkById(100, function ($tugas) use (&$jumlah) {
                foreach ($tugas as $t) {
                    $lewat = $t->batas_tanggal->lt(today());
                    $menunggu = $t->status === 'menunggu_pemeriksaan';
                    if ($menunggu) {
                        $akun = Pengguna::find($t->pemeriksa_pengguna_id);
                        $penerima = $akun && $akun->aktif && ! $akun->akunSiswa() && ! $akun->akunOrangTua()
                            && ($akun->administrator() || ($akun->akunPegawai() && $akun->pegawai?->aktif))
                            && $this->service->dapatMemeriksa($akun, $t) ? collect([$akun]) : collect();
                    } else {
                        $penerima = $this->notifikasi->penggunaUntukDaftarPegawai([$t->pegawai_id])
                            ->filter(fn ($akun) => ! $akun->akunSiswa() && ! $akun->akunOrangTua() && $akun->akunPegawai() && $akun->pegawai?->aktif);
                    }
                    $hasil = $this->notifikasi->kirimKeBanyak($penerima, $lewat ? 'peringatan' : 'informasi',
                        $menunggu ? 'Pengingat pemeriksaan laporan surat' : ($lewat ? 'Tindak lanjut surat melewati tenggat' : 'Pengingat tenggat tindak lanjut surat'),
                        $menunggu ? 'Ada laporan surat yang masih menunggu pemeriksaan Anda.' : 'Periksa tindak lanjut surat Anda dan tenggatnya di NUSA.',
                        route('tindak-lanjut-surat.show', $t),
                        'tindak-surat:'.$t->id.':batas:'.$t->batas_tanggal->toDateString().':'.($menunggu ? 'periksa:'.$t->laporan()->max('nomor') : 'penerima').':'.($lewat ? 'lewat' : 'dekat'));
                    $jumlah += $hasil->where('wasRecentlyCreated', true)->count();
                }
            });

        return $jumlah;
    }
}
