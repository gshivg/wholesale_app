<?php

namespace App\Livewire\Portal;

use App\Services\TicketService;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.portal')]
#[Title('Support')]
class Tickets extends Component
{
    use WithFileUploads;

    #[Url(as: 'order')]
    public ?int $orderId = null;
    public bool $creating = false;
    public string $category = 'other', $subject = '', $message = '';
    public ?int $order_id = null;
    public array $photos = [];

    public function mount(): void
    {
        if ($this->orderId) {
            $this->creating = true;
            $this->order_id = $this->orderId;
            $this->category = 'damaged';
        }
    }

    public function create()
    {
        $data = $this->validate([
            'category' => ['required', Rule::in(array_column(config('shared.support.categories'), 'key'))],
            'subject' => 'required|string|max:150',
            'message' => 'required|string|max:3000',
            'order_id' => ['nullable', Rule::exists('orders', 'id')->where('user_id', auth()->id())],
            'photos' => 'array|max:4', 'photos.*' => 'image|max:5120',
        ]);
        $files = collect($this->photos)->map(fn ($f) => $f->store('tickets', 'public'))->all();
        $ticket = app(TicketService::class)->open(auth()->user(), $data, $files);
        session()->flash('status', "Ticket {$ticket->number} created. An agent will reply soon.");

        return redirect()->route('portal.tickets.show', $ticket);
    }

    public function render()
    {
        return view('livewire.portal.tickets', [
            'tickets' => auth()->user()->tickets()->with('agent')->latest('last_reply_at')->get(),
            'orders' => auth()->user()->orders()->latest()->limit(20)->get(['id', 'number']),
            'categories' => config('shared.support.categories'),
        ]);
    }
}
