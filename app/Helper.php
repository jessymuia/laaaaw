<?php

use App\Models\User;
use Illuminate\Support\Facades\Log;

function sendSMS(User $user, $message): void
{
    sendCustomSMS($user->phone_number, $message);
}

function sendCustomSMS($phone_number, $message): void
{
    try {
        $curl = curl_init();

        $postData = json_encode([
            'apikey' => config('services.textsms.api_key'),
            'partnerID' => config('services.textsms.partner_id'),
            'mobile' => sanitizePhone($phone_number),
            'message' => $message,
            'shortcode' => config('services.textsms.shortcode'),
            'pass_type' => 'plain',
        ]);

        curl_setopt_array($curl, [
            CURLOPT_URL => config('services.textsms.url'),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $postData,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
            ],
        ]);

        $response = curl_exec($curl);
        $error = curl_error($curl);
        curl_close($curl);

        if ($error) {
            Log::error('TextSMS request failed: '.$error);

            return;
        }

        // Response may contain delivery metadata but never credentials; safe to log.
        Log::info('TextSMS response received', ['response' => $response]);
    } catch (Exception $e) {
        Log::error($e->getMessage()
            .' '.$e->getFile().' '.$e->getLine()
            .' '.$e->getTraceAsString()
        );
    }
}

function sanitizePhone($phoneNumber): string
{
    $phone = preg_replace('/[^0-9]/', '', $phoneNumber);
    $lastNine = substr($phone, -9);

    return '254'.$lastNine;
}
