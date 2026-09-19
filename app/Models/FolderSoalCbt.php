<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class FolderSoalCbt extends Model
{
    protected $table = 'folder_soal_cbt';

    protected $fillable = ['mata_pelajaran_id', 'tingkat', 'nama', 'keterangan'];

    public function soal(): BelongsToMany
    {
        return $this->belongsToMany(SoalCbt::class, 'anggota_folder_soal_cbt', 'folder_soal_cbt_id', 'soal_cbt_id');
    }

    public function mataPelajaran(): BelongsTo
    {
        return $this->belongsTo(MataPelajaran::class);
    }
}
