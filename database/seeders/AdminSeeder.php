<?php

namespace Database\Seeders;

use App\Enums\AdminRole;
use App\Models\Admin;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Crée le super-administrateur initial à partir des variables ADMIN_SEED_*.
 * Ne modifie jamais le mot de passe d'un compte existant.
 */
class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = (string) config('game.admin_seed.email');

        if (Admin::query()->where('email', $email)->exists()) {
            $this->command?->info("Super-admin {$email} déjà présent : inchangé.");

            return;
        }

        $password = config('game.admin_seed.password') ?: Str::password(20);

        Admin::query()->create([
            'name' => config('game.admin_seed.name'),
            'email' => $email,
            'password' => $password,
            'role' => AdminRole::SuperAdmin,
            'is_active' => true,
        ]);

        $this->command?->warn("Super-admin créé : {$email}");

        if (! config('game.admin_seed.password')) {
            // Affiché une seule fois : à noter et changer après la première connexion
            $this->command?->warn("Mot de passe généré : {$password}");
        }
    }
}
