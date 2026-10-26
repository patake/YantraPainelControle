<?php

namespace Database\Seeders;

use App\Enums\SystemFeature;
use App\Models\System;
use Illuminate\Database\Seeder;

/**
 * Cria os 4 sistemas licenciados. Pode rodar mais de uma vez.
 */
class SystemSeeder extends Seeder
{
    public function run(): void
    {
        $systems = [
            ['code' => 'YC_PRT', 'name' => 'Yantra Cardio Printer', 'feature' => SystemFeature::Printer, 'branding' => ['color' => '#b8323a']],
            ['code' => 'YO_PRT', 'name' => 'Yantra Optical Printer', 'feature' => SystemFeature::Printer, 'branding' => ['color' => '#1e5fa8']],
            ['code' => 'YO_SRC', 'name' => 'Yantra Screen Capture', 'feature' => SystemFeature::Screen, 'branding' => ['color' => '#2f7a52']],
            ['code' => 'YO_HUB', 'name' => 'Yantra Hub', 'feature' => SystemFeature::Serial, 'branding' => ['color' => '#6b3fa0']],
        ];

        foreach ($systems as $system) {
            System::updateOrCreate(['code' => $system['code']], $system);
        }
    }
}
