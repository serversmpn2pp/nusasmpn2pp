<?php

namespace App\Services\Supervisi;

class InstrumenSupervisiAwal
{
    public static function pertanyaanRefleksi(): array
    {
        return [
            'Apa yang paling berhasil dalam pembelajaran?',
            'Bagaimana keterlibatan peserta didik?',
            'Bagian mana yang perlu diperbaiki?',
            'Bagaimana strategi mengakomodasi kebutuhan belajar peserta didik?',
            'Bagaimana hasil asesmen formatif?',
            'Apa rencana perbaikan berikutnya?',
            'Dukungan apa yang diperlukan dari sekolah?',
        ];
    }

    public static function skala(): array
    {
        return array_map(fn ($skor, $label) => compact('skor', 'label'), range(0, 4), ['Tidak terlaksana/tidak ada bukti', 'Kurang', 'Cukup', 'Baik', 'Sangat Baik']);
    }

    public static function kelompok(): array
    {
        $data = [
            'perencanaan' => [
                'CP, TP dan ATP' => ['CP menjadi dasar perencanaan pembelajaran', 'TP dirumuskan secara jelas dan terukur', 'TP sesuai dengan CP', 'ATP sesuai dengan perkembangan kompetensi peserta didik'],
                'Modul/perencanaan pembelajaran' => ['Modul/perencanaan pembelajaran tersedia dan sistematis', 'Materi sesuai dengan tujuan pembelajaran', 'Kegiatan pembelajaran mendorong peserta didik aktif', 'Kegiatan pembelajaran kontekstual dan relevan dengan kehidupan peserta didik'],
                'Pembelajaran mendalam' => ['Pembelajaran dirancang agar peserta didik memahami konsep secara mendalam', 'Terdapat aktivitas yang bermakna bagi peserta didik', 'Terdapat aktivitas berpikir kritis dan pemecahan masalah', 'Terdapat kesempatan peserta didik melakukan refleksi'],
                'Diferensiasi' => ['Mempertimbangkan kesiapan belajar peserta didik', 'Mempertimbangkan karakteristik/minat peserta didik', 'Terdapat strategi diferensiasi konten/proses/produk'],
                'Asesmen' => ['Terdapat asesmen awal/diagnostik sesuai kebutuhan', 'Terdapat asesmen formatif', 'Terdapat asesmen sumatif', 'Instrumen asesmen sesuai dengan tujuan pembelajaran', 'Kriteria/rubrik penilaian tersedia dan jelas'],
                'Literasi, numerasi dan HOTS' => ['Mengintegrasikan literasi sesuai karakteristik mata pelajaran', 'Mengintegrasikan numerasi sesuai konteks pembelajaran', 'Memuat aktivitas HOTS'],
                'Profil lulusan' => ['Perencanaan mengembangkan dimensi Profil Lulusan', 'Mengembangkan kemampuan bernalar kritis', 'Mengembangkan kreativitas dan kemandirian', 'Mengembangkan kolaborasi dan komunikasi'],
                'Media dan sumber belajar' => ['Media pembelajaran sesuai dengan materi', 'Sumber belajar relevan dan bervariasi', 'Pemanfaatan teknologi digital direncanakan secara tepat'],
            ],
            'pelaksanaan' => [
                'Pendahuluan' => ['Membuka pembelajaran dengan salam dan suasana positif', 'Memastikan kesiapan belajar peserta didik', 'Mengaitkan materi dengan pengalaman/pengetahuan sebelumnya', 'Menyampaikan tujuan pembelajaran', 'Menyampaikan manfaat/relevansi pembelajaran', 'Memberikan pertanyaan pemantik', 'Melakukan asesmen awal/cek pemahaman jika diperlukan'],
                'Pembelajaran berpusat pada peserta didik' => ['Peserta didik aktif mengikuti pembelajaran', 'Peserta didik diberi kesempatan bertanya', 'Peserta didik diberi kesempatan mengemukakan pendapat', 'Peserta didik melakukan diskusi/kolaborasi', 'Guru berperan sebagai fasilitator'],
                'Pembelajaran mendalam' => ['Pembelajaran dikaitkan dengan kehidupan nyata', 'Peserta didik mengeksplorasi konsep/informasi', 'Peserta didik melakukan investigasi/inkuiri', 'Peserta didik menganalisis informasi/data', 'Peserta didik memecahkan masalah', 'Peserta didik memberikan alasan/argumentasi', 'Guru memberikan pertanyaan yang menuntut penalaran', 'Peserta didik menghubungkan berbagai konsep'],
                'Diferensiasi' => ['Guru mengenali perbedaan kebutuhan belajar peserta didik', 'Memberikan bantuan/scaffolding kepada peserta didik yang membutuhkan', 'Memberikan variasi aktivitas belajar', 'Memberikan tantangan kepada peserta didik yang sudah menguasai materi', 'Memberikan kesempatan menghasilkan produk belajar yang beragam'],
                'Asesmen formatif' => ['Memantau pemahaman peserta didik selama pembelajaran', 'Menggunakan pertanyaan untuk mengecek pemahaman', 'Memberikan umpan balik', 'Umpan balik membantu peserta didik memperbaiki pembelajaran', 'Menyesuaikan pembelajaran berdasarkan hasil asesmen'],
                'Literasi, numerasi dan HOTS' => ['Peserta didik membaca/menganalisis informasi', 'Peserta didik menginterpretasikan data/informasi', 'Aktivitas numerasi diterapkan sesuai konteks', 'Peserta didik menganalisis', 'Peserta didik mengevaluasi', 'Peserta didik menghasilkan solusi/produk/gagasan'],
                'Pengelolaan kelas' => ['Menciptakan suasana kelas yang aman dan nyaman', 'Membangun interaksi positif', 'Menghargai setiap pendapat peserta didik', 'Menerapkan disiplin positif', 'Mengelola waktu secara efektif', 'Mengelola aktivitas kelas dengan baik', 'Peserta didik menunjukkan sikap saling menghargai'],
                'Penutup' => ['Mengajak peserta didik menyimpulkan pembelajaran', 'Peserta didik melakukan refleksi', 'Mengecek ketercapaian tujuan pembelajaran', 'Memberikan umpan balik', 'Menyampaikan tindak lanjut', 'Menyampaikan gambaran pembelajaran berikutnya'],
            ],
        ];
        $hasil = [];
        foreach ($data as $tahap => $kelompok) {
            foreach ($kelompok as $nama => $indikator) {
                $hasil[] = compact('tahap', 'nama', 'indikator');
            }
        }

        return $hasil;
    }
}
