<?php

namespace Database\Seeders;

use App\Models\BlogCategory;
use App\Models\BlogPost;
use Illuminate\Database\Seeder;

class BlogSeeder extends Seeder
{
    public function run(): void
    {
        $data = json_decode(file_get_contents(__DIR__ . '/blog-data.json'), true);

        // Seed categories
        foreach ($data['categories'] as $category) {
            BlogCategory::updateOrCreate(
                ['slug' => $category['slug']],
                [
                    'name' => $category['name'],
                    'description' => $category['description'],
                    'sort_order' => $category['sort_order'],
                ]
            );
        }

        // Seed posts
        foreach ($data['posts'] as $post) {
            // Find the category by original ID mapping
            $category = BlogCategory::where('slug', $this->getCategorySlug($post['blog_category_id'], $data['categories']))->first();

            BlogPost::updateOrCreate(
                ['slug' => $post['slug']],
                [
                    'blog_category_id' => $category?->id,
                    'title' => $post['title'],
                    'excerpt' => $post['excerpt'],
                    'body' => $post['body'],
                    'featured_image' => $post['featured_image'],
                    'meta_title' => $post['meta_title'],
                    'meta_description' => $post['meta_description'],
                    'status' => $post['status'],
                    'published_at' => $post['published_at'],
                    'reading_time' => $post['reading_time'],
                    'views' => 0,
                ]
            );
        }

        $this->command->info('Seeded ' . count($data['categories']) . ' categories and ' . count($data['posts']) . ' blog posts.');
    }

    private function getCategorySlug(int $categoryId, array $categories): string
    {
        foreach ($categories as $category) {
            if ($category['id'] === $categoryId) {
                return $category['slug'];
            }
        }
        return '';
    }
}
