<?php

namespace App\Services;

use Twilio\Rest\Client;
use Illuminate\Support\Facades\Log;

class TwilioService
{
    protected $sid;
    protected $token;
    protected $from;

    public function __construct()
    {
        $this->sid = config('services.twilio.sid');
        $this->token = config('services.twilio.token');
        $this->from = config('services.twilio.from');
    }

    /**
     * Send SMS via Twilio
     *
     * @param string $to
     * @param string $message
     * @return bool
     */
    public function sendSms(string $to, string $message): bool
    {
        try {
            $client = new Client($this->sid, $this->token);

            $client->messages->create($to, [
                'from' => $this->from,
                'body' => $message
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Twilio Send SMS Failed: ' . $e->getMessage());
            return false;
        }
    }
}
