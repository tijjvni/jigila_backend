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

class TicketController extends Controller
{
    public function __construct(private TicketService $ticketService) {}

    public function index(Request $request): JsonResponse
    {
        $paginated = $this->ticketService->list($request->user());
        return $this->okResponse(TicketResource::collection($paginated));
    }

    public function store(StoreTicketRequest $request): JsonResponse
    {
        $ticket = $this->ticketService->create(
            $request->user(),
            $request->validated('subject'),
            $request->validated('body'),
        );

        return $this->createdResponse(new TicketResource($ticket->load(['messages.user', 'user'])));
    }

    public function show(Request $request, Ticket $ticket): JsonResponse
    {
        $this->ticketService->authorize($request->user(), $ticket);

        return $this->okResponse(new TicketResource($this->ticketService->find($ticket)));
    }

    public function reply(StoreTicketMessageRequest $request, Ticket $ticket): JsonResponse
    {
        $this->ticketService->authorize($request->user(), $ticket);

        $paths = [];
        foreach ($request->file('attachments', []) as $file) {
            $paths[] = $file->store('ticket-attachments', 'public');
        }

        $message = $this->ticketService->reply($ticket, $request->user(), $request->validated('body'), $paths);

        return $this->createdResponse(new TicketMessageResource($message->load('user')));
    }
}
