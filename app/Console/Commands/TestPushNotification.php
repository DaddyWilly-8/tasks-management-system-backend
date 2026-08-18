<?php

namespace App\Console\Commands;

use App\Services\FirebaseNotificationService;
use App\Models\User;
use Illuminate\Console\Command;

class TestPushNotification extends Command
{
    protected $signature = 'firebase:test-push {email?} {--message=Hello from Firebase!}';
    protected $description = 'Test Firebase push notifications';

    protected FirebaseNotificationService $firebaseNotification;

    public function __construct(FirebaseNotificationService $firebaseNotification)
    {
        parent::__construct();
        $this->firebaseNotification = $firebaseNotification;
    }

    public function handle()
    {
        $email = $this->argument('email');
        $message = $this->option('message');

        if ($email) {
            $user = User::where('email', $email)->first();
            if (!$user) {
                $this->error("User with email {$email} not found");
                return 1;
            }
            $users = collect([$user]);
        } else {
            $users = User::whereHas('fcmTokens', function ($query) {
                $query->where('is_active', true);
            })->limit(5)->get();
        }

        if ($users->isEmpty()) {
            $this->error('No users with active FCM tokens found');
            return 1;
        }

        foreach ($users as $user) {
            $tokenCount = $user->fcmTokens()->where('is_active', true)->count();
            
            $this->info("Sending to user: {$user->email} ({$user->id})");
            $this->info("Active FCM tokens: " . $tokenCount);
            
            $result = $this->firebaseNotification->sendToUser(
                $user,
                'Test Notification',
                $message,
                [
                    'test' => 'true',
                    'timestamp' => now()->toIso8601String()
                ]
            );

            if ($result['success']) {
                $this->info('✅ Notification sent successfully!');
                $this->line(json_encode($result['result'], JSON_PRETTY_PRINT));
            } else {
                $this->error('❌ Failed: ' . $result['error']);
            }
            
            $this->newLine();
        }

        return 0;
    }
}
