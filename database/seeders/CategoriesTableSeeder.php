<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CategoriesTableSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('categories')->insert([
            [
                'name' => 'Laporan Hasil Evaluasi SPBE',


            ],
            [
                'name' => 'Kebijakan SPBE Nasional',


            ],
            [
                'name' => 'SPBE Kemendikdasmen',

            ],
        ]);
    }
}
