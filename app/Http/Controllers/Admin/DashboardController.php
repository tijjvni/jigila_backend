<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\DashboardResource;
use App\Services\Admin\DashboardService;

class DashboardController extends Controller
{
    public function __construct(private DashboardService $dashboardService) {}

    public function index(): DashboardResource
    {
        return new DashboardResource($this->dashboardService->stats());
    }
}
