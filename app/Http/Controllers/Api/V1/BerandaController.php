<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Mobile\BerandaMobileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BerandaController extends Controller
{
    public function __invoke(Request $request, BerandaMobileService $service): JsonResponse
    {
        return response()
            ->json(['data' => $service->siapkan($request->user())])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }
}
