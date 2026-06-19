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

class TicketController extends Controller
{
    public function __construct(private TicketService $ticketService) {}

    public function index(): JsonResponse
    {
        $paginated = $this->ticketService->listAll();

        return $this->okResponse([
            'data'  => TicketResource::collection($paginated->items()),
            'stats' => $this->ticketService->stats(),
            'meta'  => [
                'current_page' => $paginated->currentPage(),
                'last_page'    => $paginated->lastPage(),
                'per_page'     => $paginated->perPage(),
                'total'        => $paginated->total(),
            ],
        ]);
    }

    public function show(Ticket $ticket): JsonResponse
    {
        return $this->okResponse(new TicketResource($this->ticketService->find($ticket)));
    }

    public function reply(StoreTicketMessageRequest $request, Ticket $ticket): JsonResponse
    {
        $message = $this->ticketService->reply($ticket, $request->user(), $request->validated('body'));

        return $this->createdResponse(new TicketMessageResource($message->load('user')));
    }

    public function updateStatus(UpdateTicketStatusRequest $request, Ticket $ticket): JsonResponse
    {
        return $this->okResponse(new TicketResource($this->ticketService->updateStatus($ticket, $request->validated('status'))));
    }
}
