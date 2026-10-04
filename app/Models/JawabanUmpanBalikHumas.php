<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JawabanUmpanBalikHumas extends Model
{
    protected $table = 'jawaban_umpan_balik_humas';

    public $timestamps = false;

    protected $fillable = ['pertanyaan_umpan_balik_humas_id', 'nilai', 'teks'];

    protected $hidden = ['sasaran_umpan_balik_humas_id'];

    protected $casts = ['nilai' => 'integer', 'teks' => 'encrypted'];
}
