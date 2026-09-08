<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Mobile\MenuMobileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MenuController extends Controller
{
    public function __invoke(Request $request, MenuMobileService $service): JsonResponse
    {
        return response()
            ->json(['data' => $service->siapkan($request->user())])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }

    public function updateAksesCepat(Request $request, MenuMobileService $service): JsonResponse
    {
        $data = $request->validate([
            'kode_menu' => ['required', 'array', 'min:1', 'max:4'],
            'kode_menu.*' => [
                'required',
                'string',
                'max:100',
                'distinct',
                Rule::in($service->kodeMenuTersedia($request->user())),
            ],
        ]);

        return response()
            ->json([
                'message' => 'Akses cepat berhasil disimpan.',
                'data' => $service->simpanAksesCepat($request->user(), $data['kode_menu']),
            ])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }

    public function resetAksesCepat(Request $request, MenuMobileService $service): JsonResponse
    {
        return response()
            ->json([
                'message' => 'Akses cepat dikembalikan ke rekomendasi.',
                'data' => $service->resetAksesCepat($request->user()),
            ])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }
}
