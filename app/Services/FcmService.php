<?php

namespace App\Services;

use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FcmService
{
    private function getAccessToken(): ?string
    {
        $path = storage_path('app/firebase-service-account.json');

        if (!file_exists($path)) {
            Log::warning('FCM: firebase-service-account.json not found at ' . $path);
            return null;
        }

        $sa  = json_decode(file_get_contents($path), true);
        $now = time();

        $payload = [
            'iss'   => $sa['client_email'],
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud'   => 'https://oauth2.googleapis.com/token',
            'iat'   => $now,
            'exp'   => $now + 3600,
        ];

        $jwt = JWT::encode($payload, $sa['private_key'], 'RS256');

        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion'  => $jwt,
        ]);

        if (!$response->successful()) {
            Log::warning('FCM: failed to get access token', ['body' => $response->body()]);
            return null;
        }

        return $response->json('access_token');
    }

    /**
     * Send a notification to a single FCM token.
     *
     * @param  string  $token   Device FCM token
     * @param  string  $title   Notification title
     * @param  string  $body    Notification body
     * @param  array   $data    Extra key-value data (all values must be strings)
     */
    public function send(string $token, string $title, string $body, array $data = []): bool
    {
        $projectId   = config('services.firebase.project_id');
        $accessToken = $this->getAccessToken();

        if (!$accessToken || !$projectId) {
            Log::warning('FCM: missing access token or project ID');
            return false;
        }

        $response = Http::withToken($accessToken)->post(
            "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send",
            [
                'message' => [
                    'token'        => $token,
                    'notification' => ['title' => $title, 'body' => $body],
                    'data'         => array_map('strval', $data),
                    'android'      => ['priority' => 'high'],
                ],
            ]
        );

        if (!$response->successful()) {
            Log::warning('FCM: send failed', ['token' => substr($token, 0, 20), 'response' => $response->body()]);
        }

        return $response->successful();
    }

    /**
     * Send a notification to multiple FCM tokens.
     * Returns the count of successful sends.
     */
    public function sendToMany(array $tokens, string $title, string $body, array $data = []): int
    {
        $count = 0;
        foreach ($tokens as $token) {
            if ($token && $this->send($token, $title, $body, $data)) {
                $count++;
            }
        }
        return $count;
    }
}
