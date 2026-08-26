<?php

namespace App\Services\Cbt;

use App\Models\JadwalUjianCbt;
use App\Models\Pegawai;
use App\Models\RuangKegiatanUjianCbt;
use App\Models\RuangUjianCbt;
use App\Services\Notifikasi\NotifikasiPenggunaService;

class NotifikasiUjianTerpusatService
{
    public function __construct(
        private NotifikasiPenggunaService $notifikasi,
    ) {}

    public function kirimTugasPengawas(
        JadwalUjianCbt $jadwal,
        RuangKegiatanUjianCbt $ruang,
        int $pegawaiId,
        string $peran,
    ): void {
        $jadwal->loadMissing(['kegiatanUjianCbt', 'mataPelajaran']);
        $kegiatan = $jadwal->kegiatanUjianCbt;
        $labelPeran = $peran === 'utama' ? 'pengawas utama' : 'pengawas pendamping';

        $this->notifikasi->kirimKeBanyak(
            $this->notifikasi->penggunaUntukPegawai($pegawaiId),
            'penting',
            'Tugas pengawas ujian baru',
            sprintf(
                'Anda ditugaskan sebagai %s untuk %s, %s, tingkat %s di %s pada %s pukul %s.',
                $labelPeran,
                $kegiatan?->nama ?? 'Ujian Terpusat',
                $jadwal->mataPelajaran?->nama ?? 'mata pelajaran belum ditentukan',
                $jadwal->tingkat,
                $ruang->nama,
                $jadwal->tanggal?->format('d-m-Y') ?? 'tanggal belum ditentukan',
                $jadwal->labelWaktu(),
            ),
            route('tugas-pengawas-ujian.index'),
            null,
            [
                'kegiatan_ujian_cbt_id' => $jadwal->kegiatan_ujian_cbt_id,
                'jadwal_ujian_cbt_id' => $jadwal->id,
                'ruang_kegiatan_ujian_cbt_id' => $ruang->id,
                'peran_pengawas' => $peran,
            ],
        );
    }

    public function kirimPenggantianPengawas(
        JadwalUjianCbt $jadwal,
        RuangKegiatanUjianCbt $ruang,
        Pegawai $pengawasLama,
        Pegawai $pengawasBaru,
        string $peran,
        string $alasan,
    ): void {
        $jadwal->loadMissing(['kegiatanUjianCbt', 'mataPelajaran']);
        $kegiatan = $jadwal->kegiatanUjianCbt;
        $labelPeran = $peran === 'utama' ? 'pengawas utama' : 'pengawas pendamping';
        $ringkasanAlasan = str($alasan)->squish()->limit(180);

        $this->notifikasi->kirimKeBanyak(
            $this->notifikasi->penggunaUntukPegawai($pengawasBaru->id),
            'penting',
            'Tugas sebagai pengawas pengganti',
            sprintf(
                'Anda ditugaskan menggantikan %s sebagai %s untuk %s, %s di %s pada %s pukul %s. Alasan: %s',
                $pengawasLama->nama_lengkap,
                $labelPeran,
                $kegiatan?->nama ?? 'Ujian Terpusat',
                $jadwal->mataPelajaran?->nama ?? 'mata pelajaran belum ditentukan',
                $ruang->nama,
                $jadwal->tanggal?->format('d-m-Y') ?? 'tanggal belum ditentukan',
                $jadwal->labelWaktu(),
                $ringkasanAlasan,
            ),
            route('tugas-pengawas-ujian.index'),
            null,
            [
                'kegiatan_ujian_cbt_id' => $jadwal->kegiatan_ujian_cbt_id,
                'jadwal_ujian_cbt_id' => $jadwal->id,
                'ruang_kegiatan_ujian_cbt_id' => $ruang->id,
                'peran_pengawas' => $peran,
                'jenis_penugasan' => 'penggantian_mendadak',
            ],
        );

        $this->notifikasi->kirimKeBanyak(
            $this->notifikasi->penggunaUntukPegawai($pengawasLama->id),
            'informasi',
            'Tugas pengawas telah dialihkan',
            sprintf(
                'Tugas Anda sebagai %s untuk %s, %s di %s telah dialihkan kepada %s. Alasan: %s',
                $labelPeran,
                $kegiatan?->nama ?? 'Ujian Terpusat',
                $jadwal->mataPelajaran?->nama ?? 'mata pelajaran belum ditentukan',
                $ruang->nama,
                $pengawasBaru->nama_lengkap,
                $ringkasanAlasan,
            ),
            route('tugas-pengawas-ujian.index'),
            null,
            [
                'kegiatan_ujian_cbt_id' => $jadwal->kegiatan_ujian_cbt_id,
                'jadwal_ujian_cbt_id' => $jadwal->id,
                'ruang_kegiatan_ujian_cbt_id' => $ruang->id,
                'peran_pengawas' => $peran,
                'jenis_penugasan' => 'tugas_dialihkan',
            ],
        );
    }

    public function kirimBuktiKepadaPanitia(RuangUjianCbt $ruang, ?int $pengirimId = null): void
    {
        $ruang->loadMissing([
            'jadwalUjianCbt.kegiatanUjianCbt',
            'jadwalUjianCbt.mataPelajaran',
            'ruangKegiatanUjianCbt',
        ]);
        $jadwal = $ruang->jadwalUjianCbt;
        $kegiatan = $jadwal?->kegiatanUjianCbt;

        if (! $kegiatan) {
            return;
        }

        $pegawaiPanitiaIds = $kegiatan->panitiaUjianCbt()
            ->where('aktif', true)
            ->pluck('pegawai_id');

        $this->notifikasi->kirimKeBanyak(
            $this->notifikasi->penggunaUntukDaftarPegawai($pegawaiPanitiaIds, $pengirimId),
            'penting',
            'Bukti ruang menunggu pemeriksaan',
            sprintf(
                'Pengawas %s telah mengirim daftar hadir dan berita acara untuk %s, %s, tingkat %s. Silakan periksa bukti tersebut.',
                $ruang->ruangKegiatanUjianCbt?->nama ?? $ruang->nama,
                $kegiatan->nama,
                $jadwal?->mataPelajaran?->nama ?? 'mata pelajaran belum ditentukan',
                $jadwal?->tingkat ?? '-',
            ),
            route('tugas-pengawas-ujian.show', ['ruangUjianCbt' => $ruang, 'kembali' => 'panitia']),
            null,
            [
                'kegiatan_ujian_cbt_id' => $kegiatan->id,
                'jadwal_ujian_cbt_id' => $jadwal?->id,
                'ruang_ujian_cbt_id' => $ruang->id,
            ],
        );
    }

    public function kirimPermintaanFotoUlang(RuangUjianCbt $ruang, string $catatan, ?int $pemeriksaId = null): void
    {
        $ruang->loadMissing([
            'jadwalUjianCbt.kegiatanUjianCbt',
            'jadwalUjianCbt.mataPelajaran',
            'ruangKegiatanUjianCbt',
        ]);
        $jadwal = $ruang->jadwalUjianCbt;
        $kegiatan = $jadwal?->kegiatanUjianCbt;
        $pegawaiPengawasIds = collect([
            $ruang->pengawas_utama_pegawai_id,
            $ruang->pengawas_pendamping_pegawai_id,
        ]);

        $this->notifikasi->kirimKeBanyak(
            $this->notifikasi->penggunaUntukDaftarPegawai($pegawaiPengawasIds, $pemeriksaId),
            'peringatan',
            'Bukti ujian perlu difoto ulang',
            sprintf(
                'Bukti %s untuk %s, %s perlu diperbaiki. Catatan panitia: %s',
                $ruang->ruangKegiatanUjianCbt?->nama ?? $ruang->nama,
                $kegiatan?->nama ?? 'Ujian Terpusat',
                $jadwal?->mataPelajaran?->nama ?? 'mata pelajaran belum ditentukan',
                $catatan,
            ),
            route('tugas-pengawas-ujian.show', $ruang),
            null,
            [
                'kegiatan_ujian_cbt_id' => $kegiatan?->id,
                'jadwal_ujian_cbt_id' => $jadwal?->id,
                'ruang_ujian_cbt_id' => $ruang->id,
            ],
        );
    }
}
