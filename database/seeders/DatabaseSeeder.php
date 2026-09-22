<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * Accounts are created only via the `users:create` artisan command, so
     * this seeder intentionally creates none.
     */
    public function run(): void
    {
        //
    }
}
