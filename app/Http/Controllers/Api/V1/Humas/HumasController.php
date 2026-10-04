<?php

namespace App\Http\Controllers\Api\V1\Humas;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

abstract class HumasController extends Controller
{
    protected function staff(Request $request, array|string $izin): void
    {
        $u = $request->user();
        abort_unless($u->aktif && ! $u->akunOrangTua() && ! $u->akunSiswa() && $u->memilikiIzin($izin), 403);
    }

    protected function json(array $data, ?string $pesan = null, int $status = 200): JsonResponse
    {
        return response()->json(['data' => $data] + ($pesan ? ['pesan' => $pesan] : []), $status)
            ->header('Cache-Control', 'private, no-store, max-age=0');
    }

    protected function halaman(Request $request): array
    {
        return $request->validate(['halaman' => ['nullable', 'integer', 'min:1'], 'per_halaman' => ['nullable', 'integer', 'min:1', 'max:50']]);
    }
}
