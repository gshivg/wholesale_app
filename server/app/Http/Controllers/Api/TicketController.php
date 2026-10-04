<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Support\Presenter;
use App\Models\Ticket;
use App\Services\TicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TicketController extends Controller
{
    public function __construct(private TicketService $tickets) {}

    public function index(Request $request): JsonResponse
    {
        $page = $request->user()->tickets()->with('order', 'agent')->latest('last_reply_at')->paginate(20);

        return response()->json(['data' => $page->getCollection()->map(fn ($t) => Presenter::ticket($t))->values(),
            'meta' => ['page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()]]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'category' => ['required', Rule::in(array_column(config('shared.support.categories'), 'key'))],
            'subject' => 'required|string|max:150',
            'message' => 'required|string|max:3000',
            'order_id' => ['nullable', Rule::exists('orders', 'id')->where('user_id', $request->user()->id)],
            'photos' => 'nullable|array|max:4',
            'photos.*' => 'image|max:5120',
        ]);
        $ticket = $this->tickets->open($request->user(), $data, $this->store_files($request));

        return response()->json(['data' => Presenter::ticket($ticket, true)], 201);
    }

    public function show(Request $request, int $ticket): JsonResponse
    {
        return response()->json(['data' => Presenter::ticket($this->mine($request, $ticket), true)]);
    }

    public function reply(Request $request, int $ticket): JsonResponse
    {
        $t = $this->mine($request, $ticket);
        $data = $request->validate(['message' => 'required|string|max:3000', 'photos' => 'nullable|array|max:4', 'photos.*' => 'image|max:5120']);
        $this->tickets->reply($t, $request->user(), $data['message'], $this->store_files($request));

        return response()->json(['data' => Presenter::ticket($t->fresh(), true)]);
    }

    public function close(Request $request, int $ticket): JsonResponse
    {
        $t = $this->tickets->setStatus($this->mine($request, $ticket), 'closed');

        return response()->json(['data' => Presenter::ticket($t, true)]);
    }

    public function rate(Request $request, int $ticket): JsonResponse
    {
        $data = $request->validate(['rating' => 'required|integer|between:1,5']);
        $t = $this->mine($request, $ticket);
        abort_unless(in_array($t->status, ['resolved', 'closed'], true), 422, 'You can rate a ticket once it is resolved.');
        $t->update(['rating' => $data['rating']]);

        return response()->json(['data' => Presenter::ticket($t, true)]);
    }

    private function mine(Request $request, int $id): Ticket
    {
        return $request->user()->tickets()->findOrFail($id);
    }

    private function store_files(Request $request): array
    {
        return collect($request->file('photos', []))->map(fn ($f) => $f->store('tickets', 'public'))->all();
    }
}
