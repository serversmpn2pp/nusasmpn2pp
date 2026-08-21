<?php

namespace App\Services\Sistem;

use App\Models\Pengguna;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Throwable;

class CadanganDatabaseService
{
    public function status(): array
    {
        $koneksi = $this->konfigurasiKoneksi();
        $pgDump = $this->cariExecutable('pg_dump', config('cadangan_database.pg_dump_path'));
        $pgRestore = $this->cariExecutable('pg_restore', config('cadangan_database.pg_restore_path'));

        return [
            'driver' => $koneksi['driver'],
            'database' => $koneksi['database'],
            'pg_dump' => $pgDump,
            'pg_restore' => $pgRestore,
            'siap_backup' => $koneksi['driver'] === 'pgsql' && filled($pgDump),
            'siap_restore' => $koneksi['driver'] === 'pgsql' && filled($pgDump) && filled($pgRestore),
            'otomatis_aktif' => (bool) config('cadangan_database.otomatis_aktif', true),
            'jadwal_otomatis' => (string) config('cadangan_database.jadwal_otomatis', '01:00'),
            'retensi_hari' => (int) config('cadangan_database.retensi_otomatis_hari', 30),
        ];
    }

    public function daftarCadangan(): Collection
    {
        $disk = Storage::disk('local');
        $direktori = $this->direktori();
        $disk->makeDirectory($direktori);

        return collect($disk->files($direktori))
            ->filter(fn (string $lokasi) => str_ends_with(strtolower($lokasi), '.dump'))
            ->map(function (string $lokasi) use ($disk) {
                $namaFile = basename($lokasi);
                $waktu = Carbon::createFromTimestamp($disk->lastModified($lokasi));
                $ukuran = $disk->size($lokasi);

                return [
                    'nama_file' => $namaFile,
                    'lokasi' => $lokasi,
                    'jenis' => $this->jenisDariNamaFile($namaFile),
                    'ukuran' => $ukuran,
                    'ukuran_label' => $this->formatUkuran($ukuran),
                    'waktu' => $waktu,
                    'valid' => $this->berkasMemilikiHeaderPgDump($disk->path($lokasi)),
                ];
            })
            ->sortByDesc(fn (array $item) => $item['waktu']->getTimestamp())
            ->values();
    }

    public function daftarAktivitas(int $batas = 15): Collection
    {
        $path = Storage::disk('local')->path($this->lokasiLog());

        if (! is_file($path)) {
            return collect();
        }

        $baris = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];

        return collect(array_slice($baris, -$batas))
            ->map(fn (string $item) => json_decode($item, true))
            ->filter(fn ($item) => is_array($item))
            ->reverse()
            ->values();
    }

    public function buatCadangan(string $jenis = 'manual', ?Pengguna $pengguna = null): array
    {
        $status = $this->status();

        if (! $status['siap_backup']) {
            throw new RuntimeException($this->pesanExecutableTidakSiap('pg_dump'));
        }

        $this->longgarkanWaktuEksekusi();

        $disk = Storage::disk('local');
        $direktori = $this->direktori();
        $disk->makeDirectory($direktori);

        $penandaJenis = match ($jenis) {
            'otomatis' => 'otomatis-',
            'pra-pemulihan' => 'pra-pemulihan-',
            default => '',
        };
        $namaFile = 'nusa-db-'.$penandaJenis.now()->format('Ymd-His').'.dump';
        $lokasi = $direktori.'/'.$namaFile;
        $lokasiSementara = $lokasi.'.part';
        $pathSementara = $disk->path($lokasiSementara);
        $koneksi = $this->konfigurasiKoneksi();

        $proses = new Process([
            $status['pg_dump'],
            '--format=custom',
            '--compress=6',
            '--no-owner',
            '--no-privileges',
            '--schema=public',
            '--host='.$koneksi['host'],
            '--port='.(string) $koneksi['port'],
            '--username='.$koneksi['username'],
            '--file='.$pathSementara,
            $koneksi['database'],
        ], base_path(), $this->lingkunganPostgres($koneksi));
        $proses->setTimeout($this->timeoutDetik());
        $proses->run();

        if (! $proses->isSuccessful()) {
            $disk->delete($lokasiSementara);
            $pesan = $this->pesanProsesGagal('Pembuatan cadangan', $proses);
            $this->catatAktivitas('backup', $namaFile, 'gagal', $pengguna, $pesan);

            throw new RuntimeException($pesan);
        }

        if (! $this->berkasMemilikiHeaderPgDump($pathSementara)) {
            $disk->delete($lokasiSementara);
            $pesan = 'Berkas hasil cadangan tidak dikenali sebagai PostgreSQL custom dump.';
            $this->catatAktivitas('backup', $namaFile, 'gagal', $pengguna, $pesan);

            throw new RuntimeException($pesan);
        }

        $disk->move($lokasiSementara, $lokasi);
        $metadata = $this->metadataCadangan($namaFile);
        $this->catatAktivitas('backup', $namaFile, 'berhasil', $pengguna, 'Cadangan database berhasil dibuat.');

        if ($jenis === 'otomatis') {
            $this->bersihkanCadanganOtomatis();
        }

        return $metadata;
    }

    public function simpanUnggahan(UploadedFile $berkas, ?Pengguna $pengguna = null): array
    {
        $disk = Storage::disk('local');
        $direktori = $this->direktori();
        $disk->makeDirectory($direktori);

        $namaFile = 'nusa-db-unggahan-'.now()->format('Ymd-His').'-'.str()->lower(str()->random(6)).'.dump';
        $lokasi = $berkas->storeAs($direktori, $namaFile, 'local');

        if (! $lokasi || ! $this->berkasMemilikiHeaderPgDump($disk->path($lokasi))) {
            if ($lokasi) {
                $disk->delete($lokasi);
            }

            throw new RuntimeException('Berkas yang diunggah bukan cadangan PostgreSQL NUSA yang valid. Gunakan berkas .dump dari fitur ini.');
        }

        $this->catatAktivitas('unggah', $namaFile, 'berhasil', $pengguna, 'Cadangan dari perangkat berhasil diunggah.');

        return $this->metadataCadangan($namaFile);
    }

    public function pulihkan(string $namaFile, Pengguna $pengguna): array
    {
        $status = $this->status();

        if (! $status['siap_restore']) {
            throw new RuntimeException($this->pesanExecutableTidakSiap('pg_restore'));
        }

        $cadangan = $this->metadataCadangan($namaFile);

        if (! $cadangan['valid']) {
            throw new RuntimeException('Cadangan tidak valid atau rusak sehingga pemulihan dibatalkan.');
        }

        $this->longgarkanWaktuEksekusi();
        $cadanganPengaman = $this->buatCadangan('pra-pemulihan', $pengguna);
        $koneksi = $this->konfigurasiKoneksi();
        $path = Storage::disk('local')->path($cadangan['lokasi']);
        $modePemeliharaanAktif = false;

        try {
            Artisan::call('down', ['--retry' => 60]);
            $modePemeliharaanAktif = true;
            DB::disconnect($koneksi['nama_koneksi']);

            $proses = new Process([
                $status['pg_restore'],
                '--clean',
                '--if-exists',
                '--exit-on-error',
                '--single-transaction',
                '--no-owner',
                '--no-privileges',
                '--host='.$koneksi['host'],
                '--port='.(string) $koneksi['port'],
                '--username='.$koneksi['username'],
                '--dbname='.$koneksi['database'],
                $path,
            ], base_path(), $this->lingkunganPostgres($koneksi));
            $proses->setTimeout($this->timeoutDetik());
            $proses->run();

            if (! $proses->isSuccessful()) {
                throw new RuntimeException($this->pesanProsesGagal('Pemulihan database', $proses));
            }

            DB::purge($koneksi['nama_koneksi']);
            DB::reconnect($koneksi['nama_koneksi']);
            Artisan::call('migrate', ['--force' => true]);

            $this->catatAktivitas(
                'restore',
                $namaFile,
                'berhasil',
                $pengguna,
                'Database dipulihkan. Cadangan pengaman: '.$cadanganPengaman['nama_file'].'.',
            );

            return [
                'cadangan' => $cadangan,
                'cadangan_pengaman' => $cadanganPengaman,
            ];
        } catch (Throwable $exception) {
            try {
                DB::purge($koneksi['nama_koneksi']);
                DB::reconnect($koneksi['nama_koneksi']);
            } catch (Throwable) {
                // Koneksi akan dicoba kembali oleh permintaan berikutnya.
            }

            $pesan = 'Pemulihan gagal. Database tidak dinyatakan berhasil dipulihkan. Cadangan pengaman tersedia sebagai '
                .$cadanganPengaman['nama_file'].'. '.$exception->getMessage();
            $this->catatAktivitas('restore', $namaFile, 'gagal', $pengguna, $pesan);

            throw new RuntimeException($pesan, previous: $exception);
        } finally {
            if ($modePemeliharaanAktif) {
                Artisan::call('up');
            }
        }
    }

    public function hapus(string $namaFile, ?Pengguna $pengguna = null): void
    {
        $cadangan = $this->metadataCadangan($namaFile);

        if (! Storage::disk('local')->delete($cadangan['lokasi'])) {
            throw new RuntimeException('Cadangan tidak dapat dihapus dari penyimpanan server.');
        }

        $this->catatAktivitas('hapus', $namaFile, 'berhasil', $pengguna, 'Cadangan dihapus dari server.');
    }

    public function pathCadangan(string $namaFile): string
    {
        $cadangan = $this->metadataCadangan($namaFile);

        return Storage::disk('local')->path($cadangan['lokasi']);
    }

    public function metadataCadangan(string $namaFile): array
    {
        $this->pastikanNamaFileAman($namaFile);
        $disk = Storage::disk('local');
        $lokasi = $this->direktori().'/'.$namaFile;

        if (! $disk->exists($lokasi)) {
            throw new RuntimeException('Berkas cadangan tidak ditemukan di server.');
        }

        $ukuran = $disk->size($lokasi);

        return [
            'nama_file' => $namaFile,
            'lokasi' => $lokasi,
            'jenis' => $this->jenisDariNamaFile($namaFile),
            'ukuran' => $ukuran,
            'ukuran_label' => $this->formatUkuran($ukuran),
            'waktu' => Carbon::createFromTimestamp($disk->lastModified($lokasi)),
            'valid' => $this->berkasMemilikiHeaderPgDump($disk->path($lokasi)),
        ];
    }

    public function bersihkanCadanganOtomatis(): int
    {
        $batasHari = max(1, (int) config('cadangan_database.retensi_otomatis_hari', 30));
        $batasWaktu = now()->subDays($batasHari)->getTimestamp();
        $jumlah = 0;

        foreach ($this->daftarCadangan() as $cadangan) {
            if ($cadangan['jenis'] !== 'Otomatis' || $cadangan['waktu']->getTimestamp() >= $batasWaktu) {
                continue;
            }

            if (Storage::disk('local')->delete($cadangan['lokasi'])) {
                $jumlah++;
            }
        }

        return $jumlah;
    }

    public function batasUnggah(): array
    {
        $batasAplikasi = max(1, (int) config('cadangan_database.maksimal_unggahan_mb', 250)) * 1024 * 1024;
        $batasPhp = array_filter([
            $this->ukuranKonfigurasiKeByte(ini_get('upload_max_filesize')),
            $this->ukuranKonfigurasiKeByte(ini_get('post_max_size')),
        ], fn (int $ukuran) => $ukuran > 0);
        $batasEfektif = min([$batasAplikasi, ...$batasPhp]);

        return [
            'byte' => $batasEfektif,
            'kilobyte' => max(1, (int) floor($batasEfektif / 1024)),
            'label' => $this->formatUkuran($batasEfektif),
        ];
    }

    private function konfigurasiKoneksi(): array
    {
        $namaKoneksi = (string) config('database.default');
        $konfigurasi = (array) config('database.connections.'.$namaKoneksi, []);
        $url = $konfigurasi['url'] ?? null;

        if (filled($url)) {
            $bagianUrl = parse_url((string) $url);

            if (is_array($bagianUrl)) {
                $konfigurasi['host'] = $bagianUrl['host'] ?? ($konfigurasi['host'] ?? '127.0.0.1');
                $konfigurasi['port'] = $bagianUrl['port'] ?? ($konfigurasi['port'] ?? 5432);
                $konfigurasi['username'] = isset($bagianUrl['user']) ? rawurldecode($bagianUrl['user']) : ($konfigurasi['username'] ?? '');
                $konfigurasi['password'] = isset($bagianUrl['pass']) ? rawurldecode($bagianUrl['pass']) : ($konfigurasi['password'] ?? '');
                $konfigurasi['database'] = isset($bagianUrl['path']) ? ltrim($bagianUrl['path'], '/') : ($konfigurasi['database'] ?? '');
            }
        }

        return [
            'nama_koneksi' => $namaKoneksi,
            'driver' => (string) ($konfigurasi['driver'] ?? ''),
            'host' => (string) ($konfigurasi['host'] ?? '127.0.0.1'),
            'port' => (int) ($konfigurasi['port'] ?? 5432),
            'database' => (string) ($konfigurasi['database'] ?? ''),
            'username' => (string) ($konfigurasi['username'] ?? ''),
            'password' => (string) ($konfigurasi['password'] ?? ''),
            'sslmode' => (string) ($konfigurasi['sslmode'] ?? 'prefer'),
        ];
    }

    private function cariExecutable(string $nama, ?string $pathKhusus = null): ?string
    {
        $ekstensi = PHP_OS_FAMILY === 'Windows' ? '.exe' : '';
        $calon = [];

        if (filled($pathKhusus)) {
            $calon[] = (string) $pathKhusus;
        }

        $direktoriBin = config('cadangan_database.pg_bin_path');

        if (filled($direktoriBin)) {
            $calon[] = rtrim((string) $direktoriBin, '\\/').DIRECTORY_SEPARATOR.$nama.$ekstensi;
        }

        $ditemukan = (new ExecutableFinder)->find($nama);

        if ($ditemukan) {
            $calon[] = $ditemukan;
        }

        if (PHP_OS_FAMILY === 'Windows') {
            foreach ([
                'C:/Program Files/PostgreSQL/*/bin/'.$nama.'.exe',
                'C:/laragon/bin/postgresql/*/bin/'.$nama.'.exe',
                'D:/laragon/bin/postgresql/*/bin/'.$nama.'.exe',
            ] as $pola) {
                $hasil = glob($pola) ?: [];
                rsort($hasil, SORT_NATURAL);
                array_push($calon, ...$hasil);
            }
        } else {
            array_push($calon, '/usr/bin/'.$nama, '/usr/local/bin/'.$nama);
        }

        foreach (array_unique($calon) as $item) {
            if (is_file($item) && is_readable($item)) {
                return realpath($item) ?: $item;
            }
        }

        return null;
    }

    private function lingkunganPostgres(array $koneksi): array
    {
        return [
            'PGPASSWORD' => $koneksi['password'],
            'PGSSLMODE' => $koneksi['sslmode'] ?: 'prefer',
        ];
    }

    private function pesanExecutableTidakSiap(string $executable): string
    {
        if ($this->konfigurasiKoneksi()['driver'] !== 'pgsql') {
            return 'Fitur cadangan saat ini hanya mendukung database PostgreSQL.';
        }

        return $executable.' belum ditemukan. Isi NUSA_PG_BIN_PATH pada .env dengan lokasi folder bin PostgreSQL, lalu jalankan php artisan optimize:clear.';
    }

    private function pesanProsesGagal(string $proses, Process $process): string
    {
        $detail = trim($process->getErrorOutput() ?: $process->getOutput());
        $detail = str($detail)->limit(900)->toString();

        return $proses.' gagal.'.($detail !== '' ? ' '.$detail : ' Periksa koneksi dan konfigurasi PostgreSQL.');
    }

    private function berkasMemilikiHeaderPgDump(string $path): bool
    {
        if (! is_file($path) || filesize($path) < 5) {
            return false;
        }

        $handle = fopen($path, 'rb');

        if (! $handle) {
            return false;
        }

        try {
            return fread($handle, 5) === 'PGDMP';
        } finally {
            fclose($handle);
        }
    }

    private function pastikanNamaFileAman(string $namaFile): void
    {
        if (! preg_match('/\Anusa-db-[a-z0-9._-]+\.dump\z/i', $namaFile) || basename($namaFile) !== $namaFile) {
            throw new RuntimeException('Nama berkas cadangan tidak valid.');
        }
    }

    private function jenisDariNamaFile(string $namaFile): string
    {
        return match (true) {
            str_contains($namaFile, '-pra-pemulihan-') => 'Pra-pemulihan',
            str_contains($namaFile, '-otomatis-') => 'Otomatis',
            str_contains($namaFile, '-unggahan-') => 'Unggahan',
            default => 'Manual',
        };
    }

    private function catatAktivitas(
        string $aksi,
        string $namaFile,
        string $status,
        ?Pengguna $pengguna,
        string $pesan,
    ): void {
        $disk = Storage::disk('local');
        $lokasiLog = $this->lokasiLog();
        $disk->makeDirectory(dirname($lokasiLog));
        $path = $disk->path($lokasiLog);
        $data = [
            'waktu' => now()->toIso8601String(),
            'aksi' => $aksi,
            'nama_file' => $namaFile,
            'status' => $status,
            'pengguna_id' => $pengguna?->id,
            'pengguna' => $pengguna?->nama ?: 'Sistem otomatis',
            'pesan' => str($pesan)->limit(600)->toString(),
        ];

        file_put_contents(
            $path,
            json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).PHP_EOL,
            FILE_APPEND | LOCK_EX,
        );
    }

    private function ukuranKonfigurasiKeByte(string|false $nilai): int
    {
        if ($nilai === false || trim($nilai) === '') {
            return 0;
        }

        $nilai = trim($nilai);
        $satuan = strtolower(substr($nilai, -1));
        $angka = (float) $nilai;

        return match ($satuan) {
            'g' => (int) ($angka * 1024 * 1024 * 1024),
            'm' => (int) ($angka * 1024 * 1024),
            'k' => (int) ($angka * 1024),
            default => (int) $angka,
        };
    }

    private function formatUkuran(int $byte): string
    {
        if ($byte >= 1024 * 1024 * 1024) {
            return number_format($byte / (1024 * 1024 * 1024), 2, ',', '.').' GB';
        }

        if ($byte >= 1024 * 1024) {
            return number_format($byte / (1024 * 1024), 2, ',', '.').' MB';
        }

        if ($byte >= 1024) {
            return number_format($byte / 1024, 1, ',', '.').' KB';
        }

        return $byte.' B';
    }

    private function direktori(): string
    {
        return trim((string) config('cadangan_database.direktori', 'cadangan-database/berkas'), '/');
    }

    private function lokasiLog(): string
    {
        return trim((string) config('cadangan_database.log_aktivitas', 'cadangan-database/aktivitas.jsonl'), '/');
    }

    private function timeoutDetik(): int
    {
        return max(60, (int) config('cadangan_database.timeout_detik', 900));
    }

    private function longgarkanWaktuEksekusi(): void
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }
    }
}
