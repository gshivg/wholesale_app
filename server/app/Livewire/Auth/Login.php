<?php

namespace App\Livewire\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.public')]
#[Title('Log in')]
class Login extends Component
{
    public string $phone = '';
    public string $password = '';
    public bool $remember = true;

    public function login()
    {
        $this->validate(['phone' => 'required', 'password' => 'required']);
        $key = 'login:' . request()->ip() . ':' . $this->phone;
        if (RateLimiter::tooManyAttempts($key, 8)) {
            $this->addError('phone', 'Too many attempts. Try again in ' . RateLimiter::availableIn($key) . ' seconds.');

            return;
        }
        $user = User::where('phone', trim($this->phone))->first();
        if (! $user || ! Auth::validate(['phone' => $user->phone, 'password' => $this->password])) {
            RateLimiter::hit($key, 60);
            $this->addError('phone', 'Incorrect mobile number or password.');

            return;
        }
        if (! $user->is_active) {
            $this->addError('phone', 'This account is disabled. Please contact support.');

            return;
        }
        if (! $user->isStaff() && ! $user->phone_verified_at) {
            session(['verify_phone' => $user->phone]);

            return $this->redirect(route('register') . '?verify=1', navigate: true);
        }
        RateLimiter::clear($key);
        Auth::login($user, $this->remember);
        session()->regenerate();

        return redirect()->intended($user->role->home());
    }

    public function render()
    {
        return view('livewire.auth.login');
    }
}
