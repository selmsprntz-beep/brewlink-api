<?php

namespace Database\Seeders;

use App\Models\CafeTable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CafeTableSeeder extends Seeder
{
    public function run(): void
    {
        for ($i = 1; $i <= 10; $i++) {
            CafeTable::firstOrCreate(
                ['name' => "Table $i"],
                ['qr_token' => Str::random(24)]
            );
        }
    }
}
