<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PertanyaanUmpanBalikHumas extends Model
{
    public const JENIS = ['skala' => 'Skala kepuasan', 'teks' => 'Jawaban tertulis'];

    public const SKALA = [1 => 'Sangat tidak puas', 2 => 'Kurang puas', 3 => 'Puas', 4 => 'Sangat puas', 0 => 'Tidak menilai'];

    protected $table = 'pertanyaan_umpan_balik_humas';

    protected $fillable = ['urutan', 'jenis', 'teks', 'wajib'];

    protected $casts = ['wajib' => 'boolean'];
}
