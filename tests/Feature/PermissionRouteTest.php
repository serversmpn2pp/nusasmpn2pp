<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PermissionRouteTest extends TestCase
{
    public function test_route_siswa_memisahkan_izin_lihat_dan_kelola(): void
    {
        $this->assertRouteMemakaiMiddleware('siswa.index', 'izin:siswa.lihat,siswa.kelola');
        $this->assertRouteMemakaiMiddleware('siswa.show', 'izin:siswa.lihat,siswa.kelola');
        $this->assertRouteMemakaiMiddleware('siswa.create', 'izin:siswa.kelola');
        $this->assertRouteMemakaiMiddleware('siswa.store', 'izin:siswa.kelola');
        $this->assertRouteMemakaiMiddleware('siswa.edit', 'izin:siswa.kelola');
        $this->assertRouteMemakaiMiddleware('siswa.update', 'izin:siswa.kelola');
        $this->assertRouteMemakaiMiddleware('siswa.foto.update', 'izin:siswa.kelola');
        $this->assertRouteMemakaiMiddleware('siswa.destroy', 'izin:siswa.kelola');
    }

    public function test_route_role_dan_akun_memakai_permission_sistem(): void
    {
        $this->assertRouteMemakaiMiddleware('peran.index', 'izin:peran.lihat,peran.kelola');
        $this->assertRouteMemakaiMiddleware('peran.create', 'izin:peran.kelola');
        $this->assertRouteMemakaiMiddleware('aktivitas-login.index', 'izin:aktivitas_login.lihat');
        $this->assertRouteMemakaiMiddleware('cadangan-database.index', 'izin:cadangan_database.kelola');
        $this->assertRouteMemakaiMiddleware('cadangan-database.store', 'izin:cadangan_database.kelola');
        $this->assertRouteMemakaiMiddleware('cadangan-database.restore', 'izin:cadangan_database.kelola');
        $this->assertRouteMemakaiMiddleware('cadangan-database.restore-upload', 'izin:cadangan_database.kelola');
        $this->assertRouteMemakaiMiddleware('cadangan-database.download', 'izin:cadangan_database.kelola');
        $this->assertRouteMemakaiMiddleware('cadangan-database.destroy', 'izin:cadangan_database.kelola');
        $this->assertRouteMemakaiMiddleware('akun-pegawai.index', 'izin:akun.lihat,akun.kelola');
        $this->assertRouteMemakaiMiddleware('akun-pegawai.peran.update', 'izin:akun.kelola');
        $this->assertRouteMemakaiMiddleware('akun-siswa.index', 'izin:akun_siswa.lihat,akun_siswa.kelola,akun_siswa.cetak');
        $this->assertRouteMemakaiMiddleware('akun-siswa.cetak', 'izin:akun_siswa.cetak,akun_siswa.kelola');
        $this->assertRouteMemakaiMiddleware('akun-siswa.store', 'izin:akun_siswa.kelola');
        $this->assertRouteMemakaiMiddleware('akun-orang-tua.index', 'izin:akun_orang_tua.lihat,akun_orang_tua.kelola,akun_orang_tua.cetak');
        $this->assertRouteMemakaiMiddleware('akun-orang-tua.cetak', 'izin:akun_orang_tua.cetak,akun_orang_tua.kelola');
        $this->assertRouteMemakaiMiddleware('akun-orang-tua.store', 'izin:akun_orang_tua.kelola');
        $this->assertRouteMemakaiMiddleware('profil-pegawai.edit', 'izin:pegawai.profil');
        $this->assertRouteMemakaiMiddleware('profil-pegawai.update', 'izin:pegawai.profil');
        $this->assertRouteMemakaiMiddleware('profil-pegawai.foto.update', 'izin:pegawai.profil');
        $this->assertRouteMemakaiMiddleware('pegawai.foto.update', 'izin:pegawai.kelola');
        $this->assertRouteMemakaiMiddleware('foto-identitas.index', 'izin:siswa.kelola,pegawai.kelola');
    }

    public function test_route_absensi_dan_bk_memakai_permission_modul(): void
    {
        $this->assertRouteMemakaiMiddleware('scan-absensi.index', 'izin:absensi.scan');
        $this->assertRouteMemakaiMiddleware('rekap-absensi-harian.index', 'izin:absensi.lihat,absensi.koreksi,absensi.koreksi_hari_ini,absensi.laporan');
        $this->assertRouteMemakaiMiddleware('rekap-absensi-harian.koreksi.edit', 'izin:absensi.koreksi,absensi.koreksi_hari_ini');
        $this->assertRouteMemakaiMiddleware('rekap-absensi-pegawai-harian.index', 'izin:absensi.lihat,absensi.koreksi,absensi.laporan,absensi_pegawai.pribadi');
        $this->assertRouteMemakaiMiddleware('absensi-pegawai-saya.rekap', 'izin:absensi_pegawai.pribadi');
        $this->assertRouteMemakaiMiddleware('laporan-absensi.index', 'izin:absensi.laporan');
        $this->assertRouteMemakaiMiddleware('notifikasi-absensi-siswa.index', 'izin:absensi.laporan');
        $this->assertRouteMemakaiMiddleware('jadwal-piket-guru.index', 'izin:piket_guru.kelola');
        $this->assertRouteMemakaiMiddleware('jadwal-piket-saya.index', 'izin:piket_guru.lihat_pribadi');
        $this->assertRouteMemakaiMiddleware('piket-kehadiran-siswa.index', 'izin:piket_guru.catat_kehadiran');
        $this->assertRouteMemakaiMiddleware('piket-kehadiran-siswa.update', 'izin:piket_guru.catat_kehadiran');
        $this->assertRouteMemakaiMiddleware('kegiatan-ibadah.index', 'izin:ibadah.pengaturan_kelola');
        $this->assertRouteMemakaiMiddleware('kegiatan-ibadah.store', 'izin:ibadah.pengaturan_kelola');
        $this->assertRouteMemakaiMiddleware('jadwal-kegiatan-ibadah.index', 'izin:ibadah.pengaturan_kelola');
        $this->assertRouteMemakaiMiddleware('jadwal-kegiatan-ibadah.store', 'izin:ibadah.pengaturan_kelola');
        $this->assertRouteMemakaiMiddleware('pengaturan-berhalangan-ibadah.index', 'izin:ibadah.pengaturan_kelola');
        $this->assertRouteMemakaiMiddleware('pengaturan-berhalangan-ibadah.update', 'izin:ibadah.pengaturan_kelola');
        $this->assertRouteMemakaiMiddleware('pengaturan-berhalangan-ibadah.pendamping.store', 'izin:ibadah.pengaturan_kelola');
        $this->assertRouteMemakaiMiddleware('pengaturan-berhalangan-ibadah.pendamping.destroy', 'izin:ibadah.pengaturan_kelola');
        $this->assertRouteMemakaiMiddleware('scan-kegiatan-ibadah.index', 'izin:ibadah.scan');
        $this->assertRouteMemakaiMiddleware('scan-kegiatan-ibadah.store', 'izin:ibadah.scan');
        $this->assertRouteMemakaiMiddleware('rekap-kegiatan-ibadah.index', 'izin:ibadah.rekap');
        $this->assertRouteMemakaiMiddleware('rekap-kegiatan-ibadah.bulanan', 'izin:ibadah.rekap');
        $this->assertRouteMemakaiMiddleware('rekap-kegiatan-ibadah.koreksi.edit', 'izin:ibadah.koreksi');
        $this->assertRouteMemakaiMiddleware('rekap-kegiatan-ibadah.koreksi.update', 'izin:ibadah.koreksi');
        $this->assertRouteMemakaiMiddleware('laporan-absensi.export', 'izin:laporan.export');
        $this->assertRouteMemakaiMiddleware('laporan-absensi-pegawai-bulanan.index', 'izin:absensi.laporan,absensi_pegawai.pribadi');
        $this->assertRouteMemakaiMiddleware('absensi-pegawai-saya.laporan', 'izin:absensi_pegawai.pribadi');
        $this->assertRouteMemakaiMiddleware('absensi-pegawai-saya.cetak', 'izin:absensi_pegawai.pribadi');
        $this->assertRouteMemakaiMiddleware('laporan-absensi-pegawai-bulanan.cetak-pegawai', 'izin:laporan.export,absensi_pegawai.pribadi');
        $this->assertRouteMemakaiMiddleware('laporan-pembinaan-siswa.index', 'izin:bk.lihat,bk.kelola,poin_siswa.lapor,poin_siswa.lihat,poin_siswa.sahkan_wakil');
        $this->assertRouteMemakaiMiddleware('laporan-pembinaan-siswa.show', 'izin:bk.lihat,bk.kelola,poin_siswa.lapor,poin_siswa.lihat,poin_siswa.sahkan_wakil');
        $this->assertRouteMemakaiMiddleware('laporan-pembinaan-siswa.create', 'izin:bk.kelola,poin_siswa.lapor');
        $this->assertRouteMemakaiMiddleware('laporan-saya.index', 'izin:poin_siswa.lapor');
        $this->assertRouteMemakaiMiddleware('laporan-saya.show', 'izin:poin_siswa.lapor');
        $this->assertRouteMemakaiMiddleware('verifikasi-pelanggaran.bk', 'izin:poin_siswa.verifikasi_bk');
        $this->assertRouteMemakaiMiddleware('verifikasi-pelanggaran.wakil', 'izin:poin_siswa.sahkan_wakil');
        $this->assertNull(Route::getRoutes()->getByName('verifikasi-pelanggaran.persetujuan'));
        $this->assertRouteMemakaiMiddleware('rekap-poin-siswa.index', 'izin:poin_siswa.lihat');
        $this->assertRouteMemakaiMiddleware('pembinaan-siswa-wali.index', 'izin:guru_wali.lihat,poin_siswa.lihat');
        $this->assertRouteMemakaiMiddleware('pendampingan-siswa-wali.index', 'izin:guru_wali.lihat,poin_siswa.lihat');
        $this->assertRouteMemakaiMiddleware('rekap-poin-siswa-wali.index', 'izin:guru_wali.lihat,poin_siswa.lihat');
        $this->assertRouteMemakaiMiddleware('penugasan-guru-wali.index', 'izin:guru_wali.kelola');
        $this->assertRouteMemakaiMiddleware('siswa-wali-saya.index', 'izin:guru_wali.lihat');
        $this->assertRouteMemakaiMiddleware('siswa-wali-saya.show', 'izin:guru_wali.lihat');
    }

    public function test_route_penempatan_siswa_memakai_permission_kelas(): void
    {
        $this->assertRouteMemakaiMiddleware('kelas-wali.index', 'izin:kelas.lihat');
        $this->assertRouteMemakaiMiddleware('penempatan-siswa.index', 'izin:kelas.lihat,kelas.kelola');
        $this->assertRouteMemakaiMiddleware('penempatan-siswa.store-massal', 'izin:kelas.kelola');
        $this->assertRouteMemakaiMiddleware('anggota-kelas.update', 'izin:kelas.kelola');
        $this->assertRouteMemakaiMiddleware('anggota-kelas.destroy', 'izin:kelas.kelola');
    }

    public function test_route_jadwal_pelajaran_memakai_permission_jadwal(): void
    {
        $this->assertRouteMemakaiMiddleware('guru-mata-pelajaran.ganti-guru', 'izin:guru_mapel.kelola');
        $this->assertRouteMemakaiMiddleware('guru-mata-pelajaran.simpan-pergantian', 'izin:guru_mapel.kelola');
        $this->assertRouteMemakaiMiddleware('jam-pelajaran.index', 'admin');
        $this->assertRouteMemakaiMiddleware('jam-pelajaran.create', 'admin');
        $this->assertRouteMemakaiMiddleware('jadwal-pelajaran.index', 'izin:jadwal.lihat,jadwal.kelola');
        $this->assertRouteMemakaiMiddleware('jadwal-pelajaran.create', 'izin:jadwal.kelola');
        $this->assertRouteMemakaiMiddleware('jadwal-pelajaran.susun', 'izin:jadwal.kelola');
        $this->assertRouteMemakaiMiddleware('jadwal-pelajaran.simpan-massal', 'izin:jadwal.kelola');
        $this->assertRouteMemakaiMiddleware('jadwal-saya.index', 'izin:jadwal.pribadi');
        $this->assertRouteMemakaiMiddleware('jadwal-kelas-saya.index', 'izin:jadwal.lihat');
    }

    public function test_route_jenis_ujian_cbt_memakai_permission_cbt(): void
    {
        $this->assertRouteMemakaiMiddleware('jenis-ujian-cbt.index', 'izin:cbt.lihat,cbt.kelola');
        $this->assertRouteMemakaiMiddleware('jenis-ujian-cbt.show', 'izin:cbt.lihat,cbt.kelola');
        $this->assertRouteMemakaiMiddleware('jenis-ujian-cbt.create', 'izin:cbt.kelola');
        $this->assertRouteMemakaiMiddleware('jenis-ujian-cbt.store', 'izin:cbt.kelola');
        $this->assertRouteMemakaiMiddleware('jenis-ujian-cbt.edit', 'izin:cbt.kelola');
        $this->assertRouteMemakaiMiddleware('jenis-ujian-cbt.update', 'izin:cbt.kelola');
        $this->assertRouteMemakaiMiddleware('jenis-ujian-cbt.destroy', 'izin:cbt.kelola');
        $this->assertRouteMemakaiMiddleware('ujian-cbt.index', 'izin:cbt.lihat,cbt.kelola');
        $this->assertRouteMemakaiMiddleware('ujian-cbt.show', 'izin:cbt.lihat,cbt.kelola');
        $this->assertRouteMemakaiMiddleware('jadwal-ujian-cbt.index', 'izin:cbt.lihat,cbt.kelola');
        $this->assertRouteMemakaiMiddleware('status-kelengkapan-panitia-cbt.index', 'izin:cbt.lihat,cbt.kelola');
        $this->assertRouteMemakaiMiddleware('jadwal-ujian-cbt.kegiatan.store', 'izin:cbt.kelola');
        $this->assertRouteMemakaiMiddleware('jadwal-ujian-cbt.kegiatan.update', 'izin:cbt.kelola');
        $this->assertRouteMemakaiMiddleware('jadwal-ujian-cbt.kegiatan.destroy', 'izin:cbt.kelola');
        $this->assertRouteMemakaiMiddleware('jadwal-ujian-cbt.jadwal.store', 'izin:cbt.kelola');
        $this->assertRouteMemakaiMiddleware('jadwal-ujian-cbt.jadwal.update', 'izin:cbt.kelola');
        $this->assertRouteMemakaiMiddleware('jadwal-ujian-cbt.jadwal.destroy', 'izin:cbt.kelola');
        $this->assertRouteMemakaiMiddleware('jadwal-ujian-cbt.jadwal.kunci', 'izin:cbt.kelola');
        $this->assertRouteMemakaiMiddleware('jadwal-ujian-cbt.jadwal.buka-kunci', 'izin:cbt.kelola');
        $this->assertRouteMemakaiMiddleware('ujian-cbt.create', 'izin:cbt.kelola');
        $this->assertRouteMemakaiMiddleware('ujian-cbt.store', 'izin:cbt.kelola');
        $this->assertRouteMemakaiMiddleware('ujian-cbt.edit', 'izin:cbt.kelola');
        $this->assertRouteMemakaiMiddleware('ujian-cbt.update', 'izin:cbt.kelola');
        $this->assertRouteMemakaiMiddleware('ujian-cbt.destroy', 'izin:cbt.kelola');
        $this->assertRouteMemakaiMiddleware('ujian-cbt.soal.edit', 'izin:cbt.kelola');
        $this->assertRouteMemakaiMiddleware('ujian-cbt.soal.update', 'izin:cbt.kelola');
        $this->assertRouteMemakaiMiddleware('ujian-cbt.peserta.index', 'izin:cbt.kelola');
        $this->assertRouteMemakaiMiddleware('ujian-cbt.peserta.generate', 'izin:cbt.kelola');
        $this->assertRouteMemakaiMiddleware('ujian-cbt.peserta.update', 'izin:cbt.kelola');
        $this->assertRouteMemakaiMiddleware('ujian-cbt.koreksi-manual.index', 'izin:cbt.kelola');
        $this->assertRouteMemakaiMiddleware('ujian-cbt.koreksi-manual.update', 'izin:cbt.kelola');
        $this->assertRouteMemakaiMiddleware('ujian-cbt.terapkan-nilai.store', 'izin:cbt.kelola');
        $this->assertRouteMemakaiMiddleware('ujian-cbt.ruang.index', 'izin:cbt.kelola');
        $this->assertRouteMemakaiMiddleware('ujian-cbt.ruang.cetak', 'izin:cbt.kelola');
        $this->assertRouteMemakaiMiddleware('ujian-cbt.ruang.store', 'izin:cbt.kelola');
        $this->assertRouteMemakaiMiddleware('ujian-cbt.ruang.generate', 'izin:cbt.kelola');
        $this->assertRouteMemakaiMiddleware('ujian-cbt.ruang.bagi-otomatis', 'izin:cbt.kelola');
        $this->assertRouteMemakaiMiddleware('ujian-cbt.ruang.peserta.update', 'izin:cbt.kelola');
        $this->assertRouteMemakaiMiddleware('ujian-cbt.ruang.bukti.update', 'izin:cbt.kelola');
        $this->assertRouteMemakaiMiddleware('ujian-cbt.ruang.bukti.download', 'izin:cbt.kelola');
        $this->assertRouteMemakaiMiddleware('ujian-cbt.ruang.bukti.destroy', 'izin:cbt.kelola');
        $this->assertRouteMemakaiMiddleware('ujian-cbt.ruang.update', 'izin:cbt.kelola');
        $this->assertRouteMemakaiMiddleware('ujian-cbt.ruang.destroy', 'izin:cbt.kelola');
        $this->assertRouteMemakaiMiddleware('ujian-cbt.ruang.kunci', 'izin:cbt.kelola');
        $this->assertRouteMemakaiMiddleware('ujian-cbt.ruang.buka-kunci', 'izin:cbt.kelola');
        $this->assertRouteMemakaiMiddleware('presensi-ujian-cbt.index', 'izin:cbt.presensi,cbt.kelola');
        $this->assertRouteMemakaiMiddleware('presensi-ujian-cbt.show', 'izin:cbt.presensi,cbt.kelola');
        $this->assertRouteMemakaiMiddleware('presensi-ujian-cbt.scan', 'izin:cbt.presensi,cbt.kelola');
        $this->assertRouteMemakaiMiddleware('presensi-ujian-cbt.manual', 'izin:cbt.presensi,cbt.kelola');
        $this->assertRouteMemakaiMiddleware('ujian-cbt.kartu-peserta.index', 'izin:cbt.kelola');
        $this->assertRouteMemakaiMiddleware('ujian-cbt.sesi.store', 'izin:cbt.kelola');
        $this->assertRouteMemakaiMiddleware('ujian-cbt.sesi.update', 'izin:cbt.kelola');
        $this->assertRouteMemakaiMiddleware('ujian-cbt.sesi.destroy', 'izin:cbt.kelola');
        $this->assertRouteMemakaiMiddleware('soal-cbt.index', 'izin:cbt.lihat,cbt.kelola,cbt.soal_kelola');
        $this->assertRouteMemakaiMiddleware('soal-cbt.show', 'izin:cbt.lihat,cbt.kelola,cbt.soal_kelola');
        $this->assertRouteMemakaiMiddleware('soal-cbt.create', 'izin:cbt.kelola,cbt.soal_kelola');
        $this->assertRouteMemakaiMiddleware('soal-cbt.store', 'izin:cbt.kelola,cbt.soal_kelola');
        $this->assertRouteMemakaiMiddleware('soal-cbt.edit', 'izin:cbt.kelola,cbt.soal_kelola');
        $this->assertRouteMemakaiMiddleware('soal-cbt.update', 'izin:cbt.kelola,cbt.soal_kelola');
        $this->assertRouteMemakaiMiddleware('soal-cbt.destroy', 'izin:cbt.kelola,cbt.soal_kelola');
    }

    public function test_route_jenis_perangkat_ajar_memakai_permission_kurikulum(): void
    {
        $this->assertRouteMemakaiMiddleware('jenis-perangkat-ajar.index', 'izin:perangkat_ajar.jenis_kelola');
        $this->assertRouteMemakaiMiddleware('jenis-perangkat-ajar.create', 'izin:perangkat_ajar.jenis_kelola');
        $this->assertRouteMemakaiMiddleware('jenis-perangkat-ajar.update', 'izin:perangkat_ajar.jenis_kelola');
        $this->assertRouteMemakaiMiddleware('perangkat-ajar-saya.index', 'izin:perangkat_ajar.upload');
        $this->assertRouteMemakaiMiddleware('perangkat-ajar-saya.store', 'izin:perangkat_ajar.upload');
        $this->assertRouteMemakaiMiddleware('perangkat-ajar-saya.download', 'izin:perangkat_ajar.upload,perangkat_ajar.lihat,perangkat_ajar.periksa');
        $this->assertRouteMemakaiMiddleware('pemeriksaan-perangkat-ajar.index', 'izin:perangkat_ajar.lihat,perangkat_ajar.periksa');
        $this->assertRouteMemakaiMiddleware('pemeriksaan-perangkat-ajar.show', 'izin:perangkat_ajar.lihat,perangkat_ajar.periksa');
        $this->assertRouteMemakaiMiddleware('pemeriksaan-perangkat-ajar.preview', 'izin:perangkat_ajar.lihat,perangkat_ajar.periksa');
        $this->assertRouteMemakaiMiddleware('pemeriksaan-perangkat-ajar.update', 'izin:perangkat_ajar.periksa');
        $this->assertRouteMemakaiMiddleware('pertanyaan-survei-pembelajaran.index', 'izin:survei.pertanyaan_kelola');
        $this->assertRouteMemakaiMiddleware('pertanyaan-survei-pembelajaran.create', 'izin:survei.pertanyaan_kelola');
        $this->assertRouteMemakaiMiddleware('pertanyaan-survei-pembelajaran.update', 'izin:survei.pertanyaan_kelola');
        $this->assertRouteMemakaiMiddleware('pertanyaan-survei-pembelajaran.status', 'izin:survei.pertanyaan_kelola');
        $this->assertRouteMemakaiMiddleware('hasil-survei-saya.index', 'izin:survei.hasil_pribadi');
        $this->assertRouteMemakaiMiddleware('monitoring-survei.index', 'izin:survei.monitor');
    }

    public function test_route_master_inventaris_memakai_permission_barang(): void
    {
        $this->assertRouteMemakaiMiddleware('katalog-barang.index', 'akun_pegawai');
        $this->assertRouteMemakaiMiddleware('pengajuan-barang-saya.index', 'akun_pegawai');
        $this->assertRouteMemakaiMiddleware('pengajuan-barang-saya.store', 'akun_pegawai');
        $this->assertRouteMemakaiMiddleware('pengajuan-barang-saya.batalkan', 'akun_pegawai');
        $this->assertRouteMemakaiMiddleware('pengajuan-barang.index', 'izin:barang.peminjaman_kelola');
        $this->assertRouteMemakaiMiddleware('pengajuan-barang.penuhi', 'izin:barang.peminjaman_kelola');
        $this->assertRouteMemakaiMiddleware('pengajuan-barang.tolak', 'izin:barang.peminjaman_kelola');
        $this->assertRouteMemakaiMiddleware('dashboard-sarana-prasarana.index', 'izin:barang.lihat,barang.kelola,barang.peminjaman_kelola');
        $this->assertRouteMemakaiMiddleware('laporan-inventaris-bulanan.index', 'izin:barang.lihat,barang.kelola');
        $this->assertRouteMemakaiMiddleware('laporan-inventaris-bulanan.cetak', 'izin:barang.lihat,barang.kelola');
        $this->assertRouteMemakaiMiddleware('barang.index', 'izin:barang.lihat,barang.kelola');
        $this->assertRouteMemakaiMiddleware('barang.create', 'izin:barang.kelola');
        $this->assertRouteMemakaiMiddleware('kategori-barang.index', 'izin:barang.lihat,barang.kelola');
        $this->assertRouteMemakaiMiddleware('kategori-barang.store', 'izin:barang.kelola');
        $this->assertRouteMemakaiMiddleware('satuan-barang.index', 'izin:barang.lihat,barang.kelola');
        $this->assertRouteMemakaiMiddleware('satuan-barang.update', 'izin:barang.kelola');
        $this->assertRouteMemakaiMiddleware('lokasi-barang.index', 'izin:barang.lihat,barang.kelola');
        $this->assertRouteMemakaiMiddleware('lokasi-barang.destroy', 'izin:barang.kelola');
        $this->assertRouteMemakaiMiddleware('pengaturan-inventaris.index', 'izin:barang.kelola');
        $this->assertRouteMemakaiMiddleware('pengaturan-inventaris.update', 'izin:barang.kelola');
        $this->assertRouteMemakaiMiddleware('sumber-perolehan-barang.index', 'izin:barang.lihat,barang.kelola');
        $this->assertRouteMemakaiMiddleware('sumber-perolehan-barang.store', 'izin:barang.kelola');
        $this->assertRouteMemakaiMiddleware('penerimaan-barang.index', 'izin:barang.lihat,barang.kelola');
        $this->assertRouteMemakaiMiddleware('penerimaan-barang.show', 'izin:barang.lihat,barang.kelola');
        $this->assertRouteMemakaiMiddleware('penerimaan-barang.create', 'izin:barang.kelola');
        $this->assertRouteMemakaiMiddleware('penerimaan-barang.store', 'izin:barang.kelola');
        $this->assertRouteMemakaiMiddleware('penerimaan-barang.import.create', 'izin:barang.kelola');
        $this->assertRouteMemakaiMiddleware('penerimaan-barang.import.template', 'izin:barang.kelola');
        $this->assertRouteMemakaiMiddleware('penerimaan-barang.import.unggah', 'izin:barang.kelola');
        $this->assertRouteMemakaiMiddleware('penerimaan-barang.import.pratinjau', 'izin:barang.kelola');
        $this->assertRouteMemakaiMiddleware('penerimaan-barang.import.konfirmasi', 'izin:barang.kelola');
        $this->assertRouteMemakaiMiddleware('unit-barang.index', 'izin:barang.lihat,barang.kelola');
        $this->assertRouteMemakaiMiddleware('unit-barang.store', 'izin:barang.kelola');
        $this->assertRouteMemakaiMiddleware('label-barcode-inventaris.index', 'izin:barang.lihat,barang.kelola');
        $this->assertRouteMemakaiMiddleware('saldo-stok-barang.index', 'izin:barang.lihat,barang.kelola');
        $this->assertRouteMemakaiMiddleware('mutasi-stok-barang.index', 'izin:barang.lihat,barang.kelola');
        $this->assertRouteMemakaiMiddleware('mutasi-stok-barang.store', 'izin:barang.kelola');
        $this->assertRouteMemakaiMiddleware('peminjaman-barang.index', 'izin:barang.lihat,barang.peminjaman_kelola');
        $this->assertRouteMemakaiMiddleware('peminjaman-barang.show', 'izin:barang.lihat,barang.peminjaman_kelola');
        $this->assertRouteMemakaiMiddleware('peminjaman-barang.store', 'izin:barang.peminjaman_kelola');
        $this->assertRouteMemakaiMiddleware('pengembalian-barang.index', 'izin:barang.peminjaman_kelola');
        $this->assertRouteMemakaiMiddleware('pengembalian-barang.identifikasi', 'izin:barang.peminjaman_kelola');
        $this->assertRouteMemakaiMiddleware('pengembalian-barang.store', 'izin:barang.peminjaman_kelola');
        $this->assertRouteMemakaiMiddleware('rekap-peminjaman-barang.index', 'izin:barang.lihat,barang.peminjaman_kelola');
        $this->assertRouteMemakaiMiddleware('rekap-peminjaman-barang.cetak', 'izin:barang.lihat,barang.peminjaman_kelola');
    }

    private function assertRouteMemakaiMiddleware(string $namaRoute, string $middleware): void
    {
        $route = Route::getRoutes()->getByName($namaRoute);

        $this->assertNotNull($route, "Route {$namaRoute} tidak ditemukan.");
        $this->assertContains($middleware, $route->gatherMiddleware());
    }
}
