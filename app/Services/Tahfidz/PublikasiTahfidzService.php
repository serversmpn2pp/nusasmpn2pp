<?php

namespace App\Services\Tahfidz;

use App\Models\AnggotaTahfidz;
use App\Models\KelompokTahfidz;
use App\Models\KelulusanTahfidz;
use App\Models\Pengguna;
use App\Models\PublikasiRaporTahfidz;
use App\Models\Siswa;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PublikasiTahfidzService
{
    public function __construct(private readonly AksesTahfidz $akses, private readonly NotifikasiTahfidzService $notifikasi) {}

    public function rapor(Pengguna $pengguna, KelompokTahfidz $kelompok, AnggotaTahfidz $anggota, ?array $salinan): void
    {
        abort_unless($this->akses->kelola($pengguna), 403);
        DB::transaction(function () use ($pengguna, $kelompok, $anggota, $salinan) {
            Siswa::lockForUpdate()->findOrFail($anggota->siswa_id);
            $publikasi = PublikasiRaporTahfidz::firstOrNew(['siswa_id' => $anggota->siswa_id,
                'tahun_pelajaran_id' => $kelompok->tahun_pelajaran_id]);
            if ($salinan === null) {
                if ($publikasi->dipublikasikan_pada) {
                    $publikasi->update(['dipublikasikan_pada' => null, 'dipublikasikan_oleh' => null]);
                }

                return;
            }
            if ($publikasi->dipublikasikan_pada && $publikasi->salinan === $salinan) {
                return;
            }

            $publikasi->fill(['salinan' => $salinan, 'versi' => $publikasi->versi + 1,
                'dipublikasikan_pada' => now(), 'dipublikasikan_oleh' => $pengguna->id])->save();
            $this->notifikasi->dokumen($anggota->siswa_id, 'Rapor Tahfidz tersedia',
                'Rapor Tahfidz '.$salinan['nama'].' telah dibagikan oleh sekolah.',
                'tahfidz-rapor:'.$publikasi->id.':'.$publikasi->versi,
                'Rapor Tahfidz dibagikan', 'Rapor Tahfidz Anda telah dibagikan oleh sekolah kepada orang tua/wali. Buka Tahfidz Saya untuk melihat perkembangan setoran.');
        });
    }

    public function sertifikat(Pengguna $pengguna, int $kelulusanId, bool $bagikan): void
    {
        abort_unless($this->akses->kelola($pengguna), 403);
        DB::transaction(function () use ($pengguna, $kelulusanId, $bagikan) {
            $lulus = KelulusanTahfidz::lockForUpdate()->findOrFail($kelulusanId);
            if (! $lulus->diterbitkan_pada || ! $lulus->wisuda_tahfidz_id) {
                throw ValidationException::withMessages(['sertifikat' => 'Terbitkan sertifikat sebelum membagikannya.']);
            }
            if ($bagikan === (bool) $lulus->dibagikan_pada) {
                return;
            }
            if (! $bagikan) {
                $lulus->update(['dibagikan_pada' => null, 'dibagikan_oleh' => null]);

                return;
            }

            $lulus->update(['dibagikan_pada' => now(), 'dibagikan_oleh' => $pengguna->id, 'versi_publikasi' => $lulus->versi_publikasi + 1]);
            $this->notifikasi->dokumen($lulus->siswa_id, 'Sertifikat Tahfidz tersedia',
                'Sertifikat kelulusan Tahfidz '.$lulus->nama_penerima.' telah dibagikan oleh sekolah.',
                'tahfidz-sertifikat:'.$lulus->id.':'.$lulus->versi_publikasi,
                'Sertifikat Tahfidz dibagikan', 'Sertifikat kelulusan Tahfidz Anda telah dibagikan oleh sekolah kepada orang tua/wali. Buka Tahfidz Saya untuk melihat perkembangan setoran.');
        });
    }
}
