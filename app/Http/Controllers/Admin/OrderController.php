<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateOrderBidRequest;
use App\Http\Requests\Admin\UpdateOrderLocationRequest;
use App\Http\Requests\Admin\UpdateOrderStatusRequest;
use App\Http\Resources\OrderAuditLogResource;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\OrderAuditLog;
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
        $old     = ['status' => $order->status];
        $updated = $this->orderService->updateStatus($order, $request->validated('status'));

        OrderAuditLog::create([
            'order_id'   => $order->id,
            'user_id'    => $request->user()->id,
            'action'     => 'status_changed',
            'old_values' => $old,
            'new_values' => ['status' => $updated->status],
        ]);

        return $this->okResponse(new OrderResource($updated));
    }

    public function updateBid(UpdateOrderBidRequest $request, Order $order): JsonResponse
    {
        $old     = ['bid_status' => $order->bid_status, 'out_bid_price' => $order->out_bid_price];
        $updated = $this->orderService->updateBid($order, $request->validated(), $request->user());

        OrderAuditLog::create([
            'order_id'   => $order->id,
            'user_id'    => $request->user()->id,
            'action'     => 'bid_updated',
            'old_values' => $old,
            'new_values' => ['bid_status' => $updated->bid_status, 'out_bid_price' => $updated->out_bid_price],
        ]);

        return $this->okResponse(new OrderResource($updated));
    }

    public function updateLocation(UpdateOrderLocationRequest $request, Order $order): JsonResponse
    {
        $locationFields = ['pickup_location', 'departure_port', 'destination_port'];
        $old            = $order->only($locationFields);
        $incoming       = array_filter($request->validated(), fn ($v) => $v !== null);
        $updated        = $this->orderService->updateLocation($order, $request->validated());

        OrderAuditLog::create([
            'order_id'   => $order->id,
            'user_id'    => $request->user()->id,
            'action'     => 'location_updated',
            'old_values' => array_intersect_key($old, $incoming),
            'new_values' => $incoming,
        ]);

        return $this->okResponse(new OrderResource($updated));
    }

    public function auditLog(Order $order): JsonResponse
    {
        $logs = $order->auditLogs()->with('actor')->latest()->get();

        return $this->okResponse(OrderAuditLogResource::collection($logs));
    }
}
