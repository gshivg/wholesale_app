<?php

namespace App\Livewire\Staff\Support;

use App\Enums\Role;
use App\Models\Ticket;
use App\Models\User;
use App\Services\TicketService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.staff')]
#[Title('Ticket')]
class TicketShow extends Component
{
    public Ticket $ticket;
    public string $body = '';
    public bool $internal = false;
    public ?int $assignee = null;

    public const CANNED = [
        'Sorry for the trouble — we are looking into this right now.',
        'Could you share a photo of the affected cartons and the invoice number?',
        'We have raised a credit note; it will reflect on your next order.',
        'A replacement will be sent with your next delivery to your area.',
    ];

    public function mount(Ticket $ticket): void
    {
        abort_unless(auth()->user()->hasRole(Role::Support, Role::SuperAdmin), 403);
        $this->ticket = $ticket;
        $this->assignee = $ticket->assigned_to;
    }

    public function send(): void
    {
        $this->validate(['body' => 'required|string|max:3000']);
        app(TicketService::class)->reply($this->ticket, auth()->user(), $this->body, [], $this->internal);
        $this->reset('body', 'internal');
    }

    public function setStatus(string $status): void
    {
        app(TicketService::class)->setStatus($this->ticket, $status, auth()->user());
        $this->dispatch('toast', message: 'Ticket ' . str_replace('_', ' ', $status), kind: 'success');
    }

    public function claim(): void
    {
        $this->ticket->update(['assigned_to' => auth()->id()]);
        $this->assignee = auth()->id();
    }

    public function reassign(): void
    {
        $this->ticket->update(['assigned_to' => $this->assignee ?: null]);
        if ($this->assignee) {
            User::find($this->assignee)?->notify(new \App\Notifications\AppNotice('Ticket assigned to you', "{$this->ticket->number}: {$this->ticket->subject}", 'ticket', ['ticket_id' => $this->ticket->id]));
        }
        $this->dispatch('toast', message: 'Ticket reassigned', kind: 'success');
    }

    public function render()
    {
        $this->ticket->refresh()->load('user.shop', 'agent', 'order');

        return view('livewire.staff.support.ticket-show', [
            't' => $this->ticket,
            'messages' => $this->ticket->messages()->with('user')->get(),
            'agents' => User::where('role', Role::Support->value)->where('is_active', true)->get(),
            'orders' => $this->ticket->user->orders()->latest()->limit(5)->get(),
            'canned' => self::CANNED,
        ]);
    }
}
