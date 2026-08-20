<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KonfirmasiBerhalanganIbadah extends Model
{
    public const HASIL_MASIH_BERHALANGAN = 'masih_berhalangan';

    public const HASIL_SELESAI = 'selesai';

    public const DAFTAR_HASIL = [
        self::HASIL_MASIH_BERHALANGAN => 'Masih berhalangan',
        self::HASIL_SELESAI => 'Sudah selesai',
    ];

    protected $table = 'konfirmasi_berhalangan_ibadah';

    protected $fillable = [
        'periode_berhalangan_ibadah_id',
        'dikonfirmasi_oleh_pengguna_id',
        'hasil',
        'dikonfirmasi_pada',
        'konfirmasi_berikutnya_pada',
        'catatan_privat',
    ];

    protected $casts = [
        'dikonfirmasi_pada' => 'datetime',
        'konfirmasi_berikutnya_pada' => 'date',
    ];

    public function periodeBerhalanganIbadah(): BelongsTo
    {
        return $this->belongsTo(PeriodeBerhalanganIbadah::class);
    }

    public function dikonfirmasiOlehPengguna(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'dikonfirmasi_oleh_pengguna_id');
    }

    public function labelHasil(): string
    {
        return self::DAFTAR_HASIL[$this->hasil] ?? str($this->hasil)->headline()->toString();
    }
}
