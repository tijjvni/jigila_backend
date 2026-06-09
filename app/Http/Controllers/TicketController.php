<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTicketMessageRequest;
use App\Http\Requests\StoreTicketRequest;
use App\Http\Resources\TicketMessageResource;
use App\Http\Resources\TicketResource;
use App\Models\Ticket;
use App\Services\TicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TicketController extends Controller
{
    public function __construct(private TicketService $ticketService) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return TicketResource::collection($this->ticketService->list($request->user()));
    }

    public function store(StoreTicketRequest $request): TicketResource
    {
        $ticket = $this->ticketService->create(
            $request->user(),
            $request->validated('subject'),
            $request->validated('body'),
        );

        return new TicketResource($ticket->load(['messages.user', 'user']));
    }

    public function show(Request $request, Ticket $ticket): TicketResource
    {
        $this->ticketService->authorize($request->user(), $ticket);

        return new TicketResource($this->ticketService->find($ticket));
    }

    public function reply(StoreTicketMessageRequest $request, Ticket $ticket): JsonResponse
    {
        $this->ticketService->authorize($request->user(), $ticket);

        $message = $this->ticketService->reply($ticket, $request->user(), $request->validated('body'));

        return response()->json(['data' => new TicketMessageResource($message->load('user'))], 201);
    }
}
