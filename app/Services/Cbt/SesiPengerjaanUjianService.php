<?php

namespace App\Services\Cbt;

use App\Models\PesertaUjianCbt;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SesiPengerjaanUjianService
{
    public function identitas(Request $request, bool $mobile): string
    {
        if ($mobile) {
            // Sanctum credential authenticated by the server, not an editable device label.
            $credential = $request->user()?->currentAccessToken();
            $tokenId = $credential && method_exists($credential, 'getKey') ? $credential->getKey() : null;
            abort_unless($tokenId, 401, 'Silakan masuk kembali untuk mengikuti ujian.');

            return hash('sha256', 'mobile:'.$request->user()->id.':'.$tokenId);
        }

        if (! $request->session()->has('cbt_identitas_perangkat')) {
            $request->session()->put('cbt_identitas_perangkat', Str::random(64));
        }

        return hash('sha256', 'web:'.$request->user()->id.':'.$request->session()->get('cbt_identitas_perangkat'));
    }

    // Call while holding the participant lock, keeping that lock through answer writes.
    public function pastikan(PesertaUjianCbt $peserta, string $identitas, string $saluran, string $perangkat, bool $bolehMengikat, bool $mengikat = true): void
    {
        $peserta->loadMissing('ujianCbt');
        if (! $peserta->ujianCbt->batasi_satu_perangkat
            || ! in_array($peserta->status, ['sedang_mengerjakan', 'terblokir'], true)) {
            return;
        }

        if ($peserta->sesi_ujian_hash) {
            if (! hash_equals($peserta->sesi_ujian_hash, $identitas)) {
                $this->tolak();
            }

            return;
        }

        if (! $bolehMengikat) {
            throw ValidationException::withMessages(['perangkat' => 'Buka kembali halaman ujian untuk melanjutkan sesi. Jika berganti perangkat, hubungi pengawas.']);
        }

        // Adopt legacy sessions without stealing a device already recorded before deployment.
        if (filled($peserta->perangkat_terakhir)
            && ($saluran === 'web'
                ? ! str_starts_with($peserta->perangkat_terakhir, 'Web')
                : ! hash_equals($peserta->perangkat_terakhir, trim($perangkat)))) {
            $this->tolak();
        }

        if (! $mengikat) {
            return;
        }

        $peserta->forceFill([
            'sesi_ujian_hash' => $identitas,
            'sesi_ujian_saluran' => $saluran,
            'sesi_ujian_mulai_pada' => now(),
            'perangkat_terakhir' => trim($perangkat),
        ])->save();
    }

    private function tolak(): never
    {
        throw ValidationException::withMessages(['perangkat' => 'Ujian sedang aktif di sesi atau perangkat lain. Hubungi pengawas untuk reset perangkat sebelum melanjutkan.']);
    }
}
