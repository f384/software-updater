<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('settings')->insertOrIgnore([
            'key' => 'installer_root',
            'value' => '\\\\FilipPC\\Installers', 
        ]);
    }
}
