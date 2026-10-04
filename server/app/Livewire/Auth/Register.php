<?php

namespace App\Livewire\Auth;

use App\Http\Controllers\Api\AuthController;
use App\Models\User;
use App\Services\OtpService;
use App\Support\ServiceAreas;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.public')]
#[Title('Register your shop')]
class Register extends Component
{
    use WithFileUploads;

    public int $step = 1;
    public string $name = '', $phone = '', $email = '', $password = '', $password_confirmation = '';
    public string $shop_name = '', $shop_type = '', $pan = '', $gstin = '';
    public string $address_line1 = '', $address_line2 = '', $landmark = '', $city = '', $pincode = '';
    public $shop_photo;
    public string $otp = '';
    public ?string $otpHint = null;
    public ?string $verifyPhone = null;

    public function mount(): void
    {
        $this->shop_type = config('shared.shopTypes')[0];
        if (request()->boolean('verify') && session('verify_phone')) {
            $this->verifyPhone = session('verify_phone');
            $this->step = 4;
            $this->otpHint = app(OtpService::class)->send($this->verifyPhone, 'register');
        }
    }

    public function updatedPincode(): void
    {
        $area = strlen($this->pincode) === 6 ? ServiceAreas::match($this->pincode) : null;
        if ($area && ! $this->city) {
            $this->city = $area['district'];
        }
    }

    public function getAreaProperty(): ?array
    {
        return strlen($this->pincode) === 6 ? ServiceAreas::match($this->pincode) : null;
    }

    private function rulesFor(int $step): array
    {
        $all = AuthController::registrationRules();
        $keys = [1 => ['name', 'phone', 'email', 'password'], 2 => ['shop_name', 'shop_type', 'pan', 'gstin'], 3 => ['address_line1', 'address_line2', 'landmark', 'pincode', 'city', 'shop_photo']][$step];

        return array_intersect_key($all, array_flip($keys));
    }

    public function next(): void
    {
        $this->pan = strtoupper($this->pan);
        $this->gstin = strtoupper($this->gstin);
        $this->validate($this->rulesFor($this->step), AuthController::registrationMessages());
        if ($this->step === 2 && $this->gstin !== '' && substr($this->gstin, 2, 10) !== $this->pan) {
            $this->addError('gstin', 'GSTIN does not match the PAN entered.');

            return;
        }
        $this->step++;
    }

    public function back(): void
    {
        $this->step = max(1, $this->step - 1);
    }

    public function submit(): void
    {
        $this->validate($this->rulesFor(3), AuthController::registrationMessages());
        if (! $this->area) {
            $this->addError('pincode', 'We currently deliver only in Prayagraj, Varanasi, Kanpur and Lucknow districts.');

            return;
        }
        $data = [
            'name' => $this->name, 'phone' => $this->phone, 'email' => $this->email, 'password' => $this->password,
            'shop_name' => $this->shop_name, 'shop_type' => $this->shop_type, 'pan' => $this->pan, 'gstin' => $this->gstin ?: null,
            'address_line1' => $this->address_line1, 'address_line2' => $this->address_line2 ?: null,
            'landmark' => $this->landmark ?: null, 'pincode' => $this->pincode, 'city' => $this->city,
        ];
        $this->validate(array_diff_key(AuthController::registrationRules(), ['password' => 1, 'shop_photo' => 1]) + ['password' => 'required|min:8|confirmed']);
        $user = AuthController::createShopUser($data, $this->shop_photo);
        $this->verifyPhone = $user->phone;
        $this->otpHint = app(OtpService::class)->send($user->phone, 'register');
        $this->step = 4;
    }

    public function resend(): void
    {
        $this->otpHint = app(OtpService::class)->send($this->verifyPhone, 'register');
        $this->dispatch('toast', message: 'OTP sent again', kind: 'success');
    }

    public function verify()
    {
        $this->validate(['otp' => 'required|digits:' . config('shared.otp.length')]);
        $user = User::where('phone', $this->verifyPhone)->firstOrFail();
        if (! app(OtpService::class)->verify($user->phone, 'register', $this->otp)) {
            $this->addError('otp', 'Incorrect or expired OTP.');

            return;
        }
        AuthController::markVerified($user);
        Auth::login($user, true);
        session()->regenerate();
        session()->flash('status', 'Phone verified! Your account is now awaiting approval.');

        return redirect()->route('portal');
    }

    public function render()
    {
        return view('livewire.auth.register', ['types' => config('shared.shopTypes'), 'districts' => ServiceAreas::districts()]);
    }
}
