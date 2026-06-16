<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Task;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        $users = User::factory()
            ->count(5)
            ->create();

        foreach ($users as $creator) {
            Task::factory()
                ->count(3)
                ->state(function () use ($users, $creator) {
                    return [
                        'created_by' => $creator->id,
                        'assigned_to' => $users->random()->id,
                    ];
                })
                ->create();
        }
    }
}
