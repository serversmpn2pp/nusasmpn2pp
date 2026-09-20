<?php

namespace App\Services\Cbt;

use App\Models\JadwalUjianCbt;
use App\Models\SoalCbt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PaketSimulasiCbt
{
    public const KODE = 'SIMULASI_CBT';

    public function contoh(): array
    {
        return [
            ['jenis_soal' => 'pilihan_ganda', 'pertanyaan' => 'Pilih satu jawaban benar. Tempat membaca dan meminjam buku di sekolah adalah ....', 'opsi' => ['pilihan' => ['A' => 'Kantin', 'B' => 'Perpustakaan', 'C' => 'Lapangan', 'D' => 'Tempat parkir']], 'kunci_jawaban' => ['jawaban' => 'B']],
            ['jenis_soal' => 'pilihan_ganda', 'stimulus' => 'Perhatikan foto lingkungan sekolah.', 'pertanyaan' => 'Pilih satu jawaban benar. Agar lingkungan pada foto tetap bersih, kita sebaiknya ....', 'opsi' => ['pilihan' => ['A' => 'Membuang sampah pada tempatnya', 'B' => 'Meninggalkan bungkus makanan', 'C' => 'Mencoret dinding', 'D' => 'Merusak tanaman']], 'kunci_jawaban' => ['jawaban' => 'A'], 'media' => ['gambar' => ['path' => 'cbt/simulasi/lingkungan-sekolah.jpg', 'alt' => 'Lingkungan sekolah', 'keterangan' => 'Lingkungan sekolah yang perlu dijaga bersama.']]],
            ['jenis_soal' => 'pilihan_ganda_kompleks', 'pertanyaan' => 'Pilih semua jawaban benar. Kebiasaan yang menjaga kesehatan adalah ....', 'opsi' => ['pilihan' => ['A' => 'Mencuci tangan sebelum makan', 'B' => 'Tidur cukup', 'C' => 'Tidak pernah minum air', 'D' => 'Makan sayur dan buah']], 'kunci_jawaban' => ['jawaban' => ['A', 'B', 'D']]],
            ['jenis_soal' => 'pilihan_ganda_kompleks', 'pertanyaan' => 'Pilih semua jawaban benar. Benda yang biasa digunakan untuk menulis atau menggambar di buku adalah ....', 'opsi' => ['pilihan' => ['A' => 'Pensil', 'B' => 'Sendok', 'C' => 'Pulpen', 'D' => 'Pensil warna']], 'kunci_jawaban' => ['jawaban' => ['A', 'C', 'D']]],
            ['jenis_soal' => 'benar_salah', 'pertanyaan' => 'Tentukan Benar atau Salah untuk setiap pernyataan.', 'opsi' => ['pernyataan' => [['nomor' => 1, 'teks' => 'Kita perlu mengantre dengan tertib.'], ['nomor' => 2, 'teks' => 'Buku pinjaman boleh dirusak.']]], 'kunci_jawaban' => ['jawaban' => [1 => true, 2 => false]]],
            ['jenis_soal' => 'benar_salah', 'stimulus' => 'Siti membawa 2 buku tulis dan 3 pensil.', 'pertanyaan' => 'Tentukan Benar atau Salah berdasarkan cerita.', 'opsi' => ['pernyataan' => [['nomor' => 1, 'teks' => 'Siti membawa 5 benda.'], ['nomor' => 2, 'teks' => 'Jumlah buku lebih banyak daripada pensil.']]], 'kunci_jawaban' => ['jawaban' => [1 => true, 2 => false]]],
            ['jenis_soal' => 'menjodohkan', 'pertanyaan' => 'Pasangkan benda dengan fungsinya. Ada satu pilihan yang tidak digunakan.', 'opsi' => ['pasangan' => [['nomor' => 1, 'kiri' => 'Penggaris', 'kanan' => 'Mengukur panjang'], ['nomor' => 2, 'kiri' => 'Penghapus', 'kanan' => 'Menghapus tulisan pensil']], 'pengecoh' => ['Menyiram tanaman']], 'kunci_jawaban' => ['jawaban' => [1 => 'Mengukur panjang', 2 => 'Menghapus tulisan pensil']]],
            ['jenis_soal' => 'menjodohkan', 'pertanyaan' => 'Pasangkan tempat dengan kegiatan yang sesuai. Ada satu pilihan yang tidak digunakan.', 'opsi' => ['pasangan' => [['nomor' => 1, 'kiri' => 'Perpustakaan', 'kanan' => 'Membaca buku'], ['nomor' => 2, 'kiri' => 'Lapangan', 'kanan' => 'Berolahraga']], 'pengecoh' => ['Membeli obat']], 'kunci_jawaban' => ['jawaban' => [1 => 'Membaca buku', 2 => 'Berolahraga']]],
            ['jenis_soal' => 'isian_singkat', 'pertanyaan' => 'Ketik satu kata. Hari setelah Senin adalah ....', 'kunci_jawaban' => ['jawaban' => 'Selasa']],
            ['jenis_soal' => 'isian_singkat', 'pertanyaan' => 'Ketik satu kata. Alat untuk mengukur panjang garis adalah ....', 'kunci_jawaban' => ['jawaban' => 'penggaris | mistar']],
            ['jenis_soal' => 'numerik', 'stimulus' => 'Sebuah kotak berisi \\(2^{3}\\) pensil. Setengahnya, yaitu \\(\\frac{1}{2}\\) bagian, dibagikan kepada teman.', 'pertanyaan' => 'Berapa pensil yang dibagikan? Ketik angka saja.', 'kunci_jawaban' => ['jawaban' => '4']],
            ['jenis_soal' => 'numerik', 'stimulus' => 'Perhatikan jumlah buku pada tabel.', 'pertanyaan' => 'Berapa jumlah seluruh buku? Ketik angka saja.', 'media' => ['tabel' => ['judul' => 'Buku di rak kelas', 'baris' => [['Jenis buku', 'Jumlah'], ['Buku cerita', '6'], ['Buku pengetahuan', '4']]]], 'kunci_jawaban' => ['jawaban' => '10']],
        ];
    }

    public function siapkan(JadwalUjianCbt $jadwal): array
    {
        abort_unless($jadwal->kegiatanUjianCbt->jenisUjianCbt?->kode === self::KODE, 422);
        Storage::disk('public')->put('cbt/simulasi/lingkungan-sekolah.jpg', file_get_contents(public_path('images/login-sekolah.jpg')));

        return DB::transaction(function () use ($jadwal) {
            $ids = [];
            $folder = \App\Models\FolderSoalCbt::firstOrCreate([
                'mata_pelajaran_id' => $jadwal->mata_pelajaran_id, 'tingkat' => $jadwal->tingkat, 'nama' => 'Simulasi CBT',
            ], ['keterangan' => 'Contoh soal umum untuk latihan penggunaan CBT.']);
            foreach ($this->contoh() as $index => $contoh) {
                $soal = SoalCbt::firstOrCreate(['kode' => 'SIM-CBT-'.$jadwal->id.'-'.($index + 1)], [
                    ...$contoh, 'mata_pelajaran_id' => $jadwal->mata_pelajaran_id,
                    'tahun_pelajaran_id' => $jadwal->kegiatanUjianCbt->tahun_pelajaran_id,
                    'tingkat' => $jadwal->tingkat, 'kategori' => 'lots', 'tingkat_kesulitan' => 'mudah',
                    'skor_maksimal' => 1, 'status' => 'siap', 'aktif' => true,
                    'topik' => 'Simulasi CBT', 'materi' => 'Latihan umum penggunaan CBT',
                ]);
                $ids[$soal->id] = ['dipilih' => true];
                $folder->soal()->syncWithoutDetaching([$soal->id]);
            }
            return $ids;
        });
    }
}
