<?php

namespace App\Console\Commands;

use App\Events\NotificationReceived;
use App\Models\Notification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessDueNotifications extends Command
{
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
                    if ($notification->channel !== 'echo') {
                        Log::info('Echo-only mode: overriding non-echo notification channel', [
                            'notification_id' => $notification->id,
                            'original_channel' => $notification->channel,
                        ]);
                    }

                    $echoDelivered = $this->dispatchEchoChannel($notification);

                    if ($echoDelivered) {
                        $notification->update([
                            'channel' => 'echo',
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
}
