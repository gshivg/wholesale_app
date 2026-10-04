<?php

namespace App\Livewire\Portal;

use App\Models\Ticket;
use App\Services\TicketService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.portal')]
#[Title('Ticket')]
class TicketShow extends Component
{
    use WithFileUploads;

    public Ticket $ticket;
    public string $body = '';
    public array $photos = [];

    public function mount(Ticket $ticket): void
    {
        abort_unless($ticket->user_id === auth()->id(), 404);
        $this->ticket = $ticket;
    }

    public function send(): void
    {
        $this->validate(['body' => 'required|string|max:3000', 'photos' => 'array|max:4', 'photos.*' => 'image|max:5120']);
        $files = collect($this->photos)->map(fn ($f) => $f->store('tickets', 'public'))->all();
        app(TicketService::class)->reply($this->ticket, auth()->user(), $this->body, $files);
        $this->reset('body', 'photos');
    }

    public function close(): void
    {
        app(TicketService::class)->setStatus($this->ticket, 'closed');
    }

    public function rate(int $n): void
    {
        abort_unless(in_array($this->ticket->status, ['resolved', 'closed'], true), 422);
        $this->ticket->update(['rating' => max(1, min(5, $n))]);
        $this->dispatch('toast', message: 'Thanks for your feedback!', kind: 'success');
    }

    public function render()
    {
        $this->ticket->refresh()->load('agent', 'order');

        return view('livewire.portal.ticket-show', [
            't' => $this->ticket,
            'messages' => $this->ticket->messages()->where('is_internal', false)->with('user')->get(),
        ]);
    }
}
