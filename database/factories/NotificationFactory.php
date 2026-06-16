<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Notification>
 */
class NotificationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => null,
            'title' => $this->faker->sentence(4),
            'message' => $this->faker->sentence(10),
            'type' => $this->faker->randomElement([
                'task_assigned',
                'task_reassigned',
                'task_due_soon',
            ]),
            'channel' => 'echo',
            'is_read' => $this->faker->boolean(35),
            'is_sent' => $this->faker->boolean(70),
            'scheduled_date' => now()->toDateString(),
            'scheduled_time' => now()->format('H:i:s'),
            'sent_at' => null,
            'read_at' => null,
            'action_url' => '/tasks/' . $this->faker->numberBetween(1, 100),
            'data' => [
                'task_id' => (string) $this->faker->numberBetween(1, 100),
            ],
        ];
    }
}
