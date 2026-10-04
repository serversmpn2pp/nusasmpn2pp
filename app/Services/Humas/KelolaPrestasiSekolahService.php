<?php

namespace App\Services\Humas;

use App\Models\AnggotaKelas;
use App\Models\DokumenHumas;
use App\Models\Pegawai;
use App\Models\PrestasiSekolah;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class KelolaPrestasiSekolahService
{
    public function peserta(array $data): array
    {
        $rows = $data['peserta'] ?? [];
        if ($data['penerima'] === 'sekolah') {
            if ($data['bentuk'] !== 'sekolah' || count($rows)) {
                $this->gagal('peserta', 'Prestasi sekolah tidak menggunakan daftar peserta individu.');
            }

            return [];
        }
        if ($data['bentuk'] === 'sekolah' || ! count($rows) || ($data['bentuk'] === 'individu' && count($rows) !== 1)) {
            $this->gagal('peserta', 'Pilih tepat satu penerima untuk individu, atau anggota/perwakilan untuk tim.');
        }
        $siswa = Siswa::whereIn('id', collect($rows)->pluck('siswa_id')->filter())->get(['id', 'nama_lengkap'])->keyBy('id');
        $pegawai = Pegawai::whereIn('id', collect($rows)->pluck('pegawai_id')->filter())->get(['id', 'nama_lengkap'])->keyBy('id');
        $kelas = AnggotaKelas::whereIn('siswa_id', $siswa->keys())->whereHas('tahunPelajaran', fn ($q) => $q->whereDate('tanggal_mulai', '<=', $data['tanggal_prestasi'])->whereDate('tanggal_selesai', '>=', $data['tanggal_prestasi']))
            ->with('kelas:id,nama')->orderByDesc('id')->get()->unique('siswa_id')->keyBy('siswa_id');
        $seen = [];
        $hasil = [];
        foreach ($rows as $i => $row) {
            $sid = $row['siswa_id'] ?? null;
            $pid = $row['pegawai_id'] ?? null;
            if (($sid && ($data['penerima'] !== 'siswa' || $pid || ! isset($siswa[$sid]))) || ($pid && ($data['penerima'] !== 'pegawai' || ! isset($pegawai[$pid])))) {
                $this->gagal("peserta.$i", 'Identitas penerima tidak sesuai jenis prestasi.');
            }
            $nama = $sid ? $siswa[$sid]->nama_lengkap : ($pid ? $pegawai[$pid]->nama_lengkap : Str::squish($row['nama'] ?? ''));
            if ($nama === '') {
                $this->gagal("peserta.$i.nama", 'Isi nama penerima atau pilih identitas NUSA.');
            }
            $key = $sid ? 's:'.$sid : ($pid ? 'p:'.$pid : 'm:'.Str::lower($nama));
            if (isset($seen[$key])) {
                $this->gagal('peserta', 'Penerima yang sama tidak boleh dicatat dua kali dalam satu prestasi.');
            }
            $seen[$key] = true;
            $hasil[] = ['siswa_id' => $sid ? (int) $sid : null, 'pegawai_id' => $pid ? (int) $pid : null, 'nama' => $nama,
                'kelas' => $data['penerima'] === 'siswa' ? ($sid ? $kelas->get($sid)?->kelas?->nama : (filled($row['kelas'] ?? null) ? Str::squish($row['kelas']) : null)) : null];
        }

        return $hasil;
    }

    public function hash(PrestasiSekolah $p, array $peserta): string
    {
        $identitas = collect($peserta)->map(fn ($s) => $s['siswa_id'] ? 's:'.$s['siswa_id'] : ($s['pegawai_id'] ? 'p:'.$s['pegawai_id'] : 'm:'.Str::lower($s['nama'])))->sort()->values()->all();

        return hash('sha256', json_encode([Str::lower(Str::squish($p->nama_kegiatan)), Str::lower(Str::squish($p->cabang ?? '')), $p->tanggal_prestasi->format('Y-m-d'), $p->tingkat, $p->perolehan, Str::lower(Str::squish($p->capaian)), $p->penerima, $p->bentuk, Str::lower(Str::squish($p->nama_tim ?? '')), $identitas], JSON_THROW_ON_ERROR));
    }

    public function tahun(PrestasiSekolah $p): void
    {
        if ($p->tahun_pelajaran_id) {
            $tahun = TahunPelajaran::findOrFail($p->tahun_pelajaran_id);
            if (($tahun->tanggal_mulai && $p->tanggal_prestasi->lt($tahun->tanggal_mulai)) || ($tahun->tanggal_selesai && $p->tanggal_prestasi->gt($tahun->tanggal_selesai))) {
                $this->gagal('tahun_pelajaran_id', 'Tanggal prestasi harus berada dalam tahun pelajaran yang dipilih.');
            }
        }
    }

    public function bukti(Request $request, PrestasiSekolah $p, array $data, ?array $file): void
    {
        if ($data['metode'] === 'tetap') {
            return;
        }
        if (in_array($data['metode'], ['tanpa', 'hapus'], true)) {
            $p->forceFill(['riwayat_dokumen_humas_id' => null]);

            return;
        }
        abort_unless($request->user()->memilikiIzin(['dokumen_humas.lihat', 'dokumen_humas.kelola']), 403);
        if ($data['metode'] === 'unggah') {
            abort_unless($request->user()->memilikiIzin('dokumen_humas.kelola'), 403);
            $dokumen = DokumenHumas::create($file + ['kategori' => 'prestasi', 'judul' => Str::limit($p->capaian.' - '.$p->nama_kegiatan, 180, ''), 'status' => 'aktif',
                'dibuat_oleh_pengguna_id' => $request->user()->id, 'diubah_oleh_pengguna_id' => $request->user()->id]);
            $berkas = $dokumen->riwayat()->create($file + ['versi' => 1, 'catatan' => 'Bukti prestasi sekolah.', 'diunggah_oleh_pengguna_id' => $request->user()->id, 'diunggah_pada' => now()]);
        } else {
            $dokumen = DokumenHumas::lockForUpdate()->findOrFail($data['dokumen_humas_id']);
            if ($dokumen->status !== 'aktif') {
                $this->gagal('dokumen_humas_id', 'Pilih dokumen Humas yang aktif.');
            }
            $berkas = $dokumen->riwayat()->first();
        }
        if (! $berkas || ! in_array($berkas->tipe_file, PrestasiSekolah::MIME_BUKTI, true) || ! Storage::disk('local')->exists($berkas->lokasi_file)) {
            $this->gagal('berkas', 'Bukti PDF/gambar tidak tersedia atau tidak sesuai.');
        }
        $p->forceFill(['riwayat_dokumen_humas_id' => $berkas->id]);
    }

    public function verifikasi(Request $request, PrestasiSekolah $p, bool $berubah = true): void
    {
        if ($p->status === 'terverifikasi') {
            if (! $p->tautan && ! $p->riwayat_dokumen_humas_id) {
                $this->gagal('berkas', 'Prestasi terverifikasi memerlukan bukti atau tautan pengumuman publik.');
            }
            if ($p->riwayat_dokumen_humas_id && ! Storage::disk('local')->exists($p->berkas()->value('lokasi_file'))) {
                $this->gagal('berkas', 'Bukti tersimpan tidak ditemukan. Ganti atau lepaskan bukti tersebut sebelum verifikasi.');
            }
            if ($berubah) {
                $p->forceFill(['diverifikasi_pada' => now(), 'diverifikasi_oleh_pengguna_id' => $request->user()->id]);
            }
        } elseif ($p->status === 'draf') {
            $p->forceFill(['diverifikasi_pada' => null, 'diverifikasi_oleh_pengguna_id' => null]);
        }
    }

    public function catat(Request $request, PrestasiSekolah $p, string $aksi, ?string $catatan = null): void
    {
        $p->unsetRelation('peserta')->unsetRelation('tahunPelajaran');
        $p->riwayat()->create(['versi' => $p->versi, 'aksi' => $aksi, 'snapshot' => $p->snapshot(), 'riwayat_dokumen_humas_id' => $p->riwayat_dokumen_humas_id,
            'catatan_perubahan' => $catatan, 'pengguna_id' => $request->user()->id, 'created_at' => now()]);
    }

    private function gagal(string $key, string $message): never
    {
        throw ValidationException::withMessages([$key => $message]);
    }
}
