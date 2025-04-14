<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Blog;
use App\Models\BlogComment;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class HomeController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->query('search');
        $page = $request->query('page', 1);

        // Build a unique cache key
        $cacheKey = $search
            ? 'blogs_search:' . Str::slug($search) . ":page:$page"
            : "blogs_page:$page";

        $blogs = Cache::remember($cacheKey, now()->addMinutes(10), function () use ($search) {
            $query = Blog::select([
                'id',
                'user_id',
                'title',
                'excerpt',
                'image',
                'published_at',
            ])
                ->with(['author:id,name'])
                ->withCount('comments')
                ->whereNotNull('published_at')
                ->latest();

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                        ->orWhere('keywords', 'like', "%{$search}%")
                        ->orWhere('meta_title', 'like', "%{$search}%")
                        ->orWhere('meta_description', 'like', "%{$search}%");
                });
            }

            return $query->paginate(10);
        });

        // Format published_at dates
        $formattedBlogs = $blogs->getCollection()->map(function (Blog $blog) {
            $blog->published_at = Carbon::parse($blog->published_at)->format('d-M-Y H:i A');
            return $blog;
        });
        $blogs->setCollection($formattedBlogs);

        return response()->json([
            'message' => $search ? 'Search results fetched.' : 'Blogs fetched successfully.',
            'blogs' => $blogs->items(),
            'meta' => [
                'current_page' => $blogs->currentPage(),
                'last_page' => $blogs->lastPage(),
                'per_page' => $blogs->perPage(),
                'total' => $blogs->total(),
            ]
        ]);
    }

    public function show(Blog $blog)
    {
        $cacheKey = "public:blog:{$blog->id}";

        $cachedBlog = Cache::remember($cacheKey, now()->addMinutes(10), function () use ($blog) {
            $blog->load(['author', 'comments.user']);

            return [
                ...$blog->toArray(),
                'published_at' => $blog->published_at ? Carbon::parse($blog->published_at)->format('d-M-Y') : 'Not Published',
                'comments' => $blog->comments
                    ->sortByDesc('created_at')
                    ->values()
                    ->map(function (BlogComment $comment) {
                        return [
                            'id' => $comment->id,
                            'body' => $comment->body,
                            'user' => [
                                'id' => $comment->user->id,
                                'name' => $comment->user->name,
                            ],
                            'created_at' => $comment->created_at->format('d-M-Y h:i A'),
                        ];
                    }),
            ];
        });

        return response()->json([
            'message' => 'Blog Data Fetched.',
            'blog' => $cachedBlog
        ]);
    }
}
