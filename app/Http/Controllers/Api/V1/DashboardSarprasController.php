<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Mobile\DashboardSarprasMobileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardSarprasController extends Controller
{
    public function __invoke(Request $request, DashboardSarprasMobileService $service): JsonResponse
    {
        return response()
            ->json(['data' => $service->siapkan($request->user())])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }
}
