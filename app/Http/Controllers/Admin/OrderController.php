<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReleaseShipmentHoldRequest;
use App\Http\Requests\Admin\UpdateOrderBidRequest;
use App\Http\Requests\Admin\UpdateOrderLocationRequest;
use App\Http\Requests\Admin\UpdateOrderShippingRequest;
use App\Http\Requests\Admin\UpdateOrderStatusRequest;
use App\Http\Requests\CancelOrderRequest;
use App\Http\Resources\OrderAuditLogResource;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\Admin\OrderService;
use App\Services\OrderService as CustomerOrderService;
use App\Services\PaymentDeadlineService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(
        private OrderService $orderService,
        // Cancellation rules are identical for both sides of the app — only the
        // milestone lock differs, and that is decided by the actor's role.
        private CustomerOrderService $customerOrders,
        private PaymentDeadlineService $deadlines,
    ) {}

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

    public function updateShipping(UpdateOrderShippingRequest $request, Order $order): JsonResponse
    {
        $updated = $this->orderService->updateShipping($order, $request->validated(), $request->user());

        return $this->okResponse(new OrderResource($updated));
    }

    public function cancel(CancelOrderRequest $request, Order $order): JsonResponse
    {
        $cancelled = $this->customerOrders->cancel($order, $request->user(), $request->validated('reason'));

        return $this->okResponse(new OrderResource($cancelled));
    }

    /**
     * Lift a payment hold by hand (spec 4) — a bank transfer that cleared
     * outside Paystack, a goodwill release, or a hold placed in error. The
     * order returns to the stage it was at when the hold went on.
     */
    public function releaseHold(ReleaseShipmentHoldRequest $request, Order $order): JsonResponse
    {
        $released = $this->deadlines->releaseHoldForOrder($order, $request->user(), $request->validated('reason'));

        return $this->okResponse(new OrderResource($released));
    }

    public function auditLog(Order $order): JsonResponse
    {
        $logs = $order->auditLogs()->with('actor')->latest()->get();

        return $this->okResponse(OrderAuditLogResource::collection($logs));
    }
}
