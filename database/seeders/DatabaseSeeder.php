<?php

namespace Database\Seeders;

use App\Models\Blog;
use App\Models\BlogComment;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create Sohail first
        User::factory()->create([
            'name' => 'Sohail Noor',
            'email' => 'sohailnoor1277@gmail.com',
        ]);

        // Create 9 more random users
        User::factory(9)->create();

        echo "User Seeding Completed \n";

        // Createing  200k blogs in chunks
        $totalBlogs = 200000;
        $chunkSize = 10000;

        echo "Blogs Seeding Started \n";
        for ($i = 0; $i < $totalBlogs / $chunkSize; $i++) {
            Blog::factory($chunkSize)->create()->each(function ($blog) {
                $commentsCount = rand(1, 2);
                BlogComment::factory($commentsCount)->create([
                    'blog_id' => $blog->id
                ]);
            });

            echo "Seeded " . (($i + 1) * $chunkSize) . " blogs...\n";
        }
        echo "Blogs Seeding Completed \n";

        $this->call([
            BlogCacheSeeder::class,
        ]);
    }
}
