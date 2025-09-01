<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EnablePornCategorySeeder extends Seeder
{
    public function run(): void
    {
        DB::table('categories')->updateOrInsert(
            ['name' => 'Porn'],
            [
                'icon' => 'fa-mars',
                'position' => 99,
                'porn_meta' => true,
                'movie_meta' => false,
                'tv_meta' => false,
                'game_meta' => false,
                'music_meta' => false,
                'no_meta' => false,
            ]
        );
    }
}
