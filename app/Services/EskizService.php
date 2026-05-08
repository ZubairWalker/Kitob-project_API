<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class EskizService
{
    protected $baseUrl = 'https://notify.eskiz.uz/api';
    protected $email;
    protected $password;

    public function __construct()
    {
        $this->email = config('services.eskiz.email');
        $this->password = config('services.eskiz.password');
    }

    /**
     * Get Authentication Token
     */
    protected function getToken()
    {
        return Cache::remember('eskiz_token', 86400, function () {
            $response = Http::post("{$this->baseUrl}/auth/login", [
                'email' => $this->email,
                'password' => $this->password,
            ]);

            if ($response->successful()) {
                return $response->json()['data']['token'];
            }

            Log::error('Eskiz Login Failed', $response->json() ?? []);
            return null;
        });
    }

    /**
     * Send SMS
     */
    public function sendSms($phone, $message)
    {
        $token = $this->getToken();

        if (!$token) {
            return false;
        }

        // Clean phone number (must be 9 digits without +998 for Eskiz, or full number depending on their API)
        // Eskiz usually expects 998XXXXXXXXX
        $phone = preg_replace('/[^0-9]/', '', $phone);

        $response = Http::withToken($token)->post("{$this->baseUrl}/message/sms/send", [
            'mobile_phone' => $phone,
            'message' => $message,
            'from' => '4546', // Default Eskiz sender ID
        ]);

        if ($response->successful()) {
            return true;
        }

        Log::error('Eskiz Send SMS Failed', $response->json() ?? []);
        return false;
    }
}
