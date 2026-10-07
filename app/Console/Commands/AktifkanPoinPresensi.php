<?php

namespace App\Console\Commands;

use App\Models\PengaturanPoinKeterlambatan;
use App\Models\TahunPelajaran;
use App\Services\Pembinaan\PengaturanPoinKeterlambatanService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AktifkanPoinPresensi extends Command
{
    protected $signature = 'pembinaan:aktifkan-poin-presensi {--tahun= : ID tahun pelajaran aktif} {--terlambat=15} {--alfa=25}';

    protected $description = 'Mengaktifkan poin presensi langsung mulai hari ini, tanpa memproses kejadian sebelumnya';

    public function handle(PengaturanPoinKeterlambatanService $service): int
    {
        foreach (['terlambat', 'alfa'] as $opsi) {
            if (! ctype_digit((string) $this->option($opsi)) || (int) $this->option($opsi) < 1 || (int) $this->option($opsi) > 500) {
                $this->components->error('Poin '.$opsi.' harus bilangan bulat 1-500.');

                return self::FAILURE;
            }
        }
        $tahun = TahunPelajaran::where('aktif', true)
            ->when($this->option('tahun'), fn ($q) => $q->whereKey($this->option('tahun')))->latest('tanggal_mulai')->first();
        if (! $tahun || ($tahun->tanggal_mulai && $tahun->tanggal_mulai->isFuture())
            || ($tahun->tanggal_selesai && $tahun->tanggal_selesai->lt(now()->startOfDay()))) {
            $this->components->error('Tidak ada tahun pelajaran aktif yang sedang berjalan.');

            return self::FAILURE;
        }
        $pengaturan = DB::transaction(function () use ($tahun, $service) {
            TahunPelajaran::lockForUpdate()->findOrFail($tahun->id);
            $pengaturan = PengaturanPoinKeterlambatan::firstOrCreate(['tahun_pelajaran_id' => $tahun->id], ['aktif' => true]);
            $pengaturan->update(['aktif' => true]);
            $service->simpanModeLangsung($pengaturan, ['otomatis_langsung' => true,
                'poin_terlambat' => (int) $this->option('terlambat'), 'poin_alfa' => (int) $this->option('alfa')]);
            if (! $pengaturan->rentangPoinKeterlambatan()->exists()) {
                $pengaturan->rentangPoinKeterlambatan()->create(['menit_mulai' => 1, 'menit_selesai' => null, 'poin' => $pengaturan->poin_terlambat, 'urutan' => 1]);
            }

            return $pengaturan->fresh();
        });
        $this->components->info('Aktif: '.$tahun->nama.', mulai '.$pengaturan->berlaku_mulai->format('d/m/Y')
            .'. Terlambat '.$pengaturan->poin_terlambat.' poin; alfa '.$pengaturan->poin_alfa.' poin.');

        return self::SUCCESS;
    }
}
