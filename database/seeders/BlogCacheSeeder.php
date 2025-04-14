<?php

namespace Database\Seeders;

use App\Models\Blog;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;

class BlogCacheSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        echo "Blogs Cache Seeding Started \n";
        $pagesToCache = 50; // Only caching first 50 for now
        $perPage = 10;

        for ($page = 1; $page <= $pagesToCache; $page++) {
            $cacheKey = "blogs_page:$page";

            Cache::put($cacheKey, Blog::select(['id', 'user_id', 'title', 'excerpt', 'image', 'published_at'])
                ->whereNotNull('published_at')
                ->with(['author:id,name'])
                ->withCount('comments')
                ->orderBy('published_at', 'desc')
                ->paginate($perPage, ['*'], 'page', $page), now()->addMinutes(10));
        }
        echo "Blogs Cache Seeding Completed \n";
    }
}
