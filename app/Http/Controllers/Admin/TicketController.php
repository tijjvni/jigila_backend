<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateTicketStatusRequest;
use App\Http\Requests\StoreTicketMessageRequest;
use App\Http\Resources\TicketMessageResource;
use App\Http\Resources\TicketResource;
use App\Models\Ticket;
use App\Services\TicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TicketController extends Controller
{
    public function __construct(private TicketService $ticketService) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'data'  => TicketResource::collection($this->ticketService->listAll()),
            'stats' => $this->ticketService->stats(),
        ]);
    }

    public function show(Ticket $ticket): TicketResource
    {
        return new TicketResource($this->ticketService->find($ticket));
    }

    public function reply(StoreTicketMessageRequest $request, Ticket $ticket): JsonResponse
    {
        $message = $this->ticketService->reply($ticket, $request->user(), $request->validated('body'));

        return response()->json(['data' => new TicketMessageResource($message->load('user'))], 201);
    }

    public function updateStatus(UpdateTicketStatusRequest $request, Ticket $ticket): TicketResource
    {
        return new TicketResource($this->ticketService->updateStatus($ticket, $request->validated('status')));
    }
}
