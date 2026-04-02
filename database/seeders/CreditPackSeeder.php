<?php

namespace Database\Seeders;

use App\Models\CreditPack;
use Illuminate\Database\Seeder;

class CreditPackSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $packs = [
            [
                'name' => 'Starter Pack',
                'description' => 'Great for quick launches and first few storybooks.',
                'credits' => 500,
                'price_cents' => 1000,
                'currency' => 'USD',
                'sort_order' => 10,
                'is_active' => true,
            ],
            [
                'name' => 'Growth Pack',
                'description' => 'Best value for consistent publishing and video-heavy projects.',
                'credits' => 1500,
                'price_cents' => 2500,
                'currency' => 'USD',
                'sort_order' => 20,
                'is_active' => true,
            ],
            [
                'name' => 'Pro Pack',
                'description' => 'High-volume credits for power users and agency workflows.',
                'credits' => 3500,
                'price_cents' => 5000,
                'currency' => 'USD',
                'sort_order' => 30,
                'is_active' => true,
            ],
        ];

        foreach ($packs as $pack) {
            CreditPack::query()->updateOrCreate(
                ['name' => $pack['name']],
                $pack,
            );
        }
    }
}
