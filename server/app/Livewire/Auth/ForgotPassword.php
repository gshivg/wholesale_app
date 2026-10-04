<?php

namespace App\Livewire\Auth;

use App\Models\User;
use App\Services\OtpService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.public')]
#[Title('Reset password')]
class ForgotPassword extends Component
{
    public int $step = 1;
    public string $phone = '', $code = '', $password = '', $password_confirmation = '';
    public ?string $hint = null;

    public function send(): void
    {
        $this->validate(['phone' => ['required', 'regex:/^[6-9]\d{9}$/']]);
        if (User::where('phone', $this->phone)->exists()) {
            $this->hint = app(OtpService::class)->send($this->phone, 'reset');
        }
        $this->step = 2;
    }

    public function reset_password()
    {
        $this->validate(['code' => 'required|digits:' . config('shared.otp.length'), 'password' => 'required|min:8|confirmed']);
        $user = User::where('phone', $this->phone)->first();
        if (! $user || ! app(OtpService::class)->verify($this->phone, 'reset', $this->code)) {
            $this->addError('code', 'Incorrect or expired OTP.');

            return;
        }
        $user->update(['password' => $this->password]);
        session()->flash('status', 'Password updated. Please log in.');

        return redirect()->route('login');
    }

    public function render()
    {
        return view('livewire.auth.forgot-password');
    }
}
