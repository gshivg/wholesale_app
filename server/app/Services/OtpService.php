<?php

namespace App\Services;

use App\Models\OtpCode;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

/**
 * Prototype OTP. While `wholesale.otp_prototype` is on, the code is always
 * shared.otp.prototypeCode and no SMS is sent. Swap `deliver()` for a real SMS provider later.
 */
class OtpService
{
    public function send(string $phone, string $purpose): ?string
    {
        OtpCode::where('phone', $phone)->where('purpose', $purpose)->whereNull('consumed_at')->update(['consumed_at' => now()]);

        $len = (int) config('shared.otp.length');
        $code = config('wholesale.otp_prototype')
            ? config('shared.otp.prototypeCode')
            : str_pad((string) random_int(0, 10 ** $len - 1), $len, '0', STR_PAD_LEFT);

        OtpCode::create([
            'phone' => $phone,
            'purpose' => $purpose,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes((int) config('shared.otp.ttlMinutes')),
        ]);

        $this->deliver($phone, $code);

        return config('wholesale.otp_prototype') ? $code : null;
    }

    public function verify(string $phone, string $purpose, string $code): bool
    {
        $otp = OtpCode::where('phone', $phone)->where('purpose', $purpose)
            ->whereNull('consumed_at')->where('expires_at', '>', now())->latest('id')->first();
        if (! $otp || $otp->attempts >= config('wholesale.otp_max_attempts')) {
            return false;
        }
        $otp->increment('attempts');
        if (! Hash::check($code, $otp->code_hash)) {
            return false;
        }
        $otp->update(['consumed_at' => now()]);

        return true;
    }

    private function deliver(string $phone, string $code): void
    {
        Log::info("[OTP] {$phone}: {$code}");
    }
}
