<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Mobile\MenuMobileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MenuController extends Controller
{
    public function __invoke(Request $request, MenuMobileService $service): JsonResponse
    {
        return response()
            ->json(['data' => $service->siapkan($request->user())])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }
}
