<?php

namespace App\Http\Controllers\Api;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Support\Presenter;
use App\Models\Shop;
use App\Models\User;
use App\Notifications\AppNotice;
use App\Services\OtpService;
use App\Support\ServiceAreas;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(private OtpService $otp) {}

    public static function registrationRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'regex:/^[6-9]\d{9}$/', 'unique:users,phone'],
            'email' => ['required', 'email:rfc', 'max:150', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'shop_name' => ['required', 'string', 'max:150'],
            'shop_type' => ['required', Rule::in(config('shared.shopTypes'))],
            'pan' => ['required', 'regex:/^[A-Z]{5}[0-9]{4}[A-Z]$/'],
            'gstin' => ['nullable', 'regex:/^\d{2}[A-Z]{5}\d{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/'],
            'address_line1' => ['required', 'string', 'max:200'],
            'address_line2' => ['nullable', 'string', 'max:200'],
            'landmark' => ['nullable', 'string', 'max:120'],
            'pincode' => ['required', 'digits:6'],
            'city' => ['required', 'string', 'max:80'],
            'shop_photo' => ['nullable', 'image', 'max:4096'],
        ];
    }

    public static function registrationMessages(): array
    {
        return [
            'phone.regex' => 'Enter a valid 10-digit Indian mobile number.',
            'pan.regex' => 'Enter a valid PAN, e.g. ABCDE1234F.',
            'gstin.regex' => 'Enter a valid 15-character GSTIN.',
        ];
    }

    /** Extra cross-field checks shared with the web portal. */
    public static function checkBusinessRules(array $data): void
    {
        $errors = [];
        if (! ServiceAreas::match($data['pincode'])) {
            $errors['pincode'] = 'We currently deliver only in Prayagraj, Varanasi, Kanpur and Lucknow districts.';
        }
        if (! empty($data['gstin']) && substr($data['gstin'], 2, 10) !== $data['pan']) {
            $errors['gstin'] = 'GSTIN does not match the PAN entered.';
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }

    public static function createShopUser(array $data, $photo = null): User
    {
        $data['pan'] = strtoupper($data['pan']);
        $data['gstin'] = isset($data['gstin']) ? strtoupper($data['gstin']) : null;
        self::checkBusinessRules($data);
        $area = ServiceAreas::match($data['pincode']);

        return DB::transaction(function () use ($data, $area, $photo) {
            $user = User::create([
                'name' => $data['name'], 'phone' => $data['phone'], 'email' => $data['email'],
                'password' => $data['password'], 'role' => Role::Customer,
            ]);
            Shop::create([
                'user_id' => $user->id,
                'hub_id' => ServiceAreas::hubFor($data['pincode'])?->id,
                'shop_name' => $data['shop_name'], 'shop_type' => $data['shop_type'],
                'gstin' => $data['gstin'], 'pan' => $data['pan'],
                'address_line1' => $data['address_line1'], 'address_line2' => $data['address_line2'] ?? null,
                'landmark' => $data['landmark'] ?? null, 'city' => $data['city'], 'district' => $area['district'],
                'pincode' => $data['pincode'],
                'photo_path' => $photo ? $photo->store('shops', 'public') : null,
                'status' => 'pending',
            ]);

            return $user->fresh('shop');
        });
    }

    public function register(Request $request): JsonResponse
    {
        $data = $request->validate(self::registrationRules(), self::registrationMessages());
        $user = self::createShopUser($data, $request->file('shop_photo'));
        $hint = $this->otp->send($user->phone, 'register');

        return response()->json([
            'message' => 'Registered. Verify your mobile number with the OTP we sent.',
            'phone' => $user->phone,
            'otp_hint' => $hint,
        ], 201);
    }

    public function sendOtp(Request $request): JsonResponse
    {
        $data = $request->validate(['phone' => ['required', 'regex:/^[6-9]\d{9}$/'], 'purpose' => ['required', Rule::in(['register', 'reset'])]]);
        $user = User::where('phone', $data['phone'])->first();
        // Don't reveal whether a number exists.
        $hint = $user ? $this->otp->send($data['phone'], $data['purpose']) : null;

        return response()->json(['message' => 'If this number is registered, an OTP has been sent.', 'otp_hint' => $hint]);
    }

    public function verifyOtp(Request $request): JsonResponse
    {
        $data = $request->validate(['phone' => ['required'], 'code' => ['required', 'digits:' . config('shared.otp.length')]]);
        $user = User::where('phone', $data['phone'])->first();
        if (! $user || ! $this->otp->verify($data['phone'], 'register', $data['code'])) {
            throw ValidationException::withMessages(['code' => 'Incorrect or expired OTP.']);
        }
        $user->update(['phone_verified_at' => now()]);

        foreach (User::whereIn('role', [Role::OrderManager->value, Role::SuperAdmin->value])->get() as $u) {
            $u->notify(new AppNotice('New shop registration', "{$user->shop->shop_name} ({$user->shop->city}) is waiting for approval.", 'account', ['shop_id' => $user->shop->id]));
        }

        return $this->tokenResponse($user, $request);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate(['phone' => ['required'], 'password' => ['required']]);
        $user = User::where('phone', $data['phone'])->where('role', Role::Customer->value)->first();
        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['phone' => 'Incorrect mobile number or password.']);
        }
        if (! $user->is_active) {
            return response()->json(['message' => 'This account is disabled. Please contact support.', 'code' => 'disabled'], 403);
        }
        if (! $user->phone_verified_at) {
            $hint = $this->otp->send($user->phone, 'register');

            return response()->json(['message' => 'Please verify your mobile number.', 'code' => 'phone_unverified', 'phone' => $user->phone, 'otp_hint' => $hint], 403);
        }

        return $this->tokenResponse($user, $request);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone' => ['required'], 'code' => ['required', 'digits:' . config('shared.otp.length')],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);
        $user = User::where('phone', $data['phone'])->first();
        if (! $user || ! $this->otp->verify($data['phone'], 'reset', $data['code'])) {
            throw ValidationException::withMessages(['code' => 'Incorrect or expired OTP.']);
        }
        $user->update(['password' => $data['password']]);
        $user->tokens()->delete();

        return response()->json(['message' => 'Password updated. Please log in.']);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out.']);
    }

    private function tokenResponse(User $user, Request $request): JsonResponse
    {
        $token = $user->createToken($request->input('device', 'mobile'))->plainTextToken;

        return response()->json(['token' => $token, 'user' => Presenter::user($user)]);
    }
}
