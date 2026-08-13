<?php

namespace Database\Seeders;

use App\Models\BlogCategory;
use Illuminate\Database\Seeder;

class BlogCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Weight Loss', 'slug' => 'weight-loss', 'sort_order' => 1],
            ['name' => 'Skincare', 'slug' => 'skincare', 'sort_order' => 2],
            ['name' => "Women's Health", 'slug' => 'womens-health', 'sort_order' => 3],
            ['name' => "Men's Health", 'slug' => 'mens-health', 'sort_order' => 4],
            ['name' => 'Sexual Health', 'slug' => 'sexual-health', 'sort_order' => 5],
            ['name' => 'General Health', 'slug' => 'general-health', 'sort_order' => 6],
        ];

        foreach ($categories as $cat) {
            BlogCategory::firstOrCreate(['slug' => $cat['slug']], $cat);
        }
    }
}
