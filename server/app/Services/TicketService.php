<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use App\Notifications\AppNotice;
use Illuminate\Support\Facades\DB;

class TicketService
{
    public function open(User $user, array $data, array $attachments = []): Ticket
    {
        return DB::transaction(function () use ($user, $data, $attachments) {
            $ticket = Ticket::create([
                'number' => $this->nextNumber(),
                'user_id' => $user->id,
                'order_id' => $data['order_id'] ?? null,
                'category' => $data['category'],
                'subject' => $data['subject'],
                'priority' => in_array($data['category'], ['damaged', 'missing', 'wrong', 'payment'], true) ? 'high' : 'normal',
                'last_reply_at' => now(),
            ]);
            $ticket->messages()->create(['user_id' => $user->id, 'body' => $data['message'], 'attachments' => $attachments ?: null]);
            $this->autoAssign($ticket);

            return $ticket->fresh();
        });
    }

    /** Least-loaded active support agent gets the ticket (ties → lowest id). */
    public function autoAssign(Ticket $ticket): ?User
    {
        $agent = User::where('role', Role::Support->value)->where('is_active', true)
            ->withCount(['assignedTickets as open_count' => fn ($q) => $q->whereIn('status', ['open', 'in_progress'])])
            ->orderBy('open_count')->orderBy('id')->first();
        if ($agent) {
            $ticket->update(['assigned_to' => $agent->id]);
            $agent->notify(new AppNotice('New ticket assigned', "{$ticket->number}: {$ticket->subject}", 'ticket', ['ticket_id' => $ticket->id]));
        }

        return $agent;
    }

    public function reply(Ticket $ticket, User $by, string $body, array $attachments = [], bool $internal = false): TicketMessage
    {
        return DB::transaction(function () use ($ticket, $by, $body, $attachments, $internal) {
            $msg = $ticket->messages()->create([
                'user_id' => $by->id, 'body' => $body, 'attachments' => $attachments ?: null, 'is_internal' => $internal,
            ]);
            if ($internal) {
                return $msg;
            }
            $updates = ['last_reply_at' => now()];
            if ($by->isStaff()) {
                $updates['first_response_at'] = $ticket->first_response_at ?? now();
                if ($ticket->status === 'open') {
                    $updates['status'] = 'in_progress';
                }
                if (! $ticket->assigned_to && $by->role === Role::Support) {
                    $updates['assigned_to'] = $by->id;
                }
                $ticket->user->notify(new AppNotice("Reply on {$ticket->number}", str($body)->limit(80)->toString(), 'ticket', ['ticket_id' => $ticket->id]));
            } else {
                if (in_array($ticket->status, ['resolved', 'closed'], true)) {
                    $updates['status'] = 'open';
                    $updates['resolved_at'] = null;
                }
                $ticket->agent?->notify(new AppNotice("Customer replied on {$ticket->number}", str($body)->limit(80)->toString(), 'ticket', ['ticket_id' => $ticket->id]));
            }
            $ticket->update($updates);

            return $msg;
        });
    }

    public function setStatus(Ticket $ticket, string $status, ?User $by = null): Ticket
    {
        abort_unless(in_array($status, config('shared.support.statuses'), true), 422);
        $ticket->update([
            'status' => $status,
            'resolved_at' => in_array($status, ['resolved', 'closed'], true) ? ($ticket->resolved_at ?? now()) : null,
        ]);
        if ($by?->isStaff() && in_array($status, ['resolved', 'closed'], true)) {
            $ticket->user->notify(new AppNotice("{$ticket->number} " . $status, 'Your ticket was marked ' . $status . '. Reply to reopen it.', 'ticket', ['ticket_id' => $ticket->id]));
        }

        return $ticket;
    }

    private function nextNumber(): string
    {
        do {
            $n = 'TKT-' . random_int(10000, 99999);
        } while (Ticket::where('number', $n)->exists());

        return $n;
    }
}
