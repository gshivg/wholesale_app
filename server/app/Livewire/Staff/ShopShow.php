<?php

namespace App\Livewire\Staff;

use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\Shop;
use App\Notifications\AppNotice;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.staff')]
#[Title('Shopkeeper')]
class ShopShow extends Component
{
    public Shop $shop;
    public string $creditLimit = '0';
    public string $note = '';
    public string $repayment = '';

    public function mount(Shop $shop): void
    {
        abort_unless(auth()->user()->hasRole(Role::SuperAdmin, Role::OrderManager), 403);
        $this->shop = $shop;
        $this->creditLimit = (string) (int) $shop->credit_limit;
    }

    private function setStatus(string $status, string $title, string $body): void
    {
        $this->shop->update(['status' => $status, 'status_note' => $this->note ?: null,
            'approved_by' => $status === 'approved' ? auth()->id() : $this->shop->approved_by, 'approved_at' => $status === 'approved' ? now() : $this->shop->approved_at]);
        AuditLog::record("shop.{$status}", $this->shop, ['note' => $this->note]);
        $this->shop->user->notify(new AppNotice($title, $body . ($this->note ? " Note: {$this->note}" : ''), 'account'));
        $this->note = '';
        $this->dispatch('toast', message: "Shop {$status}", kind: 'success');
    }

    public function approve(): void
    {
        $this->validate(['creditLimit' => 'required|numeric|min:0|max:10000000']);
        $this->shop->update(['credit_limit' => $this->creditLimit]);
        $this->setStatus('approved', 'You\'re approved! 🎉', 'Your shop is verified — you can start ordering now.');
    }

    public function reject(): void
    {
        $this->validate(['note' => 'required|min:3'], ['note.required' => 'Please give a reason for rejecting.']);
        $this->setStatus('rejected', 'Registration not approved', 'We could not approve your registration.');
    }

    public function suspend(): void
    {
        $this->validate(['note' => 'required|min:3'], ['note.required' => 'Please give a reason.']);
        $this->setStatus('suspended', 'Account suspended', 'Your account has been suspended.');
    }

    public function saveCredit(): void
    {
        $this->validate(['creditLimit' => 'required|numeric|min:0|max:10000000']);
        $this->shop->update(['credit_limit' => $this->creditLimit]);
        AuditLog::record('shop.credit_limit', $this->shop, ['limit' => $this->creditLimit]);
        $this->dispatch('toast', message: 'Credit limit saved', kind: 'success');
    }

    public function recordRepayment(): void
    {
        $this->validate(['repayment' => 'required|numeric|min:1']);
        $amt = min((float) $this->repayment, (float) $this->shop->credit_used);
        $this->shop->update(['credit_used' => (float) $this->shop->credit_used - $amt]);
        AuditLog::record('shop.credit_repayment', $this->shop, ['amount' => $amt]);
        $this->repayment = '';
        $this->dispatch('toast', message: 'Repayment recorded', kind: 'success');
    }

    public function render()
    {
        $this->shop->refresh()->load('user', 'hub');
        $orders = $this->shop->user->orders();

        return view('livewire.staff.shop-show', [
            's' => $this->shop,
            'orders' => $this->shop->user->orders()->latest()->limit(8)->get(),
            'lifetime' => (float) $this->shop->user->orders()->whereNotIn('status', ['cancelled', 'returned'])->sum('total'),
            'orderCount' => $this->shop->user->orders()->count(),
        ]);
    }
}
