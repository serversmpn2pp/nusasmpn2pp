<?php

namespace Tests\Feature\Api;

use App\Models\Izin;
use App\Models\Pegawai;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Models\Siswa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MenuApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_menu_mobile_memerlukan_token(): void
    {
        $this->getJson(route('api.v1.menu'))
            ->assertUnauthorized();
    }

    public function test_administrator_menerima_katalog_menu_lengkap(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();

        $response = $this->withToken($this->token($administrator))
            ->getJson(route('api.v1.menu'))
            ->assertOk()
            ->assertJsonPath('data.jumlah_menu', 78)
            ->assertJsonCount(7, 'data.kelompok')
            ->assertJsonPath('data.kelompok.0.kode', 'data-sekolah')
            ->assertJsonPath('data.kelompok.0.items.0.kode', 'tahun-pelajaran')
            ->assertJsonPath('data.kelompok.6.kode', 'sistem')
            ->assertJsonPath('data.kelompok.6.items.5.kode', 'backup-restore')
            ->assertJsonFragment([
                'kode' => 'pusat-cbt',
                'label' => 'Ujian & Asesmen',
                'status' => 'tersedia',
                'rute' => '/pusat-cbt',
            ])
            ->assertJsonFragment([
                'kode' => 'dashboard-sarpras',
                'status' => 'tersedia',
                'rute' => '/dashboard-sarpras',
            ])
            ->assertJsonFragment([
                'kode' => 'inventaris-barang',
                'status' => 'tersedia',
                'rute' => '/barang',
            ])
            ->assertJsonFragment([
                'kode' => 'unit-aset',
                'status' => 'tersedia',
                'rute' => '/unit-aset',
            ])
            ->assertJsonFragment([
                'kode' => 'label-inventaris',
                'status' => 'tersedia',
                'rute' => '/label-inventaris',
            ])
            ->assertJsonFragment([
                'kode' => 'barang-datang',
                'status' => 'tersedia',
                'rute' => '/barang-datang',
            ])
            ->assertJsonFragment([
                'kode' => 'saldo-stok',
                'status' => 'tersedia',
                'rute' => '/saldo-stok',
            ])
            ->assertJsonFragment([
                'kode' => 'mutasi-stok',
                'status' => 'tersedia',
                'rute' => '/mutasi-stok',
            ])
            ->assertJsonFragment([
                'kode' => 'peminjaman-barang',
                'status' => 'tersedia',
                'rute' => '/peminjaman-barang',
            ])
            ->assertJsonFragment([
                'kode' => 'pengajuan-barang',
                'status' => 'tersedia',
                'rute' => '/pengajuan-barang',
            ])
            ->assertJsonFragment([
                'kode' => 'pengembalian-barang',
                'status' => 'tersedia',
                'rute' => '/pengembalian-barang',
            ])
            ->assertJsonFragment([
                'kode' => 'rekap-peminjaman',
                'status' => 'tersedia',
                'rute' => '/rekap-peminjaman-barang',
            ])
            ->assertJsonFragment([
                'kode' => 'kategori-barang',
                'status' => 'tersedia',
                'rute' => '/kategori-barang',
            ])
            ->assertJsonFragment([
                'kode' => 'satuan-barang',
                'status' => 'tersedia',
                'rute' => '/satuan-barang',
            ])
            ->assertJsonFragment([
                'kode' => 'lokasi-barang',
                'status' => 'tersedia',
                'rute' => '/lokasi-barang',
            ])
            ->assertJsonFragment([
                'kode' => 'sumber-perolehan',
                'status' => 'tersedia',
                'rute' => '/sumber-perolehan',
            ])
            ->assertJsonFragment([
                'kode' => 'pengaturan-inventaris',
                'status' => 'tersedia',
                'rute' => '/pengaturan-inventaris',
            ])
            ->assertJsonFragment([
                'kode' => 'siswa',
                'status' => 'tersedia',
                'rute' => '/siswa',
            ])
            ->assertJsonFragment([
                'kode' => 'kelas',
                'status' => 'tersedia',
                'rute' => '/kelas',
            ])
            ->assertJsonFragment([
                'kode' => 'penempatan-siswa',
                'status' => 'tersedia',
                'rute' => '/penempatan-siswa',
            ])
            ->assertJsonFragment([
                'kode' => 'foto-identitas',
                'status' => 'tersedia',
                'rute' => '/foto-identitas',
            ])
            ->assertJsonFragment([
                'kode' => 'kartu-pegawai',
                'status' => 'tersedia',
                'rute' => '/kartu-pegawai',
            ])
            ->assertJsonFragment([
                'kode' => 'kartu-pelajar',
                'status' => 'tersedia',
                'rute' => '/kartu-pelajar',
            ])
            ->assertJsonFragment([
                'kode' => 'jadwal-pelajaran',
                'status' => 'tersedia',
                'rute' => '/kelas?mode=jadwal',
            ])
            ->assertJsonFragment([
                'kode' => 'jam-pelajaran',
                'status' => 'tersedia',
                'rute' => '/jam-pelajaran',
            ])
            ->assertJsonFragment([
                'kode' => 'guru-mata-pelajaran',
                'status' => 'tersedia',
                'rute' => '/guru-mata-pelajaran',
            ])
            ->assertJsonFragment([
                'kode' => 'komponen-nilai',
                'status' => 'tersedia',
                'rute' => '/komponen-nilai',
            ])
            ->assertJsonFragment([
                'kode' => 'input-nilai',
                'status' => 'tersedia',
                'rute' => '/input-nilai',
            ])
            ->assertJsonFragment([
                'kode' => 'rekap-nilai-rapor',
                'status' => 'tersedia',
                'rute' => '/rekap-nilai-rapor',
            ])
            ->assertJsonFragment([
                'kode' => 'pernyataan-survei',
                'status' => 'tersedia',
                'rute' => '/pernyataan-survei',
            ])
            ->assertJsonFragment([
                'kode' => 'monitoring-survei',
                'status' => 'tersedia',
                'rute' => '/monitoring-survei',
            ])
            ->assertJsonFragment([
                'kode' => 'pemeriksaan-perangkat-ajar',
                'status' => 'tersedia',
                'rute' => '/pemeriksaan-perangkat-ajar',
            ])
            ->assertJsonFragment([
                'kode' => 'jenis-perangkat-ajar',
                'status' => 'tersedia',
                'rute' => '/jenis-perangkat-ajar',
            ])
            ->assertJsonFragment([
                'kode' => 'kegiatan-ibadah',
                'status' => 'tersedia',
                'rute' => '/kegiatan-ibadah',
            ])
            ->assertJsonFragment([
                'kode' => 'jadwal-ibadah',
                'status' => 'tersedia',
                'rute' => '/jadwal-kegiatan-ibadah',
            ])
            ->assertJsonFragment([
                'kode' => 'pengaturan-berhalangan',
                'status' => 'tersedia',
                'rute' => '/pengaturan-berhalangan-ibadah',
            ])
            ->assertJsonFragment([
                'kode' => 'scan-ibadah-siswa',
                'status' => 'tersedia',
                'rute' => '/scan-kegiatan-ibadah',
            ])
            ->assertJsonFragment([
                'kode' => 'scan-berhalangan-ibadah',
                'status' => 'tersedia',
                'rute' => '/scan-berhalangan-ibadah',
            ])
            ->assertJsonFragment([
                'kode' => 'konfirmasi-berhalangan-ibadah',
                'status' => 'tersedia',
                'rute' => '/konfirmasi-berhalangan-ibadah',
            ])
            ->assertJsonFragment([
                'kode' => 'rekap-ibadah-siswa',
                'status' => 'tersedia',
                'rute' => '/rekap-kegiatan-ibadah',
            ])
            ->assertJsonFragment([
                'kode' => 'ringkasan-ibadah-bulanan',
                'status' => 'tersedia',
                'rute' => '/ringkasan-kegiatan-ibadah-bulanan',
            ])
            ->assertJsonFragment([
                'kode' => 'kategori-pembinaan-non-poin',
                'status' => 'tersedia',
                'rute' => '/kategori-pembinaan-siswa',
            ])
            ->assertJsonFragment([
                'kode' => 'jenis-pelanggaran-poin',
                'status' => 'tersedia',
                'rute' => '/jenis-pelanggaran-siswa',
            ])
            ->assertJsonFragment([
                'kode' => 'aturan-sanksi-poin',
                'status' => 'tersedia',
                'rute' => '/aturan-sanksi-poin',
            ])
            ->assertJsonFragment([
                'kode' => 'poin-keterlambatan',
                'status' => 'tersedia',
                'rute' => '/pengaturan-poin-keterlambatan',
            ])
            ->assertJsonFragment([
                'kode' => 'peringatan-dini-poin',
                'status' => 'tersedia',
                'rute' => '/pengaturan-peringatan-dini-poin',
            ])
            ->assertJsonFragment([
                'kode' => 'batas-proses-pelanggaran',
                'status' => 'tersedia',
                'rute' => '/pengaturan-batas-proses-pelanggaran',
            ])
            ->assertJsonFragment([
                'kode' => 'laporkan-kejadian',
                'status' => 'tersedia',
                'rute' => '/laporkan-kejadian',
            ])
            ->assertJsonFragment([
                'kode' => 'pemeriksaan-pengesahan',
                'status' => 'tersedia',
                'rute' => '/pemeriksaan-pengesahan',
            ])
            ->assertJsonFragment([
                'kode' => 'daftar-laporan-siswa',
                'status' => 'tersedia',
                'rute' => '/daftar-laporan-siswa',
            ])
            ->assertJsonFragment([
                'kode' => 'pendampingan-siswa',
                'status' => 'tersedia',
                'rute' => '/pendampingan-siswa',
            ])
            ->assertJsonFragment([
                'kode' => 'pelaksanaan-sanksi-siswa',
                'status' => 'tersedia',
                'rute' => '/pelaksanaan-sanksi-siswa',
            ])
            ->assertJsonFragment([
                'kode' => 'peringatan-dini-siswa',
                'status' => 'tersedia',
                'rute' => '/peringatan-dini-siswa',
            ])
            ->assertJsonFragment([
                'kode' => 'rekap-poin-siswa',
                'status' => 'tersedia',
                'rute' => '/rekap-poin-siswa',
            ])
            ->assertJsonFragment([
                'kode' => 'pengaturan-presensi-pegawai',
                'status' => 'tersedia',
                'rute' => '/pengaturan-presensi-pegawai',
            ])
            ->assertJsonFragment([
                'kode' => 'scan-presensi-pegawai',
                'status' => 'tersedia',
                'rute' => '/status-scan-presensi-pegawai',
            ])
            ->assertJsonFragment([
                'kode' => 'rekap-presensi-pegawai',
                'status' => 'tersedia',
                'rute' => '/rekap-presensi-pegawai',
            ])
            ->assertJsonFragment([
                'kode' => 'laporan-presensi-pegawai',
                'status' => 'tersedia',
                'rute' => '/laporan-presensi-pegawai',
            ])
            ->assertJsonFragment([
                'kode' => 'role-hak-akses',
                'status' => 'tersedia',
                'rute' => '/role-hak-akses',
            ])
            ->assertJsonMissing(['kode' => 'nilai-saya'])
            ->assertJsonMissing(['kode' => 'perangkat-ajar-saya'])
            ->assertJsonMissingPath('data.kelompok.0.items.0.izin')
            ->assertJsonStructure([
                'data' => [
                    'dihasilkan_pada',
                    'jumlah_menu',
                    'kelompok' => [
                        '*' => [
                            'kode',
                            'label',
                            'deskripsi',
                            'ikon',
                            'items' => [
                                '*' => [
                                    'kode',
                                    'label',
                                    'deskripsi',
                                    'inisial',
                                    'subkelompok',
                                    'ikon',
                                    'status',
                                    'rute',
                                ],
                            ],
                        ],
                    ],
                ],
            ]);

        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function test_menu_disaring_menurut_izin_pengguna(): void
    {
        $peran = Peran::create([
            'nama' => 'Pembaca Data Pegawai Mobile',
            'kode' => 'pembaca_pegawai_mobile',
            'aktif' => true,
            'sistem' => false,
        ]);
        $peran->izin()->attach(Izin::where('kode', 'pegawai.lihat')->firstOrFail());

        $pengguna = Pengguna::create([
            'nama' => 'Pengguna Terbatas',
            'username' => 'menu.terbatas',
            'kata_sandi' => 'RahasiaNusa123',
            'peran' => 'pegawai',
            'aktif' => true,
            'akun_sistem' => false,
            'wajib_ganti_kata_sandi' => false,
        ]);
        $pengguna->daftarPeran()->attach($peran);

        $this->withToken($this->token($pengguna))
            ->getJson(route('api.v1.menu'))
            ->assertOk()
            ->assertJsonPath('data.jumlah_menu', 3)
            ->assertJsonFragment(['kode' => 'pegawai'])
            ->assertJsonFragment(['kode' => 'kartu-pegawai'])
            ->assertJsonFragment(['kode' => 'katalog-barang'])
            ->assertJsonMissing(['kode' => 'siswa'])
            ->assertJsonMissing(['kode' => 'akun-pegawai']);
    }

    public function test_menu_dikunci_sampai_kata_sandi_awal_diganti(): void
    {
        $pengguna = Pengguna::create([
            'nama' => 'Pengguna Wajib Ganti',
            'username' => 'menu.wajib.ganti',
            'kata_sandi' => 'RahasiaNusa123',
            'peran' => 'pegawai',
            'aktif' => true,
            'akun_sistem' => false,
            'wajib_ganti_kata_sandi' => true,
        ]);

        $this->withToken($this->token($pengguna))
            ->getJson(route('api.v1.menu'))
            ->assertStatus(428)
            ->assertJsonPath('wajib_ganti_kata_sandi', true);
    }

    public function test_akun_siswa_menerima_menu_nilai_saya(): void
    {
        $siswa = Siswa::create([
            'nama_lengkap' => 'Siswa Menu Nilai',
            'nis' => '20269991',
            'nisn' => '0099999991',
            'jenis_kelamin' => 'L',
            'aktif' => true,
        ]);
        $pengguna = Pengguna::create([
            'siswa_id' => $siswa->id,
            'nama' => $siswa->nama_lengkap,
            'username' => $siswa->nisn,
            'kata_sandi' => 'RahasiaNusa123',
            'peran' => 'siswa',
            'aktif' => true,
            'akun_sistem' => false,
            'wajib_ganti_kata_sandi' => false,
        ]);

        $this->withToken($this->token($pengguna))
            ->getJson(route('api.v1.menu'))
            ->assertOk()
            ->assertJsonFragment([
                'kode' => 'nilai-saya',
                'status' => 'tersedia',
                'rute' => '/nilai-saya',
            ])
            ->assertJsonFragment([
                'kode' => 'ujian-saya',
                'status' => 'tersedia',
                'rute' => '/ujian-saya',
            ]);
    }

    public function test_akun_guru_menerima_menu_perangkat_ajar_saya(): void
    {
        $pegawai = Pegawai::create([
            'nama_lengkap' => 'Guru Perangkat Ajar',
            'nip' => '198601012026081001',
            'jenis_kelamin' => 'L',
            'aktif' => true,
        ]);
        $pengguna = Pengguna::create([
            'pegawai_id' => $pegawai->id,
            'nama' => $pegawai->nama_lengkap,
            'username' => $pegawai->nip,
            'kata_sandi' => 'RahasiaNusa123',
            'peran' => 'pegawai',
            'aktif' => true,
            'akun_sistem' => false,
            'wajib_ganti_kata_sandi' => false,
        ]);
        $peran = Peran::where('kode', 'guru_mapel')->firstOrFail();
        $pengguna->daftarPeran()->attach($peran);

        $this->withToken($this->token($pengguna))
            ->getJson(route('api.v1.menu'))
            ->assertOk()
            ->assertJsonFragment([
                'kode' => 'perangkat-ajar-saya',
                'status' => 'tersedia',
                'rute' => '/perangkat-ajar-saya',
            ])
            ->assertJsonFragment([
                'kode' => 'pengajuan-saya',
                'status' => 'tersedia',
                'rute' => '/pengajuan-saya',
            ]);
    }

    private function token(Pengguna $pengguna): string
    {
        return $pengguna->createToken('Pixel 7 Emulator', ['mobile'])->plainTextToken;
    }
}
