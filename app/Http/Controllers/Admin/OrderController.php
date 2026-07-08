<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateOrderBidRequest;
use App\Http\Requests\Admin\UpdateOrderLocationRequest;
use App\Http\Requests\Admin\UpdateOrderStatusRequest;
use App\Http\Resources\OrderAuditLogResource;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\Admin\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(private OrderService $orderService) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->input('per_page', 15), 100);

        return $this->okResponse(OrderResource::collection($this->orderService->list($perPage)));
    }

    public function show(Order $order): JsonResponse
    {
        return $this->okResponse(new OrderResource($this->orderService->find($order)));
    }

    public function updateStatus(UpdateOrderStatusRequest $request, Order $order): JsonResponse
    {
        $updated = $this->orderService->updateStatus($order, $request->validated('status'), $request->user());

        return $this->okResponse(new OrderResource($updated));
    }

    public function updateBid(UpdateOrderBidRequest $request, Order $order): JsonResponse
    {
        $updated = $this->orderService->updateBid($order, $request->validated(), $request->user());

        return $this->okResponse(new OrderResource($updated));
    }

    public function updateLocation(UpdateOrderLocationRequest $request, Order $order): JsonResponse
    {
        $updated = $this->orderService->updateLocation($order, $request->validated(), $request->user());

        return $this->okResponse(new OrderResource($updated));
    }

    public function auditLog(Order $order): JsonResponse
    {
        $logs = $order->auditLogs()->with('actor')->latest()->get();

        return $this->okResponse(OrderAuditLogResource::collection($logs));
    }
}
