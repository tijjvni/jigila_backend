<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateOrderBidRequest;
use App\Http\Requests\Admin\UpdateOrderLocationRequest;
use App\Http\Requests\Admin\UpdateOrderStatusRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\Admin\OrderService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OrderController extends Controller
{
    public function __construct(private OrderService $orderService) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = min((int) $request->input('per_page', 15), 100);

        return OrderResource::collection($this->orderService->list($perPage));
    }

    public function show(Order $order): OrderResource
    {
        return new OrderResource($this->orderService->find($order));
    }

    public function updateStatus(UpdateOrderStatusRequest $request, Order $order): OrderResource
    {
        return new OrderResource($this->orderService->updateStatus($order, $request->validated('status')));
    }

    public function updateBid(UpdateOrderBidRequest $request, Order $order): OrderResource
    {
        return new OrderResource($this->orderService->updateBid($order, $request->validated()));
    }

    public function updateLocation(UpdateOrderLocationRequest $request, Order $order): OrderResource
    {
        return new OrderResource($this->orderService->updateLocation($order, $request->validated()));
    }
}
