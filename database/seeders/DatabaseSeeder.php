<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * فقط برای محیط توسعه. ادمین سیستم را با `php artisan user:make-admin 09...` بسازید.
     */
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        User::factory()->organizational()->create([
            'name' => 'کاربر نمونه',
            'phone' => '+989120000000',
        ]);
    }
}
