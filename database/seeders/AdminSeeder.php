<?php

namespace Database\Seeders;

use App\Models\Admin;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $admins = [
            ['name' => 'Mehdi', 'phone' => '+212652403812', 'env' => 'ADMIN_MEHDI_PASSWORD'],
            ['name' => 'Bader', 'phone' => '+212619109600', 'env' => 'ADMIN_BADER_PASSWORD'],
        ];

        foreach ($admins as $a) {
            $password = env($a['env']);

            if (! $password) {
                throw new \RuntimeException("Set {$a['env']} in .env before seeding.");
            }

            Admin::updateOrCreate(
                ['phone' => $a['phone']],
                ['name' => $a['name'], 'password' => $password, 'active' => true],
            );
        }
    }
}