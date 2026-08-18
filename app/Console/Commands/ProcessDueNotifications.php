<?php

namespace App\Console\Commands;

use App\Events\NotificationReceived;
use App\Models\Notification;
use App\Models\User;
use App\Services\FirebaseNotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessDueNotifications extends Command
{
    protected FirebaseNotificationService $firebaseNotification;

    public function __construct(FirebaseNotificationService $firebaseNotification)
    {
        parent::__construct();
        $this->firebaseNotification = $firebaseNotification;
    }

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notifications:process-due';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process due notifications and dispatch configured channels';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $now = now();
        $processed = 0;

        Notification::query()
            ->where('is_sent', false)
            ->whereDate('scheduled_date', '<=', $now->toDateString())
            ->whereTime('scheduled_time', '<=', $now->format('H:i:s'))
            ->orderBy('id')
            ->chunkById(100, function ($notifications) use (&$processed, $now) {
                foreach ($notifications as $notification) {
                    $requiresEcho = in_array($notification->channel, ['echo', 'both'], true);
                    $requiresFirebase = in_array($notification->channel, ['firebase', 'both'], true);

                    $echoDelivered = !$requiresEcho || $this->wasChannelDelivered($notification, 'echo');
                    $firebaseDelivered = !$requiresFirebase || $this->wasChannelDelivered($notification, 'firebase');

                    if ($requiresEcho && !$echoDelivered) {
                        $echoDelivered = $this->dispatchEchoChannel($notification);
                        if ($echoDelivered) {
                            $this->markChannelDelivered($notification, 'echo');
                        }
                    }

                    if ($requiresFirebase && !$firebaseDelivered) {
                        $firebaseDelivered = $this->dispatchFirebaseChannel($notification);
                        if ($firebaseDelivered) {
                            $this->markChannelDelivered($notification, 'firebase');
                        }
                    }

                    if ($echoDelivered && $firebaseDelivered) {
                        $notification->update([
                            'is_sent' => true,
                            'sent_at' => $now,
                        ]);

                        $processed++;
                    }
                }
            });

        $this->info('Processed notifications: ' . $processed);

        return Command::SUCCESS;
    }

    private function dispatchEchoChannel(Notification $notification): bool
    {
        try {
            $unreadCount = Notification::query()
                ->where('user_id', $notification->user_id)
                ->where('is_read', false)
                ->count();

            event(new NotificationReceived($notification, $unreadCount));

            return true;
        } catch (\Throwable $exception) {
            Log::error('Echo notification dispatch failed', [
                'notification_id' => $notification->id,
                'user_id' => $notification->user_id,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    private function dispatchFirebaseChannel(Notification $notification): bool
    {
        $user = User::find($notification->user_id);
        if (!$user) {
            Log::warning('Firebase notification user was not found', [
                'notification_id' => $notification->id,
                'user_id' => $notification->user_id,
            ]);

            return true;
        }

        $result = $this->firebaseNotification->sendToUser(
            $user,
            $notification->title,
            $notification->message,
            [
                'notification_id' => (string) $notification->id,
                'type' => $notification->type,
                'action_url' => $notification->action_url ?? '/notifications',
                'user_id' => (string) $notification->user_id,
            ]
        );

        if (!$result['success']) {
            Log::error('Firebase notification dispatch failed', [
                'notification_id' => $notification->id,
                'user_id' => $notification->user_id,
                'error' => $result['error'] ?? 'Unknown Firebase error',
            ]);

            return false;
        }

        return true;
    }

    private function wasChannelDelivered(Notification $notification, string $channel): bool
    {
        $deliveryMap = data_get($notification->data, 'delivery', []);

        return (bool) data_get($deliveryMap, $channel, false);
    }

    private function markChannelDelivered(Notification $notification, string $channel): void
    {
        $data = $notification->data ?? [];
        $deliveryMap = data_get($data, 'delivery', []);
        $deliveryMap[$channel] = true;
        $data['delivery'] = $deliveryMap;

        $notification->update([
            'data' => $data,
        ]);
    }
}
