<?php

namespace App\Services;

use App\Models\FcmToken;
use App\Models\User;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Kreait\Laravel\Firebase\Facades\Firebase;
use Illuminate\Support\Facades\Log;

class FirebaseNotificationService
{
    protected Messaging $messaging;

    public function __construct()
    {
        try {
            $this->messaging = Firebase::messaging();
        } catch (\Exception $e) {
            Log::error('Failed to initialize Firebase Messaging: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Send push notification to single device
     */
    public function sendToDevice(string $deviceToken, string $title, string $body, array $data = []): array
    {
        try {
            $message = CloudMessage::withTarget('token', $deviceToken)
                ->withNotification(Notification::create($title, $body))
                ->withData($this->stringifyData($data));

            $result = $this->messaging->send($message);
            
            Log::info('Push notification sent to device', [
                'token' => substr($deviceToken, 0, 10) . '...',
                'result' => $result
            ]);
            
            return [
                'success' => true,
                'result' => $result
            ];
        } catch (\Exception $e) {
            Log::error('Failed to send push notification: ' . $e->getMessage(), [
                'token' => substr($deviceToken, 0, 10) . '...'
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Send push notification to multiple devices
     */
    public function sendToMultipleDevices(array $deviceTokens, string $title, string $body, array $data = []): array
    {
        try {
            if (empty($deviceTokens)) {
                return [
                    'success' => false,
                    'error' => 'No device tokens provided'
                ];
            }

            $successCount = 0;
            $failureCount = 0;
            $invalidTokens = [];
            $errors = [];

            foreach ($deviceTokens as $deviceToken) {
                $result = $this->sendToDevice($deviceToken, $title, $body, $data);

                if ($result['success']) {
                    $successCount++;
                    continue;
                }

                $failureCount++;
                $errors[] = $result['error'] ?? 'Unknown Firebase error';

                if ($this->isInvalidTokenError($result['error'] ?? '')) {
                    $invalidTokens[] = $deviceToken;
                }
            }
            
            Log::info('Push notification sent to multiple devices', [
                'total' => count($deviceTokens),
                'success' => $successCount,
                'failure' => $failureCount,
                'invalid_tokens' => count($invalidTokens)
            ]);
            
            return [
                'success' => $successCount > 0,
                'result' => [
                    'success_count' => $successCount,
                    'failure_count' => $failureCount,
                    'invalid_tokens' => $invalidTokens,
                    'errors' => $errors,
                ]
            ];
        } catch (\Exception $e) {
            Log::error('Failed to send push notification to multiple devices: ' . $e->getMessage());
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function sendToUser(User $user, string $title, string $body, array $data = []): array
    {
        $tokens = $user->fcmTokens()
            ->where('is_active', true)
            ->pluck('token')
            ->toArray();

        if (empty($tokens)) {
            Log::warning('Push notification skipped: user has no active FCM tokens', [
                'user_id' => $user->id,
            ]);

            return [
                'success' => false,
                'error' => 'User has no active FCM tokens',
                'result' => [
                    'success_count' => 0,
                    'failure_count' => 0,
                    'invalid_tokens' => [],
                ],
            ];
        }

        $result = $this->sendToMultipleDevices($tokens, $title, $body, $data);

        if ($result['success']) {
            $invalidTokens = $result['result']['invalid_tokens'] ?? [];
            if (!empty($invalidTokens)) {
                FcmToken::query()
                    ->where('user_id', $user->id)
                    ->whereIn('token', $invalidTokens)
                    ->update(['is_active' => false]);
            }
        }

        return $result;
    }

    /**
     * Send notification to a topic (all users subscribed)
     */
    public function sendToTopic(string $topic, string $title, string $body, array $data = []): array
    {
        try {
            $message = CloudMessage::withTarget('topic', $topic)
                ->withNotification(Notification::create($title, $body))
                ->withData($this->stringifyData($data));

            $result = $this->messaging->send($message);
            
            Log::info('Push notification sent to topic', [
                'topic' => $topic,
                'result' => $result
            ]);
            
            return [
                'success' => true,
                'result' => $result
            ];
        } catch (\Exception $e) {
            Log::error('Failed to send push notification to topic: ' . $e->getMessage());
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Subscribe device to a topic
     */
    public function subscribeToTopic(string $deviceToken, string $topic): array
    {
        try {
            $result = $this->messaging->subscribeToTopic($topic, $deviceToken);
            
            Log::info('Device subscribed to topic', [
                'topic' => $topic,
                'token' => substr($deviceToken, 0, 10) . '...'
            ]);
            
            return [
                'success' => true,
                'result' => $result
            ];
        } catch (\Exception $e) {
            Log::error('Failed to subscribe device to topic: ' . $e->getMessage());
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Unsubscribe device from a topic
     */
    public function unsubscribeFromTopic(string $deviceToken, string $topic): array
    {
        try {
            $result = $this->messaging->unsubscribeFromTopic($topic, $deviceToken);
            
            Log::info('Device unsubscribed from topic', [
                'topic' => $topic,
                'token' => substr($deviceToken, 0, 10) . '...'
            ]);
            
            return [
                'success' => true,
                'result' => $result
            ];
        } catch (\Exception $e) {
            Log::error('Failed to unsubscribe device from topic: ' . $e->getMessage());
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    private function stringifyData(array $data): array
    {
        return collect($data)
            ->mapWithKeys(fn ($value, $key) => [(string) $key => is_scalar($value) || $value === null ? (string) $value : json_encode($value)])
            ->all();
    }

    private function isInvalidTokenError(string $error): bool
    {
        return str_contains($error, 'not a valid FCM registration token')
            || str_contains($error, 'Requested entity was not found')
            || str_contains($error, 'registration-token-not-registered')
            || str_contains($error, 'invalid-argument');
    }
}
