<?php

namespace App\Services;

use App\Models\Pengguna;
use Illuminate\Validation\ValidationException;

class SinkronisasiUsernameAkunService
{
    public function siapkanUsername(
        ?Pengguna $pengguna,
        ?string $identitas,
        string $namaField,
        string $labelIdentitas,
    ): ?string {
        if (! $pengguna) {
            return null;
        }

        $username = preg_replace('/\s+/', '', trim((string) $identitas));
        if ($username === '') {
            throw ValidationException::withMessages([
                $namaField => "{$labelIdentitas} tidak boleh dikosongkan karena akun login sudah tersedia.",
            ]);
        }

        $sudahDigunakan = Pengguna::query()
            ->where('username', $username)
            ->whereKeyNot($pengguna->id)
            ->exists();

        if ($sudahDigunakan) {
            throw ValidationException::withMessages([
                $namaField => "{$labelIdentitas} tersebut sudah digunakan sebagai username akun lain.",
            ]);
        }

        return $username;
    }

    public function sinkronkan(?Pengguna $pengguna, ?string $username): bool
    {
        if (! $pengguna || ! $username || $pengguna->username === $username) {
            return false;
        }

        $pengguna->update(['username' => $username]);

        return true;
    }
}
