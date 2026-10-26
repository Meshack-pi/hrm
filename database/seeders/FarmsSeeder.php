<?php

namespace Database\Seeders;

use App\Models\Farm;
use Illuminate\Database\Seeder;

class FarmsSeeder extends Seeder
{
    public function run(): void
    {
        $farms = [
            ['name' => 'Kosachei Farm', 'code' => 'KOS', 'location' => 'Kosachei', 'county' => 'Uasin Gishu'],
            ['name' => 'Sugoi Farm', 'code' => 'SUG', 'location' => 'Sugoi', 'county' => 'Uasin Gishu'],
            ['name' => 'Mombasa Farm', 'code' => 'MBA', 'location' => 'Mombasa', 'county' => 'Mombasa'],
        ];

        foreach ($farms as $farm) {
            Farm::updateOrCreate(['code' => $farm['code']], $farm);
        }
    }
}
