<?php

namespace App\Http\Controllers;

use App\Http\Requests\Order\StoreOrderRequest;
use App\Http\Requests\Order\UpdateOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OrderController extends Controller
{
    public function __construct(private OrderService $orderService) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = min((int) $request->input('per_page', 15), 100);

        return OrderResource::collection($this->orderService->list($request->user(), $perPage));
    }

    public function store(StoreOrderRequest $request): JsonResponse
    {
        $order = $this->orderService->create($request->user(), $request->validated());

        return (new OrderResource($order))->response()->setStatusCode(201);
    }

    public function show(Request $request, Order $order): OrderResource
    {
        $this->orderService->authorize($request->user(), $order);

        return new OrderResource($this->orderService->find($order));
    }

    public function update(UpdateOrderRequest $request, Order $order): OrderResource
    {
        $this->orderService->authorize($request->user(), $order);

        return new OrderResource($this->orderService->update($order, $request->validated()));
    }

    public function destroy(Request $request, Order $order): JsonResponse
    {
        $this->orderService->authorize($request->user(), $order);
        $this->orderService->delete($order);

        return response()->json(['message' => 'Order deleted.']);
    }
}
