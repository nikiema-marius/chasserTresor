<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(AdminSeeder::class);

        // La campagne de démonstration n'est jamais créée en production
        if (! app()->environment('production')) {
            $this->call(DemoCampaignSeeder::class);
        }
    }
}
