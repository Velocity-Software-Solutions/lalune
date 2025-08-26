<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategoriesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
public function run(): void
    {
        $categories = [
            [
                'name' => 'Dresses',
                'name_ar' => 'فساتين',
                'slug' => Str::slug('Dresses'),
                'description' => 'Female dresses collection',
                'status' => true,
            ],
            [
                'name' => 'Chemises',
                'name_ar' => 'قمصان نوم',
                'slug' => Str::slug('Chemises'),
                'description' => 'Female chemises collection',
                'status' => true,
            ],
        ];

        foreach ($categories as $category) {
            Category::updateOrCreate(['slug' => $category['slug']], $category);
        }
    }
}
