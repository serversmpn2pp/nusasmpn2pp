<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AcaraBukuTamu extends Model
{
    protected $table = 'acara_buku_tamu';

    public const STATUS = ['draf' => 'Draf', 'dibuka' => 'Dibuka', 'ditutup' => 'Ditutup'];

    public const BANNER_DEFAULT = 'images/acara-buku-tamu-sekolah.webp';

    public const BANNER_TAMPILAN = ['potong' => 'Foto responsif', 'utuh' => 'Gambar utuh'];

    public const BANNER_MAKSIMAL_KB = 4096;

    public const BANNER_MIN_LEBAR = 1000;

    public const BANNER_MIN_TINGGI = 600;

    public const BANNER_MAKSIMAL_SISI = 6000;

    public const BANNER_MAKSIMAL_PIKSEL = 24000000;

    public const IKON_MINAT = [
        'palette' => 'Seni & karya', 'book-open' => 'Buku', 'shapes' => 'Bentuk & numerasi',
        'monitor-smartphone' => 'Teknologi', 'sparkles' => 'Inspirasi', 'trophy' => 'Prestasi',
        'music' => 'Musik', 'mic' => 'Pentas', 'leaf' => 'Lingkungan', 'flask-conical' => 'Sains',
        'camera' => 'Fotografi', 'handshake' => 'Kerja sama',
    ];

    protected $guarded = ['id'];

    protected $hidden = ['kunci_pembuatan'];

    protected $attributes = ['banner_tampilan' => 'potong', 'banner_fokus_desktop' => 66, 'banner_fokus_mobile' => 74];

    protected $casts = ['mulai_pada' => 'datetime', 'selesai_pada' => 'datetime', 'versi' => 'integer', 'pilihan_minat' => 'array',
        'banner_fokus_desktop' => 'integer', 'banner_fokus_mobile' => 'integer'];

    public static function syaratBanner(): array
    {
        return ['format' => ['jpg', 'jpeg', 'png', 'webp'], 'maksimal_kb' => self::BANNER_MAKSIMAL_KB,
            'minimal_lebar' => self::BANNER_MIN_LEBAR, 'minimal_tinggi' => self::BANNER_MIN_TINGGI,
            'maksimal_sisi' => self::BANNER_MAKSIMAL_SISI, 'maksimal_piksel' => self::BANNER_MAKSIMAL_PIKSEL,
            'rekomendasi_landscape' => ['lebar' => 1920, 'tinggi' => 1080], 'rekomendasi_portrait' => ['lebar' => 1200, 'tinggi' => 1500],
            'tampilan' => self::BANNER_TAMPILAN, 'fokus_default' => ['desktop' => 66, 'mobile' => 74]];
    }

    public function gayaBanner(): string
    {
        return '--banner-fokus-desktop:'.max(0, min(100, $this->banner_fokus_desktop)).'%;--banner-fokus-mobile:'.max(0, min(100, $this->banner_fokus_mobile)).'%;';
    }

    public static function pilihanMinatDefault(): array
    {
        $ikon = ['karya_siswa' => 'palette', 'literasi' => 'book-open', 'numerasi' => 'shapes', 'nusa' => 'monitor-smartphone', 'lainnya' => 'sparkles'];

        return collect(ResponAcaraBukuTamu::MINAT)->map(fn ($nama, $kode) => [
            'kode' => $kode, 'nama' => $nama, 'ikon' => $ikon[$kode], 'aktif' => true,
        ])->values()->all();
    }

    public function pilihanMinat(bool $aktifSaja = false): array
    {
        $pilihan = $this->pilihan_minat ?? self::pilihanMinatDefault();

        return $aktifSaja ? array_values(array_filter($pilihan, fn ($p) => $p['aktif'])) : $pilihan;
    }

    public function labelMinat(): array
    {
        return array_column($this->pilihanMinat(), 'nama', 'kode');
    }

    public function namaMinat(array $kode): string
    {
        $labels = $this->labelMinat();

        return implode(', ', array_map(fn ($k) => $labels[$k] ?? $k, $kode));
    }

    public function respon(): HasMany
    {
        return $this->hasMany(ResponAcaraBukuTamu::class);
    }

    public function menerima(): bool
    {
        return $this->status === 'dibuka' && now()->betweenIncluded($this->mulai_pada, $this->selesai_pada->copy()->endOfSecond());
    }

    public function labelPengisian(): string
    {
        if ($this->status !== 'dibuka') {
            return self::STATUS[$this->status];
        }

        return $this->menerima() ? 'Menerima kunjungan' : (now()->lt($this->mulai_pada) ? 'Terjadwal' : 'Periode berakhir');
    }

    public function urlPublik(): string
    {
        $base = rtrim(config('app.url'), '/');
        $lokal = ['localhost', '127.0.0.1', '::1'];
        if (app()->environment('local') && ! app()->runningInConsole()
            && in_array(parse_url($base, PHP_URL_HOST), $lokal, true) && in_array(request()->getHost(), $lokal, true)) {
            $base = request()->getSchemeAndHttpHost();
        }

        return $base.route('kunjungi-acara.show', $this->kode_publik, false);
    }

    public function urlBanner(): string
    {
        return $this->banner ? route('kunjungi-acara.banner', $this->kode_publik) : asset(self::BANNER_DEFAULT);
    }
}
