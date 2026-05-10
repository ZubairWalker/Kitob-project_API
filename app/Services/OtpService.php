<?php

namespace App\Services;

use App\Models\OtpCode;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class OtpService
{
    protected $twilioService;

    public function __construct(TwilioService $twilioService)
    {
        $this->twilioService = $twilioService;
    }

    /**
     * Generate and send a 4-digit OTP code to the given phone number.
     *
     * @param string $phone
     * @return bool
     */
    public function sendOtp(string $phone): bool
    {
        $code = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
        $expiresAt = Carbon::now()->addMinutes(5);

        // Update or create otp code for this phone
        OtpCode::updateOrCreate(
            ['phone' => $phone],
            ['code' => $code, 'expires_at' => $expiresAt]
        );

        // Check if we should use real SMS or simulation
        if (config('app.debug') && env('OTP_SIMULATION', true)) {
            Log::info("OTP Simulation for {$phone}: {$code}");
            return true;
        }

        return $this->twilioService->sendSms($phone, "Kitob: Tasdiqlash kodi: $code");
    }

    /**
     * Verify the OTP code for the given phone number.
     *
     * @param string $phone
     * @param string $code
     * @return bool
     */
    public function verifyOtp(string $phone, string $code): bool
    {
        $otpCode = OtpCode::where('phone', $phone)
            ->where('code', $code)
            ->where('expires_at', '>', Carbon::now())
            ->first();

        if ($otpCode) {
            $otpCode->delete(); // Consume the code
            return true;
        }

        return false;
    }
}
