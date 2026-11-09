<?php

namespace App\Services\Cbt;

use App\Models\UjianCbt;
use Illuminate\Validation\ValidationException;

class KebijakanKeamananAsesmen
{
    public function dapatDiubah(?UjianCbt $asesmen): bool
    {
        return ! $asesmen || ! $asesmen->pesertaUjianCbt()->where(function ($query) {
            $query->whereNotNull('waktu_mulai')
                ->orWhereIn('status', ['sedang_mengerjakan', 'terblokir', 'selesai']);
        })->exists();
    }

    public function pengaturan(array $data, ?UjianCbt $asesmen = null): array
    {
        $mode = $data['tindakan_pindah_aplikasi'] ?? $asesmen?->tindakan_pindah_aplikasi ?? 'catat';
        $batas = $mode === 'tahan'
            ? (int) ($data['batas_pindah_aplikasi'] ?? $asesmen?->batas_pindah_aplikasi ?? 3)
            : (int) ($asesmen?->batas_pindah_aplikasi ?? 3);
        if (! in_array($mode, ['catat', 'tahan'], true) || $batas < 1 || $batas > 20) {
            throw ValidationException::withMessages(['tindakan_pindah_aplikasi' => 'Pilih mode dan batas kejadian yang valid.']);
        }

        $berubah = $asesmen && ($mode !== $asesmen->tindakan_pindah_aplikasi
            || ($mode === 'tahan' && $batas !== (int) $asesmen->batas_pindah_aplikasi));
        if ($berubah && ! $this->dapatDiubah($asesmen)) {
            throw ValidationException::withMessages([
                'tindakan_pindah_aplikasi' => 'Mode dan batas kejadian tidak dapat diubah setelah ada siswa mulai mengerjakan.',
            ]);
        }
        if ($mode === 'tahan' && (! $asesmen || $asesmen->tindakan_pindah_aplikasi !== 'tahan')
            && ! filter_var($data['konfirmasi_mode_tahan'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            throw ValidationException::withMessages([
                'konfirmasi_mode_tahan' => 'Konfirmasikan bahwa siswa akan ditahan setelah batas kejadian. Gunakan formulir terbaru di web atau perbarui aplikasi NUSA.',
            ]);
        }

        return [...UjianCbt::KEAMANAN_ASESMEN_DEFAULT,
            'tindakan_pindah_aplikasi' => $mode, 'batas_pindah_aplikasi' => $batas];
    }
}
