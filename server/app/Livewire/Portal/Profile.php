<?php

namespace App\Livewire\Portal;

use App\Support\ServiceAreas;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.portal')]
#[Title('Profile')]
class Profile extends Component
{
    public string $name = '', $email = '', $locale = 'en';
    public string $address_line1 = '', $address_line2 = '', $landmark = '', $city = '', $pincode = '';
    public string $current_password = '', $password = '', $password_confirmation = '';

    public function mount(): void
    {
        $u = auth()->user();
        $s = $u->shop;
        $this->fill(['name' => $u->name, 'email' => (string) $u->email, 'locale' => $u->locale]);
        $this->fill(['address_line1' => $s->address_line1, 'address_line2' => (string) $s->address_line2, 'landmark' => (string) $s->landmark, 'city' => $s->city, 'pincode' => $s->pincode]);
    }

    public function save(): void
    {
        $u = auth()->user();
        $enabled = collect(config('shared.locales'))->where('enabled', true)->pluck('code')->all();
        $this->validate([
            'name' => 'required|max:100', 'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($u->id)],
            'locale' => [Rule::in($enabled)],
            'address_line1' => 'required|max:200', 'address_line2' => 'nullable|max:200', 'landmark' => 'nullable|max:120', 'city' => 'required|max:80', 'pincode' => 'required|digits:6',
        ]);
        $area = ServiceAreas::match($this->pincode);
        if (! $area) {
            $this->addError('pincode', 'We do not deliver to this PIN code yet.');

            return;
        }
        $u->update(['name' => $this->name, 'email' => $this->email, 'locale' => $this->locale]);
        $u->shop->update([
            'address_line1' => $this->address_line1, 'address_line2' => $this->address_line2 ?: null, 'landmark' => $this->landmark ?: null,
            'city' => $this->city, 'pincode' => $this->pincode, 'district' => $area['district'], 'hub_id' => ServiceAreas::hubFor($this->pincode)?->id,
        ]);
        $this->dispatch('toast', message: 'Profile saved', kind: 'success');
    }

    public function changePassword(): void
    {
        $this->validate(['current_password' => 'required', 'password' => 'required|min:8|confirmed']);
        if (! Hash::check($this->current_password, auth()->user()->password)) {
            $this->addError('current_password', 'Current password is incorrect.');

            return;
        }
        auth()->user()->update(['password' => $this->password]);
        $this->reset('current_password', 'password', 'password_confirmation');
        $this->dispatch('toast', message: 'Password changed', kind: 'success');
    }

    public function render()
    {
        return view('livewire.portal.profile', ['shop' => auth()->user()->shop->fresh('hub'), 'locales' => collect(config('shared.locales'))->where('enabled', true)]);
    }
}
