<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Support\Presenter;
use App\Support\ServiceAreas;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json(['data' => Presenter::user($request->user())]);
    }

    public function update(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => 'sometimes|string|max:100',
            'email' => ['sometimes', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'locale' => ['sometimes', Rule::in(collect(config('shared.locales'))->where('enabled', true)->pluck('code')->all())],
            'address_line1' => 'sometimes|string|max:200', 'address_line2' => 'nullable|string|max:200', 'landmark' => 'nullable|string|max:120',
            'city' => 'sometimes|string|max:80', 'pincode' => 'sometimes|digits:6',
            'shop_photo' => 'nullable|image|max:4096',
        ]);
        $user->update(collect($data)->only(['name', 'email', 'locale'])->all());
        if ($shop = $user->shop) {
            $fields = collect($data)->only(['address_line1', 'address_line2', 'landmark', 'city', 'pincode'])->all();
            if (isset($fields['pincode'])) {
                $area = ServiceAreas::match($fields['pincode']);
                if (! $area) {
                    throw ValidationException::withMessages(['pincode' => 'We do not deliver to this PIN code yet.']);
                }
                $fields += ['district' => $area['district'], 'hub_id' => ServiceAreas::hubFor($fields['pincode'])?->id];
            }
            if ($request->hasFile('shop_photo')) {
                $fields['photo_path'] = $request->file('shop_photo')->store('shops', 'public');
            }
            $shop->update($fields);
        }

        return response()->json(['data' => Presenter::user($user->fresh())]);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $data = $request->validate(['current_password' => 'required', 'password' => 'required|string|min:8|confirmed']);
        if (! Hash::check($data['current_password'], $request->user()->password)) {
            throw ValidationException::withMessages(['current_password' => 'Current password is incorrect.']);
        }
        $request->user()->update(['password' => $data['password']]);

        return response()->json(['message' => 'Password changed.']);
    }
}
