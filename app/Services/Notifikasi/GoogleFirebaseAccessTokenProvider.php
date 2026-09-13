<?php

namespace App\Services\Notifikasi;

use App\Contracts\FirebaseAccessTokenProvider;
use Google\Auth\Credentials\ServiceAccountCredentials;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

class GoogleFirebaseAccessTokenProvider implements FirebaseAccessTokenProvider
{
    private const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    public function token(): string
    {
        return Cache::remember(
            'firebase:fcm:access-token',
            now()->addMinutes(50),
            function (): string {
                $credentialsPath = (string) config('services.firebase.credentials');

                if ($credentialsPath === '' || ! is_readable($credentialsPath)) {
                    throw new RuntimeException(
                        'Kredensial Firebase untuk push notification belum tersedia.',
                    );
                }

                $credentials = new ServiceAccountCredentials(self::SCOPE, $credentialsPath);
                $token = $credentials->fetchAuthToken()['access_token'] ?? null;

                if (! is_string($token) || $token === '') {
                    throw new RuntimeException('Token akses Firebase tidak dapat dibuat.');
                }

                return $token;
            },
        );
    }
}
